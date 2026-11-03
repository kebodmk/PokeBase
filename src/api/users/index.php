<?php

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

require_once __DIR__ . "/../../includes/config.php";
require_once __DIR__ . "/../../includes/PokeDatabase.php";
require_once __DIR__ . "/../../includes/json_functions.php";
require_once __DIR__ . '/../api_functions.php';

$method = $_SERVER['REQUEST_METHOD'];

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$parts = explode('/', trim($path, '/'));
$usersIndex = array_search('users', $parts, true);
$routePart = $usersIndex !== false ? ($parts[$usersIndex + 1] ?? null) : null;
$subResource = $usersIndex !== false ? ($parts[$usersIndex + 2] ?? null) : null;

function getUserById(int $userId): ?array
{
    $sql = 'SELECT user_id, email, first_name, last_name, role, date_registered FROM users WHERE user_id = :user_id';
    $rows = PokeDatabase::getDataFromSQL($sql, [':user_id' => $userId]);
    return $rows[0] ?? null;
}

/*
------------------------------------------------------------
POST /api/users/register
------------------------------------------------------------
*/
if ($routePart === 'register') {
    if ($method !== 'POST') {
        sendJson(405, ['success' => false, 'message' => 'Method not allowed.']);
    }

    $data = getJsonBody();
    $email = trim((string)($data['email'] ?? ''));
    $userpassword = (string)($data['password'] ?? '');
    $firstName = trim((string)($data['first_name'] ?? ''));
    $lastName = trim((string)($data['last_name'] ?? ''));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendJson(400, ['success' => false, 'message' => 'A valid email address is required.']);
    }

    if (strlen($userpassword) < 8) {
        sendJson(400, ['success' => false, 'message' => 'Password must be at least 8 characters long.']);
    }

    // first_name and last_name are NOT NULL in the users table
    if ($firstName === '' || $lastName === '') {
        sendJson(400, ['success' => false, 'message' => 'First and last name are required.']);
    }

    $check_sql = 'SELECT user_id FROM users WHERE email = :email';
    $check_params = [':email' => $email];
      
    $user_already_exist_results = PokeDatabase::getDataFromSQL($check_sql,$check_params);

    if (count($user_already_exist_results)>0) {
        sendJson(409, ['success' => false, 'message' => 'That email address is already registered.']);
    }
    $insert_user_sql = "INSERT INTO users
    (email, first_name, last_name, date_registered, password, role)
    VALUES
    (:email, :first_name, :last_name, :date_registered, :password, 'user')";

    $insert_params = [
        ':email' => $email,
        ':first_name' => $firstName,
        ':last_name' => $lastName,
        ':date_registered' => date('Y-m-d'),
        ':password' => password_hash($userpassword, PASSWORD_DEFAULT),
    ];

    $userId = (int)PokeDatabase::executeSQL($insert_user_sql, $insert_params, true);
    #TODO: Manage session tokens in the DB eventually

    $user_data = getUserById($userId);


    sendJson(201, [
        'success' => true,
        'message' => 'Registration successful.',
        'user' => $user_data,
    ]);
}

/*
------------------------------------------------------------
POST /api/users/login
------------------------------------------------------------
*/
if ($routePart === 'login') {
    if ($method !== 'POST') {
        sendJson(405, ['success' => false, 'message' => 'Method not allowed.']);
    }

    $data = getJsonBody();
    $email = trim((string)($data['email'] ?? ''));
    $userpassword = (string)($data['password'] ?? '');

    $user_login_sql = 'SELECT user_id, password FROM users WHERE email = :email LIMIT 1';
    $login_params = [':email' => $email];
    $records = PokeDatabase::getDataFromSQL($user_login_sql,$login_params);
    $record = $records[0] ?? null;
    if (!$record || !password_verify($userpassword, $record['password'])) {
        sendJson(401, [
            'success' => false,
            'message' => 'Invalid email or password.'
        ]);
    }

    deleteExpiredSessions();

    // Manage cookie session token
    $token = bin2hex(random_bytes(32));

    $insert_session_sql = 'INSERT INTO api_sessions
    (user_id, token_hash, created_at, ended_at)
    VALUES
    (:user_id, :token_hash, NOW(), NOW() + INTERVAL ' . SESSION_LIFETIME_SECONDS . ' SECOND)';

    PokeDatabase::executeSQL($insert_session_sql, [
        ':user_id' => (int)$record['user_id'],
        ':token_hash' => hashSessionToken($token),
    ]);

    setSessionCookie($token, time() + SESSION_LIFETIME_SECONDS);

    $user_data = getUserById((int)$record['user_id']);

    sendJson(200, [
        'success' => true,
        'message' => 'Login successful.',
        'user' => $user_data,
    ]);
}

/*
------------------------------------------------------------
POST /api/users/logout
------------------------------------------------------------
*/
if ($routePart === 'logout') {
    if ($method !== 'POST') {
        sendJson(405, ['success' => false, 'message' => 'Method not allowed.']);
    }
    // End the session by deleting it
    $token = $_COOKIE[SESSION_COOKIE] ?? '';

    if (is_string($token) && preg_match('/^[0-9a-f]{64}$/', $token)) {
        PokeDatabase::executeSQL(
            'DELETE FROM api_sessions WHERE token_hash = :token_hash',
            [':token_hash' => hashSessionToken($token)]
        );
    }

    deleteExpiredSessions();

    setSessionCookie('', time() - 3600);
    sendJson(200, ['success' => true, 'message' => 'Logout successful.']);
}
