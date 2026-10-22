<?php
    include "header.php";
    include "navigation.php"

    $cardDataURL = $mainURL."/api/translators";
    $cardResponse  = getJSONFromURL($translatorDataURL);
    $card = $translatorResponse["data"];
?>
<script src="search.js" defer></script>
<div id = "main">
    <div class="topnav" style = "text-align:center">
        <h2>Search for cards in inventory here</h2>
        <input type="text" id="search-bar" placeholder="Search Cards..">
    </div>
</div>
<?php
    include "footer.php";
?>