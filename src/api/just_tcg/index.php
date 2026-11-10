<?php

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../includes/config.php';

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
// Shared JustTCG request helper.
// ------------------------------------------------------------

function requestJustTcg($version, $endpoint, array $params, $apiKey)
{
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
            'x-api-key: ' . $apiKey,
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
            'error' => 'Could not connect to JustTCG. Check the PHP error log.',
        ], 502);
    }

    $data = json_decode($response, true);

if ($statusCode < 200 || $statusCode >= 300) {
    // Keep the response body in the server log for debugging.
    // Do not log the API key or request headers.
    error_log(
        'JustTCG HTTP ' . $statusCode . ': '
        . substr($response, 0, 4000)
    );

    $upstreamError = is_array($data)
        ? $data
        : ['message' => substr($response, 0, 1000)];

    sendJson([
        'success' => false,
        'error' => 'JustTCG rejected the request.',
        'upstreamStatus' => $statusCode,
        'upstreamError' => $upstreamError,
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
// Load shared configuration.
// Supports a constant in config.php or an environment variable.
// ------------------------------------------------------------

$apiKey = getenv('JUSTTCG_API_KEY') ?: (defined('JUSTTCG_API_KEY') ? JUSTTCG_API_KEY : null);

if (!is_string($apiKey) || trim($apiKey) === '') {
    sendJson([
        'success' => false,
        'error' => 'JUSTTCG_API_KEY is not configured.',
    ], 500);
}

$apiKey = trim($apiKey);

if (!function_exists('curl_init')) {
    sendJson([
        'success' => false,
        'error' => 'The PHP cURL extension is not enabled.',
    ], 500);
}

// ------------------------------------------------------------
// POST index.php?route=search_cards
//
// Accepts the existing form's FormData fields.
// Returns matching cards and the original upstream response.
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

    // Normalize compatible values from your older form.
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

    // Local aliases used for comparing response values.
    $languageAliases = [
        'en' => ['en', 'eng', 'english'],
        'jap' => ['ja', 'jp', 'jpn', 'japanese'],
        'ch' => ['zh', 'zh-cn', 'zh-tw', 'chinese'],
        'kor' => ['ko', 'kr', 'kor', 'korean'],
    ];

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
    // Direct lookup of a previously selected graded variant.
    $params['variant_id'] = $gradedVariantId;
    $params['graded'] = 'include';
} else {
    // Browse/search within the selected set.
    $params['set'] = $setId;

    if ($name !== '') {
        $params['q'] = $name;
    }

    if ($cardType === 'graded') {
        // Browse/search permits graded=only, not graded=include.
        $params['graded'] = 'only';
    }

    // Raw searches omit the graded parameter.
}

    $upstream = requestJustTcg(
        'v2',
        'cards',
        $params,
        $apiKey
    );

    $cards = $upstream['data'] ?? null;

    if (!is_array($cards)) {
        sendJson([
            'success' => false,
            'error' => 'JustTCG returned an unexpected cards structure.',
        ], 502);
    }

    $filteredCards = [];

    $diagnostics = [
        'returnedCards' => count($cards),
        'matchingCards' => 0,
        'matchingVariants' => 0,
        'rejectedCardsBySet' => 0,
        'rejectedCardsByNumber' => 0,
        'rejectedVariantsByType' => 0,
        'rejectedVariantsByCondition' => 0,
        'rejectedVariantsById' => 0,
        'rejectedVariantsByPrinting' => 0,
        'rejectedVariantsByLanguage' => 0,
    ];

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
            || $returnedSetId !== $setId
        ) {
            $diagnostics['rejectedCardsBySet']++;
            continue;
        }

        if (
    $setNumber !== ''
    && !cardNumberMatches(
        $setNumber,
        $card['number'] ?? ''
    )
) {
    $diagnostics['rejectedCardsByNumber']++;
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
                $diagnostics['rejectedVariantsByType']++;
                continue;
            }

            if (
                $cardType === 'raw'
                && ($variant['condition'] ?? '') !== $condition
            ) {
                $diagnostics['rejectedVariantsByCondition']++;
                continue;
            }

            if (
                $cardType === 'graded'
                && $gradedVariantId !== ''
                && (string) ($variant['id'] ?? '') !== $gradedVariantId
            ) {
                $diagnostics['rejectedVariantsById']++;
                continue;
            }

            if (
                $printing !== ''
                && ($variant['printing'] ?? '') !== $printing
            ) {
                $diagnostics['rejectedVariantsByPrinting']++;
                continue;
            }

            $returnedLanguage = $variant['language'] ?? null;

            // Unknown language is not assumed to mean English.
            if (
                !is_string($returnedLanguage)
                || !in_array(
                    strtolower(trim($returnedLanguage)),
                    $languageAliases[$language],
                    true
                )
            ) {
                $diagnostics['rejectedVariantsByLanguage']++;
                continue;
            }

            $matchingVariants[] = $variant;
        }

        if ($matchingVariants) {
            $card['variants'] = $matchingVariants;
            $filteredCards[] = $card;

            $diagnostics['matchingVariants'] += count(
                $matchingVariants
            );
        }
    }

    $diagnostics['matchingCards'] = count($filteredCards);

    sendJson([
        'success' => true,
        'data' => $filteredCards,

        'filters' => [
            'set' => $setId,
            'number' => $setNumber,
            'name' => $name,
            'language' => $language,
            'cardType' => $cardType,
            'condition' => $cardType === 'raw' ? $condition : null,
            'gradedVariantId' => $cardType === 'graded'
                ? $gradedVariantId
                : null,
            'printing' => $printing,
        ],

        'diagnostics' => $diagnostics,

        // Retained so the frontend can inspect the original cards
        // and build printing/grading dropdowns.
        'upstream' => $upstream,

        'note' =>
            'Local filters apply only to this returned page. ' .
            'Variants with an unknown language are excluded. ' .
            'Diagnostic rejection counts record the first failed ' .
            'check for each card or variant.',
    ]);
}

// ------------------------------------------------------------
// GET index.php?route=get_price
//
// Preserves your existing fixed-card price lookup.
// This is separate from the form-driven search route.
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
        $apiKey
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
// Shared request for sets and update_sets.
// ------------------------------------------------------------

$data = requestJustTcg(
    'v1',
    'sets',
    [
        'game' => 'pokemon',
        'orderBy' => 'release_date',
        'order' => 'desc',
    ],
    $apiKey
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
                'Could not fully write sets.txt. ' .
                'Check folder permissions and the PHP error log.',
        ], 500);
    }

    sendJson([
        'success' => true,
        'message' => 'sets.txt updated successfully.',
        'count' => count($sets),
        'bytesWritten' => $bytesWritten,
    ]);
}

function normalizeCardNumberPart($value)
{
    $value = strtoupper(trim((string) $value));

    // Ignore spaces around or within the number.
    $value = preg_replace('/\s+/', '', $value);

    // Normalize numeric values without removing letter prefixes.
    // "004" becomes "4"; "TG04" remains "TG04".
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

    if ($inputNumber === '' || $inputNumber !== $returnedNumber) {
        return false;
    }

    // If the user supplies a set total, validate that too.
    if (isset($inputParts[1])) {
        if (!isset($returnedParts[1])) {
            return false;
        }

        $inputTotal = normalizeCardNumberPart($inputParts[1]);
        $returnedTotal = normalizeCardNumberPart($returnedParts[1]);

        return $inputTotal !== '' && $inputTotal === $returnedTotal;
    }

    return true;
}

// ------------------------------------------------------------
// GET index.php?route=sets
// Preserve the original JustTCG response structure.
// ------------------------------------------------------------

sendJson($data);