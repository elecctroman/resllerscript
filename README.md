# Bayi Yönetim Sistemi

Profesyonel bayilik yönetim süreçlerinizi uçtan uca yönetebilmeniz için geliştirilen PHP tabanlı bir yönetim panelidir. Sistem, klasik paylaşımlı hosting ortamlarında sorunsuz şekilde çalışacak şekilde tasarlanmıştır ve MySQL/MariaDB altyapısını kullanır.

## Özellikler
- Güvenli oturum açma sistemi ve şifre sıfırlama akışı
- Paket yönetimi: sınırsız sayıda paket ekleme, düzenleme, silme ve aktif/pasif durumu değiştirme
- Ücretli bayilik başvuru formu: paket seçimi, başvuru kaydı, otomatik admin bilgilendirmesi
- Sipariş yönetimi: başvuruları onaylama, ödeme/tamamlanma durumları, otomatik bayi hesabı oluşturma
- Ürün ve kategori yönetimi: kategori bazlı ürün ekleme/düzenleme/silme
- Bakiye yönetimi: otomatik kredi işlemleri ve manuel bakiye güncelleme, işlem kayıtları
- Destek sistemi: bayi tarafı destek talepleri, admin panelinden yanıt ve durum takibi
- Telegram bildirimi: teslim edilen siparişlerde otomatik Telegram uyarısı (opsiyonel)
- Modern Bootstrap 5 arayüzü ve responsive tasarım

## Kurulum
1. Proje dosyalarını sunucunuza yükleyin ve web sunucusunun kök dizinini bu klasöre yönlendirin.
2. `config/config.sample.php` dosyasını `config/config.php` olarak kopyalayın ve MySQL/MariaDB bağlantı bilgilerinizi güncelleyin.
3. Veritabanınızı oluşturun ve `schema.sql` dosyasındaki tabloları içeri aktarın. Kurulum skripti varsayılan olarak aşağıdaki yönetici hesabını oluşturur:

   | Kullanıcı Adı | E-posta                | Şifre          |
   |---------------|------------------------|----------------|
   | `Muhammet`    | `muhammet@example.com` | `5806958477i.` |

   İlk girişten sonra şifreyi güncellemeniz tavsiye edilir.
4. Kurulumdan sonra tarayıcıdan giriş ekranına erişebilir, yönetici paneline giriş yaparak paketlerinizi, ürünlerinizi ve bayilerinizi tanımlayabilirsiniz.

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
