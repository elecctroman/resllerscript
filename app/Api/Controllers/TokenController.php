<?php declare(strict_types=1);

namespace App\Api\Controllers;

use App\ApiToken;

final class TokenController
{
    public function updateWebhook(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(array('success' => false, 'error' => 'Yalnızca POST isteklerine izin verilir.'), 405);
        }

        $token = authenticate_token();
        require_scope($token, 'manage');

        $payload = read_json_body();
        $webhookUrl = isset($payload['webhook_url']) ? trim((string) $payload['webhook_url']) : '';

        if ($webhookUrl !== '' && !filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
            json_response(array('success' => false, 'error' => 'Geçerli bir webhook adresi giriniz.'), 422);
        }

        ApiToken::updateWebhook((int) $token['id'], $webhookUrl !== '' ? $webhookUrl : null);

        json_response(array(
            'success' => true,
            'data' => array('webhook_url' => $webhookUrl),
        ));
    }
}
