<?php
require_once __DIR__ . '/includes/auth_cookie.php';

requirePageRole([ROLE_MANAGER]);

// Show each message once
$flash = takeFlash();
$error = $flash["error"];
$message = $flash["message"];

// Call GET /api/users/list as the logged-in user
$sessionToken = $_COOKIE[SESSION_COOKIE] ?? "";

$requestHeaders = "Accept: application/json\r\n";
if (is_string($sessionToken) && preg_match('/^[0-9a-f]{64}$/', $sessionToken)) {
    $requestHeaders .= "Cookie: " . SESSION_COOKIE . "=" . $sessionToken . "\r\n";
}

$url = rtrim($mainURL, "/") . "/api/users/list";

$options = [
    "http" => [
        "method"        => "GET",
        "header"        => $requestHeaders,
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

$httpCode = 0;
if (isset($http_response_header[0]) &&
    preg_match('#HTTP/\S+\s+(\d{3})#', $http_response_header[0], $m)) {
    $httpCode = (int) $m[1];
}

// Not logged in, send them to log in
if ($httpCode === 401) {
    setFlash("error", $result["message"] ?? "You must be logged in to do that.");
    header("Location: login.php?page=login");
    exit;
}

$users = null;
if (is_array($result) && ($result["success"] ?? false) === true) {
    $users = $result["users"];
} elseif ($error === "") {
    $error = $result["message"] ?? "Unable to load users.";
}

$roles = ["user", "employee", "manager"];

include "includes/header.php";
include "navigation.php";
?>
<div id = "main">
    <h2 style = "text-align:center">User Accounts</h2>
    <div class='gerror'><?php echo htmlspecialchars($error); ?></div>
    <div class='gmessage'><?php echo htmlspecialchars($message); ?></div>

    <?php if ($users !== null): ?>
    <form id = "users-form" action = "processes/update_roles.php" method = "POST">
        <table id = "users-table">
            <tr>
                <th>First Name</th>
                <th>Last Name</th>
                <th>Email</th>
                <th>Role</th>
                <th></th>
            </tr>
            <?php foreach ($users as $user):
                $currentRole = strtolower(trim($user["role"])); ?>
            <tr>
                <td><?php echo htmlspecialchars($user["first_name"]); ?></td>
                <td><?php echo htmlspecialchars($user["last_name"]); ?></td>
                <td><?php echo htmlspecialchars($user["email"]); ?></td>
                <td>
                    <select class = "role-select" data-user-id = "<?php echo (int)$user["user_id"]; ?>" data-original = "<?php echo htmlspecialchars($currentRole); ?>">
                        <?php foreach ($roles as $role) {
                            $selected = $role === $currentRole ? " selected" : "";
                            echo "<option value=\"{$role}\"{$selected}>" . ucfirst($role) . "</option>";
                        } ?>
                    </select>
                </td>
                <td>
                    <button type = "submit" class = "archive-button" formaction = "processes/archive_user.php" name = "archive_user_id" value = "<?php echo (int)$user["user_id"]; ?>" data-name = "<?php echo htmlspecialchars($user["first_name"] . " " . $user["last_name"]); ?>">Archive</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <input type = "submit" id = "save-roles" value = "Save" disabled>
    </form>
    <script src="users.js" defer></script>
    <?php endif; ?>
</div>
<?php
    include "includes/footer.php";
?>
