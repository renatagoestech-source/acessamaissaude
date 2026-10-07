<?php
declare(strict_types=1);

const SESSION_IDLE_TIMEOUT = 1800;
const SESSION_ABSOLUTE_TIMEOUT = 28800;

function configure_secure_session(): void
{
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    $https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https')
        || getenv('VERCEL') === '1';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    date_default_timezone_set('America/Sao_Paulo');
}

function enforce_session_lifetime(): void
{
    $hasAuth = !empty($_SESSION['professional']) || !empty($_SESSION['admin']);
    if (!$hasAuth) return;
    // Mantém a sessão profissional durante o trabalho; o botão Sair continua encerrando o acesso.
    if (!empty($_SESSION['professional'])) {
        $_SESSION['_last_activity'] = time();
        return;
    }
    $now = time();
    $last = (int)($_SESSION['_last_activity'] ?? $now);
    $created = (int)($_SESSION['_auth_created_at'] ?? $now);
    if (($now - $last) > SESSION_IDLE_TIMEOUT || ($now - $created) > SESSION_ABSOLUTE_TIMEOUT) {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', ['expires' => time() - 42000, 'path' => $params['path'] ?: '/', 'domain' => $params['domain'] ?? '', 'secure' => (bool)$params['secure'], 'httponly' => true, 'samesite' => $params['samesite'] ?? 'Lax']);
            }
            session_regenerate_id(true);
        }
        return;
    }
    $_SESSION['_last_activity'] = $now;
}

function rotate_csrf_token(): string
{
    $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['_csrf_token'];
}

function require_csrf(): void
{
    $expected = (string)($_SESSION['_csrf_token'] ?? '');
    $received = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($expected === '' || $received === '' || !hash_equals($expected, $received)) {
        $currentToken = $expected !== '' ? $expected : rotate_csrf_token();
        json_response(['success' => false, 'message' => 'A sessão de segurança expirou. Atualizando a proteção; tente novamente.', 'code' => 'CSRF_INVALID', 'csrf_token' => $currentToken], 419);
    }
}

function json_response(array $data, int $status = 200): never
{
    // Remove qualquer warning/saída acidental antes do JSON, evitando
    // que o navegador receba uma resposta inválida após um agendamento.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function body_json(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }

    $data = json_decode($raw, true);

    return is_array($data) ? $data : [];
}

function required_string(array $data, string $key): string
{
    $value = trim((string)($data[$key] ?? ''));

    if ($value === '') {
        json_response([
            'success' => false,
            'message' => "O campo '{$key}' é obrigatório."
        ], 422);
    }

    return $value;
}

function clean_list(mixed $value): array
{
    if (!is_array($value)) {
        return [];
    }

    $result = [];

    foreach ($value as $item) {
        $item = trim((string)$item);

        if ($item !== '' && !in_array($item, $result, true)) {
            $result[] = $item;
        }
    }

    return $result;
}

function valid_date(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function is_holiday(string $date): bool
{
    $md = date('m-d', strtotime($date));

    return in_array($md, [
        '01-01',
        '04-21',
        '05-01',
        '09-07',
        '10-12',
        '11-02',
        '11-15',
        '12-25'
    ], true);
}

function is_business_day(string $date): bool
{
    $weekday = (int)date('N', strtotime($date));

    return $weekday <= 5 && !is_holiday($date);
}

function admin_session(): ?array
{
    return $_SESSION['admin'] ?? null;
}

function require_admin(): array
{
    $session = admin_session();

    if (!$session) {
        json_response([
            'success' => false,
            'message' => 'Sessão administrativa expirada. Faça login novamente.'
        ], 401);
    }

    return $session;
}

function professional_session(): ?array
{
    return $_SESSION['professional'] ?? null;
}

function require_professional(): array
{
    $session = professional_session();
    if (!$session) {
        json_response(['success' => false, 'message' => 'Sessão profissional expirada. Faça login novamente.'], 401);
    }
    return $session;
}

function authorize_ubs(string $ubsId): array
{
    $session = require_admin();

    if (
        !in_array($session['tipo'], ['desenvolvedor', 'secretaria'], true) &&
        $session['ubs_id'] !== $ubsId
    ) {
        json_response([
            'success' => false,
            'message' => 'Você não tem permissão para acessar esta UBS.'
        ], 403);
    }

    return $session;
}
