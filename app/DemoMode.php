<?php

namespace App;

use PDO;

class DemoMode
{
    private const DEMO_NAME = 'demo';
    private const DEMO_EMAIL = 'demo@demo.com';
    private const DEMO_PASSWORD = 'demo123!';

    /**
     * @return bool
     */
    public static function isEnabled()
    {
        return Settings::get('demo_mode_enabled') === '1';
    }

    /**
     * Ensure the demo user exists and matches the expected credentials when demo mode is active.
     *
     * @return void
     */
    public static function ensureUser()
    {
        if (!self::isEnabled()) {
            self::disableUser();

            return;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id, name, email, status, password_hash FROM users WHERE role = :role LIMIT 1');
        $stmt->execute(array('role' => 'demo'));
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            Auth::createUser(self::DEMO_NAME, self::DEMO_EMAIL, self::DEMO_PASSWORD, 'demo', 0);

            return;
        }

        $fields = array();
        $params = array('id' => (int)$user['id']);

        if (!isset($user['name']) || $user['name'] !== self::DEMO_NAME) {
            $fields[] = 'name = :name';
            $params['name'] = self::DEMO_NAME;
        }

        if (!isset($user['email']) || $user['email'] !== self::DEMO_EMAIL) {
            $fields[] = 'email = :email';
            $params['email'] = self::DEMO_EMAIL;
        }

        if (!isset($user['status']) || $user['status'] !== 'active') {
            $fields[] = "status = 'active'";
        }

        if (!isset($user['password_hash']) || !password_verify(self::DEMO_PASSWORD, $user['password_hash'])) {
            $fields[] = 'password_hash = :password_hash';
            $params['password_hash'] = password_hash(self::DEMO_PASSWORD, PASSWORD_BCRYPT);
        }

        if ($fields) {
            $fields[] = 'updated_at = NOW()';
            $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id';
            $update = $pdo->prepare($sql);
            $update->execute($params);
        }
    }

    /**
     * Disable demo users when the feature is turned off.
     *
     * @return void
     */
    public static function disableUser()
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare("UPDATE users SET status = 'inactive', updated_at = NOW() WHERE role = :role");
        $stmt->execute(array('role' => 'demo'));
    }

    /**
     * Guard demo sessions against state-changing requests.
     *
     * @param array $user
     * @return void
     */
    public static function guard(array $user)
    {
        if (!isset($user['role']) || $user['role'] !== 'demo') {
            return;
        }

        if (!self::isEnabled()) {
            unset($_SESSION['user']);
            $_SESSION['flash_warning'] = 'Demo modu devre dışı bırakıldığı için oturum sonlandırıldı.';
            Helpers::redirect('/index.php');
        }

        $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
        if (in_array($method, array('GET', 'HEAD', 'OPTIONS'), true)) {
            return;
        }

        $targetPath = null;

        if (!empty($_SERVER['HTTP_REFERER'])) {
            $refererPath = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH);
            if (is_string($refererPath) && $refererPath !== '') {
                $targetPath = $refererPath;
            }
        }

        if (!$targetPath && !empty($_SERVER['REQUEST_URI'])) {
            $targetPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        }

        $redirectPath = Helpers::normalizeRedirectPath($targetPath, '/dashboard.php');
        Helpers::setFlash('errors', array('Demo hesabı ile değişiklik yapılamaz.'));
        Helpers::redirect($redirectPath);
    }
}
