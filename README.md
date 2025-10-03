# Bayi Yönetim Sistemi

Profesyonel bayilik yönetim süreçlerinizi uçtan uca yönetebilmeniz için geliştirilen PHP tabanlı bir yönetim panelidir. Sistem, klasik paylaşımlı hosting ortamlarında sorunsuz şekilde çalışacak şekilde tasarlanmıştır ve MySQL/MariaDB altyapısını kullanır.

## Özellikler
- Güvenli oturum açma sistemi ve şifre sıfırlama akışı
- Paket yönetimi: sınırsız sayıda paket ekleme, düzenleme, silme ve aktif/pasif durumu değiştirme
- Ücretli bayilik başvuru formu: paket seçimi, başvuru kaydı, otomatik admin bilgilendirmesi
- Sipariş yönetimi: başvuruları onaylama, ödeme/tamamlanma durumları, otomatik bayi hesabı oluşturma
- Ürün ve kategori yönetimi: ayrılmış kategori konsolu, alt kategori desteği ve alış fiyatına göre otomatik USD satış fiyatı hesaplama
- Bakiye yönetimi: bayi talep/approval akışı, otomatik bakiye hareketleri ve işlem kayıtları
- Ödeme entegrasyonları: Cryptomus ve Heleket desteği, test modu sayesinde sandbox senaryolarında otomatik onay
- Destek sistemi: bayi tarafı destek talepleri, admin panelinden yanıt ve durum takibi
- WooCommerce CSV araçları: ayrı içe aktarma ve dışa aktarma ekranları, WooCommerce ile tam uyumlu dosya üretimi
- Genel ayarlar ve SEO: site adı/sloganı, meta etiketleri, modül bazlı aç/kapa anahtarları ve otomatik kur yenileme
- Bayi profil yönetimi: bayi kullanıcıları kendi bilgilerini ve şifrelerini güncelleyebilir
- WooCommerce API entegrasyonu: otomatik sipariş aktarımı ve durum senkronizasyonu için REST tabanlı uç noktalar
- Telegram bildirimi: teslim edilen siparişlerde ve bakiye onaylarında otomatik Telegram uyarısı (opsiyonel)
- Mail ayarları: yönetici panelinden gönderen kimliği, alt metin ve SMTP sunucu yapılandırması
- Çok dilli arayüz: kullanıcı ve yönetici panelleri Türkçe/İngilizce arasında geçiş yapabilir
- Dinamik para birimi: İngilizce görünümde USD, Türkçe görünümde güncel kurla TL gösterimi ve otomatik dönüşüm
- Otomatik bayi pasifleştirme: minimum bakiye ve süre tanımlayarak bayiliği pasife alma politikasını yürütün
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
- API çağrılarında `Authorization: Bearer <API_KEY>` ve `X-Reseller-Email: <bayi e-postası>` başlıklarını gönderdiğinizden emin olun.
- Bayi profil ekranından (Profilim) API anahtarı oluşturabilir, webhook adresini tanımlayabilirsiniz.
- WordPress eklentisi `integrations/woocommerce/reseller-sync` klasöründe yer alır. Zip olarak paketleyip WordPress eklentisi olarak yükleyin ve WooCommerce → Reseller Sync menüsünden bayi e-posta adresinizi ve API anahtarınızı girin.
- Panel alan adınız farklıysa eklenti içindeki `Reseller_Sync_Connector::API_BASE` sabitini veya `RESELLER_SYNC_API_BASE` tanımını güncelleyerek API uç noktasını özelleştirebilirsiniz.
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

## Lotus Lisans Partner API PHP SDK

Bu depo, Lotus Lisans Partner API için üretim kalitesinde küçük bir PHP SDK ve örnek komut satırı betiği de içerir. SDK, Guzzle tabanlı HTTP istemcisi, otomatik yeniden deneme/backoff politikası, tutarlı bir hata modeli ve `.env` ile yapılandırılabilir ortam değişkeni desteği sağlar.

### Kurulum

SDK bağımlılıklarını yüklemek için kök dizinde aşağıdaki komutu çalıştırın:

```
composer require guzzlehttp/guzzle vlucas/phpdotenv ramsey/uuid
```

Ardından `.env.example` dosyasını `.env` olarak kopyalayarak API anahtarınızı ekleyin:

```
cp .env.example .env
```

### Kullanım

1. Gerekli yapılandırmaları `.env` dosyasına girin (`LOTUS_API_KEY`, isteğe bağlı `LOTUS_BASE_URL`).
2. Örnek betiği çalıştırın:

   ```bash
   php examples/demo.php
   ```

`Lotus\Client` sınıfı varsayılan olarak API anahtarını `X-API-Key` başlığında gönderir ve her istekte benzersiz bir `X-Request-Id` üretir. `useQueryApiKey` seçeneği `true` yapılabilir ancak güvenlik nedeniyle sorgu parametresiyle kimlik doğrulamayı yalnızca zorunlu durumlarda kullanmanız önerilir.

### Hata Yakalama ve Yeniden Deneme

Tüm hata durumlarında `Lotus\Exceptions\ApiError` fırlatılır. Bu sınıf HTTP durum kodu, isteğe ait `X-Request-Id`, opsiyonel hata kodu ve ham yanıt gövdesini içerir. İstemci 429 ve 5xx yanıtları için varsayılan olarak üç deneme hakkına sahip exponential backoff (jitter'lı) stratejisi uygular. `maxRetries` ve `retryBaseMs` parametreleri ile davranışı özelleştirebilirsiniz.

### Yardımcı Özellikler

- `ResponseTypes` yardımcı sınıfı, API'nin `amount` ve `credit` alanlarını güvenle `float` tipine dönüştürür.
- `created_at` alanı bulunduğunda otomatik olarak `created_at_dt` anahtarı altında `DateTimeImmutable` nesnesi eklenir.
- Liste uç noktalarında sayfalama bilgisi (`meta.page`, `meta.per_page`, `meta.count`) yoksa otomatik olarak oluşturulur.
- `Client::getLastRequestId()` ile son isteğin izleme kimliğine erişebilirsiniz.

### API Dokümantasyonu

Aşağıdaki bölüm, Lotus Lisans Partner API'nin resmi uç noktalarını ve yanıt biçimlerini içerir.

Bu doküman, canlı ortamda kullanılan API uç noktalarını ve geri dönüş biçimlerini detaylı olarak açıklamaktadır. Tüm örnekler, en son yayınlanan kod tabanına uygun olarak hazırlanmıştır. Temel API adresi: https://partner.lotuslisans.com.tr

#### Kimlik Doğrulama (Authentication)
Tüm uç noktalar, yetkisiz erişimi önlemek amacıyla bir API Anahtarıile korunmaktadır. API anahtarınızı aşağıdaki iki yöntemden birini kullanarak sağlamanız zorunludur:
- Sorgu Parametresi: ?apikey=YOUR_KEY
- Header: X-API-Key: YOUR_KEY

Geçersiz veya Eksik Anahtar Durumu: İstek, 401 UnauthorizedHTTP durum kodu ile sonuçlanacaktır.

#### Durum Kodları ve Sipariş "status" Alanı
Genel HTTP Durum Kodları Özeti

| Kod | Anlamı | Açıklama |
|-----|--------|----------|
| 200 | Başarılı | İstek başarıyla işlendi ve beklenen yanıt döndürüldü. |
| 400 | İş Kuralı Hatası | İstek geçerli, ancak bakiye yetersizliği, doğrulama hatası gibi bir iş kuralı nedeniyle işlenemedi. |
| 401 | Yetkisiz | API anahtarı geçersiz veya eksik. |
| 404 | Bulunamadı | İstenen kaynak (ürün, sipariş vb.) bulunamadı. |
| 500 | Sunucu Hatası | Sunucu tarafında beklenmeyen bir hata oluştu. |

Sipariş Durumu Metinleri ("status" Alanı)

| Değer | Anlamı | Açıklama |
|--------|--------|----------|
| completed | Teslim Edildi (Stoklu) | Sipariş, stoktan anında düşülerek başarılı bir şekilde tamamlanmıştır. İçerik (content) alanı teslim edilen bilgiyi içerir. |
| pending | Beklemede (Stoksuz) | Ürün stoğu yetersizdi veya stoksuz satışa açıktı. Sipariş, sonradan teslim edilmek üzere sıraya alınmıştır. İçerik (content) alanında bilgilendirme mesajı bulunur. |
| cancelled | İptal Edildi | Sipariş, yönetim paneli üzerinden iptal edilmiştir. |
| failed | Başarısız | Yalnızca Sipariş Oluşturma denemelerinde döner. İş kuralı (bakiye yetersizliği gibi) veya sistemsel bir hata nedeniyle oluşturulamamıştır. |

#### API Uç Noktaları

1. **Kullanıcı Bilgisi** — `GET /api/user`
   - Kimlik doğrulama parametresi: `apikey` veya `X-API-Key`
   - Başarılı 200 Yanıt:

     ```json
     {
       "success": true,
       "data": {
         "credit": "750",
         "nickname": "user",
         "email": "user@admin.com"
       }
     }
     ```

2. **Ürün Listesi** — `GET /api/products`
   - Notlar:
     - `stock`: Teslim edilmemiş (delivery=1) satır sayısını gösterir.
     - `available`: true ise ürün anında teslim için stokludur veya stoksuz satışa açıktır.
   - Başarılı 200 Yanıt (kısaltılmış):

     ```json
     {
       "success": true,
       "data": [
         {
           "id": 20,
           "title": "Office 2021 Pro Plus - Retail (Telefon Aktivasyon)",
           "content": "Key olarak teslim edilir...",
           "amount": "50",
           "stock": 11,
           "available": true
         }
       ]
     }
     ```

3. **Sipariş Oluşturma** — `POST /api/orders`
   - JSON Gövdesi:
     - `product_id` (int, zorunlu)
     - `note` (string, opsiyonel)
   - Önemli Not: Aynı ürün için art arda POST isteği atıldığında, her istek ayrı bir sipariş olarak işlenir.
   - Başarılı Yanıt Örnekleri:

     ```json
     {
       "success": true,
       "data": {
         "order_id": 48380,
         "status": "completed",
         "content": "STOK_ICERIK_KEY_XXX"
       }
     }
     ```

     ```json
     {
       "success": true,
       "data": {
         "order_id": 48381,
         "status": "pending",
         "content": "Siparişiniz hazır olduğunda telegram bildirimi alacaksınız."
       }
     }
     ```

   - Hata Yanıtları:

     ```json
     {
       "success": false,
       "status": "failed",
       "message": "Yeterli bakiyeniz yok."
     }
     ```

     ```json
     {
       "success": false,
       "status": "failed",
       "message": "Ürün bulunamadı."
     }
     ```

     ```json
     {
       "success": false,
       "status": "failed",
       "message": "Sipariş sırasında hata oluştu."
     }
     ```

4. **Sipariş Geçmişi** — `GET /api/orders`
   - Başarılı 200 Yanıt (kısaltılmış):

     ```json
     {
       "success": true,
       "data": [
         {
           "id": 48380,
           "product_id": "29",
           "product_title": "Adobe CC - 2 Hafta",
           "amount": "40",
           "note": "müşterinin kendi girdiği not",
           "content": "STOK_ICERIK_KEY_XXX",
           "status": "completed",
           "source": "api",
           "created_at": "2025-10-02T00:06:25.000000Z"
         }
       ]
     }
     ```

5. **Sipariş Detayı** — `GET /api/orders/{id}`
   - Başarılı 200 Yanıt:

     ```json
     {
       "success": true,
       "data": {
         "id": 48380,
         "product_id": "29",
         "product_title": "Adobe CC - 2 Hafta",
         "amount": "40",
         "note": "müşterinin kendi girdiği not",
         "content": "STOK_ICERIK_KEY_XXX",
         "status": "completed",
         "source": "api",
         "created_at": "2025-10-02T00:06:25.000000Z"
       }
     }
     ```

   - 404 Hata Yanıtı:

     ```json
     {
       "success": false,
       "message": "Sipariş bulunamadı."
     }
     ```

#### İçerik Formatı Notları
- Stoklu içerikler: `content` alanı teslim edilen stok bilgisini satır sonları korunarak döndürür.
- `pending` siparişler: `content` alanı bilgilendirme mesajı içerir; teslim sonrası güncellenir.
- `cancelled` siparişler: `content` alanı iptal durumunu yansıtır.

#### İdempotensi
İdempotensi anahtarı zorunlu değildir. Aynı isteğin tekrarlanması her defasında yeni bir sipariş oluşturur. SDK, gelecekteki uyumluluk için `Idempotency-Key` başlığını isteğe bağlı olarak göndermenizi destekler.

#### Postman İçin Hızlı Örnekler
- `POST https://partner.lotuslisans.com.tr/api/orders?apikey=TEST_KEY`
- `GET https://partner.lotuslisans.com.tr/api/orders`
- `GET https://partner.lotuslisans.com.tr/api/orders/48380`

## Lisans
Bu proje MIT Lisansı ile lisanslanmıştır. Ayrıntılar için `LICENSE` dosyasına göz atın.
