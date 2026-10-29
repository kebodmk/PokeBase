<?php

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

require_once __DIR__ . "/../../includes/config.php";
require_once __DIR__ . "/../../includes/PokeDatabase.php";
require_once __DIR__ . "/../../includes/json_functions.php";
require_once __DIR__ . '/../api_functions.php';
require_once __DIR__ . '/card_functions.php';

$method = $_SERVER['REQUEST_METHOD'];

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$parts = explode('/', trim($path, '/'));
$cardsIndex = array_search('cards', $parts, true);
$routePart = $cardsIndex !== false ? ($parts[$cardsIndex + 1] ?? null) : null;
$subResource = $cardsIndex !== false ? ($parts[$cardsIndex + 2] ?? null) : null;

/*
------------------------------------------------------------
GET /api/cards/search?card_id=&name=&set=&set_num=&lang=&condition=&printing=&grade=&price_date=&price_min=&price_max=
------------------------------------------------------------
*/

if ($routePart === 'search') {
    if ($method !== 'GET') {
        sendJson(405, ['success' => false, 'message' => 'Method not allowed.']);
    }

    // name is a partial match, the other text columns are exact matches
    $exactColumns = ['card_id', 'set', 'set_num', 'lang', 'condition', 'printing', 'grade'];
    $conditions = [];
    $search_params = [];

    $name = trim((string)($_GET['name'] ?? ''));
    if ($name !== '') {
        $conditions[] = '`name` LIKE :name';
        $search_params[':name'] = '%' . addcslashes($name, '%_\\') . '%';
    }

    foreach ($exactColumns as $column) {
        $value = trim((string)($_GET[$column] ?? ''));
        if ($value !== '') {
            $conditions[] = "`$column` = :$column";
            $search_params[':' . $column] = $value;
        }
    }

    $priceDate = trim((string)($_GET['price_date'] ?? ''));
    if ($priceDate !== '') {
        if (!isValidDate($priceDate)) {
            sendJson(400, ['success' => false, 'message' => 'price_date must be a date formatted YYYY-MM-DD.']);
        }
        $conditions[] = 'price_date = :price_date';
        $search_params[':price_date'] = $priceDate;
    }

    // price is a FLOAT column, so it is searched by range instead of exact equality
    foreach (['price_min' => '>=', 'price_max' => '<='] as $param => $operator) {
        $value = trim((string)($_GET[$param] ?? ''));
        if ($value !== '') {
            if (!is_numeric($value)) {
                sendJson(400, ['success' => false, 'message' => "$param must be a number."]);
            }
            $conditions[] = "price $operator :$param";
            $search_params[':' . $param] = (float)$value;
        }
    }

    $search_sql = 'SELECT ' . CARD_SELECT_COLUMNS . ' FROM cards';
    if (count($conditions) > 0) {
        $search_sql .= ' WHERE ' . implode(' AND ', $conditions);
    }
    $search_sql .= ' ORDER BY `name`, card_id';

    $cards = PokeDatabase::getDataFromSQL($search_sql, $search_params);

    sendJson(200, [
        'success' => true,
        'count' => count($cards),
        'cards' => $cards,
    ]);
}


/*
------------------------------------------------------------
POST /api/cards/add
------------------------------------------------------------
*/
if ($routePart === 'add') {
    if ($method !== 'POST') {
        sendJson(405, ['success' => false, 'message' => 'Method not allowed.']);
    }

    $data = getJsonBody();
    [$fields, $errors] = validateCardFields($data, false);

    if (count($errors) > 0) {
        sendValidationErrors($errors);
    }

    $cardId = insertCard($fields);

    sendJson(201, [
        'success' => true,
        'message' => 'Card added.',
        'card' => getCardById($cardId),
    ]);
}

/*
------------------------------------------------------------
PUT /api/cards/update/{card_id}
------------------------------------------------------------
*/
if ($routePart === 'update') {
    if ($method !== 'PUT') {
        sendJson(405, ['success' => false, 'message' => 'Method not allowed.']);
    }

    $cardId = requireRouteId($subResource);

    if (getCardById($cardId) === null) {
        sendJson(404, ['success' => false, 'message' => 'Card not found.']);
    }

    $data = getJsonBody();
    [$fields, $errors] = validateCardFields($data, true);

    if (count($errors) > 0) {
        sendValidationErrors($errors);
    }

    if (count($fields) === 0) {
        sendJson(400, ['success' => false, 'message' => 'No card fields were provided to update.']);
    }

    [$update_sql, $update_params] = buildUpdateQuery('cards', 'card_id', $cardId, $fields);
    PokeDatabase::executeSQL($update_sql, $update_params);

    sendJson(200, [
        'success' => true,
        'message' => 'Card updated.',
        'card' => getCardById($cardId),
    ]);
}

/*
------------------------------------------------------------
DELETE /api/cards/delete/{card_id}
------------------------------------------------------------
*/
if ($routePart === 'delete') {
    if ($method !== 'DELETE') {
        sendJson(405, ['success' => false, 'message' => 'Method not allowed.']);
    }

    $cardId = requireRouteId($subResource);

    if (getCardById($cardId) === null) {
        sendJson(404, ['success' => false, 'message' => 'Card not found.']);
    }

    $exchange_check_sql = 'SELECT exchange_id FROM exchanged_cards WHERE card_id = :card_id LIMIT 1';
    $exchanges = PokeDatabase::getDataFromSQL($exchange_check_sql, [':card_id' => $cardId]);

    if (count($exchanges) > 0) {
        sendJson(409, ['success' => false, 'message' => 'Card is part of a transaction and cannot be deleted.']);
    }

    PokeDatabase::executeSQL('DELETE FROM cards WHERE card_id = :card_id', [':card_id' => $cardId]);

    sendJson(200, ['success' => true, 'message' => 'Card deleted.']);
}
sendJson(404, ['success' => false, 'message' => 'Endpoint not found.']);
