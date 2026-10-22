<?php
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE);
session_start();

require_once __DIR__ . '/../../includes/config.php';   // adjust to wherever this file lives


if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../login.php?page=login");
    exit;
}

$username     = trim($_POST["user"] ?? "");
$userpassword = $_POST["pass"] ?? "";

if ($username === "" || $userpassword === "") {
    $_SESSION["error"] = "All fields are required.";
    header("Location: ../login.php?page=login");
    exit;
}

if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
    $_SESSION["error"] = "Enter a valid email address.";
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
    $_SESSION["error"] = "Unable to communicate with the login service.";
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
    $_SESSION["error"] = "Too many attempts. Please wait a moment and try again.";
    header("Location: ../login.php?page=login");
    exit;
}
 
if ($httpCode >= 500) {
    $_SESSION["error"] = "The server ran into a problem. Please try again later.";
    header("Location: ../login.php?page=login");
    exit;
}
 
// Convert JSON response into PHP array.

$result = json_decode($response, true);
 
if (!is_array($result)) {
    $_SESSION["error"] = "Invalid response received from the login service.";
    header("Location: ../login.php?page=login");
    exit;
}
 
// Successful login

if (($result["success"] ?? false) === true && !empty($result["token"])) {
 
    session_regenerate_id(true);       // new session id after login
 
    $_SESSION["api_token"]   = $result["token"];
    $_SESSION["user"]        = $result["user"] ?? [];
    $_SESSION["LoginStatus"] = "YES";
    $_SESSION["session_id"]  = session_id();
    $_SESSION["login_time"]  = date("Y-m-d H:i:s");
    $_SESSION["error"]       = "";
 
    if (isset($result["user"]["userid"])) {
        $_SESSION["user_id"] = $result["user"]["userid"];
    }
 
    header("Location: ../profile.php");
    exit;
}
 
// Login rejected by API
if ($httpCode === 401) {
    $_SESSION["error"] = "Incorrect email or password.";
} elseif ($httpCode === 403) {
    $_SESSION["error"] = $result["message"] ?? "This account can't sign in right now.";
} else {
    $_SESSION["error"] = $result["message"] ?? "Login failed.";
}
 
$_SESSION["LoginStatus"] = "NO";
header("Location: ../login.php?page=login");
exit;