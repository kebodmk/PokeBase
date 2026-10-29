<?php

/*
------------------------------------------------------------
Card helpers shared by /api/cards and /api/transactions
------------------------------------------------------------
*/

const CARD_REQUIRED_TEXT_FIELDS = ['name', 'set', 'set_num', 'lang'];
const CARD_OPTIONAL_TEXT_FIELDS = ['condition', 'printing', 'grade'];
const CARD_TEXT_MAX_LENGTH = 45;

const CARD_SELECT_COLUMNS = 'card_id, `name`, `set`, set_num, lang, price, price_date, `condition`, printing, grade';

// Returns [$fields, $errors]. With $partial = true only the fields sent are checked (used by updates).
function validateCardFields(array $data, bool $partial): array
{
    $fields = [];
    $errors = [];

    foreach (CARD_REQUIRED_TEXT_FIELDS as $field) {
        if (!array_key_exists($field, $data)) {
            if (!$partial) {
                $errors[] = "$field is required.";
            }
            continue;
        }

        $value = trim((string)$data[$field]);
        if ($value === '') {
            $errors[] = "$field cannot be empty.";
        } elseif (strlen($value) > CARD_TEXT_MAX_LENGTH) {
            $errors[] = "$field must be " . CARD_TEXT_MAX_LENGTH . ' characters or fewer.';
        } else {
            $fields[$field] = $value;
        }
    }

    if (array_key_exists('price', $data)) {
        if (!is_numeric($data['price']) || (float)$data['price'] < 0) {
            $errors[] = 'price must be a non-negative number.';
        } else {
            $fields['price'] = (float)$data['price'];
        }
    } elseif (!$partial) {
        $errors[] = 'price is required.';
    }

    if (array_key_exists('price_date', $data)) {
        if (!isValidDate($data['price_date'])) {
            $errors[] = 'price_date must be a date formatted YYYY-MM-DD.';
        } else {
            $fields['price_date'] = $data['price_date'];
        }
    } elseif (!$partial) {
        $errors[] = 'price_date is required.';
    }

    foreach (CARD_OPTIONAL_TEXT_FIELDS as $field) {
        if (!array_key_exists($field, $data)) {
            continue;
        }

        $value = $data[$field] === null ? '' : trim((string)$data[$field]);
        if (strlen($value) > CARD_TEXT_MAX_LENGTH) {
            $errors[] = "$field must be " . CARD_TEXT_MAX_LENGTH . ' characters or fewer.';
        } else {
            $fields[$field] = $value !== '' ? $value : null;
        }
    }

    return [$fields, $errors];
}

// Expects $fields to have passed validateCardFields($data, false)
function insertCard(array $fields): int
{
    $insert_card_sql = 'INSERT INTO cards
    (`name`, `set`, set_num, lang, price, price_date, `condition`, printing, grade)
    VALUES
    (:name, :set, :set_num, :lang, :price, :price_date, :condition, :printing, :grade)';

    $insert_params = [
        ':name' => $fields['name'],
        ':set' => $fields['set'],
        ':set_num' => $fields['set_num'],
        ':lang' => $fields['lang'],
        ':price' => $fields['price'],
        ':price_date' => $fields['price_date'],
        ':condition' => $fields['condition'] ?? null,
        ':printing' => $fields['printing'] ?? null,
        ':grade' => $fields['grade'] ?? null,
    ];

    return (int)PokeDatabase::executeSQL($insert_card_sql, $insert_params, true);
}

function getCardById(int $cardId): ?array
{
    $sql = 'SELECT ' . CARD_SELECT_COLUMNS . ' FROM cards WHERE card_id = :card_id';
    $rows = PokeDatabase::getDataFromSQL($sql, [':card_id' => $cardId]);

    return $rows[0] ?? null;
}

function getCardByName(string $cardName): ?array
{
    $sql = 'select ' . CARD_SELECT_COLUMNS .' FROM cards WHERE name LIKE :card_name%';
    $rows = PokeDatabase::getDataFromSQL($sql, [':card_name' => $cardName]);

    return $rows[0] ?? null;
}
