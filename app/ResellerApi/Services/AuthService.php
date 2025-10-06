<?php declare(strict_types=1);

namespace App\ResellerApi\Services;

use App\Auth;
use App\Database;
use App\ResellerApi\Exceptions\ApiException;
use App\ResellerApi\Repositories\ResellerRepository;
use PDO;

final class AuthService
{
    private ResellerRepository $resellers;
    private BearerTokenService $tokens;
    private PDO $pdo;

    public function __construct(ResellerRepository $resellers, BearerTokenService $tokens)
    {
        $this->resellers = $resellers;
        $this->tokens = $tokens;
        $this->pdo = Database::connection();
    }

    public function login(string $email, string $password): array
    {
        $reseller = $this->resellers->findByEmail($email);
        if (!$reseller) {
            throw ApiException::unauthorized('Geçersiz kullanıcı bilgileri.');
        }
        if ($reseller['status'] !== 'active') {
            throw ApiException::forbidden('Hesabınız askıya alınmış durumda.');
        }
        if (!password_verify($password, $reseller['password_hash'])) {
            throw ApiException::unauthorized('Geçersiz kullanıcı bilgileri.');
        }

        $token = $this->tokens->issueToken((int) $reseller['id']);
        return array(
            'success' => true,
            'token' => $token,
            'reseller' => array(
                'id' => (int) $reseller['id'],
                'name' => $reseller['name'],
                'email' => $reseller['email'],
            ),
        );
    }

    public function ensureResellerUser(int $resellerId): array
    {
        $reseller = $this->resellers->findById($resellerId);
        if (!$reseller) {
            throw ApiException::unauthorized('Reseller bulunamadı.');
        }

        if (!empty($reseller['user_id'])) {
            $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
            $stmt->execute(array('id' => $reseller['user_id']));
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                return $user;
            }
        }

        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(array('email' => $reseller['email']));
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $this->pdo->prepare('UPDATE resellers SET user_id = :user_id WHERE id = :id')->execute(array('user_id' => $user['id'], 'id' => $resellerId));
            return $user;
        }

        $userId = Auth::createUser($reseller['name'], $reseller['email'], bin2hex(random_bytes(8)), 'reseller', 0, array('status' => $reseller['status'] === 'active' ? 'active' : 'inactive'));
        $stmt = $this->pdo->prepare('UPDATE resellers SET user_id = :user_id WHERE id = :id');
        $stmt->execute(array('user_id' => $userId, 'id' => $resellerId));

        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(array('id' => $userId));
        $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: array();

        return $user;
    }
}
