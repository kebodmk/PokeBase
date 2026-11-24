<?php 

require_once __DIR__ . "/includes/config.php";
require_once __DIR__ . "/includes/PokeDatabase.php";

include "includes/header.php";
include "navigation.php";
include "includes/functions.php";

$isManager = $isLoggedIn && strtolower(trim($sessionUser["role"])) === ROLE_MANAGER;

$cardId = $_GET["card_id"] ?? null;

// Show each message once
    $flash = takeFlash();
    $error = $flash["error"];
    $message = $flash["message"];

    // Fetch transactions
    $sessionToken = $_COOKIE[SESSION_COOKIE] ?? "";

    $requestHeaders = "Accept: application/json\r\n";

    if (is_string($sessionToken) && preg_match('/^[0-9a-f]{64}$/', $sessionToken)) {
        $requestHeaders .= "Cookie: " . SESSION_COOKIE . "=" . $sessionToken . "\r\n";
    }

    $url = rtrim($mainURL, "/") . "/api/cards/search?card_id={$cardId}";

    $options = [
        "http" => [
            "method"        => "GET",
            "header"        => $requestHeaders,
            "timeout"       => 8,
            "ignore_errors" => true
        ],
        "ssl" => [
            "verify_peer"       => false,
            "verify_peer_name"  => false,
            "allow_self_signed" => true
        ]
    ];

    $response = @file_get_contents($url, false, stream_context_create($options));
    $result = json_decode((string)$response, true);

    $httpCode = 0;
    if (isset($http_response_header[0]) &&
        preg_match('#HTTP/\S+\s+(\d{3})#', $http_response_header[0], $m)) {
        $httpCode = (int) $m[1];
    }

    // Not logged in, send them to log in
    if ($httpCode === 401) {
        setFlash("error", $result["message"] ?? "You must be logged in to do that.");
        header("Location: login.php?page=login");
        exit;
    }

    $cards = null;
    if (is_array($result) && ($result["success"] ?? false) === true) {
        $cards = $result["cards"];
    } elseif ($error === "") {
        $error = $result["message"] ?? "Unable to load card data.";
    }

// Display the card's data
$card = $cards[0] ?? null;

if ($card === null) {
    echo "<p>" . htmlspecialchars((string)($error ?: "Card not found.")) . "</p>";
    exit;
}

    $cardId = $card["card_id"];
    $cardName = $card["name"];
    $cardSet = $card["set"];
    $cardSetNum = $card["set_num"];
    $language = $card["lang"];
    $price = $card["price"];
    $priceDate = $card["price_date"];
    $condition = $card["condition"];
    $print = $card["printing"];
    $grade = $card["grade"] ?? "";

?>
<div id="main">
  <form id="cardForm" autocomplete="off" action="api/cards/update/<?= htmlspecialchars((string)$cardId) ?>">
    <!-- Hidden so PHP script knows which database row to update -->
    <input type="hidden" name="card_id" value="<?= htmlspecialchars((string)$cardId) ?>">

    <div class="field-row heading-h1">
      <input type="text" name="name" value="<?= htmlspecialchars((string)$cardName) ?>" readonly>
    </div>

    <div class="field-row heading-h2">
      <span>Price (Updated on <?= htmlspecialchars((string)$priceDate) ?>): $</span>
      <input type="text" inputmode="decimal" step="0.01" name="price" class="heading-h2 inline-input" value="<?= htmlspecialchars((string)$price) ?>" readonly>
    </div>

    <div class="field-row heading-h3">
      <label for="cardSet">Card Set:</label>
      <input type="text" id="cardSet" name="set" value="<?= htmlspecialchars((string)$cardSet) ?>" readonly>
    </div>

    <div class="field-row heading-h3">
      <label for="cardSetNum">Card Set Number:</label>
      <input type="text" id="cardSetNum" name="set_num" value="<?= htmlspecialchars((string)$cardSetNum) ?>" readonly>
    </div>

    <div class="field-row heading-h3">
      <label for="print">Print Edition:</label>
      <input type="text" id="print" name="printing" value="<?= htmlspecialchars((string)$print) ?>" readonly>
    </div>

    <div class="field-row heading-h3">
      <label for="condition">Card Condition:</label>
      <input type="text" id="condition" name="condition" value="<?= htmlspecialchars((string)$condition) ?>" readonly>
    </div>

    <div class="field-row heading-h3">
      <label for="grade">PSA:</label>
      <input type="text" id="grade" name="grade" value="<?= htmlspecialchars((string)$grade) ?>" readonly>
    </div>

    <div class="field-row heading-h3">
      <label for="language">Card Language:</label>
      <input type="text" id="language" name="language" value="<?= htmlspecialchars((string)$language) ?>" readonly>
    </div>

    <?php if ($isManager): ?>
      <button type="button" id="editBtn">Edit</button>
    <?php endif; ?>
    <button type="submit" id="submitBtn" class="invisible">Submit</button>
  </form>
</div>
<script src="cardUpdate.js"></script>