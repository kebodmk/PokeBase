<?php


require_once __DIR__ . '/config.php';
require_once __DIR__ . '/PokeDatabase.php';

const SESSION_COOKIE = 'pokebase_session';
const SESSION_LIFETIME_SECONDS = 28800; // 8 hours
const FLASH_COOKIE = 'pokebase_flash';

const ROLE_MANAGER = 'manager';
const ROLE_EMPLOYEE = 'employee';
const ROLE_USER = 'user';

// Only the hash is stored in api_sessions, so a leaked table can't be used to log in
function hashSessionToken(string $token): string
{
    return hash('sha256', $token);
}

function cookieOptions(int $expires): array
{
    return [
        'expires' => $expires,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function setSessionCookie(string $token, int $expires): void
{
    setcookie(SESSION_COOKIE, $token, cookieOptions($expires));
}

// Returns ['user_id', 'role', 'first_name', 'last_name'] for the session's cookie, or
// null. Read from database per every request.
function getSessionUser(): ?array
{
    static $checked = false;
    static $user = null;

    if ($checked) {
        return $user;
    }
    $checked = true;

    $token = $_COOKIE[SESSION_COOKIE] ?? '';

    if (!is_string($token) || !preg_match('/^[0-9a-f]{64}$/', $token)) {
        return null;
    }

    $sql = 'SELECT u.user_id, u.role, u.first_name, u.last_name
    FROM api_sessions s
    JOIN users u ON u.user_id = s.user_id
    WHERE s.token_hash = :token_hash AND s.ended_at > NOW() AND u.archived = 0
    LIMIT 1';

    $rows = PokeDatabase::getDataFromSQL($sql, [':token_hash' => hashSessionToken($token)]);
    $user = $rows[0] ?? null;
    return $user;
}

// Call at the top of a website page, before any output.
// Not logged in -> login page. Role not allowed -> unauthorized page (HTTP 403).
function requirePageRole(array $allowedRoles): array
{
    $user = getSessionUser();

    if ($user === null) {
        setFlash('error', 'You must be logged in to view that page.');
        header('Location: login.php?page=login');
        exit;
    }

    if (!in_array(strtolower(trim((string)$user['role'])), $allowedRoles, true)) {
        http_response_code(403);
        include __DIR__ . '/../unauthorized.php';
        exit;
    }

    return $user;
}

// One-time message shown on the next page after a redirect. $type is 'error' or 'message'.
function setFlash(string $type, string $text): void
{
    setcookie(FLASH_COOKIE, json_encode([$type => $text]), cookieOptions(0));
}

// Reads and clears the message. Call before any output. The browser can edit this cookie, so the
// values are display text only and must be escaped when shown.
function takeFlash(): array
{
    $flash = json_decode((string)($_COOKIE[FLASH_COOKIE] ?? ''), true);

    if (isset($_COOKIE[FLASH_COOKIE])) {
        setcookie(FLASH_COOKIE, '', cookieOptions(time() - 3600));
    }

    return [
        'error' => is_array($flash) && is_string($flash['error'] ?? null) ? $flash['error'] : '',
        'message' => is_array($flash) && is_string($flash['message'] ?? null) ? $flash['message'] : '',
    ];
}
?>
