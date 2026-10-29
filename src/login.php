<?php
session_start();
require_once __DIR__ . '/includes/config.php';

include "includes/header.php";
$error = $_SESSION["error"]??"";
$message = $_SESSION["message"]??"";
$page = $_GET["page"]??"login";

// Show each message once
unset($_SESSION["error"], $_SESSION["message"]);

echo "<div class='gerror'>" . htmlspecialchars($error) . "</div>";
echo "<div class='gmessage'>" . htmlspecialchars($message) . "</div>";

if($page=="login"){
    require_once "forms/loginForm.php";
}else if($page=="register"){
    require_once "forms/registrationForm.php";
} else {
    require_once "index.php";
}

include "includes/footer.php";
?>