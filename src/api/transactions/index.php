<?php

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

require_once __DIR__ . "/../../includes/config.php";
require_once __DIR__ . "/../../includes/PokeDatabase.php";
require_once __DIR__ . "/../../includes/json_functions.php";
require_once __DIR__ . '/../api_functions.php';
require_once __DIR__ . '/../cards/card_functions.php';

$method = $_SERVER['REQUEST_METHOD'];

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$parts = explode('/', trim($path, '/'));
$transactionsIndex = array_search('transactions', $parts, true);
$routePart = $transactionsIndex !== false ? ($parts[$transactionsIndex + 1] ?? null) : null;
$subResource = $transactionsIndex !== false ? ($parts[$transactionsIndex + 2] ?? null) : null;

/*
------------------------------------------------------------
POST /api/transactions/add
Adds the transaction, then one exchanged_cards row per entry in "cards".
Each card entry either references an existing card_id or carries the
full card information, in which case the card row is created first.
------------------------------------------------------------
*/
if ($routePart === 'add') {
    if ($method !== 'POST') {
        sendJson(405, ['success' => false, 'message' => 'Method not allowed.']);
    }

    $data = getJsonBody();
    [$fields, $errors] = validateTransactionFields($data, false);

    $cards = $data['cards'] ?? [];
    if (!is_array($cards) || ($cards !== [] && array_keys($cards) !== range(0, count($cards) - 1))) {
        $errors[] = 'cards must be a list of card entries.';
        $cards = [];
    }

    // Validate every card entry before anything is written
    $cardEntries = [];
    foreach ($cards as $index => $card) {
        $prefix = "cards[$index]: ";

        if (!is_array($card)) {
            $errors[] = $prefix . 'must be an object.';
            continue;
        }

        $recieved = parseBoolFlag($card['recieved'] ?? null);
        if ($recieved === null) {
            $errors[] = $prefix . 'recieved is required and must be true or false.';
        }

        if (array_key_exists('card_id', $card)) {
            if (!isPositiveInt($card['card_id'])) {
                $errors[] = $prefix . 'card_id must be a positive integer.';
            } elseif (getCardById((int)$card['card_id']) === null) {
                $errors[] = $prefix . 'card_id ' . (int)$card['card_id'] . ' does not exist.';
            }

            $cardEntries[] = ['card_id' => (int)$card['card_id'], 'card_fields' => null, 'recieved' => $recieved];
            continue;
        }

        [$cardFields, $cardErrors] = validateCardFields($card, false);
        foreach ($cardErrors as $cardError) {
            $errors[] = $prefix . $cardError;
        }

        $cardEntries[] = ['card_id' => null, 'card_fields' => $cardFields, 'recieved' => $recieved];
    }

    if (count($errors) > 0) {
        sendValidationErrors($errors);
    }

    $insert_transaction_sql = 'INSERT INTO transactions
    (`date`, user_id, sold_for)
    VALUES
    (:date, :user_id, :sold_for)';

    $insert_exchange_sql = 'INSERT INTO exchanged_cards
    (trans_id, card_id, recieved)
    VALUES
    (:trans_id, :card_id, :recieved)';

    // All inserts succeed together or none are kept
    PokeDatabase::startTransaction();

    try {
        $transId = (int)PokeDatabase::executeSQL($insert_transaction_sql, [
            ':date' => $fields['date'],
            ':user_id' => $fields['user_id'],
            ':sold_for' => $fields['sold_for'] ?? null,
        ], true);

        foreach ($cardEntries as $entry) {
            $cardId = $entry['card_id'] ?? insertCard($entry['card_fields']);

            PokeDatabase::executeSQL($insert_exchange_sql, [
                ':trans_id' => $transId,
                ':card_id' => $cardId,
                ':recieved' => $entry['recieved'],
            ]);
        }

        PokeDatabase::commitTransaction();
    } catch (Throwable $e) {
        PokeDatabase::rollbackTransaction();

        error_log('Transaction add failed: ' . $e->getMessage());
        sendJson(500, ['success' => false, 'message' => 'Transaction could not be saved.']);
    }

    sendJson(201, [
        'success' => true,
        'message' => 'Transaction added.',
        'transaction' => getTransactionWithCards($transId),
    ]);
}

/*
------------------------------------------------------------
PUT /api/transactions/update/{trans_id}
Updates the transaction row only; use /api/exchanged_cards to change its cards.
------------------------------------------------------------
*/
if ($routePart === 'update') {
    if ($method !== 'PUT') {
        sendJson(405, ['success' => false, 'message' => 'Method not allowed.']);
    }

    $transId = requireRouteId($subResource);

    if (getTransactionById($transId) === null) {
        sendJson(404, ['success' => false, 'message' => 'Transaction not found.']);
    }

    $data = getJsonBody();
    [$fields, $errors] = validateTransactionFields($data, true);

    if (count($errors) > 0) {
        sendValidationErrors($errors);
    }

    if (count($fields) === 0) {
        sendJson(400, ['success' => false, 'message' => 'No transaction fields were provided to update.']);
    }

    [$update_sql, $update_params] = buildUpdateQuery('transactions', 'trans_id', $transId, $fields);
    PokeDatabase::executeSQL($update_sql, $update_params);

    sendJson(200, [
        'success' => true,
        'message' => 'Transaction updated.',
        'transaction' => getTransactionWithCards($transId),
    ]);
}

/*
------------------------------------------------------------
DELETE /api/transactions/delete/{trans_id}
Removes the transaction and its exchanged_cards rows. The cards themselves are kept.
------------------------------------------------------------
*/
if ($routePart === 'delete') {
    if ($method !== 'DELETE') {
        sendJson(405, ['success' => false, 'message' => 'Method not allowed.']);
    }

    $transId = requireRouteId($subResource);

    if (getTransactionById($transId) === null) {
        sendJson(404, ['success' => false, 'message' => 'Transaction not found.']);
    }

    PokeDatabase::startTransaction();

    try {
        PokeDatabase::executeSQL('DELETE FROM exchanged_cards WHERE trans_id = :trans_id', [':trans_id' => $transId]);
        PokeDatabase::executeSQL('DELETE FROM transactions WHERE trans_id = :trans_id', [':trans_id' => $transId]);
        PokeDatabase::commitTransaction();
    } catch (Throwable $e) {
        PokeDatabase::rollbackTransaction();

        error_log('Transaction delete failed: ' . $e->getMessage());
        sendJson(500, ['success' => false, 'message' => 'Transaction could not be deleted.']);
    }

    sendJson(200, ['success' => true, 'message' => 'Transaction deleted.']);
}

sendJson(404, ['success' => false, 'message' => 'Endpoint not found.']);

/*
------------------------------------------------------------
Helpers
------------------------------------------------------------
*/

// Returns [$fields, $errors]. With $partial = true only the fields sent are checked (used by updates).
function validateTransactionFields(array $data, bool $partial): array
{
    $fields = [];
    $errors = [];

    if (array_key_exists('date', $data)) {
        if (!isValidDate($data['date'])) {
            $errors[] = 'date must be a date formatted YYYY-MM-DD.';
        } else {
            $fields['date'] = $data['date'];
        }
    } elseif (!$partial) {
        $errors[] = 'date is required.';
    }

    if (array_key_exists('user_id', $data)) {
        if (!isPositiveInt($data['user_id'])) {
            $errors[] = 'user_id must be a positive integer.';
        } elseif (!rowExists('users', 'user_id', (int)$data['user_id'])) {
            $errors[] = 'user_id ' . (int)$data['user_id'] . ' does not exist.';
        } else {
            $fields['user_id'] = (int)$data['user_id'];
        }
    } elseif (!$partial) {
        $errors[] = 'user_id is required.';
    }

    // sold_for is optional and may be cleared with null
    if (array_key_exists('sold_for', $data)) {
        if ($data['sold_for'] === null) {
            $fields['sold_for'] = null;
        } elseif (!is_numeric($data['sold_for']) || (float)$data['sold_for'] < 0) {
            $errors[] = 'sold_for must be a non-negative number or null.';
        } else {
            $fields['sold_for'] = (float)$data['sold_for'];
        }
    }

    return [$fields, $errors];
}

function getTransactionById(int $transId): ?array
{
    $sql = 'SELECT trans_id, `date`, user_id, sold_for FROM transactions WHERE trans_id = :trans_id';
    $rows = PokeDatabase::getDataFromSQL($sql, [':trans_id' => $transId]);

    return $rows[0] ?? null;
}

function getTransactionWithCards(int $transId): ?array
{
    $transaction = getTransactionById($transId);
    if ($transaction === null) {
        return null;
    }

    $cards_sql = 'SELECT e.exchange_id, e.recieved,
        c.card_id, c.`name`, c.`set`, c.set_num, c.lang, c.price, c.price_date, c.`condition`, c.printing, c.grade
    FROM exchanged_cards e
    JOIN cards c ON c.card_id = e.card_id
    WHERE e.trans_id = :trans_id
    ORDER BY e.exchange_id';

    $transaction['cards'] = PokeDatabase::getDataFromSQL($cards_sql, [':trans_id' => $transId]);

    return $transaction;
}
