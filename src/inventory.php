<?php
    require_once __DIR__ . "/includes/config.php";
    include "includes/header.php";
    include "navigation.php";
    include "search.php";
?>
<script src="search.js" defer></script>
<div id = "main">
    <div class="topnav" style = "text-align:center">
        <h2>Search for cards in inventory here</h2>
        <div class="topnav">
            <input type="text" id="search-bar" placeholder="Search..">
        </div>

        <div id="results"></div>
        
    </div>
</div>
<?php
    include "includes/footer.php";
?>