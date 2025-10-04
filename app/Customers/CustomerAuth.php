<?php

namespace App\Customers;

use App\Database;
use App\Helpers;

class CustomerAuth
{
    /**
     * @var array|null
     */
    private static $cachedCustomer = null;

    public static function attempt(string $email, string $password)
    {
        $customer = CustomerRepository::findByEmail($email);
        if (!$customer) {
            return null;
        }

        if (!password_verify($password, $customer['password'])) {
            return null;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE customers SET last_login_at = NOW() WHERE id = :id');
        $stmt->execute(array(':id' => $customer['id']));

        self::setAuthenticatedCustomerId((int)$customer['id']);

        self::$cachedCustomer = self::sanitize($customer);

        return self::$cachedCustomer;
    }

    public static function register(array $input)
    {
        $existing = CustomerRepository::findByEmail($input['email']);
        if ($existing) {
            throw new \RuntimeException('Bu e-posta adresi zaten kayıtlı.');
        }

        $record = CustomerRepository::create(array(
            'name' => $input['name'],
            'surname' => $input['surname'],
            'email' => $input['email'],
            'phone' => $input['phone'],
            'password' => $input['password'],
            'locale' => isset($input['locale']) ? $input['locale'] : 'tr',
            'currency' => isset($input['currency']) ? $input['currency'] : 'TRY',
        ));

        self::setAuthenticatedCustomerId((int)$record['id']);

        self::$cachedCustomer = self::sanitize($record);

        return self::$cachedCustomer;
    }

    public static function ensureCustomer(): array
    {
        $customer = self::customer();

        if (!$customer) {
            Helpers::redirect('/customer/login.php');
        }

        return $customer;
    }

    public static function requireGuest(): void
    {
        if (self::customer()) {
            Helpers::redirect('/customer/dashboard.php');
        }
    }

    public static function logout(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        unset($_SESSION['customer_id']);
        self::$cachedCustomer = null;

        if (function_exists('session_regenerate_id')) {
            session_regenerate_id(true);
        }
    }

    public static function customer(): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (empty($_SESSION['customer_id'])) {
            return null;
        }

        if (self::$cachedCustomer !== null) {
            return self::$cachedCustomer;
        }

        $record = CustomerRepository::findById((int)$_SESSION['customer_id']);
        if (!$record) {
            self::logout();
            return null;
        }

        self::$cachedCustomer = self::sanitize($record);

        return self::$cachedCustomer;
    }

    public static function refresh(): array
    {
        self::$cachedCustomer = null;

        return self::ensureCustomer();
    }

    private static function setAuthenticatedCustomerId(int $customerId): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION['customer_id'] = $customerId;
        self::$cachedCustomer = null;

        if (function_exists('session_regenerate_id')) {
            session_regenerate_id(true);
        }
    }

    private static function sanitize(array $customer): array
    {
        if (isset($customer['password'])) {
            unset($customer['password']);
        }

        return $customer;
    }
}
