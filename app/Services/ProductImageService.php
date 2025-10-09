<?php

namespace App\Services;

use App\Settings;

class ProductImageService
{
    private const PRODUCT_DIRECTORY = '/uploads/products';
    private const TEMPLATE_DIRECTORY = '/uploads/ai-template';
    private const LOG_FILE = '/logs/ai-image.log';

    /**
     * @param array|null $file
     * @return array{status:string,message?:string,data?:array}
     */
    public static function validateManualUpload($file): array
    {
        if (!is_array($file) || !isset($file['error'])) {
            return array('status' => 'empty');
        }

        $errorCode = (int) $file['error'];
        if ($errorCode === UPLOAD_ERR_NO_FILE) {
            return array('status' => 'empty');
        }

        if ($errorCode !== UPLOAD_ERR_OK) {
            return array('status' => 'error', 'message' => 'Görsel yüklenirken bir hata oluştu (kod ' . $errorCode . ').');
        }

        if (!isset($file['tmp_name']) || !is_uploaded_file((string) $file['tmp_name'])) {
            return array('status' => 'error', 'message' => 'Yüklenen dosya doğrulanamadı.');
        }

        $tmpName = (string) $file['tmp_name'];
        $fileSize = isset($file['size']) ? (int) $file['size'] : 0;
        if ($fileSize <= 0) {
            return array('status' => 'error', 'message' => 'Görsel dosyası boş görünüyor.');
        }

        $maxSize = 6 * 1024 * 1024; // 6 MB
        if ($fileSize > $maxSize) {
            return array('status' => 'error', 'message' => 'Görsel dosyası 6 MB boyutunu aşamaz.');
        }

        $detectedMime = self::detectMimeType($file, $tmpName);
        $allowedMimes = array(
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/pjpeg' => 'jpg',
            'image/webp' => 'webp',
        );

        $extension = '';
        if ($detectedMime !== '' && isset($allowedMimes[$detectedMime])) {
            $extension = $allowedMimes[$detectedMime];
        } else {
            $originalExtension = isset($file['name']) ? strtolower((string) pathinfo((string) $file['name'], PATHINFO_EXTENSION)) : '';
            if ($originalExtension === 'jpeg') {
                $originalExtension = 'jpg';
            }
            if (in_array($originalExtension, array('png', 'jpg', 'webp'), true)) {
                $extension = $originalExtension;
            }
        }

        if ($extension === '') {
            return array('status' => 'error', 'message' => 'Yalnızca PNG, JPG veya WebP formatındaki görseller yüklenebilir.');
        }

        return array(
            'status' => 'ready',
            'data' => array(
                'tmp_name' => $tmpName,
                'extension' => $extension,
                'original_name' => isset($file['name']) ? (string) $file['name'] : ('product.' . $extension),
            ),
        );
    }

    /**
     * @param array $uploadData
     * @return array{success:bool,message?:string,path?:string}
     */
    public static function storeManualUpload(array $uploadData): array
    {
        if (!isset($uploadData['tmp_name'], $uploadData['extension'], $uploadData['original_name'])) {
            return array('success' => false, 'message' => 'Görsel yükleme bilgileri eksik.');
        }

        $targetDirectory = self::absolutePath(self::PRODUCT_DIRECTORY);
        if (!self::ensureDirectory($targetDirectory)) {
            return array('success' => false, 'message' => 'Görsel klasörü oluşturulamadı.');
        }

        $safeBase = strtolower((string) pathinfo((string) $uploadData['original_name'], PATHINFO_FILENAME));
        $safeBase = preg_replace('/[^a-z0-9_-]+/i', '-', $safeBase);
        if ($safeBase === null || $safeBase === '') {
            $safeBase = 'product-image';
        }

        try {
            $randomSuffix = bin2hex(random_bytes(6));
        } catch (\Throwable $exception) {
            $randomSuffix = substr(md5(uniqid('', true)), 0, 12);
        }

        $fileName = $safeBase . '-' . date('YmdHis') . '-' . $randomSuffix . '.' . $uploadData['extension'];
        $destination = rtrim($targetDirectory, '/\\') . '/' . $fileName;

        if (!move_uploaded_file((string) $uploadData['tmp_name'], $destination)) {
            return array('success' => false, 'message' => 'Görsel kaydedilirken hata oluştu.');
        }

        return array(
            'success' => true,
            'path' => self::relativeWebPath(self::PRODUCT_DIRECTORY . '/' . $fileName),
        );
    }

    /**
     * @param int $productId
     * @param array<string,mixed> $productData
     * @return array{success:bool,message?:string,path?:string}
     */
    public static function maybeGenerateAiImage(int $productId, array $productData): array
    {
        $enabled = Settings::get('ai_image_enabled');
        if (!$enabled || (string) $enabled !== '1') {
            return array('success' => false, 'message' => 'Yapay zekâ görsel oluşturma devre dışı.');
        }

        $apiKey = (string) Settings::get('ai_api_key');
        $promptBase = (string) Settings::get('ai_prompt');
        $templateSetting = (string) Settings::get('ai_image_template');

        if ($apiKey === '') {
            self::log('Ürün #' . $productId . ' için API anahtarı tanımlı değil.');
            return array('success' => false, 'message' => 'API anahtarı bulunamadı.');
        }

        if ($templateSetting === '') {
            self::log('Ürün #' . $productId . ' için görsel şablonu ayarlanmamış.');
            return array('success' => false, 'message' => 'Şablon görsel yüklenmemiş.');
        }

        $templatePath = self::absolutePath($templateSetting);
        if (!is_file($templatePath)) {
            self::log('Ürün #' . $productId . ' için şablon dosyası bulunamadı: ' . $templatePath);
            return array('success' => false, 'message' => 'Şablon dosyası bulunamadı.');
        }

        $promptParts = array();
        if ($promptBase !== '') {
            $promptParts[] = $promptBase;
        }

        $name = isset($productData['name']) ? (string) $productData['name'] : '';
        $duration = isset($productData['duration']) ? (string) $productData['duration'] : '';
        $category = isset($productData['category']) ? (string) $productData['category'] : '';

        if ($name !== '') {
            $promptParts[] = 'Ürün adı: ' . $name;
        }
        if ($duration !== '') {
            $promptParts[] = 'Süre: ' . $duration;
        }
        if ($category !== '') {
            $promptParts[] = 'Kategori: ' . $category;
        }

        $prompt = trim(implode(' \n', $promptParts));
        if ($prompt === '') {
            $prompt = 'Profesyonel yazılım ürün görseli oluştur';
        }

        $ch = curl_init('https://api.openai.com/v1/images/edits');
        if (!$ch) {
            self::log('Ürün #' . $productId . ' için cURL başlatılamadı.');
            return array('success' => false, 'message' => 'cURL başlatılamadı.');
        }

        if (function_exists('curl_file_create')) {
            $cfile = curl_file_create($templatePath, 'image/png', basename($templatePath));
        } else {
            $cfile = new \CURLFile($templatePath, 'image/png', basename($templatePath));
        }
        $postFields = array(
            'model' => 'gpt-image-1',
            'prompt' => $prompt,
            'image' => $cfile,
        );

        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . $apiKey,
            ),
        ));

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            self::log('Ürün #' . $productId . ' için cURL hatası: ' . $curlError);
            return array('success' => false, 'message' => 'API isteği başarısız: ' . ($curlError !== '' ? $curlError : 'Bilinmeyen hata'));
        }

        $decoded = json_decode((string) $response, true);
        if (!is_array($decoded)) {
            self::log('Ürün #' . $productId . ' için API yanıtı çözümlenemedi: ' . $response);
            return array('success' => false, 'message' => 'API yanıtı çözümlenemedi.');
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            $message = isset($decoded['error']['message']) ? (string) $decoded['error']['message'] : 'Bilinmeyen API hatası.';
            self::log(sprintf('Ürün #%d için API hata yanıtı (%d): %s', $productId, $statusCode, $message));
            return array('success' => false, 'message' => 'API hatası: ' . $message);
        }

        if (empty($decoded['data'][0]['b64_json'])) {
            self::log('Ürün #' . $productId . ' için API verisi eksik: ' . json_encode($decoded));
            return array('success' => false, 'message' => 'API yanıtında görsel verisi bulunamadı.');
        }

        $imageData = base64_decode((string) $decoded['data'][0]['b64_json']);
        if ($imageData === false) {
            self::log('Ürün #' . $productId . ' için base64 çözme başarısız.');
            return array('success' => false, 'message' => 'Görsel verisi işlenemedi.');
        }

        $targetDirectory = self::absolutePath(self::PRODUCT_DIRECTORY);
        if (!self::ensureDirectory($targetDirectory)) {
            return array('success' => false, 'message' => 'Görsel klasörü oluşturulamadı.');
        }

        try {
            $randomSuffix = bin2hex(random_bytes(8));
        } catch (\Throwable $exception) {
            $randomSuffix = substr(md5(uniqid('', true)), 0, 16);
        }

        $fileName = 'ai-product-' . $productId . '-' . date('YmdHis') . '-' . $randomSuffix . '.png';
        $destination = rtrim($targetDirectory, '/\\') . '/' . $fileName;

        if (file_put_contents($destination, $imageData) === false) {
            self::log('Ürün #' . $productId . ' için görsel kaydedilemedi: ' . $destination);
            return array('success' => false, 'message' => 'Görsel kaydedilemedi.');
        }

        self::log('Ürün #' . $productId . ' için yapay zekâ görseli üretildi.', array('prompt' => $prompt));

        return array(
            'success' => true,
            'path' => self::relativeWebPath(self::PRODUCT_DIRECTORY . '/' . $fileName),
        );
    }

    /**
     * @param string|null $path
     * @return void
     */
    public static function deleteImage($path): void
    {
        if (!$path) {
            return;
        }

        $absolute = self::absolutePath($path);
        $uploadsBase = rtrim(self::absolutePath(self::PRODUCT_DIRECTORY), '/\\') . DIRECTORY_SEPARATOR;
        $realPath = @realpath($absolute);
        if ($realPath && strpos($realPath, $uploadsBase) === 0 && is_file($realPath)) {
            @unlink($realPath);
        }
    }

    /**
     * @return array{success:bool,message?:string,path?:string}
     */
    public static function runTestGeneration(): array
    {
        $dummyProduct = array(
            'name' => 'Test Yazılım Paketi',
            'duration' => '12 Ay Lisans',
            'category' => 'Test Kategorisi',
        );

        $result = self::maybeGenerateAiImage(0, $dummyProduct);
        if ($result['success']) {
            self::log('Test görsel üretimi tamamlandı: ' . $result['path']);
        }

        return $result;
    }

    /**
     * @param array $file
     * @param string $tmpName
     * @return string
     */
    private static function detectMimeType(array $file, string $tmpName): string
    {
        $candidates = array();

        if (isset($file['type']) && is_string($file['type']) && $file['type'] !== '') {
            $candidates[] = (string) $file['type'];
        }

        if (class_exists('finfo')) {
            $finfo = @new \finfo(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $detected = $finfo->file($tmpName);
                if (is_string($detected) && $detected !== '') {
                    $candidates[] = $detected;
                }
            }
        }

        if (function_exists('mime_content_type')) {
            $detected = @mime_content_type($tmpName);
            if (is_string($detected) && $detected !== '') {
                $candidates[] = $detected;
            }
        }

        if (function_exists('getimagesize')) {
            $info = @getimagesize($tmpName);
            if ($info && isset($info['mime']) && is_string($info['mime']) && $info['mime'] !== '') {
                $candidates[] = $info['mime'];
            }
        }

        foreach ($candidates as $candidate) {
            $normalized = strtolower(trim((string) $candidate));
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return '';
    }

    /**
     * @param string $path
     * @return bool
     */
    private static function ensureDirectory(string $path): bool
    {
        if (is_dir($path)) {
            return true;
        }

        return @mkdir($path, 0775, true) || is_dir($path);
    }

    /**
     * @param string $path
     * @return string
     */
    private static function absolutePath(string $path): string
    {
        $root = dirname(__DIR__, 2);
        if ($path === '') {
            return $root;
        }

        if ($path[0] === DIRECTORY_SEPARATOR || $path[0] === '/') {
            return $root . $path;
        }

        return $root . '/' . ltrim($path, '/');
    }

    /**
     * @param string $path
     * @return string
     */
    private static function relativeWebPath(string $path): string
    {
        if ($path === '') {
            return '';
        }

        return '/' . ltrim($path, '/');
    }

    /**
     * @param string $message
     * @param array<string,mixed> $context
     * @return void
     */
    private static function log(string $message, array $context = array()): void
    {
        $logPath = self::absolutePath(self::LOG_FILE);
        $logDir = dirname($logPath);
        if (!self::ensureDirectory($logDir)) {
            return;
        }

        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message;
        if ($context) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        @file_put_contents($logPath, $line . PHP_EOL, FILE_APPEND);
    }
}
