<?php
// Shown by requirePageRole() (with HTTP 403) when the logged-in user's role isn't allowed on a page
include __DIR__ . "/includes/header.php";
include __DIR__ . "/navigation.php";
?>
<div id = "main">
    <h2 style = "text-align:center">Unauthorized</h2>
    <p style = "text-align:center">You don't have permission to view this page.</p>
</div>
<?php
    include __DIR__ . "/includes/footer.php";
?>
