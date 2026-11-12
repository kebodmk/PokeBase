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
    'save_card' => 'POST',
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
// POST index.php?route=save_card
//
// Database-only route. Does not call JustTCG.
// Uses the same table fields and database method as your helpers.
// ------------------------------------------------------------

if ($routePart === 'save_card') {
    $body = file_get_contents('php://input');

    if ($body === false || trim($body) === '') {
        sendJson([
            'success' => false,
            'error' => 'A JSON request body is required.',
        ], 400);
    }

    // Decode as an object to distinguish JSON objects from arrays.
    $inputObject = json_decode($body);

    if (
        json_last_error() !== JSON_ERROR_NONE
        || !($inputObject instanceof stdClass)
    ) {
        sendJson([
            'success' => false,
            'error' => 'A valid JSON object is required.',
        ], 400);
    }

    $input = (array) $inputObject;
    $fields = [];
    $errors = [];

    $requiredTextFields = [
        'name',
        'set',
        'set_num',
        'lang',
    ];

    $optionalTextFields = [
        'condition',
        'printing',
        'grade',
    ];

    $maxTextLength = 45;

    foreach ($requiredTextFields as $field) {
        if (
            !array_key_exists($field, $input)
            || !is_string($input[$field])
        ) {
            $errors[] = $field . ' is required and must be text.';
            continue;
        }

        $value = trim($input[$field]);

        if ($value === '') {
            $errors[] = $field . ' cannot be empty.';
        } elseif (strlen($value) > $maxTextLength) {
            $errors[] = $field . ' must be '
                . $maxTextLength . ' characters or fewer.';
        } else {
            $fields[$field] = $value;
        }
    }

    foreach ($optionalTextFields as $field) {
        $value = $input[$field] ?? null;

        if ($value === null) {
            $fields[$field] = null;
            continue;
        }

        if (!is_string($value)) {
            $errors[] = $field . ' must be text or null.';
            continue;
        }

        $value = trim($value);

        if (strlen($value) > $maxTextLength) {
            $errors[] = $field . ' must be '
                . $maxTextLength . ' characters or fewer.';
        } else {
            $fields[$field] = $value !== '' ? $value : null;
        }
    }

    $price = $input['price'] ?? null;

    if (
        !is_int($price)
        && !is_float($price)
        && !is_string($price)
    ) {
        $errors[] = 'price is required and must be a number.';
    } elseif (
        !is_numeric($price)
        || !is_finite((float) $price)
        || (float) $price < 0
    ) {
        $errors[] = 'price must be a finite non-negative number.';
    } else {
        $fields['price'] = (float) $price;
    }

    $priceDate = $input['price_date'] ?? null;

    if (
        !is_string($priceDate)
        || !preg_match(
            '/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/',
            $priceDate,
            $dateParts
        )
        || !checkdate(
            (int) $dateParts[2],
            (int) $dateParts[3],
            (int) $dateParts[1]
        )
    ) {
        $errors[] = 'price_date must be a valid YYYY-MM-DD date.';
    } else {
        $fields['price_date'] = $priceDate;
    }

    if ($errors) {
        sendJson([
            'success' => false,
            'error' => implode(' ', $errors),
            'errors' => $errors,
        ], 422);
    }

    if (!class_exists('PokeDatabase')) {
    sendJson([
        'success' => false,
        'error' =>
            'PokeDatabase is not loaded. '
            . 'Load the database class or its autoloader.',
    ], 500);
}

if (!is_callable(['PokeDatabase', 'executeSQL'])) {
    sendJson([
        'success' => false,
        'error' =>
            'PokeDatabase exists, but executeSQL cannot be '
            . 'called as a public static method.',
    ], 500);
}

    try {
        $sql = 'INSERT INTO cards
            (
                `name`,
                `set`,
                set_num,
                lang,
                price,
                price_date,
                `condition`,
                printing,
                grade
            )
            VALUES
            (
                :name,
                :set,
                :set_num,
                :lang,
                :price,
                :price_date,
                :condition,
                :printing,
                :grade
            )';

        $params = [
            ':name' => $fields['name'],
            ':set' => $fields['set'],
            ':set_num' => $fields['set_num'],
            ':lang' => $fields['lang'],
            ':price' => $fields['price'],
            ':price_date' => $fields['price_date'],
            ':condition' => $fields['condition'],
            ':printing' => $fields['printing'],
            ':grade' => $fields['grade'],
        ];

        $cardId = (int) PokeDatabase::executeSQL(
            $sql,
            $params,
            true
        );

        if ($cardId <= 0) {
            throw new RuntimeException(
                'The database did not return a valid inserted card ID.'
            );
        }

        sendJson([
            'success' => true,
            'message' => 'Card added to database.',
            'card_id' => $cardId,
        ], 201);
    } catch (Throwable $error) {
        error_log(
            'Save card failed: ' . $error->getMessage()
        );

        sendJson([
            'success' => false,
            'error' =>
                'Could not save the card. Check the PHP error log.',
        ], 500);
    }
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

    $languageAliases = [
        'en' => ['en', 'eng', 'english'],
        'jap' => ['ja', 'jp', 'jpn', 'japanese'],
        'ch' => ['zh', 'zh-cn', 'zh-tw', 'chinese'],
        'kor' => ['ko', 'kr', 'kor', 'korean'],
    ];

    $allowedGradingCompanies = [
        'PSA',
        'BGS',
        'CGC',
        'BCCG',
        'BVG',
        'SGC',
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
            || $returnedSetId !== $setId
        ) {
            continue;
        }

        if (
            $setNumber !== ''
            && !cardNumberMatches(
                $setNumber,
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

            if (
                $cardType === 'graded'
                && $gradingCompany !== ''
            ) {
                $grading = $variant['grading'] ?? [];

                $returnedCompany = is_array($grading)
                    ? ($grading['company'] ?? '')
                    : '';

                if (
                    !is_string($returnedCompany)
                    || strtoupper(trim($returnedCompany)) !== $gradingCompany
                ) {
                    continue;
                }
            }

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

    // Return only the fields needed by your results UI.
    sendJson([
        'success' => true,
        'data' => $filteredCards,
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