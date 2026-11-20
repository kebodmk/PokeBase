<?php
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth_cookie.php';
require_once __DIR__ . '/../includes/json_functions.php';
require_once __DIR__ . '/../includes/trade_options.php';

header('Content-Type: application/json; charset=UTF-8');

function fail(int $status, string $message, array $errors = []): never
{
    sendJson($status, ['success' => false, 'message' => $message, 'errors' => $errors]);
}

function callApi(string $method, string $path, ?array $body = null): array
{
    global $mainURL;

    $options = [
        'http' => [
            'method'        => $method,
            'header'        => "Content-Type: application/json\r\n" .
                               "Accept: application/json\r\n" .
                               'Cookie: ' . SESSION_COOKIE . '=' . $_COOKIE[SESSION_COOKIE] . "\r\n",
            'content'       => $body === null ? '' : json_encode($body),
            'timeout'       => 8,
            'ignore_errors' => true
        ],
        'ssl' => [
            // Dev only. Remove these three lines once the API has a real certificate.
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true
        ]
    ];

    $response = @file_get_contents(rtrim($mainURL, '/') . '/api/' . $path, false, stream_context_create($options));
    $result = json_decode((string)$response, true);

    return is_array($result) ? $result : [];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail(405, 'Method not allowed.');
}

$user = getSessionUser();
if ($user === null) {
    fail(401, 'You must be logged in to do that.');
}
if (!in_array(strtolower(trim((string)$user['role'])), [ROLE_MANAGER, ROLE_EMPLOYEE], true)) {
    fail(403, 'You do not have permission to perform that action.');
}

$cards = $_POST['cards'] ?? [];
if (!is_array($cards) || count($cards) === 0) {
    fail(400, 'Add at least one card.');
}

$errors = [];
$tradeCards = [];

// Validate all data from POST request
foreach (array_values($cards) as $i => $card) {
    if (!is_array($card)) {
        $errors[] = 'Card ' . ($i + 1) . ' is invalid.';
        continue;
    }

    $card = array_map(fn($value) => is_string($value) ? trim($value) : null, $card);
    $label = 'Card ' . ($i + 1) . (($card['name'] ?? '') !== '' ? " ({$card['name']})" : '');
    $cardErrors = array_merge(validateTradeCard($card), validateTradePrice($card['price'] ?? null, $card['price_date'] ?? null));

    if (!in_array($card['recieved'] ?? null, ['0', '1'], true)) {
        array_unshift($cardErrors, 'must be incoming or outgoing');
    }

    if (count($cardErrors) > 0) {
        $errors[] = "$label: " . implode(', ', $cardErrors) . '.';
        continue;
    }

    $tradeCards[] = [$label, $card];
}

$cardsOnly = ($_POST['cards_only'] ?? '') === '1';
$incoming = is_string($_POST['incoming_money'] ?? null) ? trim($_POST['incoming_money']) : '';
$outgoing = is_string($_POST['outgoing_money'] ?? null) ? trim($_POST['outgoing_money']) : '';
$soldFor = null;

if ($incoming !== '' && $outgoing !== '') {
    $errors[] = 'Only one of Incoming Money or Outgoing Money can be filled.';
} elseif ($cardsOnly) {
    if ($incoming !== '' || $outgoing !== '') {
        $errors[] = 'Cards Only transactions cannot include money.';
    }
} elseif ($incoming === '' && $outgoing === '') {
    $errors[] = 'Enter Incoming Money or Outgoing Money, or check Cards Only.';
} else {
    $amount = $incoming !== '' ? $incoming : $outgoing;
    if (!preg_match('/^\d+(\.\d{1,2})?$/', $amount) || (float)$amount <= 0) {
        $errors[] = 'Money must be a dollar amount greater than 0, e.g. 12.50.';
    } else {
        $soldFor = $incoming !== '' ? (float)$amount : -(float)$amount;
    }
}

if (count($errors) > 0) {
    fail(400, 'Please fix the following:', $errors);
}


$exchangedCards = [];
$priceUpdates = [];
$usedOutgoingIds = [];

// Incoming cards always become a new card row, even when a matching card is already in the database.
// Outgoing cards must match a card in inventory. Same matching rule as findDatabaseCard() in trades.js
foreach ($tradeCards as [$label, $card]) {
    $graded = $card['card_type'] === 'graded';
    $grade = $graded ? (string)(float)$card['grade'] : null;
    $incoming = $card['recieved'] === '1';

    // The page sets price_from_api when JustTCG confirmed the card (it was not in the database, or its
    // price was synced), and price_overridden when the employee typed their own price
    $apiPriced = ($card['price_from_api'] ?? '') === '1';
    $overridden = ($card['price_overridden'] ?? '') === '1';

    $newCard = [
        'recieved' => true,
        'name' => $card['name'],
        'set' => $card['set'],
        'set_num' => $card['set_num'],
        'lang' => $card['lang'],
        'price' => (float)$card['price'],
        'price_date' => $card['price_date'],
        'condition' => $graded ? null : $card['condition'],
        'printing' => $card['printing'],
        'grade' => $grade,
    ];

    // JustTCG already confirmed this card exists, so no database lookup is needed.
    // /api/transactions/add creates the card in the same database transaction.
    if ($incoming && $apiPriced) {
        $exchangedCards[] = $newCard;
        continue;
    }

    $query = [
        'name' => $card['name'],
        'set' => $card['set'],
        'set_num' => $card['set_num'],
        'lang' => $card['lang'],
        'printing' => $card['printing'],
    ];
    $query += $graded ? ['grade' => $grade] : ['condition' => $card['condition']];
    // Outgoing cards must be in inventory, i.e. never sent out before
    if (!$incoming) {
        $query['in_inventory'] = '1';
    }

    $result = callApi('GET', 'cards/search?' . http_build_query($query));
    if (($result['success'] ?? false) !== true) {
        fail(502, 'Unable to look up cards.');
    }

    $match = null;
    foreach ($result['cards'] as $row) {
        $usedHere = !$incoming && in_array((int)$row['card_id'], $usedOutgoingIds, true);
        if (!$usedHere && strcasecmp($row['name'], $card['name']) === 0 && ($graded ? $row['condition'] : $row['grade']) === null) {
            $match = $row;
            break;
        }
    }

    // Incoming: a database match only proves the card exists. It still gets its own new card row.
    if ($incoming) {
        if ($match === null) {
            $errors[] = "$label was not found in the database or on JustTCG.";
            continue;
        }
        $exchangedCards[] = $newCard;
        continue;
    }

    if ($match === null) {
        $errors[] = "$label is not in inventory. Outgoing cards must be in the database and not already sold.";
        continue;
    }

    $cardId = (int)$match['card_id'];
    $exchangedCards[] = ['card_id' => $cardId, 'recieved' => false];
    $usedOutgoingIds[] = $cardId;

    // Save a price synced from JustTCG or overridden on the page onto the card being sold
    if ($apiPriced || $overridden) {
        $priceUpdates[$cardId] = ['price' => (float)$card['price'], 'price_date' => $card['price_date']];
    }
}

if (count($errors) > 0) {
    fail(400, 'Some cards could not be matched:', $errors);
}

// Add the transaction to the database
$result = callApi('POST', 'transactions/add', [
    'date' => date('Y-m-d'),
    'sold_for' => $soldFor,
    'cards' => $exchangedCards,
]);

if (($result['success'] ?? false) !== true) {
    fail(502, $result['message'] ?? 'Unable to save the transaction.', $result['errors'] ?? []);
}

$message = 'Transaction #' . $result['transaction']['trans_id'] . ' completed.';

// Save prices that were synced from JustTCG or overridden on the page for outgoing cards.
// Runs only after the transaction is saved. A failure here leaves the old price, not a broken transaction.
$failedUpdates = 0;
foreach ($priceUpdates as $cardId => $fields) {
    $update = callApi('PUT', 'cards/update/' . $cardId, $fields);
    if (($update['success'] ?? false) !== true) {
        $failedUpdates++;
    }
}
if ($failedUpdates > 0) {
    $message .= " $failedUpdates updated card price(s) could not be saved.";
}

// Confirmation message
sendJson(201, ['success' => true, 'message' => $message]);
