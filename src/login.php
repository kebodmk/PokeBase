<?php
require_once __DIR__ . "/../includes/config.php";

include "includes/header.php";
include "includes/navbar.php";
$error = $_SESSION["error"]??"";
$page = $_GET["page"]??"login";


echo "<div class='gerror'>{$error}</div>";

if($page=="login"){
    require_once "forms/loginForm.php";
}else if($page=="register"){
    require_once "forms/registrationForm.php";
} else {
    require_once "index.php";
}

include "include/footer.php";
?>