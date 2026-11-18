<?php
    require_once __DIR__ . "/includes/auth_cookie.php";
    requirePageRole([ROLE_MANAGER, ROLE_EMPLOYEE]);

    include "includes/header.php";
    include "navigation.php";

    // Show each message once
    $flash = takeFlash();
    $error = $flash["error"];
    $message = $flash["message"];

    // Fetch transactions
    $sessionToken = $_COOKIE[SESSION_COOKIE] ?? "";

    $requestHeaders = "Accept: application/json\r\n";

    if (is_string($sessionToken) && preg_match('/^[0-9a-f]{64}$/', $sessionToken)) {
        $requestHeaders .= "Cookie: " . SESSION_COOKIE . "=" . $sessionToken . "\r\n";
    }

    $url = rtrim($mainURL, "/") . "/api/audits/get";

    $options = [
        "http" => [
            "method"        => "GET",
            "header"        => $requestHeaders,
            "timeout"       => 8,
            "ignore_errors" => true
        ],
        "ssl" => [
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

    $audit = null;
    if (is_array($result) && ($result["success"] ?? false) === true) {
        $audit = $result["audits"];
    } elseif ($error === "") {
        $error = $result["message"] ?? "Unable to load audit log data.";
    }

?>
<div id = "main">
    <h1 style = "text-align:center">Audit Log</h1>
    <div class="table-container" style = "display: flex; justify-content: center; align-items: center;">
        <table id="auditTable">
            <thead>
                <tr>
                    <th>AUDIT ID</th>
                    <th>USER</th>
                    <th>Date</th>
                    <th>Notes</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($audit as $row): ?>

                    <tr>
                        <td>
                            <?= htmlspecialchars($row['audit_id']) ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($row['User']) ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($row['date']) ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($row['notes']) ?>
                        </td>
                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>
    </div>
</div>
<?php
    include "includes/footer.php";
?>