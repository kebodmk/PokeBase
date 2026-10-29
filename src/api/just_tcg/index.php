<?php

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . "/../../includes/config.php";

// These aren't needed here unless this endpoint uses them:
// require_once __DIR__ . "/../../includes/PokeDatabase.php";
// require_once __DIR__ . "/../../includes/json_functions.php";

function sendJson($data, $status = 200)
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// Only allow GET requests.
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    sendJson(["error" => "Method not allowed."], 405);
}

// Find the route after /just_tcg/.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$parts = explode('/', trim($path, '/'));

$apiIndex = array_search('just_tcg', $parts, true);
$routePart = $apiIndex !== false
    ? ($parts[$apiIndex + 1] ?? '')
    : '';

$expectedMethod = $routePart === 'update_sets' ? 'POST' : 'GET';

if ($method !== $expectedMethod) {
    header('Allow: ' . $expectedMethod);
    sendJson(["error" => "Method not allowed."], 405);
}

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

        if ($routePart === 'update_sets') {
            // Here you would implement the logic to update the sets in your database.
            // For now, we'll just return a success message.
            $sets = $data["data"] ?? null;
            if (!is_array($sets)) {
                sendJson(["error" => "Invalid data received from JustTCG."], 502);
            }

            $contents = json_encode($sets, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($contents === false) {
                sendJson(["error" => "Failed to encode sets data."], 500);
            }

            $filePath = __DIR__ . '/../../data/just_tcg_sets.json';
            $written = file_put_contents($filePath, $contents . PHP_EOL, LOCK_EX);
            if ($written === false) {
                sendJson(["error" => "Failed to write sets data to file."], 500);
            }

            sendJson(["success" => true, "message" => "Sets updated successfully.", "count" => count($sets)]);
        }
        break;

    default:
        sendJson(["error" => "Endpoint not found."], 404);
}

$apiKey = getenv('JUSTTCG_API_KEY');

if (!$apiKey) {
    sendJson(["error" => "API key is not configured."], 500);
}

$url = "https://api.justtcg.com/v1/"
    . $endpoint
    . "?"
    . http_build_query($params);

$curl = curl_init($url);

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
    error_log("JustTCG error: " . curl_error($curl));
    curl_close($curl);

    sendJson(["error" => "Could not connect to JustTCG."], 502);
}

curl_close($curl);

if ($statusCode < 200 || $statusCode >= 300) {
    sendJson([
        "error" => "JustTCG request failed.",
        "upstreamStatus" => $statusCode,
    ], 502);
}

$data = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    sendJson(["error" => "JustTCG returned invalid JSON."], 502);
}

if ($routePart === 'get_price') {
    $price = $data["data"][0]["variants"][0]["price"] ?? null;

    if ($price === null) {
        sendJson(["error" => "No price found."], 404);
    }

    sendJson(["price" => $price]);
}

// Return the sets response.
sendJson($data);