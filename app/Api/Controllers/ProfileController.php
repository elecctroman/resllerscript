<?php declare(strict_types=1);

namespace App\Api\Controllers;

final class ProfileController
{
    public function show(): void
    {
        $token = authenticate_token();
        require_scope($token, 'read');

        json_response(array(
            'success' => true,
            'data' => array(
                'user' => array(
                    'id' => (int) $token['user_id'],
                    'name' => $token['name'],
                    'email' => $token['email'],
                    'balance' => isset($token['balance']) ? (float) $token['balance'] : 0.0,
                    'role' => isset($token['role']) ? $token['role'] : 'reseller',
                    'status' => isset($token['status']) ? $token['status'] : 'active',
                    'created_at' => isset($token['user_created_at']) ? $token['user_created_at'] : null,
                    'updated_at' => isset($token['user_updated_at']) ? $token['user_updated_at'] : null,
                ),
                'token' => array(
                    'id' => isset($token['id']) ? (int) $token['id'] : null,
                    'label' => isset($token['label']) ? $token['label'] : null,
                    'scopes' => isset($token['scopes']) ? $token['scopes'] : '',
                    'webhook_url' => isset($token['webhook_url']) ? $token['webhook_url'] : null,
                    'last_used_at' => isset($token['last_used_at']) ? $token['last_used_at'] : null,
                ),
            ),
        ));
    }
}
