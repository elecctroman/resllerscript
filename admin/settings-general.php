<?php
require __DIR__ . '/../bootstrap.php';

use App\Auth;
use App\AuditLog;
use App\FeatureToggle;
use App\Helpers;
use App\Services\ProductImageService;
use App\Settings;

$currentUser = Auth::requireAdmin(array('super_admin', 'admin'));
$errors = array();
$warnings = array();
$success = '';

$current = Settings::getMany(array(
    'site_name',
    'site_tagline',
    'seo_meta_description',
    'seo_meta_keywords',
    'site_logo',
    'pricing_commission_rate',
    'reseller_auto_suspend_enabled',
    'reseller_auto_suspend_threshold',
    'reseller_auto_suspend_days',
    'platform_default_locale',
    'google_oauth_client_id',
    'google_oauth_client_secret',
    'ai_image_enabled',
    'ai_api_key',
    'ai_prompt',
    'ai_image_template',
));

$existingLogoSetting = isset($current['site_logo']) ? $current['site_logo'] : null;
$currentLogoUrl = $existingLogoSetting ? '/' . ltrim((string) $existingLogoSetting, '/') : '';

$existingAiTemplateSetting = isset($current['ai_image_template']) ? $current['ai_image_template'] : null;
$currentAiTemplateUrl = $existingAiTemplateSetting ? '/' . ltrim((string) $existingAiTemplateSetting, '/') : '';

$googleRedirectUri = Helpers::url('oauth/google.php', true);

$featureLabels = array(
    'products' => 'Ürün kataloğu ve sipariş verme',
    'orders' => 'Sipariş geçmişi görüntüleme',
    'balance' => 'Bakiye yönetimi',
    'support' => 'Destek talepleri',
    'packages' => 'Bayilik paketleri başvurusu',
    'premium_modules' => 'Premium modül pazarı',
);

$featureStates = FeatureToggle::all();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!Helpers::verifyCsrf($token)) {
        $errors[] = 'Oturum anahtarınız doğrulanamadı. Lütfen sayfayı yenileyin ve tekrar deneyin.';
    } else {
        $runTestImage = isset($_POST['test_ai_image']);
        $siteName = isset($_POST['site_name']) ? trim($_POST['site_name']) : '';
        $siteTagline = isset($_POST['site_tagline']) ? trim($_POST['site_tagline']) : '';
        $metaDescription = isset($_POST['seo_meta_description']) ? trim($_POST['seo_meta_description']) : '';
            $metaKeywords = isset($_POST['seo_meta_keywords']) ? trim($_POST['seo_meta_keywords']) : '';
            $commissionInput = isset($_POST['pricing_commission_rate']) ? str_replace(',', '.', trim($_POST['pricing_commission_rate'])) : '0';
            $commissionRate = (float)$commissionInput;
            if ($commissionRate < 0) {
                $commissionRate = 0.0;
            }

            $defaultLocale = isset($_POST['platform_default_locale']) ? strtolower((string)$_POST['platform_default_locale']) : 'tr';
            $availableLocales = App\Lang::availableLocales();
            if (!in_array($defaultLocale, $availableLocales, true)) {
                $defaultLocale = 'tr';
            }

            $autoSuspendEnabled = isset($_POST['reseller_auto_suspend_enabled']) ? '1' : '0';
            $autoThresholdInput = isset($_POST['reseller_auto_suspend_threshold']) ? str_replace(',', '.', trim($_POST['reseller_auto_suspend_threshold'])) : '0';
            $autoThreshold = (float)$autoThresholdInput;
            $autoDays = isset($_POST['reseller_auto_suspend_days']) ? (int)$_POST['reseller_auto_suspend_days'] : 0;

            if ($siteName === '') {
                $errors[] = 'Site adı zorunludur.';
            }

            if ($autoSuspendEnabled === '1') {
                if ($autoThreshold <= 0) {
                    $errors[] = 'Otomatik pasife alma için minimum bakiye değeri pozitif olmalıdır.';
                }
                if ($autoDays <= 0) {
                    $errors[] = 'Otomatik pasife alma için gün sayısı pozitif olmalıdır.';
                }
            }

            $googleClientId = isset($_POST['google_oauth_client_id']) ? trim($_POST['google_oauth_client_id']) : '';
            $googleClientSecret = isset($_POST['google_oauth_client_secret']) ? trim($_POST['google_oauth_client_secret']) : '';

            if ($googleClientId !== '' && $googleClientSecret === '') {
                $errors[] = 'Google Client Secret alanı boş bırakılamaz.';
            }

            if ($googleClientSecret !== '' && $googleClientId === '') {
                $errors[] = 'Google Client ID alanı boş bırakılamaz.';
            }

            $newLogoSetting = $existingLogoSetting;

            $aiEnabled = isset($_POST['ai_image_enabled']) ? '1' : '0';
            $aiApiKey = isset($_POST['ai_api_key']) ? trim($_POST['ai_api_key']) : '';
            $aiPrompt = isset($_POST['ai_prompt']) ? trim($_POST['ai_prompt']) : '';
            $aiTemplateSetting = $existingAiTemplateSetting;
            $aiTemplateFile = isset($_FILES['ai_image_template']) ? $_FILES['ai_image_template'] : null;
            $removeAiTemplate = isset($_POST['remove_ai_image_template']);
            $aiTemplateUploadInfo = null;

            $logoFile = isset($_FILES['site_logo']) ? $_FILES['site_logo'] : null;
            $removeLogo = isset($_POST['remove_site_logo']);
            $logoUploadInfo = null;

            if (is_array($logoFile) && isset($logoFile['error']) && (int)$logoFile['error'] !== UPLOAD_ERR_NO_FILE) {
                if ((int)$logoFile['error'] !== UPLOAD_ERR_OK) {
                    $errors[] = 'Site logosu yüklenirken bir hata oluştu. Lütfen dosyayı kontrol ederek tekrar deneyin.';
                } elseif (!isset($logoFile['tmp_name']) || !is_uploaded_file((string) $logoFile['tmp_name'])) {
                    $errors[] = 'Site logosu yüklemesi doğrulanamadı. Lütfen tekrar deneyin.';
                } else {
                    $tmpName = (string) $logoFile['tmp_name'];
                    $detectedMime = '';

                    if (class_exists('finfo')) {
                        $finfo = new \finfo(FILEINFO_MIME_TYPE);
                        if ($finfo !== false) {
                            $mimeType = $finfo->file($tmpName);
                            if (is_string($mimeType) && $mimeType !== '') {
                                $detectedMime = $mimeType;
                            }
                        }
                    }

                    if ($detectedMime === '' && function_exists('mime_content_type')) {
                        $mimeCandidate = @mime_content_type($tmpName);
                        if (is_string($mimeCandidate) && $mimeCandidate !== '') {
                            $detectedMime = $mimeCandidate;
                        }
                    }

                    if ($detectedMime === '' && function_exists('getimagesize')) {
                        $imageSize = @getimagesize($tmpName);
                        if ($imageSize && isset($imageSize['mime']) && is_string($imageSize['mime'])) {
                            $detectedMime = $imageSize['mime'];
                        }
                    }

                    $allowedMimes = array(
                        'image/png' => 'png',
                        'image/jpeg' => 'jpg',
                        'image/jpg' => 'jpg',
                        'image/pjpeg' => 'jpg',
                        'image/svg+xml' => 'svg',
                        'image/webp' => 'webp',
                    );

                    $extension = '';
                    if ($detectedMime !== '' && isset($allowedMimes[$detectedMime])) {
                        $extension = $allowedMimes[$detectedMime];
                    } else {
                        $originalExtension = '';
                        if (isset($logoFile['name'])) {
                            $originalExtension = strtolower((string) pathinfo((string) $logoFile['name'], PATHINFO_EXTENSION));
                        }
                        if ($originalExtension === 'jpeg') {
                            $originalExtension = 'jpg';
                        }
                        if (in_array($originalExtension, array('png', 'jpg', 'svg', 'webp'), true)) {
                            $extension = $originalExtension;
                        }
                    }

                    if ($extension === '') {
                        $errors[] = 'Site logosu olarak yalnızca PNG, JPG, SVG veya WebP dosyaları yükleyebilirsiniz.';
                    }

                    $fileSize = isset($logoFile['size']) ? (int) $logoFile['size'] : 0;
                    if ($extension !== '' && $fileSize > 0) {
                        $maxSize = 4 * 1024 * 1024;
                        if ($fileSize > $maxSize) {
                            $errors[] = 'Site logosu 4 MB boyutundan büyük olamaz.';
                        }
                    }

                    if (!$errors) {
                        $originalName = isset($logoFile['name']) ? (string) $logoFile['name'] : 'logo.' . $extension;
                        $baseName = strtolower((string) pathinfo($originalName, PATHINFO_FILENAME));
                        $safeBase = preg_replace('/[^a-z0-9_-]+/i', '-', $baseName);
                        if ($safeBase === null || $safeBase === '') {
                            $safeBase = 'logo';
                        }

                        $logoUploadInfo = array(
                            'tmp_name' => $tmpName,
                            'extension' => $extension,
                            'base' => $safeBase,
                        );
                    }
                }
            }

            if (is_array($aiTemplateFile) && isset($aiTemplateFile['error']) && (int)$aiTemplateFile['error'] !== UPLOAD_ERR_NO_FILE) {
                if ((int)$aiTemplateFile['error'] !== UPLOAD_ERR_OK) {
                    $errors[] = 'Görsel şablonu yüklenirken bir hata oluştu. Lütfen tekrar deneyin.';
                } elseif (!isset($aiTemplateFile['tmp_name']) || !is_uploaded_file((string)$aiTemplateFile['tmp_name'])) {
                    $errors[] = 'Görsel şablonu yüklemesi doğrulanamadı.';
                } else {
                    $templateTmpName = (string)$aiTemplateFile['tmp_name'];
                    $imageInfo = @getimagesize($templateTmpName);
                    if (!$imageInfo || !isset($imageInfo['mime']) || $imageInfo['mime'] !== 'image/png') {
                        $errors[] = 'Şablon olarak yalnızca PNG dosyaları yükleyebilirsiniz.';
                    }

                    $templateSize = isset($aiTemplateFile['size']) ? (int)$aiTemplateFile['size'] : 0;
                    if ($templateSize > 0) {
                        $maxTemplateSize = 6 * 1024 * 1024;
                        if ($templateSize > $maxTemplateSize) {
                            $errors[] = 'Görsel şablon dosyası 6 MB boyutunu aşamaz.';
                        }
                    }

                    if (!$errors) {
                        $originalName = isset($aiTemplateFile['name']) ? (string)$aiTemplateFile['name'] : 'ai-template.png';
                        $baseName = strtolower((string) pathinfo($originalName, PATHINFO_FILENAME));
                        $safeBase = preg_replace('/[^a-z0-9_-]+/i', '-', $baseName);
                        if ($safeBase === null || $safeBase === '') {
                            $safeBase = 'ai-template';
                        }

                        $aiTemplateUploadInfo = array(
                            'tmp_name' => $templateTmpName,
                            'base' => $safeBase,
                        );
                    }
                }
            }

            if (!$errors) {
                if ($logoUploadInfo) {
                    $rootPath = dirname(__DIR__);
                    $uploadDir = $rootPath . '/uploads';
                    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
                        $errors[] = 'Site logosu için yükleme klasörü oluşturulamadı.';
                    } else {
                        try {
                            $randomSuffix = bin2hex(random_bytes(4));
                        } catch (\Throwable $exception) {
                            $randomSuffix = substr(md5(uniqid('', true)), 0, 8);
                        }
                        $fileName = $logoUploadInfo['base'] . '-' . date('YmdHis') . '-' . $randomSuffix . '.' . $logoUploadInfo['extension'];
                        $targetPath = rtrim($uploadDir, '/\\') . '/' . $fileName;

                        if (!move_uploaded_file($logoUploadInfo['tmp_name'], $targetPath)) {
                            $errors[] = 'Site logosu yüklenirken beklenmedik bir hata oluştu.';
                        } else {
                            $newLogoSetting = '/uploads/' . $fileName;
                        }
                    }
                } elseif ($removeLogo) {
                    $newLogoSetting = null;
                }
            }

            if (!$errors) {
                if ($aiTemplateUploadInfo) {
                    $rootPath = dirname(__DIR__);
                    $templateDir = $rootPath . '/uploads/ai-template';
                    if (!is_dir($templateDir) && !mkdir($templateDir, 0775, true) && !is_dir($templateDir)) {
                        $errors[] = 'Görsel şablonu için yükleme klasörü oluşturulamadı.';
                    } else {
                        try {
                            $randomSuffix = bin2hex(random_bytes(4));
                        } catch (\Throwable $exception) {
                            $randomSuffix = substr(md5(uniqid('', true)), 0, 8);
                        }
                        $fileName = $aiTemplateUploadInfo['base'] . '-' . date('YmdHis') . '-' . $randomSuffix . '.png';
                        $targetPath = rtrim($templateDir, '/\\') . '/' . $fileName;

                        if (!move_uploaded_file($aiTemplateUploadInfo['tmp_name'], $targetPath)) {
                            $errors[] = 'Görsel şablon kaydedilirken hata oluştu.';
                        } else {
                            $aiTemplateSetting = '/uploads/ai-template/' . $fileName;
                        }
                    }
                } elseif ($removeAiTemplate && $existingAiTemplateSetting) {
                    $aiTemplateSetting = null;
                }
            }

            if (!$errors) {
                Settings::set('site_name', $siteName);
                Settings::set('site_tagline', $siteTagline !== '' ? $siteTagline : null);
                Settings::set('seo_meta_description', $metaDescription !== '' ? $metaDescription : null);
                Settings::set('seo_meta_keywords', $metaKeywords !== '' ? $metaKeywords : null);
                Settings::set('pricing_commission_rate', (string)$commissionRate);

                if ($newLogoSetting !== $existingLogoSetting) {
                    Settings::set('site_logo', $newLogoSetting !== null ? $newLogoSetting : null);

                    if ($existingLogoSetting && $existingLogoSetting !== $newLogoSetting) {
                        $rootPath = dirname(__DIR__);
                        $previousPath = $rootPath . '/' . ltrim((string) $existingLogoSetting, '/\\');
                        $uploadsBase = rtrim($rootPath . '/uploads', '/\\') . DIRECTORY_SEPARATOR;
                        $realPrevious = @realpath($previousPath);
                        if ($realPrevious && strpos($realPrevious, $uploadsBase) === 0 && is_file($realPrevious)) {
                            @unlink($realPrevious);
                        }
                    }

                    $existingLogoSetting = $newLogoSetting;
                }

                Settings::set('platform_default_locale', $defaultLocale);
                foreach ($featureLabels as $key => $label) {
                    $enabled = isset($_POST['features'][$key]);
                    FeatureToggle::setEnabled($key, $enabled);
                    $featureStates[$key] = $enabled;
                }

                Settings::set('reseller_auto_suspend_enabled', $autoSuspendEnabled);
                if ($autoSuspendEnabled === '1') {
                    Settings::set('reseller_auto_suspend_threshold', number_format($autoThreshold, 2, '.', ''));
                    Settings::set('reseller_auto_suspend_days', (string)$autoDays);
                } else {
                    Settings::set('reseller_auto_suspend_threshold', null);
                    Settings::set('reseller_auto_suspend_days', null);
                }

                Settings::set('google_oauth_client_id', $googleClientId !== '' ? $googleClientId : null);
                Settings::set('google_oauth_client_secret', $googleClientSecret !== '' ? $googleClientSecret : null);

                Settings::set('ai_image_enabled', $aiEnabled);
                Settings::set('ai_api_key', $aiApiKey !== '' ? $aiApiKey : null);
                Settings::set('ai_prompt', $aiPrompt !== '' ? $aiPrompt : null);
                if ($aiTemplateSetting !== $existingAiTemplateSetting) {
                    Settings::set('ai_image_template', $aiTemplateSetting !== null ? $aiTemplateSetting : null);

                    if ($existingAiTemplateSetting && $existingAiTemplateSetting !== $aiTemplateSetting) {
                        $rootPath = dirname(__DIR__);
                        $previousTemplate = $rootPath . '/' . ltrim((string)$existingAiTemplateSetting, '/\\');
                        $templateBase = rtrim($rootPath . '/uploads/ai-template', '/\\') . DIRECTORY_SEPARATOR;
                        $realPrev = @realpath($previousTemplate);
                        if ($realPrev && strpos($realPrev, $templateBase) === 0 && is_file($realPrev)) {
                            @unlink($realPrev);
                        }
                    }

                    $existingAiTemplateSetting = $aiTemplateSetting;
                }

                $success = 'Genel ayarlar kaydedildi.';
                AuditLog::record(
                    $currentUser['id'],
                    'settings.general.update',
                    'settings',
                    null,
                    'Genel ayarlar güncellendi'
                );

                $current = Settings::getMany(array(
                    'site_name',
                    'site_tagline',
                    'seo_meta_description',
                    'seo_meta_keywords',
                    'site_logo',
                    'pricing_commission_rate',
                    'reseller_auto_suspend_enabled',
                    'reseller_auto_suspend_threshold',
                    'reseller_auto_suspend_days',
                    'platform_default_locale',
                    'google_oauth_client_id',
                    'google_oauth_client_secret',
                    'ai_image_enabled',
                    'ai_api_key',
                    'ai_prompt',
                    'ai_image_template',
                ));
                $existingLogoSetting = isset($current['site_logo']) ? $current['site_logo'] : null;
                $currentLogoUrl = $existingLogoSetting ? '/' . ltrim((string) $existingLogoSetting, '/') : '';
                $existingAiTemplateSetting = isset($current['ai_image_template']) ? $current['ai_image_template'] : null;
                $currentAiTemplateUrl = $existingAiTemplateSetting ? '/' . ltrim((string) $existingAiTemplateSetting, '/') : '';

                if ($runTestImage) {
                    if (isset($current['ai_image_enabled']) && $current['ai_image_enabled'] === '1') {
                        $testResult = ProductImageService::runTestGeneration();
                        if (!empty($testResult['success'])) {
                            $success .= ' Yapay zekâ test görseli başarıyla üretildi.';
                        } elseif (isset($testResult['message'])) {
                            $warnings[] = 'Test görseli üretilemedi: ' . $testResult['message'];
                        } else {
                            $warnings[] = 'Test görseli üretilemedi. Lütfen ayarları kontrol edin.';
                        }
                    } else {
                        $warnings[] = 'Test görseli oluşturmak için yapay zekâ görsel oluşturmayı etkinleştirin.';
                    }
                }
            }
    }
}

$pageTitle = 'Genel Ayarlar';

include __DIR__ . '/../templates/header.php';
?>
<div class="row justify-content-center g-4">
    <div class="col-12 col-xl-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0">Site Bilgileri</h5>
            </div>
            <div class="card-body">
                <?php if ($errors): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= Helpers::sanitize($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($warnings): ?>
                    <div class="alert alert-warning">
                        <ul class="mb-0">
                            <?php foreach ($warnings as $warning): ?>
                                <li><?= Helpers::sanitize($warning) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?= Helpers::sanitize($success) ?></div>
                <?php endif; ?>

                <form method="post" enctype="multipart/form-data" class="vstack gap-4">
                    <input type="hidden" name="action" value="save_general">
                    <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Site Adı</label>
                            <input type="text" name="site_name" class="form-control" value="<?= Helpers::sanitize(isset($current['site_name']) ? $current['site_name'] : Helpers::siteName()) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Site Sloganı</label>
                            <input type="text" name="site_tagline" class="form-control" value="<?= Helpers::sanitize(isset($current['site_tagline']) ? $current['site_tagline'] : '') ?>" placeholder="Opsiyonel">
                        </div>
                        <div class="col-12">
                            <label class="form-label">SEO Açıklaması</label>
                            <textarea name="seo_meta_description" class="form-control" rows="3" placeholder="Arama motorları için kısa açıklama"><?= Helpers::sanitize(isset($current['seo_meta_description']) ? $current['seo_meta_description'] : '') ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">SEO Anahtar Kelimeler</label>
                            <input type="text" name="seo_meta_keywords" class="form-control" value="<?= Helpers::sanitize(isset($current['seo_meta_keywords']) ? $current['seo_meta_keywords'] : '') ?>" placeholder="Virgülle ayırın">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Site Logosu</label>
                            <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-3">
                                <?php if ($currentLogoUrl): ?>
                                    <div class="d-flex align-items-center gap-3">
                                        <img src="<?= Helpers::sanitize($currentLogoUrl) ?>" alt="<?= Helpers::sanitize('Site Logosu') ?>" class="border rounded bg-white p-2" style="max-height: 60px;">
                                        <div class="form-check mb-0">
                                            <input class="form-check-input" type="checkbox" value="1" name="remove_site_logo" id="removeSiteLogo">
                                            <label class="form-check-label small text-muted" for="removeSiteLogo"><?= Helpers::sanitize('Mevcut logoyu kaldır') ?></label>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <div class="flex-grow-1">
                                    <input type="file" name="site_logo" class="form-control" accept=".png,.jpg,.jpeg,.svg,.webp">
                                    <small class="text-muted d-block mt-2"><?= Helpers::sanitize('PNG, JPG, SVG veya WebP formatında en fazla 4 MB boyutunda logo yükleyebilirsiniz.') ?></small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="row g-3 align-items-center">
                        <div class="col-md-4">
                            <label class="form-label">Ürün Satış Komisyonu (%)</label>
                            <input type="number" name="pricing_commission_rate" step="0.01" min="0" class="form-control" value="<?= Helpers::sanitize(isset($current['pricing_commission_rate']) ? $current['pricing_commission_rate'] : '0') ?>">
                        </div>
                    </div>

                    <div class="row g-3 mt-3">
                        <div class="col-md-6">
                            <label class="form-label">Varsayılan Platform Dili</label>
                            <select name="platform_default_locale" class="form-select">
                                <?php foreach (App\Lang::availableLocales() as $locale): ?>
                                    <option value="<?= Helpers::sanitize($locale) ?>" <?= isset($current['platform_default_locale']) && $current['platform_default_locale'] === $locale ? 'selected' : '' ?>><?= strtoupper(Helpers::sanitize($locale)) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Bayi profili aksi seçmedikçe bu dil kullanılır.</small>
                        </div>
                    </div>

                    <hr>

                    <div>
                        <h6>Google ile Giriş</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Client ID</label>
                                <input type="text" name="google_oauth_client_id" class="form-control" value="<?= Helpers::sanitize(isset($current['google_oauth_client_id']) ? $current['google_oauth_client_id'] : '') ?>" placeholder="xxxx.apps.googleusercontent.com">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Client Secret</label>
                                <input type="text" name="google_oauth_client_secret" class="form-control" value="<?= Helpers::sanitize(isset($current['google_oauth_client_secret']) ? $current['google_oauth_client_secret'] : '') ?>" placeholder="Google secret değeri">
                            </div>
                            <div class="col-12">
                                <small class="text-muted">Google Cloud Console &gt; Credentials üzerinden OAuth 2.0 kimlik bilgileri oluşturun. Yetkili yönlendirme URI'si: <code><?= Helpers::sanitize($googleRedirectUri) ?></code></small>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div>
                        <h6>Görsel Oluşturucu</h6>
                        <div class="form-check form-switch mb-3">
                            <input type="hidden" name="ai_image_enabled" value="0">
                            <input class="form-check-input" type="checkbox" id="aiImageEnabled" name="ai_image_enabled" value="1" <?= isset($current['ai_image_enabled']) && $current['ai_image_enabled'] === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="aiImageEnabled">Yapay zekâ görsel oluşturmayı etkinleştir</label>
                        </div>
                        <div class="row g-3 align-items-end">
                            <div class="col-md-6">
                                <label class="form-label">OpenAI API Key</label>
                                <input type="text" name="ai_api_key" class="form-control" value="<?= Helpers::sanitize(isset($current['ai_api_key']) ? $current['ai_api_key'] : '') ?>" placeholder="sk-...">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Varsayılan Prompt</label>
                                <textarea name="ai_prompt" class="form-control" rows="3" placeholder="Örn: Modern, yüksek çözünürlüklü ürün görseli"><?= Helpers::sanitize(isset($current['ai_prompt']) ? $current['ai_prompt'] : '') ?></textarea>
                                <small class="text-muted">Ürün adı, süre ve kategori bu prompta otomatik eklenir.</small>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Görsel Şablonu (PNG)</label>
                                <?php if ($currentAiTemplateUrl): ?>
                                    <div class="d-flex flex-column flex-sm-row align-items-sm-center gap-3 mb-2">
                                        <img src="<?= Helpers::sanitize($currentAiTemplateUrl) ?>" alt="Görsel şablonu" class="rounded border bg-white" style="max-height: 80px;">
                                        <div class="form-check mb-0">
                                            <input class="form-check-input" type="checkbox" name="remove_ai_image_template" value="1" id="removeAiTemplate">
                                            <label class="form-check-label" for="removeAiTemplate">Mevcut şablonu kaldır</label>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <input type="file" name="ai_image_template" class="form-control" accept="image/png">
                                <small class="text-muted d-block mt-1">Saydam arka planlı PNG yükleyin. Yapay zekâ bu şablonu düzenleyerek ürün görseli üretir.</small>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div>
                        <h6>Özellik Yönetimi</h6>
                        <div class="row g-3">
                            <?php foreach ($featureLabels as $key => $label): ?>
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="feature<?= Helpers::sanitize($key) ?>" name="features[<?= Helpers::sanitize($key) ?>]" <?= !empty($featureStates[$key]) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="feature<?= Helpers::sanitize($key) ?>"><?= Helpers::sanitize($label) ?></label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <hr>

                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="autoSuspend" name="reseller_auto_suspend_enabled" <?= isset($current['reseller_auto_suspend_enabled']) && $current['reseller_auto_suspend_enabled'] === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="autoSuspend">Düşük bakiyede bayiliği pasife al</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Minimum Bakiye (₺)</label>
                            <input type="number" step="0.01" min="0" name="reseller_auto_suspend_threshold" class="form-control" value="<?= Helpers::sanitize(isset($current['reseller_auto_suspend_threshold']) ? $current['reseller_auto_suspend_threshold'] : '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Pasife Alma Süresi (gün)</label>
                            <input type="number" min="0" name="reseller_auto_suspend_days" class="form-control" value="<?= Helpers::sanitize(isset($current['reseller_auto_suspend_days']) ? $current['reseller_auto_suspend_days'] : '') ?>">
                        </div>
                        <div class="col-12">
                            <small class="text-muted">Belirlenen tutarın altına düşen bayiler bu süre sonunda otomatik olarak pasif duruma geçirilir.</small>
                        </div>
                    </div>

                <div class="d-flex flex-column flex-sm-row justify-content-end gap-2">
                    <button type="submit" class="btn btn-primary">Ayarları Kaydet</button>
                    <button type="submit" name="test_ai_image" value="1" class="btn btn-outline-secondary">Test Görseli Üret</button>
                </div>
            </form>
        </div>
    </div>

    </div>
</div>
<?php include __DIR__ . '/../templates/footer.php';
