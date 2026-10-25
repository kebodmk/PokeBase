<?php
    require_once __DIR__ . "/includes/config.php";
    include "includes/header.php";
    include "navigation.php";
    include "includes/functions.php";

    #REST API grabs
    $cardDataURL = $mainURL."/api/cards/search?";
    $cardResponse  = getJSONFromURL($cardDataURL);
    $card = $cardResponse["data"];

    

    //Grab whats in search bar
    if (isset($_GET['query'])) {
        $search_string = trim($_GET['query']);
        if (!empty($search_string)) {
            // Displaying searched for users
            $displayedUsers = [];
            foreach ($users as $user){
                $name = $user["first_name"] . " " . $user["last_name"];
                if(str_contains(strtolower($name), strtolower($search_string)) && !in_array($user["userid"], $displayedUsers)){
                    echo "<div class='search-item'>
                        <a href='translator.php?id={$user["userid"]}'>" .
                        htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) .
                        "</a>
                    </div>";
                    // Made so duplicates don't appear when searching due to multiple entries from a user having multiple langauges listed
                    $displayedUsers[] = $user["userid"];
                }
            }
        }
        exit;
    }
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