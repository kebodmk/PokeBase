<?php

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

// require_once __DIR__ . "/../../includes/config.php";
// require_once __DIR__ . "/../../includes/WFDatabase.php";
// require_once __DIR__ . "/../../includes/json_functions.php";

$method = $_SERVER['REQUEST_METHOD'];

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$parts = explode('/', trim($path, '/'));
$usersIndex = array_search('users', $parts, true);
$routePart = $usersIndex !== false ? ($parts[$usersIndex + 1] ?? null) : null;
$subResource = $usersIndex !== false ? ($parts[$usersIndex + 2] ?? null) : null;

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

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendJson(400, ['success' => false, 'message' => 'A valid email address is required.']);
    }

    if (strlen($userpassword) < 8) {
        sendJson(400, ['success' => false, 'message' => 'Password must be at least 8 characters long.']);
    }

    $check_sql = 'SELECT userid FROM users WHERE email = :email';
    $check_params = [':email' => $email];
      
    $user_already_exist_results = WFDatabase::getDataFromSQL($check_sql,$check_params);

    if (count($user_already_exist_results)>0) {
        sendJson(409, ['success' => false, 'message' => 'That email address is already registered.']);
    }
    #TODO: Should validate the country id
    $countryId = $data['original_country_id']; 

    $insert_user_sql = "INSERT INTO users
    (email, first_name, last_name, date_registered, original_country_id, password, role)
    VALUES
    (:email, :first_name, :last_name, :date_registered, :original_country_id, :password, 'user')";
    
    $insert_params = [
        ':email' => $email,
        ':first_name' => isset($data['first_name']) && trim((string)$data['first_name']) !== '' ? trim((string)$data['first_name']) : null,
        ':last_name' => isset($data['last_name']) && trim((string)$data['last_name']) !== '' ? trim((string)$data['last_name']) : null,
        ':date_registered' => date('Y-m-d H:i:s'),
        ':original_country_id' => $countryId,
        ':password' => password_hash($userpassword, PASSWORD_DEFAULT),
    ];
    
    $userId = WFDatabase::executeSQL($insert_user_sql,$insert_params);
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

    $user_login_sql = 'SELECT userid, password FROM users WHERE email = :email LIMIT 1';
    $login_params = [':email' => $email];
    $records = WFDatabase::getDatafromSQL($user_login_sql,$login_params);
    $record = $records[0];
    if (!$record || !password_verify($userpassword, $record['password'])) {
        sendJson(401, [
            'success' => false,
            'message' => 'Invalid email or password.'
        ]);
    }

    //TODO: Manage sessions in database
    //$token 
    
    $user_data = getUserById((int)$record['userid']);
    
    sendJson(200, [
        'success' => true,
        'message' => 'Login successful.',
        #'token' => $token,
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
    //TODO: Manage revoking sessions in db
    session_destroy();
    sendJson(200, ['success' => true, 'message' => 'Logout successful.']);
}
