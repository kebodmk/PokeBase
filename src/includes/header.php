<?php
/**if(!isset($url)) $url = url();
session_start();*/

// navigation.php looks up the logged-in user with getSessionUser()
require_once __DIR__ . '/auth_cookie.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="./images/logo.jpg">
    <link rel="stylesheet" type="text/css" href="styles.css">
    <script src = "navigation.js"></script>
    <title>Pokébase</title>
</head>
<body>