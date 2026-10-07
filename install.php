<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

// As variáveis ACESSA_DB_* permitem usar outra porta, usuário ou senha sem
// editar o código. No XAMPP padrão, os valores abaixo já são os corretos.
$defaults = [
    'host' => '127.0.0.1',
    'port' => '3306',
    'user' => 'root',
    'pass' => '',
    'db'   => 'conecta_saude'
];
$config = [
    'host' => getenv('ACESSA_DB_HOST') ?: $defaults['host'],
    'port' => getenv('ACESSA_DB_PORT') ?: $defaults['port'],
    'user' => getenv('ACESSA_DB_USER') ?: $defaults['user'],
    'pass' => getenv('ACESSA_DB_PASS') !== false ? getenv('ACESSA_DB_PASS') : $defaults['pass'],
    'db'   => getenv('ACESSA_DB_NAME') ?: $defaults['db']
];

$message = '';
$error = '';

try {
    if (is_file(__DIR__ . '/install.lock')) {
        throw new RuntimeException('O instalador já foi executado e está bloqueado por segurança. Remova install.lock somente se realmente precisar recriar o banco, após fazer backup.');
    }
    if (!extension_loaded('pdo_mysql')) {
        throw new RuntimeException('A extensão PDO MySQL não está habilitada no PHP. Ative pdo_mysql no php.ini do XAMPP e reinicie o Apache.');
    }

    // Conecta ao servidor sem selecionar um banco. Isso permite remover
    // uma instalação antiga/incompatível antes de criar a nova estrutura.
    $pdo = new PDO(
        'mysql:host=' . $config['host'] . ';port=' . $config['port'] . ';charset=utf8mb4',
        $config['user'],
        $config['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    // O projeto usa conecta_saude por padrão para manter compatibilidade com
    // instalações anteriores. O nome exibido ao usuário é Acessa+ Saúde.
    $dbName = str_replace('`', '``', $config['db']);
    $pdo->exec("DROP DATABASE IF EXISTS `{$dbName}`");
    $pdo->exec("CREATE DATABASE `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$dbName}`");

    $sql = file_get_contents(__DIR__ . '/database.sql');
    if ($sql === false) {
        throw new RuntimeException('Não foi possível ler o arquivo database.sql.');
    }

    // O database.sql também funciona diretamente pelo phpMyAdmin. Aqui já
    // recriamos o banco acima, então retiramos CREATE/DROP DATABASE e USE.
    $sql = preg_replace('/^\s*DROP DATABASE IF EXISTS.*?;\s*/ims', '', $sql, 1);
    $sql = preg_replace('/^\s*CREATE DATABASE.*?;\s*/ims', '', $sql, 1);
    $sql = preg_replace('/^\s*USE\s+`?[^`\s;]+`?\s*;\s*/im', '', $sql, 1);
    $sql = preg_replace('/^\s*USE\s+`?[^`\s;]+`?\s*;\s*/im', '', $sql, 1);

    // Executa cada comando terminado em ;. Os comandos do projeto não usam
    // procedures/triggers, portanto essa divisão é segura para o instalador.
    $statements = preg_split('/;\s*(?=(?:CREATE|INSERT|ALTER|SET|DROP|UPDATE|DELETE|USE)\b)/i', $sql);

    foreach ($statements as $statement) {
        $statement = trim($statement);
        if ($statement !== '') {
            $pdo->exec($statement);
        }
    }

    file_put_contents(__DIR__ . '/install.lock', 'Instalado em ' . date('c') . PHP_EOL, LOCK_EX);
    $message = 'Instalação concluída! O banco foi criado e o instalador foi bloqueado por segurança.';
} catch (Throwable $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Instalação - Acessa+ Saúde</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Arial,sans-serif;background:linear-gradient(135deg,#e9fbf9,#f7ffff);color:#073f43;padding:40px}.card{max-width:820px;margin:30px auto;background:#fff;border-radius:24px;padding:34px;box-shadow:0 15px 45px rgba(0,76,80,.12);border:1px solid #d8eeee}h1{margin-top:0;color:#006c70}.ok{background:#e4f8ef;border:1px solid #bce9d2;padding:16px;border-radius:12px;color:#176d43}.err{background:#fff0f0;border:1px solid #f0c2c2;padding:16px;border-radius:12px;color:#a52d2d}a{display:inline-block;margin-top:20px;background:#008f8c;color:#fff;text-decoration:none;padding:13px 20px;border-radius:12px;font-weight:700}code{background:#edf7f7;padding:3px 6px;border-radius:5px}.note{font-size:14px;color:#527174}
</style>
</head>
<body>
<div class="card">
<h1>Acessa+ Saúde</h1>
<h2>Instalação do banco de dados</h2>
<?php if ($message): ?>
    <p class="ok"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
    <p>Agora abra o sistema principal.</p>
    <a href="index.php">Abrir Acessa+ Saúde</a>
<?php else: ?>
    <p class="err">Não foi possível concluir a instalação:</p>
    <pre><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></pre>
    <p>Confirme se o Apache e o MySQL estão verdes no XAMPP. Se o seu MySQL usa senha para <code>root</code>, altere <code>install.php</code> e <code>config.php</code>.</p>
    <a href="diagnostico_xampp.php">Executar diagnóstico do XAMPP</a>
<?php endif; ?>
<p class="note">A instalação recria o banco conecta_saude para evitar conflitos com tabelas de versões anteriores.</p>
</div>
</body>
</html>
