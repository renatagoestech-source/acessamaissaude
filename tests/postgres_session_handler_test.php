<?php
declare(strict_types=1);

require_once __DIR__ . '/../common.php';

final class SessionStoreTestPDO extends PDO
{
    public array $rows = [];

    public function __construct() {}

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new SessionStoreTestStatement($this, $query);
    }
}

final class SessionStoreTestStatement extends PDOStatement
{
    private mixed $result = false;
    private int $affectedRows = 0;

    public function __construct(private SessionStoreTestPDO $database, private string $query) {}

    public function execute(?array $params = null): bool
    {
        $params ??= [];
        $sql = strtolower(preg_replace('/\s+/', ' ', trim($this->query)) ?? '');
        $this->result = false;
        $this->affectedRows = 0;

        if (str_starts_with($sql, 'select 1 from public.php_sessions')) {
            $row = $this->database->rows[(string)$params[0]] ?? null;
            $this->result = $row !== null && $row['expires_at'] > (int)$params[1] ? 1 : false;
        } elseif (str_starts_with($sql, 'select payload from public.php_sessions')) {
            $row = $this->database->rows[(string)$params[0]] ?? null;
            $this->result = $row !== null && $row['expires_at'] > (int)$params[1] ? $row['payload'] : false;
        } elseif (str_starts_with($sql, 'insert into public.php_sessions')) {
            [$idHash, $payload, $expiresAt] = $params;
            $this->database->rows[(string)$idHash] = ['payload' => (string)$payload, 'expires_at' => (int)$expiresAt];
            $this->affectedRows = 1;
        } elseif (str_starts_with($sql, 'delete from public.php_sessions where id_hash')) {
            $idHash = (string)$params[0];
            if (isset($this->database->rows[$idHash])) {
                unset($this->database->rows[$idHash]);
                $this->affectedRows = 1;
            }
        } elseif (str_starts_with($sql, 'delete from public.php_sessions where expires_at')) {
            $now = (int)$params[0];
            foreach ($this->database->rows as $idHash => $row) {
                if ($row['expires_at'] <= $now) {
                    unset($this->database->rows[$idHash]);
                    $this->affectedRows++;
                }
            }
        } else {
            throw new RuntimeException('Consulta inesperada no teste do handler: ' . $sql);
        }
        return true;
    }

    public function fetchColumn(int $column = 0): mixed
    {
        return $this->result;
    }

    public function rowCount(): int
    {
        return $this->affectedRows;
    }
}

function expect_true(bool $value, string $label): void
{
    if (!$value) throw new RuntimeException($label);
}

$pdo = new SessionStoreTestPDO();
$handler = new PostgresSessionHandler($pdo);
expect_true($handler instanceof SessionUpdateTimestampHandlerInterface, 'O handler precisa suportar strict mode e validação de ID.');

$id = 'synthetic-session-id';
$payload = 'professional|a:1:{s:2:"id";i:42;}';
expect_true($handler->write($id, $payload), 'A sessão deve ser gravada.');
$idHash = hash('sha256', $id);
expect_true(isset($pdo->rows[$idHash]), 'A chave persistida deve ser o hash do ID, não o ID bruto.');
expect_true(!isset($pdo->rows[$id]), 'O ID bruto da sessão não deve ser armazenado.');
expect_true($pdo->rows[$idHash]['payload'] === base64_encode($payload), 'O payload deve ser codificado antes do armazenamento.');
expect_true($handler->validateId($id), 'Uma sessão válida deve passar pela validação de ID.');
expect_true($handler->read($id) === $payload, 'A leitura deve reconstruir o payload original.');
expect_true($handler->updateTimestamp($id, $payload), 'A atualização lazy do PHP deve renovar a sessão.');

$expiredId = 'expired-session-id';
$handler->write($expiredId, 'expired-payload');
$expiredHash = hash('sha256', $expiredId);
$pdo->rows[$expiredHash]['expires_at'] = time() - 1;
expect_true(!$handler->validateId($expiredId), 'Uma sessão expirada não deve ser validada.');
expect_true($handler->read($expiredId) === '', 'Uma sessão expirada deve ser lida como sessão vazia.');
expect_true($handler->gc(1) === 1, 'A coleta deve remover sessões expiradas.');
expect_true(!isset($pdo->rows[$expiredHash]), 'A sessão expirada deve ser removida.');
expect_true($handler->destroy($id), 'O logout deve remover a sessão pelo hash do ID.');
expect_true(!isset($pdo->rows[$idHash]), 'A sessão encerrada não deve permanecer armazenada.');

$api = file_get_contents(__DIR__ . '/../api.php');
$handlerAt = strpos($api, 'configure_database_session_handler($pdo);');
$startAt = strpos($api, 'session_start()');
if ($handlerAt === false || $startAt === false || $handlerAt >= $startAt) {
    throw new RuntimeException('O armazenamento persistente deve ser configurado antes de session_start().');
}

$migration = file_get_contents(__DIR__ . '/../migrations/20261009_create_php_session_store.sql');
if ($migration === false
    || !str_contains($migration, 'ENABLE ROW LEVEL SECURITY')
    || !str_contains($migration, 'REVOKE ALL ON TABLE public.php_sessions FROM PUBLIC, anon, authenticated')
    || !str_contains($migration, 'GRANT ALL ON TABLE public.php_sessions TO service_role')) {
    throw new RuntimeException('A migração deve preservar RLS e manter a tabela indisponível aos papéis públicos.');
}

ini_set('session.use_cookies', '0');
ini_set('session.use_strict_mode', '1');
$integrationPdo = new SessionStoreTestPDO();
if (!session_set_save_handler(new PostgresSessionHandler($integrationPdo), false)) {
    throw new RuntimeException('Não foi possível registrar o handler no teste de integração PHP.');
}
$untrustedId = 'php-session-store-integration';
session_id($untrustedId);
if (!session_start()) throw new RuntimeException('A sessão de integração não iniciou.');
$_SESSION['probe'] = 'survives';
$savedId = session_id();
expect_true($savedId !== $untrustedId, 'O strict mode deve rejeitar um ID ainda não persistido.');
session_write_close();
session_id($savedId);
if (!session_start()) throw new RuntimeException('A sessão persistida não reabriu.');
expect_true(($_SESSION['probe'] ?? null) === 'survives', 'Os dados devem sobreviver ao fechamento e reabertura da sessão.');
session_abort();

echo "PostgreSQL session handler tests: OK\n";
