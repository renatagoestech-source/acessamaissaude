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

configure_secure_session(0);
if ((int)session_get_cookie_params()['lifetime'] !== 0) {
    throw new RuntimeException('Rotas não profissionais devem manter o cookie de sessão atual.');
}
configure_secure_session(SESSION_ABSOLUTE_TIMEOUT);
if ((int)session_get_cookie_params()['lifetime'] !== SESSION_ABSOLUTE_TIMEOUT) {
    throw new RuntimeException('A sessão profissional deve usar cookie persistente alinhado ao prazo do servidor.');
}

$apiBootstrap = substr($api, 0, strpos($api, 'switch ($action)') ?: strlen($api));
$validationPosition = strpos($apiBootstrap, 'validate_professional_session($pdo);');
$cookieRefreshPosition = strpos($apiBootstrap, 'refresh_professional_session_cookie();');
if ($validationPosition === false || $cookieRefreshPosition === false || $cookieRefreshPosition <= $validationPosition) {
    throw new RuntimeException('O cookie profissional deve ser renovado somente após a validação da conta.');
}

ini_set('session.use_cookies', '0');
if (!session_start()) {
    throw new RuntimeException('A sessão do teste de cookie não iniciou.');
}
$_SESSION['professional'] = ['id' => 42, 'auth_version' => 1];
if (!refresh_professional_session_cookie()) {
    throw new RuntimeException('O cookie persistente da sessão profissional não pôde ser renovado.');
}
session_abort();

echo "Professional session tests: OK\n";
