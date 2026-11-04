<?php
    require_once __DIR__ . "/includes/config.php";
    include "includes/functions.php";

    $cardDataURL = $mainURL."/api/cards/search?";
    $cardResponse  = getJSONFromURL($cardDataURL);
    $cards = $cardResponse["cards"];

    //Grab whats in search bar
    if (isset($_GET['query'])) {
        $search_string  = trim($_GET['query']);
        $selected_Set   = trim($_GET['set'] ?? '');
        $selected_Print = trim($_GET['printing'] ?? '');

        if ($search_string !== '') {
            $displayedCards = [];

            foreach ($cards as $card) {
                $matchesName  = str_contains(strtolower($card["name"]), strtolower($search_string));
                $matchesSet   = $selected_Set === ''   || strcasecmp($card["set"], $selected_Set) === 0;
                $matchesPrint = $selected_Print === '' || strcasecmp($card["printing"], $selected_Print) === 0;

                // One entry per card/set/printing combination, so the same card
                // in a different printing isn't hidden by the duplicate check.
                $key = $card["card_id"] . '|' . $card["set"] . '|' . $card["printing"];

                if ($matchesName && $matchesSet && $matchesPrint && !isset($displayedCards[$key])) {
                    echo "<div class='search-item'>
                        <a href='cards.php?card_id=" . urlencode($card["card_id"]) . "'>" .
                        htmlspecialchars($card['name']) . " || " .
                        htmlspecialchars($card['set']) . " || " .
                        htmlspecialchars($card['lang']) . " || " .
                        htmlspecialchars($card['condition']) . " || " .
                        htmlspecialchars($card['printing']) . " || $" .
                        htmlspecialchars($card['price']) .
                        "</a>
                    </div>";
                    $displayedCards[$key] = true;
                }
            }
        }
        exit;
    }
?>