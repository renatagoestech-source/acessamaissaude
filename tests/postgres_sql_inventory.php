<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/postgres_compat.php';

$files = [dirname(__DIR__) . '/api.php'];
$count = 0;
$errors = [];
foreach ($files as $file) {
    $tokens = token_get_all(file_get_contents($file));
    foreach ($tokens as $token) {
        if (!is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING) continue;
        try {
            $sql = eval('return ' . $token[1] . ';');
        } catch (Throwable $e) {
            continue;
        }
        if (!is_string($sql) || !preg_match('/^\s*(?:SELECT|INSERT|UPDATE|DELETE)\b/i', $sql)) continue;
        $count++;
        try {
            postgres_compat_sql($sql);
        } catch (Throwable $e) {
            $errors[] = basename($file) . ':' . $token[2] . ': ' . $e->getMessage() . ' | ' . preg_replace('/\s+/', ' ', substr($sql, 0, 180));
        }
    }
}
if ($errors) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}
echo "OK: {$count} SQL literals in api.php translated without unsupported MySQL syntax\n";
