<?php
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth_cookie.php';


if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../login.php?page=login");
    exit;
}

$username     = trim($_POST["user"] ?? "");
$userpassword = $_POST["pass"] ?? "";

if ($username === "" || $userpassword === "") {
    setFlash("error", "All fields are required.");
    header("Location: ../login.php?page=login");
    exit;
}

if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
    setFlash("error", "Enter a valid email address.");
    header("Location: ../login.php?page=login");   // this was missing
    exit;
}

// Build request body
$jsonData = json_encode([
    "email"    => $username,
    "password" => $userpassword,
]);
 
// Call POST /api/users/login

$url = rtrim($mainURL, "/") . "/api/users/login";
 
$options = [
    "http" => [
        "method"        => "POST",
        "header"        => "Content-Type: application/json\r\n" .
                           "Accept: application/json\r\n",
        "content"       => $jsonData,
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


// Error: no response at all

if ($response === false) {
    setFlash("error", "Unable to communicate with the login service.");
    header("Location: ../login.php?page=login");
    exit;
}
 
/*
------------------------------------------------------------
Read the HTTP status code from the response headers.
------------------------------------------------------------
*/
$httpCode = 0;
if (isset($http_response_header[0]) &&
    preg_match('#HTTP/\S+\s+(\d{3})#', $http_response_header[0], $m)) {
    $httpCode = (int) $m[1];
}
 
/*
------------------------------------------------------------
Error: common HTTP failures.
------------------------------------------------------------
*/
if ($httpCode === 429) {
    setFlash("error", "Too many attempts. Please wait a moment and try again.");
    header("Location: ../login.php?page=login");
    exit;
}
 
if ($httpCode >= 500) {
    setFlash("error", "The server ran into a problem. Please try again later.");
    header("Location: ../login.php?page=login");
    exit;
}
 
// Convert JSON response into PHP array.

$result = json_decode($response, true);
 
if (!is_array($result)) {
    setFlash("error", "Invalid response received from the login service.");
    header("Location: ../login.php?page=login");
    exit;
}
 
// Successful login

// The API issues the session as a cookie. This call came from the server, not the browser,
// so read it from the response headers and pass it on to the browser.
$sessionToken = null;
foreach ($http_response_header ?? [] as $responseHeader) {
    if (preg_match('/^Set-Cookie:\s*' . SESSION_COOKIE . '=([0-9a-f]{64})/i', $responseHeader, $m)) {
        $sessionToken = $m[1];
    }
}

if (($result["success"] ?? false) === true && !empty($result["user"]) && $sessionToken !== null) {

    // The cookie is the whole login. Pages look the user up from it in the database.
    setSessionCookie($sessionToken, time() + SESSION_LIFETIME_SECONDS);

    header("Location: ../index.php");
    exit;
}
 
// Login rejected by API
if ($httpCode === 401) {
    setFlash("error", "Incorrect email or password.");
} elseif ($httpCode === 403) {
    setFlash("error", $result["message"] ?? "This account can't sign in right now.");
} else {
    setFlash("error", $result["message"] ?? "Login failed.");
}
 
header("Location: ../login.php?page=login");
exit;