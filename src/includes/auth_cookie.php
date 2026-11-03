<?php

/*
------------------------------------------------------------
Session cookie shared by the API (issues/checks it) and the
processes/ pages (pass it on to the browser).
------------------------------------------------------------
*/

const SESSION_COOKIE = 'pokebase_session';
const SESSION_LIFETIME_SECONDS = 28800; // 8 hours

// Only the hash is stored in api_sessions, so a leaked table can't be used to log in
function hashSessionToken(string $token): string
{
    return hash('sha256', $token);
}

// HttpOnly keeps the token away from page JavaScript, SameSite=Strict stops other sites sending it (CSRF)
function setSessionCookie(string $token, int $expires): void
{
    setcookie(SESSION_COOKIE, $token, [
        'expires' => $expires,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}
?>