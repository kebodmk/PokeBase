<?php
    require_once __DIR__ . "/includes/auth_cookie.php";
    requirePageRole([ROLE_MANAGER, ROLE_EMPLOYEE]);

    include "includes/header.php";
    include "navigation.php"
?>
<div id = "main">
    <h2 style = "text-align:center">Scanner In Development</h2>
</div>
<?php
    include "includes/footer.php";
?>