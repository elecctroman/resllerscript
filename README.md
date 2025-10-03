# Lotus API Düz PHP Entegrasyonu

Bu paket, Lotus Lisans sağlayıcısının REST API'sini düz PHP 8.1+ ortamında kullanmak isteyen uygulamalar için hazırlanmış hafif bir entegrasyon katmanıdır. Proje; sipariş oluşturma sırasında idempotensi kontrolü, pending siparişlerin periyodik takibi, SQLite tabanlı kalıcılık ve dosya loglama yetenekleri ile birlikte gelir.

## Kurulum

```bash
composer install
cp .env.example .env
# .env içindeki LOTUS_API_KEY değerini doldurun
php -S 0.0.0.0:8000 -t public
```

## Yapı Taşları

- `bootstrap.php`: Ortak bootstrap, `.env` yüklenmesi ve yardımcı fonksiyonlar.
- `src/LotusClient.php`: Guzzle tabanlı HTTP istemcisi, retry/backoff mekanizması içerir.
- `src/LotusOrderRepository.php`: SQLite üzerinde sipariş kayıtlarını saklar ve idempotensi sağlar.
- `src/LotusOrderService.php`: Sipariş oluşturma ve pending siparişlerin güncellenmesi için iş mantığı katmanı.
- `bin/lotus-poll.php`: Cron/CLI üzerinden pending siparişleri kontrol eden komut.
- `example/order_callback.php`: Ödeme sonrası tetiklenebilecek basit örnek uç nokta.
- `storage/`: Varsayılan olarak SQLite veritabanı ve log dosyasını barındırır (Git tarafından yok sayılır).

## Ortam Değişkenleri

`.env` dosyasında yer alan değişkenler:

| Anahtar | Açıklama | Varsayılan |
| --- | --- | --- |
| `LOTUS_API_KEY` | Sağlayıcı tarafından verilen API anahtarı | - |
| `LOTUS_BASE_URL` | Lotus API temel adresi | `https://partner.lotuslisans.com.tr` |
| `LOTUS_TIMEOUT_MS` | HTTP isteği zaman aşımı (ms) | `20000` |
| `LOTUS_CONNECT_TIMEOUT_MS` | Bağlantı zaman aşımı (ms) | `10000` |
| `LOTUS_DB_PATH` | SQLite dosya yolu | `/storage/lotus.sqlite` |
| `LOTUS_LOG_PATH` | Log dosyası | `/storage/lotus.log` |

## Sipariş Akışı

1. Ödeme tamamlandığında `example/order_callback.php` uç noktasına `local_order_id` ve `lotus_product_id` alanlarını içeren bir POST isteği gönderin.
2. Uç nokta idempotent olarak Lotus API'sine sipariş aktarır ve sonucu JSON olarak döner.
3. `bin/lotus-poll.php` komutunu cron veya supervisor ile çalıştırarak `pending` durumundaki siparişlerin durumunu güncel tutun.
4. Sipariş `completed` olduğunda CLI çıktısında ve `storage/lotus.log` dosyasında içerik görüntülenir; kendi uygulama mantığınızı callback içerisinde genişletebilirsiniz.

## Loglama

Tüm HTTP hataları ve iş akışı mesajları `LOTUS_LOG_PATH` ile belirtilen dosyaya yazılır. Dosya yoksa otomatik oluşturulur.

## Test / Kontrol Listesi

1. `.env` dosyasına geçerli bir `LOTUS_API_KEY` girildi mi?
2. `composer install` komutu sonrasında `vendor/` klasörü oluştu mu?
3. `php example/order_callback.php` (CLI üzerinden) ile temel akış hatasız tetikleniyor mu?
4. `bin/lotus-poll.php` çalıştırıldığında pending siparişler güncelleniyor mu?
5. `storage/lotus.log` dosyasında HTTP hataları ve bilgilendirici loglar tutuluyor mu?

## Lisans

MIT lisansı altında dağıtılır.
