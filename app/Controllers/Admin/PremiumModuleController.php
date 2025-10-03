<?php

namespace App\Controllers\Admin;

use App\Helpers;
use App\Models\PremiumModule;
use App\Models\UserPurchase;
use App\Services\PremiumModuleService;
use App\Services\PremiumPurchaseService;
use RuntimeException;

class PremiumModuleController
{
    /**
     * @return array<string,mixed>
     */
    public function index(): array
    {
        return array(
            'modules' => PremiumModule::all(),
            'errors' => Helpers::getFlash('errors', array()),
            'success' => Helpers::getFlash('success', ''),
        );
    }

    /**
     * @param array<string,mixed> $post
     * @param array<string,mixed> $files
     * @return void
     */
    public function store(array $post, array $files): void
    {
        $service = new PremiumModuleService();
        $result = $service->create($post, isset($files['module_file']) ? $files['module_file'] : array());

        if (!$result['success']) {
            Helpers::redirectWithFlash('/admin/premium-modules.php', array('errors' => $result['errors']));
        }

        Helpers::redirectWithFlash('/admin/premium-modules.php', array('success' => 'Modül başarıyla eklendi.'));
    }

    /**
     * @param int  $moduleId
     * @param bool $active
     * @return void
     */
    public function updateStatus($moduleId, $active): void
    {
        $service = new PremiumModuleService();
        $service->setStatus($moduleId, $active);
        Helpers::redirectWithFlash('/admin/premium-modules.php', array('success' => 'Modül durumu güncellendi.'));
    }

    /**
     * @return array<string,mixed>
     */
    public function purchases(): array
    {
        return array(
            'purchases' => UserPurchase::allWithUsers(),
            'errors' => Helpers::getFlash('errors', array()),
            'success' => Helpers::getFlash('success', ''),
        );
    }

    /**
     * @param int $purchaseId
     * @return void
     */
    public function markPurchasePaid($purchaseId): void
    {
        try {
            $service = new PremiumPurchaseService();
            $service->markAsPaid($purchaseId);
            Helpers::redirectWithFlash('/admin/premium-module-purchases.php', array('success' => 'Satın alma kaydı aktifleştirildi.'));
        } catch (RuntimeException $exception) {
            Helpers::redirectWithFlash('/admin/premium-module-purchases.php', array('errors' => array($exception->getMessage())));
        }
    }
}
