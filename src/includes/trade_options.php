<?php

const TRADE_LANGUAGES = ['English', 'Japanese', 'Chinese', 'Korean'];
const TRADE_CONDITIONS = ['Near Mint', 'Lightly Played', 'Moderately Played', 'Heavily Played', 'Damaged', 'Sealed'];
const TRADE_PRINTINGS = ['Normal', 'Holofoil', 'Reverse Holofoil', 'First Edition', 'First Edition Holofoil'];

// Graded cards are priced from this company's JustTCG listings. The page has no company field.
const TRADE_DEFAULT_GRADING_COMPANY = 'PSA';

// Trade page language => language key used by api/just_tcg/index.php
const TRADE_LANGUAGE_CODES = ['English' => 'en', 'Japanese' => 'jap', 'Chinese' => 'ch', 'Korean' => 'kor'];

// Trade page printing => printing names JustTCG may return for it
const TRADE_PRINTING_ALIASES = [
    'Normal' => ['Normal', 'Unlimited'],
    'Holofoil' => ['Holofoil', 'Unlimited Holofoil'],
    'Reverse Holofoil' => ['Reverse Holofoil'],
    'First Edition' => ['1st Edition', '1st Edition Normal'],
    'First Edition Holofoil' => ['1st Edition Holofoil'],
];

// Pull pre-saved sets from text file. This could be updated across TCG API calls
function loadTradeSets(): array
{
    static $sets = null;

    if ($sets === null) {
        $decoded = json_decode((string)@file_get_contents(__DIR__ . '/../api/just_tcg/sets.txt'), true);
        $decoded = $decoded['data'] ?? $decoded;
        $sets = is_array($decoded) ? array_filter($decoded, 'is_array') : [];
    }

    return $sets;
}

function getSetNames(): array
{
    $names = array_values(array_unique(array_filter(array_column(loadTradeSets(), 'name'), 'is_string')));
    sort($names, SORT_NATURAL | SORT_FLAG_CASE);

    return $names;
}

// Set name => JustTCG set id
function getSetIdsByName(): array
{
    $ids = [];
    foreach (loadTradeSets() as $set) {
        if (is_string($set['name'] ?? null) && is_string($set['id'] ?? null)) {
            $ids[$set['name']] ??= $set['id'];
        }
    }

    return $ids;
}

// JustTCG set id => set name
function getSetNamesById(): array
{
    $names = [];
    foreach (getSetIdsByName() as $name => $id) {
        $names[$id] ??= $name;
    }

    return $names;
}

// JustTCG printing name => trades page printing, e.g. "Unlimited Holofoil" => "Holofoil"
function tradePrintingFromJustTcg(string $printing): ?string
{
    foreach (TRADE_PRINTING_ALIASES as $pagePrinting => $aliases) {
        if (in_array($printing, $aliases, true)) {
            return $pagePrinting;
        }
    }

    return null;
}

// Normalizes a card's set number to the printed form, e.g. "8/108" => "008/108", "19" => "019".
// Numeric parts are padded to 3 digits. Parts with letters (TG01/TG30, SV001/SV094, SWSH050)
// cannot be padded safely and are only uppercased. Same rule as normalizeSetNumber() in trades.js.
function normalizeSetNumber(string $setNumber): string
{
    $setNumber = strtoupper(preg_replace('/\s+/', '', $setNumber));
    $parts = explode('/', $setNumber);

    if (count($parts) > 2) {
        return $setNumber;
    }

    foreach ($parts as &$part) {
        if (preg_match('/^\d+$/', $part)) {
            $part = str_pad(ltrim($part, '0'), 3, '0', STR_PAD_LEFT);
        }
    }

    return implode('/', $parts);
}

// Checks the card fields sent by the trade page. Values must already be trimmed strings or null.
// Returns a list of problems, empty when the card is valid.
function validateTradeCard(array $card): array
{
    $errors = [];

    if (!in_array($card['set'] ?? null, getSetNames(), true)) {
        $errors[] = 'set is not a known set';
    }
    foreach (['set_num' => 'set number', 'name' => 'name'] as $field => $fieldName) {
        $value = $card[$field] ?? '';
        if ($value === '' || strlen($value) > 45) {
            $errors[] = "$fieldName is required and must be 45 characters or fewer";
        }
    }
    if (!in_array($card['lang'] ?? null, TRADE_LANGUAGES, true)) {
        $errors[] = 'language is invalid';
    }
    if (!in_array($card['printing'] ?? null, TRADE_PRINTINGS, true)) {
        $errors[] = 'printing is invalid';
    }

    $type = $card['card_type'] ?? null;
    if ($type === 'raw') {
        if (!in_array($card['condition'] ?? null, TRADE_CONDITIONS, true)) {
            $errors[] = 'condition is invalid';
        }
    } elseif ($type === 'graded') {
        $grade = $card['grade'] ?? '';
        if (!is_numeric($grade) || $grade < 1 || $grade > 10) {
            $errors[] = 'grade must be a number from 1 to 10';
        }
    } else {
        $errors[] = 'card type must be raw or graded';
    }

    return $errors;
}

// Checks the price fields the trade page sends with each card. The employee has the final say on price,
// so any dollar amount is accepted. Returns a list of problems, empty when valid.
function validateTradePrice($price, $priceDate): array
{
    $errors = [];

    if (!is_string($price) || !preg_match('/^\d+(\.\d{1,2})?$/', $price)) {
        $errors[] = 'price must be a dollar amount, e.g. 12.50';
    }

    $date = is_string($priceDate) ? DateTime::createFromFormat('Y-m-d', $priceDate) : false;
    if ($date === false || $date->format('Y-m-d') !== $priceDate) {
        $errors[] = 'price date must be formatted YYYY-MM-DD';
    }

    return $errors;
}
