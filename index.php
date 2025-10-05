<?php
session_start();

$autoloader = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloader)) {
    require_once $autoloader;
}

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/app/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

$configPath = __DIR__ . '/config/config.php';

if (!file_exists($configPath)) {

    App\Helpers::includeTemplate('auth-header.php');
    ?>
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="text-center mb-4">
                <div class="brand">Bayi Yönetim Sistemi</div>
                <p class="text-muted mt-2">Kuruluma başlamadan önce yapılandırmayı tamamlayın</p>
            </div>
            <div class="alert alert-warning">
                <h5 class="alert-heading">Yapılandırma Gerekli</h5>
                <p class="mb-2">Lütfen <code>config/config.sample.php</code> dosyasını <code>config/config.php</code> olarak
                    kopyalayın ve MySQL bağlantı bilgilerinizi girin.</p>
                <ol class="mb-0 text-start">
                    <li><code>config/config.sample.php</code> dosyasını kopyalayın.</li>
                    <li>Yeni dosyada <code>DB_HOST</code>, <code>DB_NAME</code>, <code>DB_USER</code> ve <code>DB_PASSWORD</code>
                        değerlerini güncelleyin.</li>
                    <li>Veritabanınızı oluşturup <code>schema.sql</code> dosyasındaki tabloları içeri aktarın.</li>
                    <li>Ardından bu sayfayı yenileyerek giriş ekranına ulaşın.</li>
                </ol>
            </div>
        </div>
    </div>
    <?php
    App\Helpers::includeTemplate('auth-footer.php');
    exit;
}

require $configPath;

use App\Auth;
use App\Blog\BlogRepository;
use App\Helpers;
use App\Lang;
use App\Settings;

try {
    App\Database::initialize([
        'host' => DB_HOST,
        'name' => DB_NAME,
        'user' => DB_USER,
        'password' => DB_PASSWORD,
    ]);
} catch (\PDOException $exception) {
    App\Helpers::includeTemplate('auth-header.php');
    ?>
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="text-center mb-4">
                <div class="brand">Bayi Yönetim Sistemi</div>
                <p class="text-muted mt-2">Veritabanı bağlantısı kurulamadı</p>
            </div>
            <div class="alert alert-danger">
                <h5 class="alert-heading">Bağlantı Hatası</h5>
                <p class="mb-2">Lütfen <code>config/config.php</code> dosyanızdaki MySQL bilgilerini kontrol edin ve veritabanı sunucunuzu doğrulayın.</p>
                <p class="mb-0 small text-muted">Hata detayı: <?= Helpers::sanitize($exception->getMessage()) ?></p>
            </div>
        </div>
    </div>
    <?php
    App\Helpers::includeTemplate('auth-footer.php');
    exit;
}

Lang::boot();

$siteName = Helpers::siteName();
$siteTagline = Helpers::siteTagline();

if (!empty($_SESSION['user'])) {
    $redirectTarget = Auth::isAdminRole($_SESSION['user']['role']) ? '/admin/dashboard.php' : '/dashboard.php';
    Helpers::redirect($redirectTarget);
}

$flashSuccess = isset($_SESSION['flash_success']) ? $_SESSION['flash_success'] : null;
$flashWarning = isset($_SESSION['flash_warning']) ? $_SESSION['flash_warning'] : null;
unset($_SESSION['flash_success'], $_SESSION['flash_warning']);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Helpers::verifyCsrf(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
        $errors[] = 'Güvenlik doğrulaması başarısız oldu. Lütfen tekrar deneyin.';
    } else {
        $identifier = isset($_POST['email']) ? trim($_POST['email']) : '';
        $password = isset($_POST['password']) ? (string)$_POST['password'] : '';

        if ($identifier === '' || $password === '') {
            $errors[] = 'Lütfen kullanıcı adı/e-posta ve şifre alanlarını doldurun.';
        } else {
            $user = Auth::attempt($identifier, $password);
            if ($user) {
                $_SESSION['user'] = $user;
                $preferredLanguage = Settings::get('user_' . $user['id'] . '_preferred_language');
                if ($preferredLanguage) {
                    Lang::setLocale($preferredLanguage);
                } else {
                    Lang::boot();
                }
                $redirectTarget = Auth::isAdminRole($user['role']) ? '/admin/dashboard.php' : '/dashboard.php';
                Helpers::redirect($redirectTarget);
            } else {
                $errors[] = 'Bilgileriniz doğrulanamadı. Lütfen tekrar deneyin.';
            }
        }
    }
}

try {
    $pdo = App\Database::connection();
} catch (\PDOException $exception) {
    $pdo = null;
}

$topCategories = array();
if ($pdo instanceof \PDO) {
    try {
        $categoryStmt = $pdo->query(
            "SELECT id, name, description FROM categories WHERE parent_id IS NULL ORDER BY created_at DESC LIMIT 6"
        );
        if ($categoryStmt) {
            $topCategories = $categoryStmt->fetchAll(\PDO::FETCH_ASSOC) ?: array();
        }
    } catch (\PDOException $exception) {
        $topCategories = array();
    }
}

$featuredProducts = array();
if ($pdo instanceof \PDO) {
    try {
        $productStmt = $pdo->query(
            "SELECT p.id, p.name, p.price, p.description, p.created_at,"
            . " c.name AS category_name"
            . " FROM products AS p"
            . " LEFT JOIN categories AS c ON c.id = p.category_id"
            . " WHERE p.status = 'active'"
            . " ORDER BY p.updated_at DESC, p.created_at DESC"
            . " LIMIT 12"
        );
        if ($productStmt) {
            $featuredProducts = $productStmt->fetchAll(\PDO::FETCH_ASSOC) ?: array();
        }
    } catch (\PDOException $exception) {
        $featuredProducts = array();
    }
}

$blogHighlights = array();
try {
    $blogHighlights = BlogRepository::latestPosts(3);
} catch (\Throwable $exception) {
    $blogHighlights = array();
}

$metrics = array(
    'orders' => 0,
    'revenue' => 0.0,
    'products' => 0,
    'resellers' => 0,
);

if ($pdo instanceof \PDO) {
    try {
        $metrics['orders'] = (int) $pdo->query("SELECT COUNT(*) FROM product_orders")->fetchColumn();
    } catch (\PDOException $exception) {
        $metrics['orders'] = 0;
    }

    try {
        $metrics['revenue'] = (float) $pdo->query("SELECT COALESCE(SUM(price), 0) FROM product_orders")->fetchColumn();
    } catch (\PDOException $exception) {
        $metrics['revenue'] = 0.0;
    }

    try {
        $metrics['products'] = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn();
    } catch (\PDOException $exception) {
        $metrics['products'] = 0;
    }

    try {
        $metrics['resellers'] = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'reseller' AND status = 'active'")->fetchColumn();
    } catch (\PDOException $exception) {
        $metrics['resellers'] = 0;
    }
}

$pageTitle = 'Ana Sayfa';
$metaDescription = 'Reseller automation platformu ile E-Pin, lisans ve hesap satışlarınızı tek panelden yönetin.';
$brandUrl = '/';
$navLinks = array(
    array('label' => 'Özellikler', 'url' => '#features'),
    array('label' => 'Ürünler', 'url' => '#featured-products'),
    array('label' => 'Blog', 'url' => '/blog/'),
    array('label' => 'Destek', 'url' => '/support.php'),
    array('label' => 'Bayi Ol', 'url' => '/register.php', 'is_button' => true),
);

Helpers::includeTemplate('public-header.php');
?>
<section class="public-hero-home position-relative overflow-hidden" id="login-card">
    <div class="row align-items-center g-5">
        <div class="col-lg-7 position-relative">
            <span class="public-hero-badge">
                <i class="bi bi-lightning-charge-fill"></i>
                Anlık Teslimat &amp; Otomasyon
            </span>
            <h1 class="mt-4 mb-3"><?= Helpers::sanitize($siteName) ?></h1>
            <p class="lead mb-4">
                <?= Helpers::sanitize($siteTagline ?: 'E-Pin, oyun içi para ve lisans satışlarınızı tek panelden yönetin. Otomatik teslimat, gelişmiş raporlama ve bayi paneli tek pakette.') ?>
            </p>
            <div class="d-flex flex-wrap gap-3">
                <a href="/register.php" class="btn btn-primary btn-lg px-4">Bayi Ol</a>
            </div>
            <div class="public-hero-feature-list">
                <div class="public-hero-feature">
                    <i class="bi bi-shield-check"></i>
                    256-bit SSL ve gelişmiş güvenlik
                </div>
                <div class="public-hero-feature">
                    <i class="bi bi-wallet2"></i>
                    Shopier, Papara, PayTR ve cüzdan entegrasyonu
                </div>
                <div class="public-hero-feature">
                    <i class="bi bi-graph-up"></i>
                    Gerçek zamanlı raporlama ve stok uyarıları
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="public-login-card p-4 p-lg-5">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h5 class="mb-1">Bayi Yönetim Paneli</h5>
                        <p class="text-muted mb-0">Hızlı giriş yapın, siparişleri yönetin.</p>
                    </div>
                </div>
                <?php if ($flashSuccess): ?>
                    <div class="alert alert-success">
                        <?= Helpers::sanitize($flashSuccess) ?>
                    </div>
                <?php endif; ?>
                <?php if ($flashWarning): ?>
                    <div class="alert alert-warning">
                        <?= Helpers::sanitize($flashWarning) ?>
                    </div>
                <?php endif; ?>
                <?php if ($errors): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= Helpers::sanitize($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <form method="post" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= Helpers::sanitize(Helpers::csrfToken()) ?>">
                    <div class="mb-3">
                        <label for="email" class="form-label">E-posta Adresi veya Kullanıcı Adı</label>
                        <input type="text" class="form-control" id="email" name="email" required placeholder="ornek@bayinetwork.com" value="<?= Helpers::sanitize(isset($_POST['email']) ? $_POST['email'] : '') ?>">
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Şifre</label>
                        <input type="password" class="form-control" id="password" name="password" required placeholder="Şifreniz">
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <a href="/password-reset.php" class="small">Şifremi Unuttum</a>
                        <a href="/register.php" class="small">Yeni Bayilik Başvurusu</a>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Panele Giriş Yap</button>
                    <div class="text-center mt-3">
                        <a href="/admin/" class="small text-muted">Yönetici misiniz? Admin girişine gidin.</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<section class="mt-5">
    <div class="row g-3">
        <div class="col-6 col-lg-3">
            <div class="public-metric-card text-center h-100">
                <div class="public-metric-number"><?= Helpers::sanitize(number_format($metrics['orders'])) ?></div>
                <div class="public-metric-label">Toplam Sipariş</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="public-metric-card text-center h-100">
                <div class="public-metric-number"><?= Helpers::sanitize(number_format($metrics['products'])) ?></div>
                <div class="public-metric-label">Aktif Ürün</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="public-metric-card text-center h-100">
                <div class="public-metric-number"><?= Helpers::sanitize(number_format($metrics['resellers'])) ?></div>
                <div class="public-metric-label">Aktif Bayi</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="public-metric-card text-center h-100">
                <div class="public-metric-number"><?= Helpers::sanitize(Helpers::formatCurrency($metrics['revenue'])) ?></div>
                <div class="public-metric-label">İşlenen Tutar</div>
            </div>
        </div>
    </div>
</section>

<section class="py-5" id="features">
    <div class="text-center mb-5">
        <h2 class="public-section-title">Tek panelde uçtan uca satış otomasyonu</h2>
        <p class="public-section-subtitle mx-auto">Stok yönetiminden müşteri paneline kadar tüm iş akışınızı hızlandırmak için tasarlandı.</p>
    </div>
    <div class="row g-4">
        <div class="col-md-4">
            <div class="public-feature-card h-100">
                <div class="public-feature-icon"><i class="bi bi-cloud-arrow-down-fill"></i></div>
                <h5 class="mb-3">Otomatik Teslimat</h5>
                <p class="text-muted mb-0">Satın alınan lisans, hesap veya E-Pin saniyeler içinde müşteriye ulaştırılır. Stok azaldığında otomatik uyarılar alın.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="public-feature-card h-100">
                <div class="public-feature-icon"><i class="bi bi-kanban"></i></div>
                <h5 class="mb-3">Gelişmiş Panel</h5>
                <p class="text-muted mb-0">Bayi, müşteri ve admin panelleri ile tüm rolleri ayrı ayrı yönetin. Destek talepleri, kuponlar ve affiliate yönetimi tek ekranda.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="public-feature-card h-100">
                <div class="public-feature-icon"><i class="bi bi-credit-card"></i></div>
                <h5 class="mb-3">Çoklu Ödeme</h5>
                <p class="text-muted mb-0">Shopier, Papara, PayTR ve cüzdan ile tahsilat alın. Otomatik bakiye yüklemeleri ve kupon kodu desteği sunun.</p>
            </div>
        </div>
    </div>
</section>

<?php if ($topCategories): ?>
    <section class="py-5 border-top border-opacity-10 border-light">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h2 class="public-section-title mb-1">Popüler kategoriler</h2>
                <p class="public-section-subtitle mb-0">En çok satan oyunlar, yazılımlar ve dijital kodlar tek panelde.</p>
            </div>
            <a href="/products.php" class="public-pill-link">
                Tüm ürünlere göz at
                <i class="bi bi-arrow-right"></i>
            </a>
        </div>
        <div class="row g-4">
            <?php foreach ($topCategories as $category): ?>
                <div class="col-md-6 col-xl-4">
                    <div class="public-category-card h-100">
                        <div class="public-category-icon"><i class="bi bi-collection"></i></div>
                        <h5 class="mb-2"><?= Helpers::sanitize($category['name']) ?></h5>
                        <p class="text-muted mb-3">
                            <?= Helpers::sanitize($category['description'] ?: 'Bu kategoride yüzlerce stok ve anlık teslimat seçeneği bulunur.') ?>
                        </p>
                        <a href="/products.php?category=<?= Helpers::sanitize((int)$category['id']) ?>" class="public-pill-link">
                            Ürünleri İncele
                            <i class="bi bi-arrow-up-right"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<section class="py-5" id="featured-products">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2 class="public-section-title mb-1">Trend ürünler</h2>
            <p class="public-section-subtitle mb-0">Güncel stoklar, otomatik fiyatlandırma ve esnek teslimat seçenekleri.</p>
        </div>
        <a href="/products.php" class="btn btn-outline-light">Tüm ürünler</a>
    </div>
    <div class="row g-4">
        <?php if ($featuredProducts): ?>
            <?php foreach ($featuredProducts as $product): ?>
                <div class="col-md-6 col-lg-4 col-xl-3">
                    <div class="public-product-card h-100">
                        <span class="badge mb-3"><?= Helpers::sanitize($product['category_name'] ?: 'Kategori') ?></span>
                        <div class="public-product-title"><?= Helpers::sanitize($product['name']) ?></div>
                        <p class="public-product-meta mb-4">
                            <?= Helpers::sanitize($product['description'] ?: Helpers::defaultProductDescription()) ?>
                        </p>
                        <div class="d-flex justify-content-between align-items-end">
                            <div>
                                <div class="public-product-price"><?= Helpers::sanitize(Helpers::formatCurrency((float)$product['price'])) ?></div>
                                <small class="text-muted">Anında teslimat</small>
                            </div>
                            <a href="/products.php?product=<?= Helpers::sanitize((int)$product['id']) ?>" class="public-pill-link">
                                Satın Al
                                <i class="bi bi-cart3"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="public-product-card text-center">
                    <h5 class="mb-2">Henüz ürün eklenmedi</h5>
                    <p class="text-muted mb-4">Admin panelinden ürün oluşturduğunuzda bu alan otomatik olarak güncellenecektir.</p>
                    <a href="/admin/products.php" class="public-pill-link">
                        Admin paneline git
                        <i class="bi bi-gear"></i>
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="py-5 border-top border-opacity-10 border-light">
    <div class="text-center mb-4">
        <h2 class="public-section-title mb-1">Tercih edilen ödeme sağlayıcıları</h2>
        <p class="public-section-subtitle mb-0">Müşterilerinize güvenilir ödeme deneyimi sunun.</p>
    </div>
    <div class="d-flex flex-wrap justify-content-center gap-3">
        <span class="public-partner-logo">Shopier</span>
        <span class="public-partner-logo">PayTR</span>
        <span class="public-partner-logo">Papara</span>
        <span class="public-partner-logo">FastPay</span>
        <span class="public-partner-logo">Havale / EFT</span>
    </div>
</section>

<?php if ($blogHighlights): ?>
    <section class="py-5 border-top border-opacity-10 border-light">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h2 class="public-section-title mb-1">Blogdan öne çıkanlar</h2>
                <p class="public-section-subtitle mb-0">Satış stratejileri, oyun haberleri ve güncel kampanyalar.</p>
            </div>
            <a href="/blog/" class="public-pill-link">
                Tüm yazıları oku
                <i class="bi bi-arrow-right-circle"></i>
            </a>
        </div>
        <div class="row g-4">
            <?php foreach ($blogHighlights as $post): ?>
                <div class="col-md-6 col-xl-4">
                    <article class="public-blog-card h-100">
                        <?php if (!empty($post['image_url'])): ?>
                            <img src="<?= Helpers::sanitize($post['image_url']) ?>" alt="<?= Helpers::sanitize($post['title']) ?>">
                        <?php endif; ?>
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-primary bg-opacity-25 text-light"><?= Helpers::sanitize($post['category_name'] ?: 'Genel') ?></span>
                                <?php if (!empty($post['published_at'])): ?>
                                    <span class="public-blog-meta">
                                        <?= Helpers::sanitize(date('d.m.Y', strtotime($post['published_at']))) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <h5 class="mb-2"><?= Helpers::sanitize($post['title']) ?></h5>
                            <p class="text-muted mb-4 flex-grow-1">
                                <?= Helpers::sanitize($post['excerpt'] ?: Helpers::seoDescription()) ?>
                            </p>
                            <a class="public-pill-link mt-auto" href="/blog/<?= Helpers::sanitize($post['slug']) ?>">
                                Yazıyı Oku
                                <i class="bi bi-arrow-up-right"></i>
                            </a>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<section class="py-5 text-center">
    <div class="public-hero-home py-5">
        <h2 class="public-section-title mb-3">Hazır mısınız?</h2>
        <p class="public-section-subtitle mx-auto mb-4">Reseller ağınızı büyütmek ve müşteri memnuniyetini artırmak için hemen ücretsiz deneme hesabı oluşturun.</p>
        <div class="d-flex flex-wrap justify-content-center gap-3">
            <a href="/register.php" class="btn btn-primary btn-lg px-4">Bayilik Başvurusu</a>
        </div>
    </div>
</section>

<?php Helpers::includeTemplate('public-footer.php'); ?>
