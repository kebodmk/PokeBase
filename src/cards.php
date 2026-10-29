<?php 

require_once __DIR__ . "/includes/config.php";
require_once __DIR__ . "/includes/PokeDatabase.php";

include "includes/header.php";
include "navigation.php";
include "includes/functions.php";

$cardId = $_GET["card_id"] ?? null;

//Access the API to get data
$cardsDataURL = $mainURL."/api/cards/search?card_id={$cardId}";

$cardsResponse  = getJSONFromURL($cardsDataURL);
$cards = $cardsResponse["cards"];

// Display the translators's data
$card = $cards[0];

    // Getting the origin country
    $cardId = $card["card_id"];
    $cardName = $card["name"];
    $cardSet = $card["set"];
    $cardSetNum = $card["set_num"];
    $language = $card["lang"];
    $price = $card["price"];
    $priceDate = $card["price_date"];
    $condition = $card["condition"];
    $print = $card["printing"];
    $grade = $card["grade"] ?? null;

    echo "<h1>{$cardName}</h1>";
    echo "<h2>Price (Updated on {$priceDate}): \${$price}</h2>";
    echo "<h3>Card Set: {$cardSet}</h3>";
    echo "<h3>Card Set Number: {$cardSetNum}</h3>";
    echo "<h3>Print Edition: {$print}";
    echo "<h3>Card Condition: {$condition}</h3>";
    echo "<h3>PSA: {$grade}</h3>";
    echo "<h3>Card Language: {$language}</h3>";

?>
