# Bayi Yönetim Sistemi

Profesyonel bayilik yönetim süreçlerinizi uçtan uca yönetebilmeniz için geliştirilen PHP tabanlı bir yönetim panelidir. Sistem, klasik paylaşımlı hosting ortamlarında sorunsuz şekilde çalışacak şekilde tasarlanmıştır ve MySQL/MariaDB altyapısını kullanır.

## Özellikler
- Güvenli oturum açma sistemi ve şifre sıfırlama akışı
- Kurulum sihirbazı ile tek adımda veritabanı yapılandırması ve ilk yönetici oluşturma
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
2. `config/config.php` dosyası varsayılan olarak git tarafından yok sayılır; ilk kurulum için gerekmez.
3. Tarayıcınızdan sitenize eriştiğinizde otomatik olarak kurulum sihirbazı açılır. Veritabanı bilgilerinizi ve ilk yönetici hesabını girin.
4. Kurulum tamamlandığında sistem gerekli tabloları oluşturur ve `config/config.php` dosyasını yazar.
5. Kurulumdan sonra yönetici paneline giriş yaparak paketlerinizi, ürünlerinizi ve bayilerinizi tanımlayabilirsiniz.

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
