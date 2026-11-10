<?php
require_once __DIR__ . '/includes/auth_cookie.php';

// Show each message once. Read before any output, since this clears the message cookie.
$flash = takeFlash();
$error = $flash["error"];
$message = $flash["message"];
$page = $_GET["page"]??"login";

include "includes/header.php";
include "navigation.php";

echo "<div id='main'>";
echo "<div class='gerror'>" . htmlspecialchars($error) . "</div>";
echo "<div class='gmessage'>" . htmlspecialchars($message) . "</div>";

if($page=="login"){
    require_once "forms/loginForm.php";
}else if($page=="register"){
    require_once "forms/registrationForm.php";
} else {
    require_once "index.php";
}
echo "</div>";

include "includes/footer.php";
?>