<?php

/*
------------------------------------------------------------
Shared helpers for the table endpoints under /api/
Relies on sendJson() from includes/json_functions.php
------------------------------------------------------------
*/

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
