<?php

/*
------------------------------------------------------------
Shared helpers for the table endpoints under /api/
Relies on sendJson() from includes/json_functions.php
------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/auth_cookie.php';

// Role names as stored in users.role (compared case-insensitively)
const ROLE_MANAGER = 'manager';
const ROLE_EMPLOYEE = 'employee';
const ROLE_USER = 'user';

// Returns ['user_id', 'role'] for the session in the request's cookie, or null when there is no
// unexpired session. Identity only ever comes from a token issued by /api/users/login.
function getSessionUser(): ?array
{
    $token = $_COOKIE[SESSION_COOKIE] ?? '';

    if (!is_string($token) || !preg_match('/^[0-9a-f]{64}$/', $token)) {
        return null;
    }

    $sql = 'SELECT u.user_id, u.role
    FROM api_sessions s
    JOIN users u ON u.user_id = s.user_id
    WHERE s.token_hash = :token_hash AND s.ended_at > NOW()
    LIMIT 1';

    $rows = PokeDatabase::getDataFromSQL($sql, [':token_hash' => hashSessionToken($token)]);
    return $rows[0] ?? null;
}

// Ended sessions are deleted, not kept. Nothing runs on a timer, so login and logout sweep out
// expired rows. Until then getSessionUser() still rejects them through the ended_at check.
function deleteExpiredSessions(): void
{
    PokeDatabase::executeSQL('DELETE FROM api_sessions WHERE ended_at <= NOW()');
}

// Call at the start of an endpoint
// Not logged in -> 401
// Role not allowed -> 403
function requireRole(array $allowedRoles): array
{
    $user = getSessionUser();

    if ($user === null) {
        sendJson(401, ['success' => false, 'message' => 'You must be logged in to do that.']);
    }

    $role = strtolower(trim((string)$user['role']));

    if (!in_array($role, $allowedRoles, true)) {
        sendJson(403, ['success' => false, 'message' => 'You do not have permission to perform that action or access this page.']);
    }

    return ['user_id' => (int)$user['user_id'], 'role' => $role];
}

function isValidDate($value): bool
{
    if (!is_string($value)) {
        return false;
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

function isPositiveInt($value): bool
{
    return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false;
}

// Accepts true/false or 1/0 (as numbers or strings) and returns 1/0, or null when invalid
function parseBoolFlag($value): ?int
{
    if (is_bool($value)) {
        return (int)$value;
    }

    if (in_array($value, [0, 1, '0', '1'], true)) {
        return (int)$value;
    }

    return null;
}

// Reads the numeric id from routes like /api/cards/update/{id}
function requireRouteId(?string $value): int
{
    if ($value === null || !isPositiveInt($value)) {
        sendJson(400, ['success' => false, 'message' => 'A valid numeric id is required in the URL.']);
    }

    return (int)$value;
}

function sendValidationErrors(array $errors): void
{
    sendJson(400, ['success' => false, 'message' => 'Validation failed.', 'errors' => $errors]);
}

// Builds an UPDATE that only touches the columns present in $fields.
// Column names must come from a validator whitelist, never straight from the request.
function buildUpdateQuery(string $table, string $idColumn, int $id, array $fields): array
{
    $assignments = [];
    $params = [':' . $idColumn => $id];

    foreach ($fields as $column => $value) {
        $assignments[] = "`$column` = :$column";
        $params[':' . $column] = $value;
    }

    $sql = "UPDATE `$table` SET " . implode(', ', $assignments) . " WHERE `$idColumn` = :$idColumn";

    return [$sql, $params];
}

// Used to check foreign keys before writing. $table and $idColumn must be hard-coded by the caller.
function rowExists(string $table, string $idColumn, int $id): bool
{
    $sql = "SELECT `$idColumn` FROM `$table` WHERE `$idColumn` = :id LIMIT 1";
    $rows = PokeDatabase::getDataFromSQL($sql, [':id' => $id]);
    return count($rows) > 0;
}
