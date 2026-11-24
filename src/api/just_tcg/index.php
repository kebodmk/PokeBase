<?php

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../includes/PokeDatabase.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth_cookie.php';
require_once __DIR__ . '/../../includes/trade_options.php';

const JUSTTCG_LANGUAGE_ALIASES = [
    'en' => ['en', 'eng', 'english'],
    'jap' => ['ja', 'jp', 'jpn', 'japanese'],
    'ch' => ['zh', 'zh-cn', 'zh-tw', 'chinese'],
    'kor' => ['ko', 'kr', 'kor', 'korean'],
];

const JUSTTCG_GRADING_COMPANIES = ['PSA', 'BGS', 'CGC', 'BCCG', 'BVG', 'SGC'];

// ------------------------------------------------------------
// Response and input helpers.
// ------------------------------------------------------------

function sendJson($data, $status = 200)
{
    http_response_code($status);

    $json = json_encode(
        $data,
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_INVALID_UTF8_SUBSTITUTE
    );

    if ($json === false) {
        http_response_code(500);
        echo '{"success":false,"error":"Could not encode the JSON response."}';
        exit;
    }

    echo $json;
    exit;
}

function postString($key, $default = '')
{
    $value = $_POST[$key] ?? $default;

    return is_string($value) ? trim($value) : $default;
}

// ------------------------------------------------------------
// Card-number matching.
// ------------------------------------------------------------

function normalizeCardNumberPart($value)
{
    $value = strtoupper(trim((string) $value));
    $value = preg_replace('/\s+/', '', $value);

    if (preg_match('/^[0-9]+$/', $value)) {
        $value = ltrim($value, '0');

        return $value === '' ? '0' : $value;
    }

    return $value;
}

function cardNumberMatches($input, $returned)
{
    $inputParts = explode('/', trim((string) $input), 2);
    $returnedParts = explode('/', trim((string) $returned), 2);

    $inputNumber = normalizeCardNumberPart($inputParts[0]);
    $returnedNumber = normalizeCardNumberPart($returnedParts[0]);

    if (
        $inputNumber === ''
        || $inputNumber !== $returnedNumber
    ) {
        return false;
    }

    if (isset($inputParts[1])) {
        if (!isset($returnedParts[1])) {
            return false;
        }

        $inputTotal = normalizeCardNumberPart($inputParts[1]);
        $returnedTotal = normalizeCardNumberPart($returnedParts[1]);

        return $inputTotal !== ''
            && $inputTotal === $returnedTotal;
    }

    return true;
}

// ------------------------------------------------------------
// JustTCG request helper.
// ------------------------------------------------------------

function requestJustTcg(
    $version,
    $endpoint,
    array $params,
    $tcgApiKey
) {
    $url = 'https://api.justtcg.com/'
        . $version
        . '/'
        . $endpoint
        . '?'
        . http_build_query(
            $params,
            '',
            '&',
            PHP_QUERY_RFC3986
        );

    $curl = curl_init($url);

    if ($curl === false) {
        sendJson([
            'success' => false,
            'error' => 'Could not initialize the JustTCG request.',
        ], 500);
    }

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'x-api-key: ' . $tcgApiKey,
        ],
    ]);

    $response = curl_exec($curl);
    $curlError = curl_error($curl);
    $statusCode = (int) curl_getinfo(
        $curl,
        CURLINFO_HTTP_CODE
    );

    if ($response === false) {
        error_log('JustTCG connection error: ' . $curlError);

        sendJson([
            'success' => false,
            'error' => 'Could not connect to JustTCG.',
        ], 502);
    }

    $data = json_decode($response, true);

    if ($statusCode < 200 || $statusCode >= 300) {
        error_log(
            'JustTCG HTTP ' . $statusCode . ': '
            . substr($response, 0, 4000)
        );

        sendJson([
            'success' => false,
            'error' => 'JustTCG rejected the request.',
            'upstreamStatus' => $statusCode,
            'upstreamError' => is_array($data)
                ? $data
                : ['message' => substr($response, 0, 1000)],
        ], 502);
    }

    if (
        json_last_error() !== JSON_ERROR_NONE
        || !is_array($data)
    ) {
        sendJson([
            'success' => false,
            'error' => 'JustTCG returned invalid JSON.',
        ], 502);
    }

    return $data;
}

// ------------------------------------------------------------
// Keeps only the cards and variants that match the search.
//
// $criteria keys:
//   set_id, set_number, card_type ('raw' | 'graded'),
//   condition (raw only, '' = any), variant_id (graded only),
//   printings (list, empty = any), grading_company ('' = any),
//   languages (accepted language names, lowercase),
//   grade (float or null = any, graded only)
// ------------------------------------------------------------

function filterCardVariants(array $cards, array $criteria)
{
    $cardType = $criteria['card_type'];
    $filteredCards = [];

    foreach ($cards as $card) {
        if (!is_array($card)) {
            continue;
        }

        $returnedSet = $card['set'] ?? null;

        $returnedSetId = is_array($returnedSet)
            ? ($returnedSet['id'] ?? '')
            : $returnedSet;

        if (
            !is_string($returnedSetId)
            || $returnedSetId !== $criteria['set_id']
        ) {
            continue;
        }

        if (
            $criteria['set_number'] !== ''
            && !cardNumberMatches(
                $criteria['set_number'],
                $card['number'] ?? ''
            )
        ) {
            continue;
        }

        $variants = $card['variants'] ?? [];

        if (!is_array($variants)) {
            continue;
        }

        $matchingVariants = [];

        foreach ($variants as $variant) {
            if (!is_array($variant)) {
                continue;
            }

            if (($variant['type'] ?? '') !== $cardType) {
                continue;
            }

            if (
                $cardType === 'raw'
                && $criteria['condition'] !== ''
                && ($variant['condition'] ?? '') !== $criteria['condition']
            ) {
                continue;
            }

            if (
                $cardType === 'graded'
                && $criteria['variant_id'] !== ''
                && (string) ($variant['id'] ?? '') !== $criteria['variant_id']
            ) {
                continue;
            }

            if (
                $criteria['printings']
                && !in_array(
                    $variant['printing'] ?? '',
                    $criteria['printings'],
                    true
                )
            ) {
                continue;
            }

            $grading = $variant['grading'] ?? [];

            if (!is_array($grading)) {
                $grading = [];
            }

            if (
                $cardType === 'graded'
                && $criteria['grading_company'] !== ''
            ) {
                $returnedCompany = $grading['company'] ?? '';

                if (
                    !is_string($returnedCompany)
                    || strtoupper(trim($returnedCompany)) !== $criteria['grading_company']
                ) {
                    continue;
                }
            }

            if (
                $cardType === 'graded'
                && $criteria['grade'] !== null
                && (
                    !is_numeric($grading['grade'] ?? null)
                    || (float) $grading['grade'] !== $criteria['grade']
                )
            ) {
                continue;
            }

            $returnedLanguage = $variant['language'] ?? null;

            if (
                !is_string($returnedLanguage)
                || !in_array(
                    strtolower(trim($returnedLanguage)),
                    $criteria['languages'],
                    true
                )
            ) {
                continue;
            }

            $matchingVariants[] = $variant;
        }

        if ($matchingVariants) {
            $card['variants'] = $matchingVariants;
            $filteredCards[] = $card;
        }
    }

    return $filteredCards;
}

// Returns the US/USD market price of a variant, or the first usable price, or null.
function variantMarketPrice(array $variant)
{
    $markets = is_array($variant['markets'] ?? null) ? $variant['markets'] : [];
    $fallback = null;

    foreach ($markets as $market) {
        $price = is_array($market) ? ($market['price'] ?? null) : null;

        if (!is_int($price) && !is_float($price)) {
            continue;
        }

        if ($price < 0 || !is_finite((float) $price)) {
            continue;
        }

        if (
            ($market['region'] ?? '') === 'US'
            && ($market['currency'] ?? '') === 'USD'
        ) {
            return (float) $price;
        }

        $fallback ??= (float) $price;
    }

    return $fallback;
}

// ------------------------------------------------------------
// Trades page helpers. $tradeCard has passed validateTradeCard().
// ------------------------------------------------------------

// One JustTCG card search for the trade card's set and name.
function searchTradeCards(
    array $tradeCard,
    $setId,
    array $extraParams,
    $tcgApiKey
) {
    $upstream = requestJustTcg(
        'v2',
        'cards',
        [
            'game' => 'pokemon',
            'set' => $setId,
            'q' => $tradeCard['name'],
        ] + $extraParams,
        $tcgApiKey
    );

    $cards = $upstream['data'] ?? null;

    if (!is_array($cards)) {
        sendJson([
            'success' => false,
            'error' => 'JustTCG returned an unexpected cards structure.',
        ], 502);
    }

    return $cards;
}

// Keeps the cards and variants that match the trade card.
// $cardType 'raw' with $anyCondition = true only checks that the
// card exists. Graded variants must be from the default company.
function matchTradeCards(
    array $cards,
    array $tradeCard,
    $setId,
    $cardType,
    $anyCondition = false
) {
    $graded = $cardType === 'graded';

    $cards = filterCardVariants($cards, [
        'set_id' => $setId,
        'set_number' => $tradeCard['set_num'],
        'card_type' => $cardType,
        'condition' => $anyCondition ? '' : $tradeCard['condition'],
        'variant_id' => '',
        'printings' => TRADE_PRINTING_ALIASES[$tradeCard['printing']],
        'grading_company' => $graded ? TRADE_DEFAULT_GRADING_COMPANY : '',
        'languages' => JUSTTCG_LANGUAGE_ALIASES[
            TRADE_LANGUAGE_CODES[$tradeCard['lang']]
        ],
        'grade' => $graded ? (float) $tradeCard['grade'] : null,
    ]);

    return exactNameCards($cards, tradeMatchName($tradeCard));
}

// The JustTCG card name to match or the typed name.
function tradeMatchName(array $tradeCard)
{
    return ($tradeCard['tcg_name'] ?? '') !== '' ? $tradeCard['tcg_name'] : $tradeCard['name'];
}

// q= is a partial search
function exactNameCards(array $cards, $name)
{
    return array_values(array_filter(
        $cards,
        fn($card) => is_string($card['name'] ?? null)
            && strcasecmp(trim($card['name']), $name) === 0
    ));
}

// Raw printings JustTCG lists for the trade card in any condition,
// named as on the trades page when possible, e.g. ['Normal', 'Reverse Holofoil'].
function availableTradePrintings(array $cards, array $tradeCard, $setId)
{
    $cards = exactNameCards(filterCardVariants($cards, [
        'set_id' => $setId,
        'set_number' => $tradeCard['set_num'],
        'card_type' => 'raw',
        'condition' => '',
        'variant_id' => '',
        'printings' => [],
        'grading_company' => '',
        'languages' => JUSTTCG_LANGUAGE_ALIASES[
            TRADE_LANGUAGE_CODES[$tradeCard['lang']]
        ],
        'grade' => null,
    ]), tradeMatchName($tradeCard));

    $printings = [];

    foreach ($cards as $card) {
        foreach ($card['variants'] as $variant) {
            $printing = (string) ($variant['printing'] ?? '');
            $printing = tradePrintingFromJustTcg($printing) ?? $printing;

            if ($printing !== '') {
                $printings[$printing] = true;
            }
        }
    }

    return array_keys($printings);
}

// Trades page routes are for employees and managers only
function requireTradeUser()
{
    $user = getSessionUser();

    if ($user === null) {
        sendJson([
            'success' => false,
            'error' => 'You must be logged in to do that.',
        ], 401);
    }

    if (!in_array(
        strtolower(trim((string) $user['role'])),
        [ROLE_MANAGER, ROLE_EMPLOYEE],
        true
    )) {
        sendJson([
            'success' => false,
            'error' => 'You do not have permission to perform that action.',
        ], 403);
    }
}

// Returns ['price' => float|null, 'matched' => label|null] from the
// first priced variant, e.g. "Near Mint Normal" or "PSA 9".
function firstTradePrice(array $cards)
{
    foreach ($cards as $card) {
        foreach ($card['variants'] as $variant) {
            $price = variantMarketPrice($variant);

            if ($price === null) {
                continue;
            }

            return [
                'price' => round($price, 2),
                'matched' => ($variant['type'] ?? '') === 'graded'
                    ? ($variant['grading']['canonical'] ?? 'Graded')
                    : trim(($variant['condition'] ?? '') . ' ' . ($variant['printing'] ?? '')),
            ];
        }
    }

    return ['price' => null, 'matched' => null];
}

// ------------------------------------------------------------
// Read and validate the route.
// ------------------------------------------------------------

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$routePart = isset($_GET['route']) && is_string($_GET['route'])
    ? trim($_GET['route'])
    : '';

$allowedRoutes = [
    'get_price' => 'GET',
    'sets' => 'GET',
    'update_sets' => 'POST',
    'search_cards' => 'POST',
    'trade_price' => 'POST',
    'trade_lookup' => 'POST',
];

if (!isset($allowedRoutes[$routePart])) {
    sendJson([
        'success' => false,
        'error' => 'Endpoint not found.',
        'route' => $routePart,
    ], 404);
}

$expectedMethod = $allowedRoutes[$routePart];

if ($method !== $expectedMethod) {
    header('Allow: ' . $expectedMethod);

    sendJson([
        'success' => false,
        'error' => 'Method not allowed.',
        'route' => $routePart,
        'receivedMethod' => $method,
        'expectedMethod' => $expectedMethod,
    ], 405);
}

// ------------------------------------------------------------
// JustTCG configuration.
//
// Prefer the existing $tcgApiKey from config.php.
// Otherwise accept a constant or environment variable.
// ------------------------------------------------------------

if (!isset($tcgApiKey)) {
    $tcgApiKey = defined('JUSTTCG_API_KEY')
        ? constant('JUSTTCG_API_KEY')
        : getenv('JUSTTCG_API_KEY');
}

if (
    !is_string($tcgApiKey)
    || trim($tcgApiKey) === ''
) {
    sendJson([
        'success' => false,
        'error' => 'The JustTCG API key is not configured.',
    ], 500);
}

$tcgApiKey = trim($tcgApiKey);

if (!function_exists('curl_init')) {
    sendJson([
        'success' => false,
        'error' => 'The PHP cURL extension is not enabled.',
    ], 500);
}

// ------------------------------------------------------------
// POST index.php?route=search_cards
// ------------------------------------------------------------

if ($routePart === 'search_cards') {
    $setId = postString('text1');
    $setNumber = postString('text2');
    $name = postString('text3');
    $language = postString('extra1', 'en');
    $condition = postString('extra2');
    $cardType = postString('card_type', 'raw');
    $gradedVariantId = postString('graded_variant_id');
    $printing = postString('printing');
    $gradingCompany = strtoupper(postString('grading_company'));

    $legacyConditions = [
        'mint' => 'Near Mint',
        'near-mint' => 'Near Mint',
    ];

    $condition = $legacyConditions[$condition] ?? $condition;

    $allowedConditions = [
        'Near Mint',
        'Lightly Played',
        'Moderately Played',
        'Heavily Played',
        'Damaged',
        'Sealed',
    ];

    $languageAliases = JUSTTCG_LANGUAGE_ALIASES;
    $allowedGradingCompanies = JUSTTCG_GRADING_COMPANIES;

    if ($setId === '') {
        sendJson([
            'success' => false,
            'error' => 'Please select a set.',
        ], 422);
    }

    if (!in_array($cardType, ['raw', 'graded'], true)) {
        sendJson([
            'success' => false,
            'error' => 'Please select Raw or Graded.',
        ], 422);
    }

    if (!array_key_exists($language, $languageAliases)) {
        sendJson([
            'success' => false,
            'error' => 'Please select a valid language.',
        ], 422);
    }

    if (
        $cardType === 'raw'
        && !in_array($condition, $allowedConditions, true)
    ) {
        sendJson([
            'success' => false,
            'error' => 'Please select a valid raw condition.',
        ], 422);
    }

    if ($cardType === 'raw') {
        $gradingCompany = '';
        $gradedVariantId = '';
    } elseif (
        $gradingCompany !== ''
        && !in_array(
            $gradingCompany,
            $allowedGradingCompanies,
            true
        )
    ) {
        sendJson([
            'success' => false,
            'error' => 'Please select a valid grading company.',
        ], 422);
    }

    if (
        strlen($setId) > 200
        || strlen($setNumber) > 100
        || strlen($name) > 200
        || strlen($printing) > 200
        || strlen($gradedVariantId) > 250
    ) {
        sendJson([
            'success' => false,
            'error' => 'One or more search values are too long.',
        ], 422);
    }

    $params = [
        'game' => 'pokemon',
    ];

    if ($cardType === 'graded' && $gradedVariantId !== '') {
        $params['variant_id'] = $gradedVariantId;
        $params['graded'] = 'include';
    } else {
        $params['set'] = $setId;

        if ($name !== '') {
            $params['q'] = $name;
        }

        if ($cardType === 'graded') {
            $params['graded'] = 'only';
        }
    }

    if ($cardType === 'graded' && $gradingCompany !== '') {
        $params['grading_company'] = $gradingCompany;
    }

    $upstream = requestJustTcg(
        'v2',
        'cards',
        $params,
        $tcgApiKey
    );

    $cards = $upstream['data'] ?? null;

    if (!is_array($cards)) {
        sendJson([
            'success' => false,
            'error' => 'JustTCG returned an unexpected cards structure.',
        ], 502);
    }

    $filteredCards = filterCardVariants($cards, [
        'set_id' => $setId,
        'set_number' => $setNumber,
        'card_type' => $cardType,
        'condition' => $condition,
        'variant_id' => $gradedVariantId,
        'printings' => $printing !== '' ? [$printing] : [],
        'grading_company' => $gradingCompany,
        'languages' => $languageAliases[$language],
        'grade' => null,
    ]);

    // Return only the fields needed by your results UI.
    sendJson([
        'success' => true,
        'data' => $filteredCards,
    ]);
}

// ------------------------------------------------------------
// POST index.php?route=trade_price
//
// Prices one card using the fields from the trades page
// (set name, set_num, name, lang, card_type, condition | grade,
// printing).
//
// Raw: 404 unless JustTCG has the card with a price.
// Graded: 404 unless JustTCG has the card. The price comes from
// TRADE_DEFAULT_GRADING_COMPANY and is null when that company has
// no listing for the grade; the employee then enters one.
// ------------------------------------------------------------

if ($routePart === 'trade_price') {
    requireTradeUser();

    $tradeCard = [];

    foreach (
        ['set', 'set_num', 'name', 'tcg_name', 'lang', 'card_type', 'condition', 'grade', 'printing']
        as $field
    ) {
        $tradeCard[$field] = postString($field);
    }

    $tradeCard['set_num'] = normalizeSetNumber($tradeCard['set_num']);

    $cardErrors = validateTradeCard($tradeCard);

    if ($cardErrors) {
        sendJson([
            'success' => false,
            'error' => 'Card details are invalid: '
                . implode(', ', $cardErrors) . '.',
        ], 422);
    }

    $graded = $tradeCard['card_type'] === 'graded';
    $setId = getSetIdsByName()[$tradeCard['set']];

    // JustTCG search returns raw or graded variants, never both, so a
    // graded card is first confirmed to exist through its raw listing.
    $searchResults = searchTradeCards($tradeCard, $setId, [], $tcgApiKey);
    $rawCards = matchTradeCards(
        $searchResults,
        $tradeCard,
        $setId,
        'raw',
        $graded
    );

    if (!$rawCards) {
        // Say whether the card exists in another printing or condition,
        // so a wrong dropdown choice does not read as "card does not exist".
        $printings = availableTradePrintings($searchResults, $tradeCard, $setId);

        if (!$printings) {
            $error = 'No card matching this set, number, name and language was found on JustTCG.';
        } elseif (!in_array($tradeCard['printing'], $printings, true)) {
            $error = 'JustTCG has this card only in printing: '
                . implode(', ', $printings) . '.';
        } else {
            $error = 'JustTCG has no ' . $tradeCard['condition']
                . ' ' . $tradeCard['printing'] . ' listing for this card.';
        }

        sendJson([
            'success' => false,
            'error' => $error,
            'available_printings' => $printings,
        ], 404);
    }

    if ($graded) {
        $pick = firstTradePrice(matchTradeCards(
            searchTradeCards($tradeCard, $setId, [
                'graded' => 'only',
                'grading_company' => TRADE_DEFAULT_GRADING_COMPANY,
            ], $tcgApiKey),
            $tradeCard,
            $setId,
            'graded'
        ));
    } else {
        $pick = firstTradePrice($rawCards);

        if ($pick['price'] === null) {
            sendJson([
                'success' => false,
                'error' => 'JustTCG found this card but has no current price for it.',
            ], 404);
        }
    }

    sendJson([
        'success' => true,
        'price' => $pick['price'],
        'price_date' => date('Y-m-d'),
        'matched' => $pick['matched'],
        'grading_company' => $graded ? TRADE_DEFAULT_GRADING_COMPANY : null,
    ]);
}

// ------------------------------------------------------------
// POST index.php?route=trade_lookup
//
// Every JustTCG card matching a trades-page name + set number,
// with its raw variants and prices
//
// Response: { success, price_date, cards: [{ set, name, number, rarity,
//   variants: [{ printing, condition, price|null }] }] }
// Set, printing and condition use the trades page's names.
// ------------------------------------------------------------

if ($routePart === 'trade_lookup') {
    requireTradeUser();

    $name = postString('name');
    $setNumber = normalizeSetNumber(postString('set_num'));
    $setName = postString('set');
    $language = postString('lang');
    $setIds = getSetIdsByName();
    $errors = [];

    foreach (['name' => $name, 'set number' => $setNumber] as $fieldName => $value) {
        if ($value === '' || strlen($value) > 45) {
            $errors[] = "$fieldName is required and must be 45 characters or fewer";
        }
    }

    if ($setName !== '' && !isset($setIds[$setName])) {
        $errors[] = 'set is not a known set';
    }

    if (!isset(TRADE_LANGUAGE_CODES[$language])) {
        $errors[] = 'language is invalid';
    }

    if ($errors) {
        sendJson([
            'success' => false,
            'error' => 'Card details are invalid: ' . implode(', ', $errors) . '.',
        ], 422);
    }

    $params = [
        'game' => 'pokemon',
        'q' => $name,
        'number' => $setNumber,
        'limit' => 20,
    ];

    if ($setName !== '') {
        $params['set'] = $setIds[$setName];
    }

    $upstream = requestJustTcg('v2', 'cards', $params, $tcgApiKey);
    $cards = $upstream['data'] ?? null;

    if (!is_array($cards)) {
        sendJson([
            'success' => false,
            'error' => 'JustTCG returned an unexpected cards structure.',
        ], 502);
    }

    $setNamesById = getSetNamesById();
    $languages = JUSTTCG_LANGUAGE_ALIASES[TRADE_LANGUAGE_CODES[$language]];
    $results = [];

    foreach ($cards as $card) {
        if (!is_array($card) || !is_string($card['name'] ?? null)) {
            continue;
        }

        $setId = is_array($card['set'] ?? null) ? ($card['set']['id'] ?? '') : '';

        if (!isset($setNamesById[$setId]) || !cardNumberMatches($setNumber, $card['number'] ?? '')) {
            continue;
        }

        $variants = [];

        foreach (is_array($card['variants'] ?? null) ? $card['variants'] : [] as $variant) {
            if (!is_array($variant) || ($variant['type'] ?? '') !== 'raw') {
                continue;
            }

            $printing = tradePrintingFromJustTcg((string) ($variant['printing'] ?? ''));
            $condition = $variant['condition'] ?? '';
            $variantLanguage = strtolower(trim((string) ($variant['language'] ?? '')));

            if (
                $printing === null
                || !in_array($condition, TRADE_CONDITIONS, true)
                || !in_array($variantLanguage, $languages, true)
            ) {
                continue;
            }

            $price = variantMarketPrice($variant);

            // "Normal" and "Unlimited" both map to Normal; keep the first
            $variants["$printing|$condition"] ??= [
                'printing' => $printing,
                'condition' => $condition,
                'price' => $price === null ? null : round($price, 2),
            ];
        }

        if ($variants) {
            $results[] = [
                'set' => $setNamesById[$setId],
                'name' => trim($card['name']),
                'number' => (string) ($card['number'] ?? ''),
                'rarity' => $card['rarity'] ?? null,
                'variants' => array_values($variants),
            ];
        }
    }

    if (!$results) {
        sendJson([
            'success' => false,
            'error' => 'No ' . $language . ' card with this name and set number was found on JustTCG'
                . ($setName !== '' ? ' in ' . $setName : '') . '.',
        ], 404);
    }

    sendJson([
        'success' => true,
        'price_date' => date('Y-m-d'),
        'cards' => $results,
    ]);
}

// ------------------------------------------------------------
// GET index.php?route=get_price
// Preserves your existing fixed-card lookup.
// ------------------------------------------------------------

if ($routePart === 'get_price') {
    $data = requestJustTcg(
        'v1',
        'cards',
        [
            'cardId' =>
                'pokemon-battle-academy-fire-energy-22-charizard-stamped-promo',
            'condition' => 'NM,LP',
            'priceHistoryDuration' => '30d',
        ],
        $tcgApiKey
    );

    $price = $data['data'][0]['variants'][0]['price'] ?? null;

    if ($price === null) {
        sendJson([
            'success' => false,
            'error' => 'No price found.',
        ], 404);
    }

    sendJson([
        'success' => true,
        'price' => $price,
    ]);
}

// ------------------------------------------------------------
// Only sets/update_sets should reach this point.
// ------------------------------------------------------------

if (!in_array($routePart, ['sets', 'update_sets'], true)) {
    sendJson([
        'success' => false,
        'error' => 'No handler was found for this route.',
    ], 500);
}

$data = requestJustTcg(
    'v1',
    'sets',
    [
        'game' => 'pokemon',
        'orderBy' => 'release_date',
        'order' => 'desc',
    ],
    $tcgApiKey
);

$sets = $data['data'] ?? null;

if (!is_array($sets)) {
    sendJson([
        'success' => false,
        'error' => 'JustTCG did not return a valid sets list.',
    ], 502);
}

// ------------------------------------------------------------
// POST index.php?route=update_sets
// ------------------------------------------------------------

if ($routePart === 'update_sets') {
    $contents = json_encode(
        $sets,
        JSON_PRETTY_PRINT
        | JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_INVALID_UTF8_SUBSTITUTE
    );

    if ($contents === false) {
        sendJson([
            'success' => false,
            'error' => 'Failed to encode the sets list.',
        ], 500);
    }

    $filePath = __DIR__ . '/sets.txt';
    $fileContents = $contents . PHP_EOL;

    error_clear_last();

    $bytesWritten = @file_put_contents(
        $filePath,
        $fileContents,
        LOCK_EX
    );

    if (
        $bytesWritten === false
        || $bytesWritten !== strlen($fileContents)
    ) {
        $writeError = error_get_last();

        error_log(
            'Failed to fully write ' . $filePath . ': '
            . ($writeError['message'] ?? 'Incomplete file write.')
        );

        sendJson([
            'success' => false,
            'error' =>
                'Could not fully write sets.txt. '
                . 'Check folder permissions and the PHP error log.',
        ], 500);
    }

    sendJson([
        'success' => true,
        'message' => 'sets.txt updated successfully.',
        'count' => count($sets),
        'bytesWritten' => $bytesWritten,
    ]);
}

// ------------------------------------------------------------
// GET index.php?route=sets
// ------------------------------------------------------------

sendJson($data);