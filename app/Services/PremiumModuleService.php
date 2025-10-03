<?php

namespace App\Services;

use App\Models\PremiumModule;

class PremiumModuleService
{
    /**
     * @param array<string,string> $input
     * @param array<string,mixed>  $fileInfo
     * @return array{success:bool,errors:array<int,string>,module_id:int|null}
     */
    public function create(array $input, array $fileInfo): array
    {
        $errors = array();
        $name = isset($input['name']) ? trim($input['name']) : '';
        $description = isset($input['description']) ? trim($input['description']) : '';
        $price = isset($input['price']) ? (float) $input['price'] : 0.0;
        $status = isset($input['status']) && (int) $input['status'] === 1 ? 1 : 0;

        if ($name === '') {
            $errors[] = 'Modül adı gereklidir.';
        }

        if ($description === '') {
            $errors[] = 'Modül açıklaması gereklidir.';
        }

        if (!is_numeric(isset($input['price']) ? $input['price'] : null) || $price < 0) {
            $errors[] = 'Geçerli bir fiyat giriniz.';
        }

        if (!isset($fileInfo['tmp_name']) || !is_uploaded_file((string) $fileInfo['tmp_name'])) {
            $errors[] = 'ZIP dosyası yüklenmelidir.';
        } elseif (!isset($fileInfo['name']) || strtolower(pathinfo((string) $fileInfo['name'], PATHINFO_EXTENSION)) !== 'zip') {
            $errors[] = 'Yalnızca ZIP dosyaları yüklenebilir.';
        }

        if ($errors) {
            return array('success' => false, 'errors' => $errors, 'module_id' => null);
        }

        $directory = dirname(__DIR__, 1) . '/../storage/modules';
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            $errors[] = 'Modül klasörü oluşturulamadı.';
            return array('success' => false, 'errors' => $errors, 'module_id' => null);
        }

        $originalName = (string) $fileInfo['name'];
        $safeName = preg_replace('/[^a-zA-Z0-9_\.-]+/', '-', strtolower($originalName));
        $targetName = uniqid('module_', true) . '-' . $safeName;
        $targetPath = rtrim($directory, '/\\') . '/' . $targetName;

        if (!move_uploaded_file((string) $fileInfo['tmp_name'], $targetPath)) {
            $errors[] = 'Dosya yükleme işlemi başarısız oldu.';
            return array('success' => false, 'errors' => $errors, 'module_id' => null);
        }

        $moduleId = PremiumModule::create(array(
            'name' => $name,
            'description' => $description,
            'price' => $price,
            'file_path' => $targetPath,
            'status' => $status,
        ));

        return array('success' => true, 'errors' => array(), 'module_id' => $moduleId);
    }

    /**
     * @param int  $moduleId
     * @param bool $active
     * @return bool
     */
    public function setStatus($moduleId, $active)
    {
        return PremiumModule::setStatus($moduleId, $active);
    }
}
