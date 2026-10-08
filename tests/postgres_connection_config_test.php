<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';

$cases = [
    5432 => false,
    6543 => true,
];

foreach ($cases as $port => $expectedEmulation) {
    $options = postgres_pdo_options($port);
    $actualEmulation = $options[PDO::ATTR_EMULATE_PREPARES] ?? null;
    if ($actualEmulation !== $expectedEmulation) {
        fwrite(STDERR, "FAIL: porta {$port} esperava ATTR_EMULATE_PREPARES=" . ($expectedEmulation ? 'true' : 'false') . "\n");
        exit(1);
    }
    if (($options[PDO::ATTR_ERRMODE] ?? null) !== PDO::ERRMODE_EXCEPTION) {
        fwrite(STDERR, "FAIL: porta {$port} deve manter PDO::ERRMODE_EXCEPTION\n");
        exit(1);
    }
}

echo "OK: conexão direta/sessão usa prepared statements nativos; Transaction Pooler (6543) usa emulação\n";
