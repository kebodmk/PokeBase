<?php
    require_once __DIR__ . "/includes/config.php";
    include "includes/functions.php";

    $cardDataURL = $mainURL."/api/cards/search?";
    $cardResponse  = getJSONFromURL($cardDataURL);
    $cards = $cardResponse["cards"];

    //Grab whats in search bar
    if (isset($_GET['query'])) {
        $search_string = trim($_GET['query']);
        if (!empty($search_string)) {
            // Displaying searched for users
            $displayedCards = [];
            foreach ($cards as $card){
                $name = $card["name"];
                if(str_contains(strtolower($name), strtolower($search_string)) && !in_array($card["card_id"], $displayedCards)){
                    echo "<div class='search-item'>
                        <a href='cards.php?card_id={$card["card_id"]}'>" .
                        htmlspecialchars($card['name']) .
                        "</a>
                    </div>";
                    // TODO : Duplicate tracking will need worked on to make it filter as intended
                    $displayedCards[] = $card["card_id"];
                }
            }
        }
        exit;
    }
?>