<?php
declare(strict_types=1);

/**
 * PDO para Supabase/PostgreSQL com conversões limitadas às construções MySQL
 * ainda usadas pelas consultas da aplicação. DDL/migrações ficam fora desta
 * classe e são intencionalmente ignoradas no caminho PostgreSQL.
 */
final class PostgresCompatStatement extends PDOStatement
{
    protected function __construct()
    {
    }

    public function execute(?array $params = null): bool
    {
        if ($params !== null) {
            foreach ($params as $key => $value) {
                if (is_bool($value)) {
                    $params[$key] = $value ? 'true' : 'false';
                }
            }
        }

        return parent::execute($params);
    }
}

final class PostgresCompatPDO extends PDO
{
    public function __construct(string $dsn, ?string $username = null, ?string $password = null, array $options = [])
    {
        $options[PDO::ATTR_STATEMENT_CLASS] = [PostgresCompatStatement::class];
        parent::__construct($dsn, $username, $password, $options);
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return parent::prepare(postgres_compat_sql($query), $options);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $query = postgres_compat_sql($query);
        return $fetchMode === null
            ? parent::query($query)
            : parent::query($query, $fetchMode, ...$fetchModeArgs);
    }

    public function exec(string $statement): int|false
    {
        return parent::exec(postgres_compat_sql($statement));
    }

    public function lastInsertId(?string $name = null): string|false
    {
        if ($name !== null) {
            return parent::lastInsertId($name);
        }

        // PostgreSQL exposes the most recently used sequence with LASTVAL().
        $statement = parent::query('SELECT LASTVAL()');
        $value = $statement === false ? false : $statement->fetchColumn();
        return $value === false ? false : (string)$value;
    }
}

/** Convert only SQL double-quoted string literals; this codebase uses no quoted SQL identifiers. */
function postgres_convert_mysql_double_quoted_strings(string $sql): string
{
    $out = '';
    $length = strlen($sql);

    for ($i = 0; $i < $length;) {
        if ($sql[$i] === "'") {
            $end = postgres_skip_single_quoted_string($sql, $i);
            $out .= substr($sql, $i, $end - $i);
            $i = $end;
            continue;
        }

        if ($sql[$i] !== '"') {
            $out .= $sql[$i++];
            continue;
        }

        $i++;
        $value = '';
        $closed = false;
        while ($i < $length) {
            if ($sql[$i] === '"') {
                if ($i + 1 < $length && $sql[$i + 1] === '"') {
                    $value .= '"';
                    $i += 2;
                    continue;
                }
                $i++;
                $closed = true;
                break;
            }
            if ($sql[$i] === '\\' && $i + 1 < $length) {
                $value .= $sql[$i + 1];
                $i += 2;
                continue;
            }
            $value .= $sql[$i++];
        }

        if (!$closed) {
            throw new RuntimeException('Literal SQL MySQL sem fechamento encontrado na consulta PostgreSQL.');
        }
        $out .= "'" . str_replace("'", "''", $value) . "'";
    }

    return $out;
}

function postgres_skip_single_quoted_string(string $sql, int $start): int
{
    $length = strlen($sql);
    for ($i = $start + 1; $i < $length; $i++) {
        if ($sql[$i] !== "'") {
            continue;
        }
        if ($i + 1 < $length && $sql[$i + 1] === "'") {
            $i++;
            continue;
        }
        return $i + 1;
    }
    return $length;
}

function postgres_find_matching_parenthesis(string $sql, int $open): ?int
{
    $depth = 0;
    $length = strlen($sql);
    for ($i = $open; $i < $length; $i++) {
        if ($sql[$i] === "'") {
            $i = postgres_skip_single_quoted_string($sql, $i) - 1;
            continue;
        }
        if ($sql[$i] === '(') {
            $depth++;
        } elseif ($sql[$i] === ')' && --$depth === 0) {
            return $i;
        }
    }
    return null;
}

function postgres_split_sql_arguments(string $arguments): array
{
    $parts = [];
    $start = 0;
    $depth = 0;
    $length = strlen($arguments);

    for ($i = 0; $i < $length; $i++) {
        if ($arguments[$i] === "'") {
            $i = postgres_skip_single_quoted_string($arguments, $i) - 1;
            continue;
        }
        if ($arguments[$i] === '(') {
            $depth++;
        } elseif ($arguments[$i] === ')') {
            $depth--;
        } elseif ($arguments[$i] === ',' && $depth === 0) {
            $parts[] = trim(substr($arguments, $start, $i - $start));
            $start = $i + 1;
        }
    }

    $parts[] = trim(substr($arguments, $start));
    return $parts;
}

/** Rewrite a named SQL function without touching quoted values or nested arguments. */
function postgres_rewrite_sql_function(string $sql, string $function, callable $rewrite): string
{
    $out = '';
    $length = strlen($sql);

    for ($i = 0; $i < $length;) {
        if ($sql[$i] === "'") {
            $end = postgres_skip_single_quoted_string($sql, $i);
            $out .= substr($sql, $i, $end - $i);
            $i = $end;
            continue;
        }

        if (!ctype_alpha($sql[$i]) && $sql[$i] !== '_') {
            $out .= $sql[$i++];
            continue;
        }

        $start = $i;
        while ($i < $length && (ctype_alnum($sql[$i]) || $sql[$i] === '_')) {
            $i++;
        }
        $word = substr($sql, $start, $i - $start);
        $afterWord = $i;
        while ($afterWord < $length && ctype_space($sql[$afterWord])) {
            $afterWord++;
        }

        if (strcasecmp($word, $function) !== 0 || $afterWord >= $length || $sql[$afterWord] !== '(') {
            $out .= $word;
            continue;
        }

        $close = postgres_find_matching_parenthesis($sql, $afterWord);
        if ($close === null) {
            throw new RuntimeException('Parênteses SQL sem fechamento na consulta PostgreSQL.');
        }
        $inner = substr($sql, $afterWord + 1, $close - $afterWord - 1);
        $replacement = $rewrite(postgres_split_sql_arguments($inner));
        if (!is_string($replacement)) {
            $out .= substr($sql, $start, $close - $start + 1);
        } else {
            $out .= $replacement;
        }
        $i = $close + 1;
    }

    return $out;
}

function postgres_mysql_format(string $format): string
{
    $tokens = [
        '%Y' => 'YYYY', '%y' => 'YY', '%m' => 'MM', '%c' => 'FMMM',
        '%d' => 'DD', '%e' => 'FMDD', '%H' => 'HH24', '%h' => 'HH12',
        '%I' => 'HH12', '%i' => 'MI', '%s' => 'SS', '%S' => 'SS',
        '%%' => '%',
    ];
    return strtr($format, $tokens);
}

function postgres_interval_expression(array $arguments, int $direction): ?string
{
    if (count($arguments) !== 2 || !preg_match(
        '/^INTERVAL\s+(\?|\d+)\s+(SECOND|MINUTE|HOUR|DAY|WEEK|MONTH|QUARTER|YEAR)$/i',
        trim($arguments[1]),
        $match
    )) {
        return null;
    }

    $amount = $match[1];
    $unit = strtolower($match[2]);
    if ($unit === 'quarter') {
        $unit = 'month';
        $amount = $amount === '?' ? '?' : (string)((int)$amount * 3);
    }
    $operator = $direction < 0 ? '-' : '+';
    if ($amount === '?') {
        return '(' . trim($arguments[0]) . ' ' . $operator . ' (? * INTERVAL \'1 ' . $unit . '\'))';
    }
    return '(' . trim($arguments[0]) . ' ' . $operator . ' INTERVAL \'' . $amount . ' ' . $unit . '\')';
}

/** Translate the finite set of MySQL constructs currently used in api.php. */
function postgres_compat_sql(string $sql): string
{
    $sql = postgres_convert_mysql_double_quoted_strings($sql);

    foreach (['DATE_FORMAT', 'TIME_FORMAT'] as $formatFunction) {
        $sql = postgres_rewrite_sql_function($sql, $formatFunction, static function (array $args): ?string {
            if (count($args) !== 2 || !preg_match("/^'((?:[^']|'')*)'$/s", trim($args[1]), $match)) {
                return null;
            }
            $format = str_replace("''", "'", $match[1]);
            return 'TO_CHAR(' . trim($args[0]) . ", '" . str_replace("'", "''", postgres_mysql_format($format)) . "')";
        });
    }

    $sql = postgres_rewrite_sql_function($sql, 'DATE_ADD', static fn(array $args): ?string => postgres_interval_expression($args, 1));
    $sql = postgres_rewrite_sql_function($sql, 'DATE_SUB', static fn(array $args): ?string => postgres_interval_expression($args, -1));
    $sql = postgres_rewrite_sql_function($sql, 'DATE', static function (array $args): ?string {
        return count($args) === 1 ? 'CAST(' . trim($args[0]) . ' AS DATE)' : null;
    });

    $sql = preg_replace('/\bCURDATE\s*\(\s*\)/i', 'CURRENT_DATE', $sql) ?? $sql;

    $sql = postgres_rewrite_sql_function($sql, 'IF', static function (array $args): ?string {
        if (count($args) !== 3) {
            return null;
        }
        return 'CASE WHEN ' . $args[0] . ' THEN ' . $args[1] . ' ELSE ' . $args[2] . ' END';
    });

    $sql = postgres_rewrite_sql_function($sql, 'FIELD', static function (array $args): ?string {
        if (count($args) < 2) {
            return null;
        }
        $case = 'CASE ' . array_shift($args);
        foreach ($args as $index => $value) {
            $case .= ' WHEN ' . $value . ' THEN ' . ($index + 1);
        }
        return $case . ' ELSE ' . (count($args) + 1) . ' END';
    });

    // These identifiers are boolean columns in the Supabase schema; TRUE/FALSE
    // is accepted by both PostgreSQL and the MySQL compatibility path.
    $sql = preg_replace('/\b((?:[A-Za-z_][A-Za-z0-9_]*\.)?(?:ativa|ativo|lembrete|notificado|encaminhado_desenvolvedor))\s*=\s*1\b/i', '$1 = TRUE', $sql) ?? $sql;
    $sql = preg_replace('/\b((?:[A-Za-z_][A-Za-z0-9_]*\.)?(?:ativa|ativo|lembrete|notificado|encaminhado_desenvolvedor))\s*=\s*0\b/i', '$1 = FALSE', $sql) ?? $sql;
    $sql = preg_replace('/\bNOT\s+LIKE\b/i', 'NOT ILIKE', $sql) ?? $sql;
    $sql = preg_replace('/\bLIKE\b/i', 'ILIKE', $sql) ?? $sql;

    $sql = preg_replace('/^\s*INSERT\s+IGNORE\s+INTO\b/i', 'INSERT INTO', $sql, 1, $ignoredInsertCount) ?? $sql;
    if ($ignoredInsertCount > 0) {
        $hasSemicolon = str_ends_with(rtrim($sql), ';');
        $sql = rtrim($sql, " \t\n\r\0\x0B;") . ' ON CONFLICT DO NOTHING' . ($hasSemicolon ? ';' : '');
    }

    $upsertPattern = '/\bON\s+DUPLICATE\s+KEY\s+UPDATE\s+nome\s*=\s*VALUES\s*\(\s*nome\s*\)\s*,\s*telefone\s*=\s*VALUES\s*\(\s*telefone\s*\)\s*,\s*ubs_id\s*=\s*COALESCE\s*\(\s*VALUES\s*\(\s*ubs_id\s*\)\s*,\s*ubs_id\s*\)/i';
    $sql = preg_replace(
        $upsertPattern,
        'ON CONFLICT (sus) DO UPDATE SET nome = EXCLUDED.nome, telefone = EXCLUDED.telefone, ubs_id = COALESCE(EXCLUDED.ubs_id, pacientes.ubs_id)',
        $sql,
        1,
        $upsertCount
    ) ?? $sql;

    $unsupported = '/\b(?:INSERT\s+IGNORE|ON\s+DUPLICATE\s+KEY|DATE_FORMAT\s*\(|TIME_FORMAT\s*\(|DATE_ADD\s*\(|DATE_SUB\s*\(|CURDATE\s*\(|FIELD\s*\(|IF\s*\(|SHOW\s+(?:INDEX|TABLES)|AUTO_INCREMENT|ENGINE\s*=)/i';
    if ($upsertCount === 0 && preg_match('/\bON\s+DUPLICATE\s+KEY\b/i', $sql)) {
        throw new RuntimeException('Upsert MySQL não mapeado para o esquema PostgreSQL atual.');
    }
    if (preg_match($unsupported, $sql) || str_contains($sql, '`') || preg_match('/\bUPDATE\s+\w+\s+\w+\s+(?:INNER|LEFT|RIGHT)\s+JOIN\b/i', $sql)) {
        throw new RuntimeException('A consulta contém sintaxe MySQL não mapeada para PostgreSQL.');
    }

    return $sql;
}

function postgres_connection_details(string $url): array
{
    $parts = parse_url($url);
    if (!is_array($parts) || !in_array(strtolower((string)($parts['scheme'] ?? '')), ['postgres', 'postgresql'], true)) {
        throw new InvalidArgumentException('DATABASE_URL deve ser uma URL PostgreSQL válida.');
    }

    $host = trim((string)($parts['host'] ?? ''), '[]');
    $port = (int)($parts['port'] ?? 5432);
    $database = rawurldecode(trim((string)($parts['path'] ?? ''), '/'));
    if ($host === '' || !preg_match('/^[A-Za-z0-9.:-]+$/', $host) || $port < 1 || $port > 65535 || $database === '' || !preg_match('/^[A-Za-z0-9_.-]+$/', $database)) {
        throw new InvalidArgumentException('DATABASE_URL contém host, porta ou nome de banco inválido.');
    }

    $query = [];
    parse_str((string)($parts['query'] ?? ''), $query);
    $sslMode = strtolower((string)($query['sslmode'] ?? 'require'));
    if (!in_array($sslMode, ['require', 'verify-ca', 'verify-full'], true)) {
        throw new InvalidArgumentException('DATABASE_URL contém um modo TLS PostgreSQL inválido.');
    }

    $user = rawurldecode((string)($parts['user'] ?? ''));
    $password = rawurldecode((string)($parts['pass'] ?? ''));
    if ($user === '' || $password === '') {
        throw new InvalidArgumentException('DATABASE_URL precisa incluir usuário e senha PostgreSQL.');
    }

    $dsn = 'pgsql:host=' . $host . ';port=' . $port . ';dbname=' . $database . ';sslmode=' . $sslMode;
    if (isset($query['connect_timeout'])) {
        $timeout = filter_var($query['connect_timeout'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 60]]);
        if ($timeout === false) {
            throw new InvalidArgumentException('DATABASE_URL contém um tempo limite inválido.');
        }
        $dsn .= ';connect_timeout=' . $timeout;
    }

    return [
        'dsn' => $dsn,
        'user' => $user,
        'password' => $password,
    ];
}

function database_is_postgres(PDO $pdo): bool
{
    return strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)) === 'pgsql';
}

function database_bool(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }
    if (is_int($value) || is_float($value)) {
        return $value !== 0 && $value !== 0.0;
    }
    return in_array(strtolower(trim((string)$value)), ['1', 't', 'true', 'yes', 'on'], true);
}

function database_normalize_booleans(mixed $value): mixed
{
    if (!is_array($value)) {
        return $value;
    }

    static $booleanKeys = [
        'ativa', 'ativo', 'lembrete', 'notificado', 'encaminhado_desenvolvedor',
        'encaminhadoDesenvolvedor', 'confirmacao_automatica', 'confirmacaoAutomatica',
        'ubs_ativa',
    ];

    foreach ($value as $key => $item) {
        if (is_array($item)) {
            $value[$key] = database_normalize_booleans($item);
        } elseif ($item !== null && in_array((string)$key, $booleanKeys, true)) {
            $value[$key] = database_bool($item);
        }
    }

    return $value;
}

function database_is_unique_violation(PDOException $exception): bool
{
    $sqlState = strtoupper((string)($exception->errorInfo[0] ?? $exception->getCode()));
    $driverCode = (int)($exception->errorInfo[1] ?? 0);
    return $sqlState === '23505' || $driverCode === 1062;
}
