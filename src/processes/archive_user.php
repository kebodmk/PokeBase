<?php
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE);
session_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth_cookie.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../users.php");
    exit;
}

$userId = $_POST["archive_user_id"] ?? "";

$sessionToken = $_SESSION["api_token"] ?? ($_COOKIE[SESSION_COOKIE] ?? "");

if (!is_string($sessionToken) || !preg_match('/^[0-9a-f]{64}$/', $sessionToken)) {
    $_SESSION["error"] = "You must be logged in to do that.";
    header("Location: ../login.php?page=login");
    exit;
}

// Call PUT /api/users/archive/{user_id}. The API's RBAC rejects anyone who isn't a manager.
$url = rtrim($mainURL, "/") . "/api/users/archive/" . rawurlencode((string)$userId);

$options = [
    "http" => [
        "method"        => "PUT",
        "header"        => "Content-Type: application/json\r\n" .
                           "Accept: application/json\r\n" .
                           "Cookie: " . SESSION_COOKIE . "=" . $sessionToken . "\r\n",
        "content"       => json_encode(["archived" => 1]),
        "timeout"       => 8,
        "ignore_errors" => true       // lets us read the body on 4xx/5xx
    ],
    "ssl" => [
        // Dev only. Remove these three lines once the API has a real certificate.
        "verify_peer"       => false,
        "verify_peer_name"  => false,
        "allow_self_signed" => true
    ]
];

$response = @file_get_contents($url, false, stream_context_create($options));
$result = json_decode((string)$response, true);

if (is_array($result) && ($result["success"] ?? false) === true) {
    $_SESSION["message"] = "User archived.";
} else {
    $_SESSION["error"] = $result["message"] ?? "Unable to communicate with the user service.";
}

header("Location: ../users.php");
exit;
