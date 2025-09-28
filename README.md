# Bayi Yönetim Sistemi

Profesyonel bayilik yönetim süreçlerinizi uçtan uca yönetebilmeniz için geliştirilen PHP tabanlı bir yönetim panelidir. Sistem, klasik paylaşımlı hosting ortamlarında sorunsuz şekilde çalışacak şekilde tasarlanmıştır ve MySQL/MariaDB altyapısını kullanır.

## Özellikler
- Güvenli oturum açma sistemi ve şifre sıfırlama akışı
- Paket yönetimi: sınırsız sayıda paket ekleme, düzenleme, silme ve aktif/pasif durumu değiştirme
- Ücretli bayilik başvuru formu: paket seçimi, başvuru kaydı, otomatik admin bilgilendirmesi
- Sipariş yönetimi: başvuruları onaylama, ödeme/tamamlanma durumları, otomatik bayi hesabı oluşturma
- Ürün ve kategori yönetimi: kategori bazlı ürün ekleme/düzenleme/silme
- Bakiye yönetimi: bayi talep/approval akışı, otomatik bakiye hareketleri ve işlem kayıtları
- Destek sistemi: bayi tarafı destek talepleri, admin panelinden yanıt ve durum takibi
- WooCommerce CSV içe aktarımı: dışa aktarılan ürünlerin kategori uyumlu hızlı importu
- Bayi profil yönetimi: bayi kullanıcıları kendi bilgilerini ve şifrelerini güncelleyebilir
- WooCommerce API entegrasyonu: otomatik sipariş aktarımı ve durum senkronizasyonu için REST tabanlı uç noktalar
- Telegram bildirimi: teslim edilen siparişlerde ve bakiye onaylarında otomatik Telegram uyarısı (opsiyonel)
- Mail ayarları: yönetici panelinden gönderen kimliği ve alt metin yapılandırması
- Çok dilli arayüz: kullanıcı ve yönetici panelleri Türkçe/İngilizce arasında geçiş yapabilir
- Dinamik para birimi: İngilizce görünümde USD, Türkçe görünümde güncel kurla TL gösterimi ve otomatik dönüşüm
- Modern sol menülü Bootstrap 5 arayüzü ve responsive tasarım

## Kurulum
1. Proje dosyalarını sunucunuza yükleyin ve web sunucusunun kök dizinini bu klasöre yönlendirin.
2. `config/config.sample.php` dosyasını `config/config.php` olarak kopyalayın, MySQL/MariaDB bağlantı bilgilerinizi ve varsayılan dili (`DEFAULT_LANGUAGE`) güncelleyin.
3. Veritabanınızı oluşturun ve `schema.sql` dosyasındaki tabloları içeri aktarın. Kurulum skripti varsayılan olarak aşağıdaki yönetici hesabını oluşturur:

   | Kullanıcı Adı | E-posta                | Şifre          |
   |---------------|------------------------|----------------|
   | `Muhammet`    | `muhammet@example.com` | `5806958477i.` |

   İlk girişten sonra şifreyi güncellemeniz tavsiye edilir.
4. Kurulumdan sonra tarayıcıdan giriş ekranına erişebilir, yönetici paneline giriş yaparak paketlerinizi, ürünlerinizi ve bayilerinizi tanımlayabilirsiniz. Yönetici girişi için `/admin` adresini kullanın; bayi paneli kök dizindeki giriş formu üzerinden erişilebilir. Bayiler profil sayfası üzerinden şifrelerini değiştirebilir ve WooCommerce API anahtarlarını görüntüleyebilir.

## WooCommerce API ve WordPress Eklentisi

- REST API kök adresi: `<kurulum>/api/v1/`
    - `GET /api/v1/products.php` — Aktif ürün ve kategori listesini döndürür.
    - `POST /api/v1/orders.php` — WooCommerce siparişlerini SKU bazlı olarak sisteme aktarır.
    - `GET /api/v1/orders.php` — Dış referansa veya duruma göre siparişleri listeler.
    - `POST /api/v1/token-webhook.php` — Webhook adresinizi kaydetmenizi sağlar.
- Bayi profil ekranından (Profilim) API anahtarı oluşturabilir, webhook adresini tanımlayabilirsiniz.
- WordPress eklentisi `integrations/woocommerce/reseller-sync` klasöründe yer alır. Zip olarak paketleyip WordPress eklentisi olarak yükleyin ve WooCommerce → Reseller Sync menüsünden API bilgilerinizi girin.
- Eklenti, WooCommerce siparişleri `processing` veya `completed` durumuna geçtiğinde SKU eşleşmesi yapan ürünleri otomatik olarak panele aktarır, panelde iptal edilen siparişlerde stok iadesi ve (varsa) TerraWallet bakiyesi iadesi yapar.

## Gereksinimler
- PHP 8.1 veya üzeri (PDO, cURL, OpenSSL, mbstring eklentileri aktif olmalıdır)
- MySQL veya MariaDB
- SMTP veya standart `mail()` fonksiyonunun çalışabildiği bir sunucu (e-posta gönderimleri için)
- Telegram bildirimleri için bot token ve chat ID (opsiyonel)

## Geliştirme
- Projede Composer kullanılmaz; tüm sınıflar basit bir autoloader ile yüklenir.
- `config/config.php` dosyası git tarafından izlenmez; dağıtıma özel yapılandırmalar için bu dosya kullanılır.
- Arayüz Bootstrap CDN üzerinden yüklenir. Ek CSS düzenlemeleri `assets/css/style.css` üzerinden yapılabilir.

## Lisans
Bu proje MIT Lisansı ile lisanslanmıştır. Ayrıntılar için `LICENSE` dosyasına göz atın.
