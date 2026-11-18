<?php
    require_once __DIR__ . '/includes/auth_cookie.php';

    requirePageRole([ROLE_MANAGER]);

    include "includes/header.php";
    include "navigation.php";
?>
<script src="managerNavigation.js"></script>
<div id = "main">
    <div id="managerButtons">
        <button id="audit_log">Audit Log</button>
        <button id="sales_hist">Sales History</button>
        <button id="users">Users</button>
    </div>
</div>
<?php
    include "includes/footer.php";
?>