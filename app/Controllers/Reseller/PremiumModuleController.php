<?php

namespace App\Controllers\Reseller;

use App\Models\PremiumModule;
use App\Models\UserPurchase;
use App\Services\PremiumPurchaseService;

class PremiumModuleController
{
    /**
     * @param int $userId
     * @return array<int,array<string,mixed>>
     */
    public function availableForUser($userId): array
    {
        $modules = PremiumModule::active();
        $purchases = UserPurchase::forUser($userId);
        $indexed = array();

        foreach ($purchases as $purchase) {
            $indexed[(int) $purchase['module_id']] = $purchase;
        }

        foreach ($modules as &$module) {
            $moduleId = (int) $module['id'];
            if (isset($indexed[$moduleId])) {
                $module['purchase'] = $indexed[$moduleId];
            }
        }
        unset($module);

        return $modules;
    }

    /**
     * @param int $userId
     * @param int $moduleId
     * @param string $method
     * @return array{success:bool,message:string,errors:array<int,string>,purchase_id:int|null}
     */
    public function purchase($userId, $moduleId, $method): array
    {
        $service = new PremiumPurchaseService();
        return $service->purchase($userId, $moduleId, $method);
    }

    /**
     * @param int $userId
     * @param int $purchaseId
     * @return string
     */
    public function downloadLink($userId, $purchaseId): string
    {
        $service = new PremiumPurchaseService();
        return $service->generateDownloadLink($purchaseId, $userId);
    }
}
