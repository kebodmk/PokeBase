<?php

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

require_once __DIR__ . "/../../includes/config.php";
require_once __DIR__ . "/../../includes/PokeDatabase.php";
require_once __DIR__ . "/../../includes/json_functions.php";
require_once __DIR__ . '/../api_functions.php';

$method = $_SERVER['REQUEST_METHOD'];

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$parts = explode('/', trim($path, '/'));
$exchangedCardsIndex = array_search('exchanged_cards', $parts, true);
$routePart = $exchangedCardsIndex !== false ? ($parts[$exchangedCardsIndex + 1] ?? null) : null;
$subResource = $exchangedCardsIndex !== false ? ($parts[$exchangedCardsIndex + 2] ?? null) : null;

/*
------------------------------------------------------------
POST /api/exchanged_cards/add
------------------------------------------------------------
*/
if ($routePart === 'add') {
    if ($method !== 'POST') {
        sendJson(405, ['success' => false, 'message' => 'Method not allowed.']);
    }

    requireRole([ROLE_MANAGER, ROLE_EMPLOYEE]);

    $data = getJsonBody();
    [$fields, $errors] = validateExchangedCardFields($data, false);

    if (count($errors) > 0) {
        sendValidationErrors($errors);
    }

    $insert_exchange_sql = 'INSERT INTO exchanged_cards
    (trans_id, card_id, recieved)
    VALUES
    (:trans_id, :card_id, :recieved)';

    $exchangeId = (int)PokeDatabase::executeSQL($insert_exchange_sql, [
        ':trans_id' => $fields['trans_id'],
        ':card_id' => $fields['card_id'],
        ':recieved' => $fields['recieved'],
    ], true);

    sendJson(201, [
        'success' => true,
        'message' => 'Exchanged card added.',
        'exchanged_card' => getExchangedCardById($exchangeId),
    ]);
}

/*
------------------------------------------------------------
PUT /api/exchanged_cards/update/{exchange_id}
------------------------------------------------------------
*/
if ($routePart === 'update') {
    if ($method !== 'PUT') {
        sendJson(405, ['success' => false, 'message' => 'Method not allowed.']);
    }

    requireRole([ROLE_MANAGER, ROLE_EMPLOYEE]);

    $exchangeId = requireRouteId($subResource);

    if (getExchangedCardById($exchangeId) === null) {
        sendJson(404, ['success' => false, 'message' => 'Exchanged card not found.']);
    }

    $data = getJsonBody();
    [$fields, $errors] = validateExchangedCardFields($data, true);

    if (count($errors) > 0) {
        sendValidationErrors($errors);
    }

    if (count($fields) === 0) {
        sendJson(400, ['success' => false, 'message' => 'No exchanged card fields were provided to update.']);
    }

    [$update_sql, $update_params] = buildUpdateQuery('exchanged_cards', 'exchange_id', $exchangeId, $fields);
    PokeDatabase::executeSQL($update_sql, $update_params);

    sendJson(200, [
        'success' => true,
        'message' => 'Exchanged card updated.',
        'exchanged_card' => getExchangedCardById($exchangeId),
    ]);
}

/*
------------------------------------------------------------
DELETE /api/exchanged_cards/delete/{exchange_id}
------------------------------------------------------------
*/
if ($routePart === 'delete') {
    if ($method !== 'DELETE') {
        sendJson(405, ['success' => false, 'message' => 'Method not allowed.']);
    }

    requireRole([ROLE_MANAGER]);

    $exchangeId = requireRouteId($subResource);

    if (getExchangedCardById($exchangeId) === null) {
        sendJson(404, ['success' => false, 'message' => 'Exchanged card not found.']);
    }

    PokeDatabase::executeSQL('DELETE FROM exchanged_cards WHERE exchange_id = :exchange_id', [':exchange_id' => $exchangeId]);

    sendJson(200, ['success' => true, 'message' => 'Exchanged card deleted.']);
}

sendJson(404, ['success' => false, 'message' => 'Endpoint not found.']);

/*
------------------------------------------------------------
Helpers
------------------------------------------------------------
*/

// Returns [$fields, $errors]. With $partial = true only the fields sent are checked (used by updates).
function validateExchangedCardFields(array $data, bool $partial): array
{
    $fields = [];
    $errors = [];

    $references = [
        'trans_id' => 'transactions',
        'card_id' => 'cards',
    ];

    foreach ($references as $column => $table) {
        if (!array_key_exists($column, $data)) {
            if (!$partial) {
                $errors[] = "$column is required.";
            }
            continue;
        }

        if (!isPositiveInt($data[$column])) {
            $errors[] = "$column must be a positive integer.";
        } elseif (!rowExists($table, $column, (int)$data[$column])) {
            $errors[] = "$column " . (int)$data[$column] . ' does not exist.';
        } else {
            $fields[$column] = (int)$data[$column];
        }
    }

    if (array_key_exists('recieved', $data)) {
        $recieved = parseBoolFlag($data['recieved']);
        if ($recieved === null) {
            $errors[] = 'recieved must be true or false.';
        } else {
            $fields['recieved'] = $recieved;
        }
    } elseif (!$partial) {
        $errors[] = 'recieved is required.';
    }

    return [$fields, $errors];
}

function getExchangedCardById(int $exchangeId): ?array
{
    $sql = 'SELECT exchange_id, trans_id, card_id, recieved FROM exchanged_cards WHERE exchange_id = :exchange_id';
    $rows = PokeDatabase::getDataFromSQL($sql, [':exchange_id' => $exchangeId]);

    return $rows[0] ?? null;
}
