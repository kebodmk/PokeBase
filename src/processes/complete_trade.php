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
$setNames = getSetNames();
$tradeCards = [];

// Validate all data from POST request
foreach (array_values($cards) as $i => $card) {
    if (!is_array($card)) {
        $errors[] = 'Card ' . ($i + 1) . ' is invalid.';
        continue;
    }

    $card = array_map(fn($value) => is_string($value) ? trim($value) : null, $card);
    $label = 'Card ' . ($i + 1) . (($card['name'] ?? '') !== '' ? " ({$card['name']})" : '');
    $cardErrors = [];

    if (!in_array($card['recieved'] ?? null, ['0', '1'], true)) {
        $cardErrors[] = 'must be incoming or outgoing';
    }
    if (!in_array($card['set'] ?? null, $setNames, true)) {
        $cardErrors[] = 'set is not a known set';
    }
    foreach (['set_num' => 'set number', 'name' => 'name'] as $field => $fieldName) {
        $value = $card[$field] ?? '';
        if ($value === '' || strlen($value) > 45) {
            $cardErrors[] = "$fieldName is required and must be 45 characters or fewer";
        }
    }
    if (!in_array($card['lang'] ?? null, TRADE_LANGUAGES, true)) {
        $cardErrors[] = 'language is invalid';
    }
    if (!in_array($card['printing'] ?? null, TRADE_PRINTINGS, true)) {
        $cardErrors[] = 'printing is invalid';
    }

    $type = $card['card_type'] ?? null;
    if ($type === 'raw') {
        if (!in_array($card['condition'] ?? null, TRADE_CONDITIONS, true)) {
            $cardErrors[] = 'condition is invalid';
        }
    } elseif ($type === 'graded') {
        $grade = $card['grade'] ?? '';
        if (!is_numeric($grade) || $grade < 1 || $grade > 10) {
            $cardErrors[] = 'grade must be a number from 1 to 10';
        }
    } else {
        $cardErrors[] = 'card type must be raw or graded';
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

// Ensure data matches stored cards
foreach ($tradeCards as [$label, $card]) {
    $graded = $card['card_type'] === 'graded';
    $query = [
        'name' => $card['name'],
        'set' => $card['set'],
        'set_num' => $card['set_num'],
        'lang' => $card['lang'],
        'printing' => $card['printing'],
    ];
    $query += $graded ? ['grade' => (string)(float)$card['grade']] : ['condition' => $card['condition']];

    $result = callApi('GET', 'cards/search?' . http_build_query($query));
    if (($result['success'] ?? false) !== true) {
        fail(502, 'Unable to look up cards.');
    }

    $match = null;
    foreach ($result['cards'] as $row) {
        if (strcasecmp($row['name'], $card['name']) === 0 && ($graded ? $row['condition'] : $row['grade']) === null) {
            $match = $row;
            break;
        }
    }

    if ($match === null) {
        $errors[] = "$label was not found in the database.";
        continue;
    }

    $exchangedCards[] = ['card_id' => (int)$match['card_id'], 'recieved' => $card['recieved'] === '1'];
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

// Confirmation message
sendJson(201, ['success' => true, 'message' => 'Transaction #' . $result['transaction']['trans_id'] . ' completed.']);
