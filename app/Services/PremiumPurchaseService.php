<?php

namespace App\Services;

use App\Database;
use App\Models\PremiumModule;
use App\Models\UserPurchase;
use RuntimeException;

class PremiumPurchaseService
{
    /**
     * @param int    $userId
     * @param int    $moduleId
     * @param string $method
     * @return array{success:bool,message:string,errors:array<int,string>,purchase_id:int|null}
     */
    public function purchase($userId, $moduleId, $method): array
    {
        $module = PremiumModule::find($moduleId);
        if (!$module || (int)$module['status'] !== 1) {
            return array('success' => false, 'message' => '', 'errors' => array('Modül bulunamadı veya pasif.'), 'purchase_id' => null);
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $existing = UserPurchase::findByUserAndModule($userId, $moduleId);
            if ($existing && $existing['payment_status'] === 'paid') {
                $pdo->rollBack();
                return array('success' => false, 'message' => '', 'errors' => array('Bu modül zaten hesabınızda aktif.'), 'purchase_id' => (int) $existing['id']);
            }

            $status = 'pending';
            $licenseKey = null;
            $message = 'Ödeme bekleniyor.';
            $price = (float) $module['price'];

            if ($method === 'balance') {
                $userStmt = $pdo->prepare('SELECT id, balance FROM users WHERE id = :id FOR UPDATE');
                $userStmt->execute(array('id' => (int) $userId));
                $user = $userStmt->fetch(PDO::FETCH_ASSOC);

                if (!$user) {
                    throw new RuntimeException('Kullanıcı bulunamadı.');
                }

                if ((float) $user['balance'] < $price) {
                    $pdo->rollBack();
                    return array('success' => false, 'message' => '', 'errors' => array('Bakiyeniz yetersiz.'), 'purchase_id' => null);
                }

                $newBalance = (float) $user['balance'] - $price;
                $pdo->prepare('UPDATE users SET balance = :balance WHERE id = :id')->execute(array(
                    'balance' => $newBalance,
                    'id' => (int) $userId,
                ));

                $status = 'paid';
                $licenseKey = $this->generateLicenseKey();
                $message = 'Modül başarıyla aktifleştirildi. İndirme bağlantısı e-postanıza da gönderildi.';
            } elseif ($method === 'bank_transfer') {
                $message = 'Havale bildiriminden sonra yönetici onayı ile modülünüz aktifleştirilecektir.';
            } else {
                $pdo->rollBack();
                return array('success' => false, 'message' => '', 'errors' => array('Geçersiz ödeme yöntemi.'), 'purchase_id' => null);
            }

            $purchaseId = UserPurchase::create(array(
                'user_id' => $userId,
                'module_id' => $moduleId,
                'payment_status' => $status,
                'license_key' => $licenseKey,
            ));

            if ($status === 'paid') {
                $this->afterPayment($purchaseId, $module, $userId, $licenseKey);
            }

            $pdo->commit();

            return array(
                'success' => true,
                'message' => $message,
                'errors' => array(),
                'purchase_id' => $purchaseId,
            );
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return array('success' => false, 'message' => '', 'errors' => array($exception->getMessage()), 'purchase_id' => null);
        }
    }

    /**
     * @param int $purchaseId
     * @param string|null $licenseKey
     * @return void
     */
    public function markAsPaid($purchaseId, $licenseKey = null)
    {
        $purchase = UserPurchase::find($purchaseId);
        if (!$purchase) {
            throw new RuntimeException('Satın alma kaydı bulunamadı.');
        }

        $module = PremiumModule::find((int) $purchase['module_id']);
        if (!$module) {
            throw new RuntimeException('Modül bulunamadı.');
        }

        $license = $licenseKey ?: ($purchase['license_key'] ?: $this->generateLicenseKey());

        UserPurchase::updateStatus($purchaseId, 'paid');
        UserPurchase::setLicenseKey($purchaseId, $license);

        $this->afterPayment($purchaseId, $module, (int) $purchase['user_id'], $license);
    }

    /**
     * @param int $purchaseId
     * @param int $userId
     * @return string
     */
    public function generateDownloadLink($purchaseId, $userId): string
    {
        $purchase = UserPurchase::find($purchaseId);
        if (!$purchase || (int)$purchase['user_id'] !== (int)$userId || $purchase['payment_status'] !== 'paid') {
            throw new RuntimeException('Geçersiz satın alma kaydı.');
        }

        if (empty($purchase['license_key'])) {
            $license = $this->generateLicenseKey();
            UserPurchase::setLicenseKey($purchaseId, $license);
            $purchase['license_key'] = $license;
        }

        $expires = time() + 600;
        $signature = $this->signature($purchaseId, $expires, (string) $purchase['license_key']);

        return sprintf('/premium-module-download.php?purchase=%d&expires=%d&signature=%s', $purchaseId, $expires, urlencode($signature));
    }

    /**
     * @param int    $purchaseId
     * @param int    $expires
     * @param string $signature
     * @return array<string,mixed>
     */
    public function validateDownloadRequest($purchaseId, $expires, $signature)
    {
        $purchase = UserPurchase::find($purchaseId);
        if (!$purchase || $purchase['payment_status'] !== 'paid') {
            throw new RuntimeException('Geçersiz indirme isteği.');
        }

        if ($expires < time()) {
            throw new RuntimeException('İndirme bağlantısının süresi doldu.');
        }

        $expected = $this->signature($purchaseId, $expires, (string) $purchase['license_key']);
        if (!hash_equals($expected, (string) $signature)) {
            throw new RuntimeException('İndirme imzası doğrulanamadı.');
        }

        $module = PremiumModule::find((int) $purchase['module_id']);
        if (!$module) {
            throw new RuntimeException('Modül bulunamadı.');
        }

        return array('purchase' => $purchase, 'module' => $module);
    }

    /**
     * @return string
     */
    private function generateLicenseKey(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * @param int    $purchaseId
     * @param array<string,mixed> $module
     * @param int    $userId
     * @param string $licenseKey
     * @return void
     */
    private function afterPayment($purchaseId, array $module, $userId, $licenseKey)
    {
        // Placeholder for future automation hooks (Telegram enablement, etc.)
        // Burada lisans anahtarı ve modül bilgileri e-posta ya da Telegram üzerinden iletilebilir.
        // Şimdilik yalnızca kayıt güncellenir ve ilerideki sürümler için hook noktası oluşturulur.
    }

    /**
     * @param int    $purchaseId
     * @param int    $expires
     * @param string $license
     * @return string
     */
    private function signature($purchaseId, $expires, $license): string
    {
        $payload = $purchaseId . '|' . $expires . '|' . $license;
        return hash_hmac('sha256', $payload, $license);
    }
}
