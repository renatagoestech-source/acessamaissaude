<?php
declare(strict_types=1);

require_once __DIR__ . '/../postgres_compat.php';

$dsn = (string)(getenv('PG_COMPAT_TEST_DSN') ?: '');
$user = (string)(getenv('PG_COMPAT_TEST_USER') ?: '');
$password = (string)(getenv('PG_COMPAT_TEST_PASSWORD') ?: '');
if (!preg_match('/^pgsql:host=(?:127\.0\.0\.1|localhost);.*dbname=manus_pg_compat_test(?:;|$)/', $dsn)) {
    fwrite(STDERR, "Recusado: o teste exige o banco local manus_pg_compat_test em localhost.\n");
    exit(78);
}
if ($user === '' || $password === '') {
    fwrite(STDERR, "Defina usuário/senha sintéticos do banco local de teste.\n");
    exit(78);
}

$port = preg_match('/(?:^|;)port=(\d+)(?:;|$)/', $dsn, $portMatch) ? (int)$portMatch[1] : 5432;
$localUrl = 'postgresql://' . rawurlencode($user) . ':' . rawurlencode($password)
    . '@127.0.0.1:' . $port . '/manus_pg_compat_test?sslmode=require';
putenv('DATABASE_URL=' . $localUrl);
require_once __DIR__ . '/../config.php';
$pdo = db();
if (!$pdo instanceof PostgresCompatPDO || !database_is_postgres($pdo)) {
    throw new RuntimeException('config.php não selecionou o adaptador PostgreSQL para DATABASE_URL.');
}

$pdo->exec('DROP TABLE IF EXISTS consultas, notificacoes, pacientes, compat_subscriptions, compat_indicators, compat_timestamps, compat_auto, compat_auth');
$pdo->exec('CREATE TABLE pacientes (id SERIAL PRIMARY KEY, codigo TEXT, nome TEXT, telefone TEXT, sus TEXT UNIQUE, ubs_id TEXT)');
$pdo->exec('CREATE TABLE consultas (id TEXT PRIMARY KEY, paciente_id INTEGER NOT NULL REFERENCES pacientes(id), status TEXT NOT NULL, lembrete BOOLEAN NOT NULL DEFAULT FALSE, notificado BOOLEAN NOT NULL DEFAULT TRUE)');
$pdo->exec('CREATE TABLE notificacoes (id SERIAL PRIMARY KEY, tipo TEXT UNIQUE NOT NULL)');
$pdo->exec('CREATE TABLE compat_subscriptions (id INTEGER PRIMARY KEY, status TEXT, inicio DATE, fim DATE)');
$pdo->exec('CREATE TABLE compat_indicators (tipo TEXT NOT NULL)');
$pdo->exec('CREATE TABLE compat_timestamps (recebido_em TIMESTAMP NOT NULL)');
$pdo->exec('CREATE TABLE compat_auto (id SERIAL PRIMARY KEY, valor TEXT)');
$pdo->exec('CREATE TABLE compat_auth (id INTEGER PRIMARY KEY, tentativas INTEGER NOT NULL, inicio_janela TIMESTAMP NOT NULL, bloqueado_ate TIMESTAMP NULL)');

$assert = static function (bool $condition, string $label): void {
    if (!$condition) {
        throw new RuntimeException('Falha no teste de integração: ' . $label);
    }
};

try {
    $patient = $pdo->prepare('INSERT INTO pacientes (codigo,nome,telefone,sus,ubs_id) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE nome=VALUES(nome),telefone=VALUES(telefone),ubs_id=COALESCE(VALUES(ubs_id),ubs_id)');
    $patient->execute(['PAC-1', 'Renata', '11999999999', 'SUS-TESTE-0001', 'UBS-TESTE']);
    $patient->execute(['PAC-2', 'RENATA Atualizada', '11888888888', 'SUS-TESTE-0001', null]);
    $patientId = (int)$pdo->query("SELECT id FROM pacientes WHERE sus='SUS-TESTE-0001'")->fetchColumn();
    $name = (string)$pdo->query("SELECT nome FROM pacientes WHERE sus='SUS-TESTE-0001'")->fetchColumn();
    $ubsId = $pdo->query("SELECT ubs_id FROM pacientes WHERE sus='SUS-TESTE-0001'")->fetchColumn();
    $assert($name === 'RENATA Atualizada' && $ubsId === 'UBS-TESTE', 'upsert PostgreSQL pelo cartão SUS preserva UBS existente');

    $notification = $pdo->prepare('INSERT IGNORE INTO notificacoes (tipo) VALUES (?)');
    $notification->execute(['lembrete']);
    $notification->execute(['lembrete']);
    $notificationCount = (int)$pdo->query("SELECT COUNT(*) FROM notificacoes WHERE tipo='lembrete'")->fetchColumn();
    $assert($notificationCount === 1, 'INSERT IGNORE traduzido em ON CONFLICT DO NOTHING');

    $pdo->prepare('INSERT INTO consultas (id,paciente_id,status) VALUES (?,?,?)')->execute(['CONS-TESTE', $patientId, 'agendado']);
    $reminder = $pdo->prepare('UPDATE consultas SET lembrete=TRUE, notificado=FALSE WHERE id=? AND status="agendado" AND EXISTS (SELECT 1 FROM pacientes p WHERE p.id=consultas.paciente_id AND p.sus=?)');
    $reminder->execute(['CONS-TESTE', 'SUS-TESTE-0001']);
    $flags = $pdo->query("SELECT lembrete,notificado FROM consultas WHERE id='CONS-TESTE'")->fetch();
    $assert(database_bool($flags['lembrete']) && !database_bool($flags['notificado']), 'UPDATE com booleanos e EXISTS');
    $boundBoolean = $pdo->prepare('UPDATE consultas SET lembrete=? WHERE id=?');
    $boundBoolean->execute([false, 'CONS-TESTE']);
    $assert(!database_bool($pdo->query("SELECT lembrete FROM consultas WHERE id='CONS-TESTE'")->fetchColumn()), 'binding de false em coluna BOOLEAN');
    $boundBoolean->execute([true, 'CONS-TESTE']);
    $assert(database_bool($pdo->query("SELECT lembrete FROM consultas WHERE id='CONS-TESTE'")->fetchColumn()), 'binding de true em coluna BOOLEAN');

    $formatted = $pdo->query('SELECT DATE_FORMAT(DATE \'2026-10-08\', "%Y-%m-%d") AS data, TIME_FORMAT(TIME \'09:30:00\', \'%H:%i\') AS horario')->fetch();
    $assert($formatted['data'] === '2026-10-08' && $formatted['horario'] === '09:30', 'DATE_FORMAT e TIME_FORMAT via TO_CHAR');

    $dates = $pdo->prepare('SELECT DATE_ADD(CURDATE(), INTERVAL 1 MONTH) AS next_month, DATE_SUB(NOW(), INTERVAL 15 MINUTE) AS cutoff, DATE_ADD(NOW(), INTERVAL ? HOUR) AS future');
    $dates->execute([2]);
    $dateValues = $dates->fetch();
    $assert(!empty($dateValues['next_month']) && !empty($dateValues['cutoff']) && !empty($dateValues['future']), 'aritmética de datas com intervalos e placeholder');

    $pdo->exec("INSERT INTO compat_subscriptions (id,status) VALUES (1,'pendente')");
    $subscription = $pdo->prepare('UPDATE compat_subscriptions SET status=?,inicio=IF(?=\'ativa\' AND inicio IS NULL,CURDATE(),inicio),fim=IF(?=\'ativa\',DATE_ADD(CURDATE(),INTERVAL 1 MONTH),fim) WHERE id=?');
    $subscription->execute(['ativa', 'ativa', 'ativa', 1]);
    $subscriptionRow = $pdo->query('SELECT inicio,fim FROM compat_subscriptions WHERE id=1')->fetch();
    $assert(!empty($subscriptionRow['inicio']) && !empty($subscriptionRow['fim']), 'IF convertido para CASE WHEN');

    $pdo->exec("INSERT INTO compat_auth (id,tentativas,inicio_janela) VALUES (1,0,NOW())");
    $attempts = $pdo->prepare('UPDATE compat_auth SET tentativas=?,inicio_janela=IF(?,NOW(),inicio_janela),bloqueado_ate=IF(? >= 5,DATE_ADD(NOW(),INTERVAL 15 MINUTE),NULL) WHERE id=?');
    $attempts->execute([5, true, 5, 1]);
    $authRow = $pdo->query('SELECT tentativas,bloqueado_ate FROM compat_auth WHERE id=1')->fetch();
    $assert((int)$authRow['tentativas'] === 5 && !empty($authRow['bloqueado_ate']), 'limitador com condição booleana e prazo de bloqueio');

    $pdo->exec("INSERT INTO compat_indicators (tipo) VALUES ('citologia'),('vacina'),('campanha')");
    $ordered = $pdo->query('SELECT tipo FROM compat_indicators ORDER BY FIELD(tipo,"campanha","vacina","citologia")')->fetchAll(PDO::FETCH_COLUMN);
    $assert($ordered === ['campanha', 'vacina', 'citologia'], 'FIELD convertido para CASE de ordenação');

    $pdo->prepare('INSERT INTO compat_timestamps (recebido_em) VALUES (?)')->execute(['2026-10-08 09:10:11']);
    $castDate = $pdo->query('SELECT DATE(recebido_em) AS dia FROM compat_timestamps')->fetchColumn();
    $assert($castDate === '2026-10-08', 'DATE convertido para CAST AS DATE');

    $search = $pdo->prepare('SELECT nome FROM pacientes WHERE nome LIKE ?');
    $search->execute(['%renata%']);
    $assert($search->fetchColumn() === 'RENATA Atualizada', 'LIKE traduzido para ILIKE');

    $pdo->prepare('INSERT INTO compat_auto (valor) VALUES (?)')->execute(['id']);
    $assert((int)$pdo->lastInsertId() === 1, 'LASTVAL usado para recuperar ID serial');

    $normalized = database_normalize_booleans(['ubs_ativa' => 't', 'ativa' => 'f', 'nested' => [['notificado' => 'f']]]);
    $assert($normalized['ubs_ativa'] === true && $normalized['ativa'] === false && $normalized['nested'][0]['notificado'] === false, 'serialização converte booleanos t/f');

    echo "PostgreSQL local integration tests: OK\n";
} finally {
    $pdo->exec('DROP TABLE IF EXISTS consultas, notificacoes, pacientes, compat_subscriptions, compat_indicators, compat_timestamps, compat_auto, compat_auth');
}
