<?php
declare(strict_types=1);

require_once __DIR__ . '/../common.php';

function expect_session_result(bool $expected, ?array $session, ?array $account, string $label): void
{
    $actual = professional_session_matches_account($session, $account);
    if ($actual !== $expected) {
        throw new RuntimeException($label . ': resultado inesperado.');
    }
}

$session = ['id' => 42, 'auth_version' => 1];
$activeUnverified = ['status' => 'ativo', 'email_verificado_em' => null, 'auth_version' => 1];

expect_session_result(true, $session, $activeUnverified, 'Conta ativa autenticada sem timestamp de verificação');
expect_session_result(false, $session, ['status' => 'suspenso', 'auth_version' => 1], 'Conta suspensa');
expect_session_result(false, $session, ['status' => 'ativo', 'auth_version' => 2], 'Versão de autenticação revogada');
expect_session_result(false, $session, null, 'Conta inexistente');
expect_session_result(false, null, $activeUnverified, 'Sessão ausente');

$api = file_get_contents(__DIR__ . '/../api.php');
$start = strpos($api, 'function validate_professional_session(PDO $pdo): void {');
$end = $start === false ? false : strpos($api, "\nfunction professional_logout", $start);
if ($start === false || $end === false) {
    throw new RuntimeException('Validador de sessão profissional não encontrado.');
}
$validator = substr($api, $start, $end - $start);
if (!str_contains($validator, 'professional_session_matches_account')) {
    throw new RuntimeException('O validador deve usar os critérios de conta ativa e auth_version.');
}
if (str_contains($validator, 'email_verificado_em')) {
    throw new RuntimeException('A ausência do timestamp de verificação não deve invalidar sessão autenticada.');
}

echo "Professional session tests: OK\n";
