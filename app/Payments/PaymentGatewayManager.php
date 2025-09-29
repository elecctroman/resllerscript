<?php

namespace App\Payments;

use App\Settings;
use RuntimeException;

class PaymentGatewayManager
{
    /**
     * @return array<string,array{label:string,callback:string}>
     */
    public static function getActiveGateways()
    {
        $gateways = [];

        if (Settings::get('cryptomus_enabled') === '1') {
            $gateways['cryptomus'] = [
                'label' => 'Cryptomus',
                'callback' => '/webhooks/cryptomus.php',
            ];
        }

        if (Settings::get('heleket_enabled') === '1') {
            $gateways['heleket'] = [
                'label' => 'Heleket',
                'callback' => '/webhooks/heleket.php',
            ];
        }

        return $gateways;
    }

    /**
     * @param string $identifier
     * @return string
     */
    public static function getLabel($identifier)
    {
        $gateways = self::getActiveGateways();
        if (isset($gateways[$identifier])) {
            return $gateways[$identifier]['label'];
        }

        switch ($identifier) {
            case 'cryptomus':
                return 'Cryptomus';
            case 'heleket':
                return 'Heleket';
            case 'test-mode':
                return 'Test Modu';
        }

        return ucfirst($identifier);
    }

    /**
     * @param string $identifier
     * @return object
     */
    public static function createGateway($identifier)
    {
        switch ($identifier) {
            case 'cryptomus':
                return new CryptomusClient();
            case 'heleket':
                return new HeleketClient();
        }

        throw new RuntimeException('İstenen ödeme sağlayıcısı desteklenmiyor.');
    }
}
