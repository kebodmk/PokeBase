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
$auditsIndex = array_search('audits', $parts, true);
$routePart = $auditsIndex !== false ? ($parts[$auditsIndex + 1] ?? null) : null;
$subResource = $auditsIndex !== false ? ($parts[$auditsIndex + 2] ?? null) : null;

/*
------------------------------------------------------------
POST /api/audits/add
------------------------------------------------------------
*/
if ($routePart === 'add') {
    if ($method !== 'POST') {
        sendJson(405, ['success' => false, 'message' => 'Method not allowed.']);
    }

    $data = getJsonBody();
    [$fields, $errors] = validateAuditFields($data, false);

    if (count($errors) > 0) {
        sendValidationErrors($errors);
    }

    $insert_audit_sql = 'INSERT INTO audits
    (user_id, `date`, notes)
    VALUES
    (:user_id, :date, :notes)';

    $auditId = (int)PokeDatabase::executeSQL($insert_audit_sql, [
        ':user_id' => $fields['user_id'],
        ':date' => $fields['date'],
        ':notes' => $fields['notes'],
    ], true);

    sendJson(201, [
        'success' => true,
        'message' => 'Audit added.',
        'audit' => getAuditById($auditId),
    ]);
}

/*
------------------------------------------------------------
PUT /api/audits/update/{audit_id}
------------------------------------------------------------
*/
if ($routePart === 'update') {
    if ($method !== 'PUT') {
        sendJson(405, ['success' => false, 'message' => 'Method not allowed.']);
    }

    $auditId = requireRouteId($subResource);

    if (getAuditById($auditId) === null) {
        sendJson(404, ['success' => false, 'message' => 'Audit not found.']);
    }

    $data = getJsonBody();
    [$fields, $errors] = validateAuditFields($data, true);

    if (count($errors) > 0) {
        sendValidationErrors($errors);
    }

    if (count($fields) === 0) {
        sendJson(400, ['success' => false, 'message' => 'No audit fields were provided to update.']);
    }

    [$update_sql, $update_params] = buildUpdateQuery('audits', 'audit_id', $auditId, $fields);
    PokeDatabase::executeSQL($update_sql, $update_params);

    sendJson(200, [
        'success' => true,
        'message' => 'Audit updated.',
        'audit' => getAuditById($auditId),
    ]);
}

/*
------------------------------------------------------------
DELETE /api/audits/delete/{audit_id}
------------------------------------------------------------
*/
if ($routePart === 'delete') {
    if ($method !== 'DELETE') {
        sendJson(405, ['success' => false, 'message' => 'Method not allowed.']);
    }

    $auditId = requireRouteId($subResource);

    if (getAuditById($auditId) === null) {
        sendJson(404, ['success' => false, 'message' => 'Audit not found.']);
    }

    PokeDatabase::executeSQL('DELETE FROM audits WHERE audit_id = :audit_id', [':audit_id' => $auditId]);

    sendJson(200, ['success' => true, 'message' => 'Audit deleted.']);
}

sendJson(404, ['success' => false, 'message' => 'Endpoint not found.']);

/*
------------------------------------------------------------
Helpers
------------------------------------------------------------
*/

// Returns [$fields, $errors]. With $partial = true only the fields sent are checked (used by updates).
function validateAuditFields(array $data, bool $partial): array
{
    $fields = [];
    $errors = [];

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

    if (array_key_exists('date', $data)) {
        if (!isValidDate($data['date'])) {
            $errors[] = 'date must be a date formatted YYYY-MM-DD.';
        } else {
            $fields['date'] = $data['date'];
        }
    } elseif (!$partial) {
        $errors[] = 'date is required.';
    }

    if (array_key_exists('notes', $data)) {
        $notes = trim((string)$data['notes']);
        if ($notes === '') {
            $errors[] = 'notes cannot be empty.';
        } elseif (strlen($notes) > 45) {
            $errors[] = 'notes must be 45 characters or fewer.';
        } else {
            $fields['notes'] = $notes;
        }
    } elseif (!$partial) {
        $errors[] = 'notes is required.';
    }

    return [$fields, $errors];
}

function getAuditById(int $auditId): ?array
{
    $sql = 'SELECT audit_id, user_id, `date`, notes FROM audits WHERE audit_id = :audit_id';
    $rows = PokeDatabase::getDataFromSQL($sql, [':audit_id' => $auditId]);

    return $rows[0] ?? null;
}
