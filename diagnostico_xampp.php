<?php
header('Content-Type: text/html; charset=utf-8');
$checks = [];
$checks[] = ['PHP', PHP_VERSION, true];
$checks[] = ['PDO', extension_loaded('pdo'), extension_loaded('pdo')];
$checks[] = ['PDO MySQL', extension_loaded('pdo_mysql'), extension_loaded('pdo_mysql')];
$host = getenv('ACESSA_DB_HOST') ?: '127.0.0.1';
$port = getenv('ACESSA_DB_PORT') ?: '3306';
$user = getenv('ACESSA_DB_USER') ?: 'root';
$pass = getenv('ACESSA_DB_PASS') !== false ? getenv('ACESSA_DB_PASS') : '';
$db = getenv('ACESSA_DB_NAME') ?: 'conecta_saude';
if (isset($_GET['porta']) && preg_match('/^\d{2,5}$/', $_GET['porta'])) $port = $_GET['porta'];
try {
    $pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $checks[] = ['Conexão com MySQL', "Conectado em $host:$port", true];
    try { $pdo->query("USE `$db`"); $checks[] = ['Banco conecta_saude', 'Banco encontrado', true]; } catch (Throwable $e) { $checks[] = ['Banco conecta_saude', 'Não encontrado. Abra install.php.', false]; }
} catch (Throwable $e) {
    $checks[] = ['Conexão com MySQL', $e->getMessage(), false];
}
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>Diagnóstico XAMPP - Acessa+ Saúde</title><style>body{font:16px Arial;background:#f4fbfb;color:#16474b;padding:30px}.box{max-width:800px;margin:auto;background:#fff;padding:28px;border-radius:18px;box-shadow:0 10px 30px #064f5218}li{padding:12px;margin:8px 0;border-radius:8px;list-style:none}.ok{background:#e4f8ef;color:#176d43}.bad{background:#fff0f0;color:#a52d2d}code{background:#edf7f7;padding:3px 6px;border-radius:4px}a{display:inline-block;margin-top:15px;background:#079a98;color:#fff;padding:12px 16px;border-radius:8px;text-decoration:none;font-weight:bold}</style></head><body><div class="box"><h1>Acessa+ Saúde — Diagnóstico do XAMPP</h1><p>Use esta página para descobrir exatamente por que o sistema não conecta.</p><ul><?php foreach($checks as $c): ?><li class="<?= $c[2] ? 'ok' : 'bad' ?>"><strong><?= htmlspecialchars($c[0]) ?>:</strong> <?= htmlspecialchars((string)$c[1]) ?></li><?php endforeach; ?></ul><p>Se a porta for 3307, teste <code>diagnostico_xampp.php?porta=3307</code>.</p><a href="install.php">Abrir instalador</a> <a href="index.php">Abrir sistema</a></div></body></html>
