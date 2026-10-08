<?php
declare(strict_types=1);

/**
 * Converts the small, known MySQL dialect subset used by this application to PostgreSQL.
 * Keep this list narrow and covered by tests: this is not a general SQL parser.
 */
function postgres_compat_sql(string $sql): string
{
    $sql = postgres_compat_double_quoted_literals($sql);

    // MySQL date/time formatting functions -> PostgreSQL to_char().
    $sql = preg_replace_callback(
        '/\bDATE_FORMAT\(\s*([a-z_][a-z0-9_.]*)\s*,\s*\'%Y-%m-%d\'\s*\)/i',
        static fn(array $m): string => "TO_CHAR({$m[1]}::date, 'YYYY-MM-DD')",
        $sql
    ) ?? $sql;
    $sql = preg_replace_callback(
        '/\bTIME_FORMAT\(\s*([a-z_][a-z0-9_.]*)\s*,\s*\'%H:%i\'\s*\)/i',
        static fn(array $m): string => "TO_CHAR({$m[1]}::time, 'HH24:MI')",
        $sql
    ) ?? $sql;

    // MySQL DATE_ADD/DATE_SUB forms actually present in this app.
    $sql = preg_replace('/\bDATE_ADD\(\s*NOW\(\)\s*,\s*INTERVAL\s*\?\s*HOUR\s*\)/i', "(NOW() + (? * INTERVAL '1 hour'))", $sql) ?? $sql;
    $sql = preg_replace('/\bDATE_ADD\(\s*NOW\(\)\s*,\s*INTERVAL\s*15\s*MINUTE\s*\)/i', "(NOW() + INTERVAL '15 minutes')", $sql) ?? $sql;
    $sql = preg_replace('/\bDATE_SUB\(\s*NOW\(\)\s*,\s*INTERVAL\s*15\s*MINUTE\s*\)/i', "(NOW() - INTERVAL '15 minutes')", $sql) ?? $sql;
    $sql = preg_replace('/\bDATE_ADD\(\s*(?:CURDATE\(\s*\)|CURRENT_DATE)\s*,\s*INTERVAL\s*1\s*MONTH\s*\)/i', "(CURRENT_DATE + INTERVAL '1 month')", $sql) ?? $sql;
    $sql = preg_replace('/\bCURDATE\(\s*\)/i', 'CURRENT_DATE', $sql) ?? $sql;

    // MySQL FIELD() ordering used by the health-indicator dashboard.
    $sql = preg_replace(
        "/ORDER BY\\s+FIELD\\(i\\.tipo,\\s*'campanha',\\s*'vacina',\\s*'citologia'\\),/i",
        "ORDER BY CASE i.tipo WHEN 'campanha' THEN 1 WHEN 'vacina' THEN 2 WHEN 'citologia' THEN 3 ELSE 4 END,",
        $sql
    ) ?? $sql;

    // PostgreSQL folds unquoted aliases to lower-case; preserve the API's camelCase keys.
    $sql = preg_replace_callback(
        '/\bAS\s+([a-z_][a-z0-9_]*)/i',
        static function (array $m): string {
            $alias = $m[1];
            return 'AS ' . (preg_match('/[A-Z]/', $alias) ? '"' . $alias . '"' : $alias);
        },
        $sql
    ) ?? $sql;

    // These flags are PostgreSQL boolean columns (the older MySQL schema used 0/1).
    $sql = preg_replace('/\b(ativa|ativo|lembrete|notificado|encaminhado_desenvolvedor)\s*=\s*1\b/i', '$1 = TRUE', $sql) ?? $sql;
    $sql = preg_replace('/\b(ativa|ativo|lembrete|notificado|encaminhado_desenvolvedor)\s*=\s*0\b/i', '$1 = FALSE', $sql) ?? $sql;

    // INSERT IGNORE was used only for unique-key idempotency in this project.
    if (preg_match('/^\s*INSERT\s+IGNORE\s+INTO\b/i', $sql)) {
        $sql = preg_replace('/^\s*INSERT\s+IGNORE\s+INTO\b/i', 'INSERT INTO', $sql, 1) ?? $sql;
        if (!preg_match('/\bON\s+CONFLICT\b/i', $sql)) {
            $sql = rtrim($sql, " \t\n\r\0\x0B;") . ' ON CONFLICT DO NOTHING';
        }
    }

    // The app's patient upsert is keyed by the unique SUS identifier.
    $sql = preg_replace(
        '/ON DUPLICATE KEY UPDATE\s+nome\s*=\s*VALUES\(nome\)\s*,\s*telefone\s*=\s*VALUES\(telefone\)\s*,\s*ubs_id\s*=\s*COALESCE\(VALUES\(ubs_id\),\s*ubs_id\)/i',
        'ON CONFLICT (sus) DO UPDATE SET nome = EXCLUDED.nome, telefone = EXCLUDED.telefone, ubs_id = COALESCE(EXCLUDED.ubs_id, pacientes.ubs_id)',
        $sql
    ) ?? $sql;

    foreach (['DATE_FORMAT(', 'TIME_FORMAT(', 'DATE_ADD(', 'DATE_SUB(', 'CURDATE(', 'INSERT IGNORE', 'ON DUPLICATE KEY', 'FIELD('] as $unsupported) {
        if (stripos($sql, $unsupported) !== false) {
            throw new RuntimeException('Consulta SQL ainda contém sintaxe MySQL não convertida: ' . $unsupported);
        }
    }
    if (preg_match('/\bIF\s*\(/i', $sql) || preg_match('/\bUPDATE\s+\w+\s+\w+\s+(?:INNER|LEFT|RIGHT)\s+JOIN\b/i', $sql)) {
        throw new RuntimeException('Consulta SQL ainda contém IF() ou UPDATE JOIN de MySQL.');
    }
    return $sql;
}

/** Convert MySQL's double-quoted string literals without changing single-quoted SQL. */
function postgres_compat_double_quoted_literals(string $sql): string
{
    $out = '';
    $length = strlen($sql);
    $inSingle = false;
    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        if ($inSingle) {
            $out .= $char;
            if ($char === "'" && $i + 1 < $length && $sql[$i + 1] === "'") {
                $out .= $sql[++$i];
            } elseif ($char === "'") {
                $inSingle = false;
            }
            continue;
        }
        if ($char === "'") {
            $inSingle = true;
            $out .= $char;
            continue;
        }
        if ($char !== '"') {
            $out .= $char;
            continue;
        }
        $literal = '';
        $i++;
        while ($i < $length && $sql[$i] !== '"') {
            $literal .= $sql[$i++];
        }
        if ($i >= $length) {
            throw new RuntimeException('Consulta SQL contém aspas duplas sem fechamento.');
        }
        $out .= "'" . str_replace("'", "''", $literal) . "'";
    }
    return $out;
}

/** PDO subclass applying the compatibility conversion to every SQL entry point. */
class PostgresCompatPDO extends PDO
{
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return parent::prepare(postgres_compat_sql($query), $options);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $query = postgres_compat_sql($query);
        if ($fetchMode === null) {
            return parent::query($query);
        }
        return parent::query($query, $fetchMode, ...$fetchModeArgs);
    }

    public function exec(string $statement): int|false
    {
        return parent::exec(postgres_compat_sql($statement));
    }
}
