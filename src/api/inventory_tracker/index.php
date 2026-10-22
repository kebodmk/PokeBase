<?php

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

// require_once __DIR__ . "/../../includes/config.php";
// require_once __DIR__ . "/../../includes/WFDatabase.php";
// require_once __DIR__ . "/../../includes/json_functions.php";
require_once __DIR__ . '/../api_functions.php';

$method = $_SERVER['REQUEST_METHOD'];

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$parts = explode('/', trim($path, '/'));
$inventoryIndex = array_search('inventory_tracker', $parts, true);
$routePart = $inventoryIndex !== false ? ($parts[$inventoryIndex + 1] ?? null) : null;
$subResource = $inventoryIndex !== false ? ($parts[$inventoryIndex + 2] ?? null) : null;

/*
------------------------------------------------------------
POST /api/inventory_tracker/add
------------------------------------------------------------
*/
if ($routePart === 'add') {
    if ($method !== 'POST') {
        sendJson(405, ['success' => false, 'message' => 'Method not allowed.']);
    }

    $data = getJsonBody();
    [$fields, $errors] = validateInventoryFields($data, false);

    if (count($errors) > 0) {
        sendValidationErrors($errors);
    }

    $insert_inventory_sql = 'INSERT INTO inventory_tracker
    (`date`, total_value)
    VALUES
    (:date, :total_value)';

    $invId = (int)WFDatabase::executeSQL($insert_inventory_sql, [
        ':date' => $fields['date'],
        ':total_value' => $fields['total_value'],
    ]);

    sendJson(201, [
        'success' => true,
        'message' => 'Inventory snapshot added.',
        'inventory' => getInventoryById($invId),
    ]);
}

/*
------------------------------------------------------------
PUT /api/inventory_tracker/update/{inv_id}
------------------------------------------------------------
*/
if ($routePart === 'update') {
    if ($method !== 'PUT') {
        sendJson(405, ['success' => false, 'message' => 'Method not allowed.']);
    }

    $invId = requireRouteId($subResource);

    if (getInventoryById($invId) === null) {
        sendJson(404, ['success' => false, 'message' => 'Inventory snapshot not found.']);
    }

    $data = getJsonBody();
    [$fields, $errors] = validateInventoryFields($data, true);

    if (count($errors) > 0) {
        sendValidationErrors($errors);
    }

    if (count($fields) === 0) {
        sendJson(400, ['success' => false, 'message' => 'No inventory fields were provided to update.']);
    }

    [$update_sql, $update_params] = buildUpdateQuery('inventory_tracker', 'inv_id', $invId, $fields);
    WFDatabase::executeSQL($update_sql, $update_params);

    sendJson(200, [
        'success' => true,
        'message' => 'Inventory snapshot updated.',
        'inventory' => getInventoryById($invId),
    ]);
}

/*
------------------------------------------------------------
DELETE /api/inventory_tracker/delete/{inv_id}
------------------------------------------------------------
*/
if ($routePart === 'delete') {
    if ($method !== 'DELETE') {
        sendJson(405, ['success' => false, 'message' => 'Method not allowed.']);
    }

    $invId = requireRouteId($subResource);

    if (getInventoryById($invId) === null) {
        sendJson(404, ['success' => false, 'message' => 'Inventory snapshot not found.']);
    }

    WFDatabase::executeSQL('DELETE FROM inventory_tracker WHERE inv_id = :inv_id', [':inv_id' => $invId]);

    sendJson(200, ['success' => true, 'message' => 'Inventory snapshot deleted.']);
}

sendJson(404, ['success' => false, 'message' => 'Endpoint not found.']);

/*
------------------------------------------------------------
Helpers
------------------------------------------------------------
*/

// Returns [$fields, $errors]. With $partial = true only the fields sent are checked (used by updates).
function validateInventoryFields(array $data, bool $partial): array
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

    if (array_key_exists('total_value', $data)) {
        if (!is_numeric($data['total_value']) || (float)$data['total_value'] < 0) {
            $errors[] = 'total_value must be a non-negative number.';
        } else {
            $fields['total_value'] = (float)$data['total_value'];
        }
    } elseif (!$partial) {
        $errors[] = 'total_value is required.';
    }

    return [$fields, $errors];
}

function getInventoryById(int $invId): ?array
{
    $sql = 'SELECT inv_id, `date`, total_value FROM inventory_tracker WHERE inv_id = :inv_id';
    $rows = WFDatabase::getDataFromSQL($sql, [':inv_id' => $invId]);

    return $rows[0] ?? null;
}
