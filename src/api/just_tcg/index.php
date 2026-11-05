<?php

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . "/../../includes/config.php";

function sendJson($data, $status = 200)
{
    http_response_code($status);

    echo json_encode(
        $data,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    exit;
}

// ------------------------------------------------------------
// Read the requested route and HTTP method.
// Example: index.php?route=update_sets
// ------------------------------------------------------------

$method = $_SERVER['REQUEST_METHOD'];

$routePart = isset($_GET['route']) && is_string($_GET['route'])
    ? $_GET['route']
    : '';

$allowedRoutes = [
    'get_price' => 'GET',
    'sets' => 'GET',
    'update_sets' => 'POST',
];

if (!isset($allowedRoutes[$routePart])) {
    sendJson([
        "error" => "Endpoint not found.",
        "route" => $routePart,
    ], 404);
}

$expectedMethod = $allowedRoutes[$routePart];

if ($method !== $expectedMethod) {
    header('Allow: ' . $expectedMethod);

    sendJson([
        "error" => "Method not allowed.",
        "route" => $routePart,
        "receivedMethod" => $method,
        "expectedMethod" => $expectedMethod,
    ], 405);
}

// ------------------------------------------------------------
// Configure the JustTCG request.
// ------------------------------------------------------------

// Add this to your existing API index.php before its default route handling.
// Do not add a second opening PHP tag if the file is already inside PHP.

if (($_GET['route'] ?? '') === 'search_cards') {
    header('Content-Type: application/json; charset=utf-8');

    $respond = static function (int $status, array $payload): void {
        http_response_code($status);

        echo json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );

        exit;
    };

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');

        $respond(405, [
            'success' => false,
            'error' => 'Use POST for this endpoint.',
        ]);
    }

    $read = static function (string $key): string {
        $value = $_POST[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    };

    // Read the fields from your form.
    $setId = $read('text1');
    $setNumber = $read('text2');
    $name = $read('text3');
    $language = $read('extra1');
    $condition = $read('extra2');
    $cardType = $read('card_type');
    $gradedVariantId = $read('graded_variant_id');
    $printing = $read('printing');

    if (!in_array($cardType, ['raw', 'graded'], true)) {
        $respond(422, [
            'success' => false,
            'error' => 'Please select Raw or Graded.',
        ]);
    }

    if ($setId === '') {
        $respond(422, [
            'success' => false,
            'error' => 'Please select a set.',
        ]);
    }

    if (
        strlen($setId) > 200
        || strlen($setNumber) > 100
        || strlen($name) > 200
        || strlen($printing) > 200
        || strlen($gradedVariantId) > 250
    ) {
        $respond(422, [
            'success' => false,
            'error' => 'One or more search values are too long.',
        ]);
    }

    $allowedConditions = [
        'Near Mint',
        'Lightly Played',
        'Moderately Played',
        'Heavily Played',
        'Damaged',
        'Sealed',
    ];

    if (
        $cardType === 'raw'
        && !in_array($condition, $allowedConditions, true)
    ) {
        $respond(422, [
            'success' => false,
            'error' => 'Please select a valid raw condition.',
        ]);
    }

    /*
     * Configure JUSTTCG_API_KEY in your server environment.
     * Alternatively, load it from a private PHP configuration file
     * outside your public htdocs directory.
     */
    $apiKey = getenv('JUSTTCG_API_KEY');

    if (!is_string($apiKey) || $apiKey === '') {
        $respond(500, [
            'success' => false,
            'error' => 'The JustTCG API key is not configured.',
        ]);
    }

    if (!function_exists('curl_init')) {
        $respond(500, [
            'success' => false,
            'error' => 'The PHP cURL extension is not enabled.',
        ]);
    }

    /*
     * Fetch raw and graded variants so the response can also
     * populate your graded-options dropdown.
     *
     * Language, condition, printing and number are filtered below.
     * This avoids guessing unsupported upstream parameter names
     * or forwarding your local language codes unchanged.
     */
    $query = [
        'game' => 'pokemon',
        'graded' => 'include',
    ];

    if ($cardType === 'graded' && $gradedVariantId !== '') {
        // A selected graded variant can be looked up directly.
        $query['variant_id'] = $gradedVariantId;
    } else {
        $query['set'] = $setId;

        if ($name !== '') {
            $query['q'] = $name;
        }
    }

    $url = 'https://api.justtcg.com/v2/cards?' .
        http_build_query($query, '', '&', PHP_QUERY_RFC3986);

    $curl = curl_init($url);

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'x-api-key: ' . $apiKey,
        ],
    ]);

    $responseBody = curl_exec($curl);
    $curlError = curl_error($curl);
    $upstreamStatus = (int) curl_getinfo(
        $curl,
        CURLINFO_HTTP_CODE
    );

    if ($responseBody === false) {
        error_log('JustTCG request failed: ' . $curlError);

        $respond(502, [
            'success' => false,
            'error' => 'Could not connect to JustTCG.',
        ]);
    }

    $decoded = json_decode($responseBody, true);

    if (!is_array($decoded)) {
        $respond(502, [
            'success' => false,
            'error' => 'JustTCG returned an invalid JSON response.',
        ]);
    }

    if ($upstreamStatus < 200 || $upstreamStatus >= 300) {
        $respond(502, [
            'success' => false,
            'error' => 'JustTCG rejected the request.',
            'upstreamStatus' => $upstreamStatus,
            'details' => $decoded,
        ]);
    }

    $cards = $decoded['data'] ?? [];

    if (!is_array($cards)) {
        $respond(502, [
            'success' => false,
            'error' => 'JustTCG returned an unexpected data structure.',
        ]);
    }

    /*
     * These aliases are local comparison rules, not claimed
     * JustTCG request-parameter values.
     */
    $languageAliases = [
        'en' => ['en', 'eng', 'english'],
        'jap' => ['ja', 'jp', 'jpn', 'japanese'],
        'ch' => ['zh', 'zh-cn', 'zh-tw', 'chinese'],
        'kor' => ['ko', 'kr', 'kor', 'korean'],
    ];

    if (!array_key_exists($language, $languageAliases)) {
        $respond(422, [
            'success' => false,
            'error' => 'Please select a valid language.',
        ]);
    }

    $filteredCards = [];

    foreach ($cards as $card) {
        if (!is_array($card)) {
            continue;
        }

        $returnedSet = $card['set'] ?? null;
        $returnedSetId = is_array($returnedSet)
            ? ($returnedSet['id'] ?? '')
            : $returnedSet;

        // Also verify the set when looking up a variant directly.
        if ((string) $returnedSetId !== $setId) {
            continue;
        }

        if (
            $setNumber !== ''
            && strcasecmp(
                trim((string) ($card['number'] ?? '')),
                $setNumber
            ) !== 0
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
                && ($variant['condition'] ?? '') !== $condition
            ) {
                continue;
            }

            if (
                $cardType === 'graded'
                && $gradedVariantId !== ''
                && (string) ($variant['id'] ?? '') !== $gradedVariantId
            ) {
                continue;
            }

            if (
                $printing !== ''
                && ($variant['printing'] ?? '') !== $printing
            ) {
                continue;
            }

            /*
             * A null language is not automatically English.
             * Exclude unknown languages when applying a language filter.
             */
            $returnedLanguage = $variant['language'] ?? null;

            if (
                !is_string($returnedLanguage)
                || !in_array(
                    strtolower(trim($returnedLanguage)),
                    $languageAliases[$language],
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

    $respond(200, [
        'success' => true,
        'data' => $filteredCards,

        // Keep the upstream response available for debugging and
        // for building card/printing/grading selection controls.
        'upstream' => $decoded,

        'note' =>
            'Local filters apply only to this returned page. ' .
            'Variants with an unknown language are excluded.',
    ]);
}

// Your existing update_sets route and other routes continue below.

switch ($routePart) {
    case 'get_price':
        $endpoint = 'cards';

        $params = [
            "cardId" => "pokemon-battle-academy-fire-energy-22-charizard-stamped-promo",
            "condition" => "NM,LP",
            "priceHistoryDuration" => "30d",
        ];
        break;

    case 'sets':
    case 'update_sets':
        $endpoint = 'sets';

        $params = [
            "game" => "pokemon",
            "orderBy" => "release_date",
            "order" => "desc",
        ];
        break;
}

// ------------------------------------------------------------
// Load the API key.
// ------------------------------------------------------------

$apiKey = 'tcg_689238b6d0f841ceb2f632c381a979d3';

if (!$apiKey) {
    sendJson([
        "error" => "JUSTTCG_API_KEY is not configured.",
    ], 500);
}

if (!function_exists('curl_init')) {
    sendJson([
        "error" => "The PHP cURL extension is not enabled.",
    ], 500);
}

// ------------------------------------------------------------
// Make the request to JustTCG.
// ------------------------------------------------------------

$url = "https://api.justtcg.com/v1/"
    . $endpoint
    . "?"
    . http_build_query($params);

$curl = curl_init($url);

if ($curl === false) {
    sendJson([
        "error" => "Could not initialize the API request.",
    ], 500);
}

curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "x-api-key: " . $apiKey,
        "Accept: application/json",
    ],
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 30,
]);

$response = curl_exec($curl);
$statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

if ($response === false) {
    $curlError = curl_error($curl);


    error_log("JustTCG connection error: " . $curlError);

    sendJson([
        "error" => "Could not connect to JustTCG. Check the PHP error log.",
    ], 502);
}


if ($statusCode < 200 || $statusCode >= 300) {
    sendJson([
        "error" => "JustTCG request failed.",
        "upstreamStatus" => $statusCode,
    ], 502);
}

// ------------------------------------------------------------
// Decode the response before using its data.
// ------------------------------------------------------------

$data = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
    sendJson([
        "error" => "JustTCG returned invalid JSON.",
    ], 502);
}

// ------------------------------------------------------------
// GET index.php?route=get_price
// ------------------------------------------------------------

if ($routePart === 'get_price') {
    $price = $data["data"][0]["variants"][0]["price"] ?? null;

    if ($price === null) {
        sendJson([
            "error" => "No price found.",
        ], 404);
    }

    sendJson([
        "price" => $price,
    ]);
}

// ------------------------------------------------------------
// Validate the returned sets list.
// ------------------------------------------------------------

$sets = $data["data"] ?? null;

if (!is_array($sets)) {
    sendJson([
        "error" => "JustTCG did not return a valid sets list.",
    ], 502);
}

// ------------------------------------------------------------
// POST index.php?route=update_sets
// Save the returned list to sets.txt.
// ------------------------------------------------------------

if ($routePart === 'update_sets') {
    $contents = json_encode(
        $sets,
        JSON_PRETTY_PRINT
        | JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    );

    if ($contents === false) {
        sendJson([
            "error" => "Failed to encode the sets list.",
        ], 500);
    }

    // Matches the sets.txt location used by your frontend.
    $filePath = __DIR__ . '/sets.txt';

    // Log filesystem warnings instead of mixing them into the JSON response.
    error_clear_last();

    $bytesWritten = @file_put_contents(
        $filePath,
        $contents . PHP_EOL,
        LOCK_EX
    );

    if ($bytesWritten === false) {
        $writeError = error_get_last();

        error_log(
            "Failed to write " . $filePath . ": "
            . ($writeError['message'] ?? 'Unknown filesystem error.')
        );

        sendJson([
            "success" => false,
            "error" => "Could not write sets.txt. Check folder permissions and the PHP error log.",
        ], 500);
    }

    sendJson([
        "success" => true,
        "message" => "sets.txt updated successfully.",
        "count" => count($sets),
        "bytesWritten" => $bytesWritten,
    ]);
}

// ------------------------------------------------------------
// GET index.php?route=sets
// Return the JustTCG response without updating the file.
// ------------------------------------------------------------

sendJson($data);