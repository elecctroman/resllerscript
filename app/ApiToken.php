<?php

namespace App;

use App\Database;
class ApiToken
{
    /**
     * Locate an API token row with the owning user.
     *
     * @param string $token
     * @return array|null
     */
    public static function findActiveToken($token)
    {
        if ($token === '') {
            return null;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT t.id AS token_id, t.user_id, t.token, t.label, t.webhook_url, t.created_at AS token_created_at, t.last_used_at, u.id AS user_id, u.name, u.email, u.balance, u.role, u.status, u.created_at, u.updated_at FROM api_tokens t INNER JOIN users u ON t.user_id = u.id WHERE t.token = :token AND u.status = :status LIMIT 1');
        $stmt->execute(array(
            'token' => $token,
            'status' => 'active',
        ));
        $row = $stmt->fetch();

        if ($row) {
            $pdo->prepare('UPDATE api_tokens SET last_used_at = NOW() WHERE id = :id')->execute(array('id' => $row['token_id']));
            return array(
                'id' => (int)$row['token_id'],
                'user_id' => (int)$row['user_id'],
                'token' => $row['token'],
                'label' => isset($row['label']) ? $row['label'] : null,
                'webhook_url' => isset($row['webhook_url']) ? $row['webhook_url'] : null,
                'created_at' => isset($row['token_created_at']) ? $row['token_created_at'] : null,
                'last_used_at' => isset($row['last_used_at']) ? $row['last_used_at'] : null,
                'name' => isset($row['name']) ? $row['name'] : null,
                'email' => isset($row['email']) ? $row['email'] : null,
                'balance' => isset($row['balance']) ? (float)$row['balance'] : 0.0,
                'role' => isset($row['role']) ? $row['role'] : 'reseller',
                'status' => isset($row['status']) ? $row['status'] : 'active',
                'user_created_at' => isset($row['created_at']) ? $row['created_at'] : null,
                'user_updated_at' => isset($row['updated_at']) ? $row['updated_at'] : null,
            );
        }

        return null;
    }

    /**
     * Issue a new API token for a user.
     *
     * @param int $userId
     * @param string $label
     * @return array{token:string,id:int}
     */
    public static function issueToken($userId, $label = 'WooCommerce Entegrasyonu')
    {
        $plain = bin2hex(random_bytes(16));
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO api_tokens (user_id, token, label, created_at) VALUES (:user_id, :token, :label, NOW())');
        $stmt->execute(array(
            'user_id' => $userId,
            'token' => $plain,
            'label' => $label,
        ));

        return array(
            'token' => $plain,
            'id' => (int)$pdo->lastInsertId(),
            'user_id' => $userId,
            'label' => $label,
            'webhook_url' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'last_used_at' => null,
        );
    }

    /**
     * Delete previous tokens for the user and issue a new one.
     *
     * @param int $userId
     * @return array{token:string,id:int}
     */
    public static function regenerateForUser($userId)
    {
        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM api_tokens WHERE user_id = :user_id')->execute(array('user_id' => $userId));

        return self::issueToken($userId);
    }

    /**
     * Update the webhook URL for a token.
     *
     * @param int $tokenId
     * @param string|null $webhookUrl
     * @return void
     */
    public static function updateWebhook($tokenId, $webhookUrl)
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE api_tokens SET webhook_url = :url WHERE id = :id');
        $stmt->execute(array(
            'url' => $webhookUrl,
            'id' => $tokenId,
        ));
    }

    /**
     * Notify the webhook assigned to an API token.
     *
     * @param int $tokenId
     * @param array $payload
     * @return void
     */
    public static function notifyWebhook($tokenId, array $payload)
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT webhook_url, token FROM api_tokens WHERE id = :id LIMIT 1');
        $stmt->execute(array('id' => $tokenId));
        $tokenRow = $stmt->fetch();

        if (!$tokenRow) {
            return;
        }

        $webhookUrl = isset($tokenRow['webhook_url']) ? trim($tokenRow['webhook_url']) : '';
        if ($webhookUrl === '') {
            return;
        }

        $options = array(
            'http' => array(
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAuthorization: Bearer " . $tokenRow['token'],
                'timeout' => 10,
                'content' => json_encode($payload),
            ),
        );

        @file_get_contents($webhookUrl, false, stream_context_create($options));
    }

    /**
     * Retrieve or lazily create an API token for a user.
     *
     * @param int $userId
     * @return array|null
     */
    public static function getOrCreateForUser($userId)
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM api_tokens WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 1');
        $stmt->execute(array('user_id' => $userId));
        $existing = $stmt->fetch();

        if ($existing) {
            return array(
                'id' => (int)$existing['id'],
                'user_id' => (int)$existing['user_id'],
                'token' => $existing['token'],
                'label' => isset($existing['label']) ? $existing['label'] : null,
                'webhook_url' => isset($existing['webhook_url']) ? $existing['webhook_url'] : null,
                'created_at' => isset($existing['created_at']) ? $existing['created_at'] : null,
                'last_used_at' => isset($existing['last_used_at']) ? $existing['last_used_at'] : null,
            );
        }

        $issued = self::issueToken($userId);
        return $issued;
    }
}
