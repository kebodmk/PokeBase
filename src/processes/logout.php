<?php
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth_cookie.php';

// POST only, so another site can't log people out with a link or image
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../index.php");
    exit;
}

$sessionToken = $_COOKIE[SESSION_COOKIE] ?? "";

if (is_string($sessionToken) && preg_match('/^[0-9a-f]{64}$/', $sessionToken)) {
    $url = rtrim($mainURL, "/") . "/api/users/logout";

    $options = [
        "http" => [
            "method"        => "POST",
            "header"        => "Accept: application/json\r\n" .
                               "Cookie: " . SESSION_COOKIE . "=" . $sessionToken . "\r\n",
            "timeout"       => 8,
            "ignore_errors" => true
        ],
        "ssl" => [
            // Dev only. Remove these three lines once the API has a real certificate.
            "verify_peer"       => false,
            "verify_peer_name"  => false,
            "allow_self_signed" => true
        ]
    ];

    @file_get_contents($url, false, stream_context_create($options));
}

// Clear the browser's copy of the token either way
setSessionCookie("", time() - 3600);

header("Location: ../login.php?page=login");
exit;
