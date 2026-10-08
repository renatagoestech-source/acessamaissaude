<?php
declare(strict_types=1);

require_once __DIR__ . '/../postgres_compat.php';

function expect_contains(string $needle, string $haystack, string $label): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException($label . ' — ausente: ' . $needle . "\nSQL: " . $haystack);
    }
}

function expect_not_contains(string $needle, string $haystack, string $label): void
{
    if (str_contains($haystack, $needle)) {
        throw new RuntimeException($label . ' — ainda contém: ' . $needle . "\nSQL: " . $haystack);
    }
}

$formatted = postgres_compat_sql('SELECT DATE_FORMAT(c.data_consulta, "%Y-%m-%d") AS data, TIME_FORMAT(c.horario, \'%H:%i\') AS horario');
expect_contains("TO_CHAR(c.data_consulta, 'YYYY-MM-DD')", $formatted, 'DATE_FORMAT');
expect_contains("TO_CHAR(c.horario, 'HH24:MI')", $formatted, 'TIME_FORMAT');

$dates = postgres_compat_sql('SELECT CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 MONTH), DATE_SUB(NOW(), INTERVAL 15 MINUTE), DATE_ADD(NOW(), INTERVAL ? HOUR)');
expect_contains('CURRENT_DATE', $dates, 'CURDATE');
expect_contains("INTERVAL '1 month'", $dates, 'DATE_ADD fixo');
expect_contains("INTERVAL '15 minute'", $dates, 'DATE_SUB');
expect_contains("(? * INTERVAL '1 hour')", $dates, 'DATE_ADD com placeholder');
expect_not_contains('DATE_ADD', $dates, 'DATE_ADD residual');

$condition = postgres_compat_sql("UPDATE assinaturas SET inicio=IF(?='ativa' AND inicio IS NULL,CURDATE(),inicio),fim=IF(?='ativa',DATE_ADD(CURDATE(),INTERVAL 1 MONTH),fim) WHERE id=?");
expect_contains('CASE WHEN ?=', $condition, 'IF para CASE');
expect_contains('THEN CURRENT_DATE ELSE inicio END', $condition, 'ramo CASE de data');
expect_not_contains('IF(', $condition, 'IF residual');

$ignored = postgres_compat_sql('INSERT IGNORE INTO notificacoes (paciente_id,tipo) VALUES (?,?)');
expect_contains('INSERT INTO notificacoes', $ignored, 'INSERT IGNORE');
expect_contains('ON CONFLICT DO NOTHING', $ignored, 'conflito ignorado');

$upsert = postgres_compat_sql('INSERT INTO pacientes (codigo,nome,telefone,sus,ubs_id) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE nome=VALUES(nome),telefone=VALUES(telefone),ubs_id=COALESCE(VALUES(ubs_id),ubs_id)');
expect_contains('ON CONFLICT (sus) DO UPDATE', $upsert, 'upsert de paciente');
expect_contains('EXCLUDED.nome', $upsert, 'EXCLUDED do upsert');
expect_not_contains('ON DUPLICATE KEY', $upsert, 'upsert MySQL residual');

$ordered = postgres_compat_sql('SELECT id FROM indicadores WHERE ativa=1 ORDER BY FIELD(tipo,"campanha","vacina","citologia")');
expect_contains('ativa = TRUE', $ordered, 'booleano de UBS');
expect_contains("CASE tipo WHEN 'campanha' THEN 1 WHEN 'vacina' THEN 2 WHEN 'citologia' THEN 3 ELSE 4 END", $ordered, 'FIELD');

$search = postgres_compat_sql('SELECT id FROM pacientes WHERE nome LIKE ? OR codigo LIKE ?');
expect_contains('ILIKE', $search, 'LIKE sem diferenciar caixa');

$dateCast = postgres_compat_sql('SELECT DATE(f.recebido_em) FROM pagamentos_pacientes f');
expect_contains('CAST(f.recebido_em AS DATE)', $dateCast, 'DATE cast');

$details = postgres_connection_details('postgresql://app_user:example%40pass@db.example.supabase.co:5432/postgres?sslmode=require&connect_timeout=12');
if ($details['dsn'] !== 'pgsql:host=db.example.supabase.co;port=5432;dbname=postgres;sslmode=require;connect_timeout=12' || $details['user'] !== 'app_user' || $details['password'] !== 'example@pass') {
    throw new RuntimeException('A conversão de DATABASE_URL PostgreSQL falhou.');
}

foreach (['t' => true, 'true' => true, '1' => true, 'f' => false, 'false' => false, '0' => false] as $input => $expected) {
    if (database_bool($input) !== $expected) {
        throw new RuntimeException('A normalização de booleano falhou para o valor de teste.');
    }
}

$postgresDuplicate = new PDOException('duplicate');
$postgresDuplicate->errorInfo = ['23505', null, 'unique_violation'];
$mysqlDuplicate = new PDOException('duplicate');
$mysqlDuplicate->errorInfo = ['23000', 1062, 'duplicate key'];
if (!database_is_unique_violation($postgresDuplicate) || !database_is_unique_violation($mysqlDuplicate)) {
    throw new RuntimeException('O reconhecimento de duplicidades SQLSTATE não funcionou.');
}

echo "PostgreSQL compat tests: OK\n";
