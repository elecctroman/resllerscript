# PHP WhatsApp Web Gateway

Bu proje, WhatsApp Web oturumu üzerinden bildirim gönderen, tamamen PHP ile yazılmış bir gateway altyapısı sağlar. Yönetim paneli üzerinden QR kodu tarayarak oturumu eşler, Redis kuyruğuna atılan bildirimleri Selenium + ChromeDriver aracılığıyla gönderir ve tüm işlemleri MySQL veritabanında saklar.

## Mimari Bileşenler
- **app (PHP HTTP sunucusu)**: Yönetim arayüzü ve iç API uç noktalarını barındırır.
- **php-worker**: Redis kuyruğunu dinleyerek WhatsApp Web üzerinden mesaj gönderen uzun süreli işçi.
- **selenium**: `selenium/standalone-chrome` imajı ile çalışan ChromeDriver.
- **redis**: Bildirim kuyruğu ve rate limit verilerini tutar.
- **mysql**: Oturumlar, abonelikler ve bildirim günlükleri için kalıcı veri deposu.

## Kurulum
1. Gerekli dizinleri oluşturun ve ortam değişkenlerinizi ayarlamak için proje köküne `.env` dosyası ekleyin (aşağıdaki örneğe bakın).
2. Veritabanında `migrations/0001_create_whatsapp_tables.sql` dosyasındaki komutları çalıştırın.
3. Docker servislerini ayağa kaldırın:
   ```bash
   docker-compose up --build
   ```
4. Tarayıcınızdan `http://localhost:8080/admin/whatsapp.php` adresine giderek yeni bir oturum oluşturun ve QR kodu telefonunuzla tarayın.
5. Ayrı bir terminalde işçiyi başlatın (Docker Compose otomatik olarak `php-worker` servisini başlatır). Manuel çalıştırmak isterseniz:
   ```bash
   docker-compose run --rm php-worker
   ```
6. Test amaçlı bir mesaj kuyruğa atmak için CLI aracını veya test scriptini kullanın.

## Ortam Değişkenleri
`.env` dosyası örneği:
```env
DB_HOST=db
DB_NAME=whatsapp
DB_USER=whatsapp
DB_PASS=secret
REDIS_HOST=redis
REDIS_PORT=6379
SELENIUM_HOST=http://selenium:4444/wd/hub
APP_GATEWAY_KEY=supersecretkey
WHATSAPP_SESSION_DIR_BASE=/var/www/html/storage/sessions
WORKER_MAX_ATTEMPTS=5
RATE_LIMIT_PER_MINUTE=60
LOG_DIR=/var/www/html/storage/logs
APP_TIMEZONE=Europe/Istanbul
```

> Not: İç API (`public/internal/whatsapp_send.php`) çağrılarında `X-API-KEY` başlığının bu anahtarla eşleşmesi gerekir. Anahtar boş ise `api_keys` tablosuna kayıtlı anahtarlar kontrol edilir.

## Docker Servisleri
- `app`: PHP 8.2 CLI tabanlı imaj, `public/` dizinini 8080 portu üzerinden sunar.
- `php-worker`: Aynı imajı kullanarak `console/worker.php` çalıştırır.
- `selenium`: WhatsApp Web otomasyonu için ChromeDriver.
- `redis`: Kuyruk, rate limit ve gecikmeli işler için kullanılır.
- `db`: MySQL 8 veritabanı sunucusu.

Tüm servisler için gerekli kalıcı dizinler `docker-compose.yml` dosyasında volume olarak tanımlanmıştır (`sessions`, `logs`, `db_data`).

## Yönetim Arayüzü
### `admin/whatsapp.php`
- Yeni oturum oluşturma
- QR kod görüntüleme ve oturum durum takibi (bağlı/pending)
- Test mesajı kuyruğa alma

### `admin/whatsapp_sessions.php`
- Tüm oturumları listeleme
- Oturum silme ve ilgili session dizinlerini temizleme

### `admin/notification_subscriptions.php`
- Bayi bazlı bildirim abonelikleri oluşturma
- Etkinlik (order_completed, support_replied, price_changed, balance_low) seçme
- Abonelikleri aktif/pasif yapma

## İç API
`POST /internal/whatsapp_send.php`
- **Başlık:** `X-API-KEY: <APP_GATEWAY_KEY>`
- **İstek gövdesi (JSON veya form):**
  ```json
  {
    "to": "+905551112233",
    "message": "Merhaba {{name}} siparişiniz tamamlandı",
    "event": "manual",
    "metadata": { "order_id": 123 }
  }
  ```
- Telefon numarası E.164 formatında olmalıdır. Varsayılan rate limit dakika başına 60 mesajdır.
- Başarılı isteklerde 202 döner ve bildirim `notifications` tablosuna “pending” olarak kaydedilir.

## Bildirim Servisi
`App\Services\NotificationService` sınıfı, abonelikleri okuyarak ilgili telefonlara mesaj kuyruğa alır. `notify($event, $payload, $resellerId)` metodu, aboneliğinde ilgili etkinliği işaretleyen tüm bayiler için mesaj oluşturur ve Redis kuyruğuna işler.

## Worker Davranışı
`console/worker.php` scripti:
- Redis kuyruğunu (`whatsapp:queue`) `BRPOP` ile dinler.
- Bildirim kayıtlarını veritabanından okur, bağlı oturumu seçer ve `App\WhatsApp\Gateway` aracılığıyla mesajı gönderir.
- Başarısız denemeleri loglar, exponential backoff ile `whatsapp:retry` kuyruğuna erteler ve azami deneme sayısına ulaşıldığında bildirimi `failed` durumuna çeker.

## Testler
- CLI ile test:
  ```bash
  php app/Console/SendTest.php +905551112233 "Test mesajı"
  ```
- HTTP üzerinden test (`scripts/test_send.sh`):
  ```bash
  ./scripts/test_send.sh +905551112233 "Merhaba" "manual"
  ```

## Hızlı Kontrol Listesi
1. `docker-compose up --build`
2. `http://localhost:8080/admin/whatsapp.php` adresinde QR kodunu tarayın.
3. `docker-compose logs php-worker` ile işçi loglarını takip edin.
4. `scripts/test_send.sh` veya CLI ile test bildirimi oluşturun.
5. `notifications` tablosunda kaydın `sent` durumuna geçtiğini doğrulayın.

## Güvenlik
- İç API tüm isteklerde `X-API-KEY` zorunludur.
- Rate limit, Redis üzerinde numara başına dakikada 60 istek ile sınırlar.
- Tüm hatalar `storage/logs/app.log` dosyasına yazılır; oturum dizinleri `storage/sessions` altında saklanır.

## Lisans
MIT Lisansı. Ayrıntılar için `LICENSE` dosyasına bakın.
