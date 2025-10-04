<?php

namespace App\Customers;

use App\Database;
use App\Helpers;
use PDO;

class CustomerAuth
{
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

        unset($customer['password']);
        return $customer;
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
        if (isset($record['password'])) {
            unset($record['password']);
        }
        return $record;
    }

    public static function ensureCustomer(): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (empty($_SESSION['customer'])) {
            Helpers::redirect('/customer/login.php');
        }

        return $_SESSION['customer'];
    }

    public static function requireGuest(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (!empty($_SESSION['customer'])) {
            Helpers::redirect('/customer/dashboard.php');
        }
    }
}
