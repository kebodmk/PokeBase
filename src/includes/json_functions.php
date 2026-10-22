<?PHP
function sendJson(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body);
    exit;
}

function getJsonBody(): array
{
    $raw = file_get_contents('php://input');

    if ($raw === '' || $raw === false) {
        return [];
    }

    $data = json_decode($raw, true);

    if (!is_array($data)) {
        sendJson(400, [
            'success' => false,
            'message' => 'Request body must contain valid JSON.'
        ]);
    }

    return $data;
}
?>