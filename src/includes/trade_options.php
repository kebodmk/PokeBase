<?php

const TRADE_LANGUAGES = ['English', 'Japanese', 'Chinese', 'Korean'];
const TRADE_CONDITIONS = ['Near Mint', 'Lightly Played', 'Moderately Played', 'Heavily Played', 'Damaged', 'Sealed'];
const TRADE_PRINTINGS = ['Normal', 'Holofoil', 'First Edition', 'First Edition Holofoil'];

// Pull pre-saved sets from text file. This could be updated across TCG API calls
function getSetNames(): array
{
    $sets = json_decode((string)@file_get_contents(__DIR__ . '/../api/just_tcg/sets.txt'), true);
    $sets = $sets['data'] ?? $sets;

    if (!is_array($sets)) {
        return [];
    }

    $names = array_values(array_unique(array_filter(array_column($sets, 'name'), 'is_string')));
    sort($names, SORT_NATURAL | SORT_FLAG_CASE);

    return $names;
}
