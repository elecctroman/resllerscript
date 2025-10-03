# ResellerScript Platform

ResellerScript; bayileri, paket siparişlerini ve destek süreçlerini tek panelde toplamak için geliştirilmiş PHP tabanlı bir yönetim uygulamasıdır. Yönetici ekibi siparişleri takip ederken bayiler Telegram üzerinden bildirim alır, bakiye yüklemelerini bildirir ve mobil uyumlu arayüz üzerinden işlemlerini tamamlar.

## Başlıca Özellikler
- **Bayi ve yönetici panelleri:** Paket/sipariş yönetimi, bakiye onayı, destek talepleri ve raporlar için ayrı ekranlar.
- **Telegram entegrasyonu:** Bayilik onayı, sipariş tamamlanması, bakiye onayı ve destek yanıtları için otomatik bildirimler.
- **Demo ve güvenlik kısıtları:** Yönetici tarafından açılıp kapatılabilen demo hesap, hata günlükleme ve zorunlu şifre/telefon doğrulamaları.
- **Bankadan ödeme bildirimi:** Havale/EFT ile yapılan başvurular otomatik olarak bekleyen bakiye talebi oluşturur.
- **Mobil uyum:** Navigasyon, tablolar ve modaller küçük ekranlarda kullanılabilir olacak şekilde yeniden düzenlendi.
- **Premium modül pazarı:** Yönetici sınırsız modül yükleyip fiyatlandırabilir, bayiler satın aldıkları modülleri güvenli indirme bağlantılarıyla çekebilir.

## Gereksinimler
- PHP 8.0 veya üzeri (pdo_mysql, intl, zip, mbstring uzantıları ile)
- MySQL 8.x
- Composer (bağımlılık yönetimi için)
- İsteğe bağlı: Docker ve Docker Compose

## Kurulum Adımları
1. Depoyu sunucunuza klonlayın veya dosyaları kopyalayın.
2. `config/config.sample.php` dosyasını `config/config.php` olarak kopyalayın ve veritabanı/Telegram ayarlarınızı girin.
3. `composer install --no-dev` komutunu çalıştırarak autoloader oluşturun.
4. Boş bir MySQL veritabanı oluşturun ve `schema.sql` dosyasındaki tabloları içeri aktarın.
5. `storage/logs` dizininin yazılabilir olduğundan emin olun.
6. Web sunucunuzu proje köküne yönlendirin (Apache/Nginx) veya yerel geliştirme için `php -S 0.0.0.0:8080` komutunu kullanın.

## Ortam Değişkenleri
`.env` dosyasıyla veya sistem değişkenleriyle aşağıdaki anahtarlar tanımlanabilir:

| Değişken | Açıklama | Varsayılan |
| --- | --- | --- |
| `DB_HOST` | MySQL sunucusu | `127.0.0.1` |
| `DB_NAME` | Veritabanı adı | `resellerscript` |
| `DB_USER` | Veritabanı kullanıcısı | `root` |
| `DB_PASS` | Veritabanı parolası | boş |
| `LOG_DIR` | Uygulama log dizini | `storage/logs` |
| `APP_TIMEZONE` | PHP varsayılan zaman dilimi | `UTC` |

Telegram bildirimleri için `config/config.php` dosyasında `TELEGRAM_BOT_TOKEN` ve `TELEGRAM_CHAT_ID` değerlerini doldurmayı unutmayın.

## Docker ile Hızlı Başlangıç (Opsiyonel)
```bash
docker-compose up --build
```
- Uygulama: `http://localhost:8080`
- Veritabanı: `localhost:3306` (kullanıcı: `reseller`, parola: `secret`)

İlk kurulumdan sonra `schema.sql` dosyasını konteyner içerisinden MySQL'e aktarmanız gerekir.

## Premium Modüller
- Yönetici panelinden **Premium Modüller &rarr; Modülleri Yönet** sayfasını kullanarak isim/açıklama/fiyat bilgisiyle ZIP dosyaları yükleyebilirsiniz.
- Dosyalar `storage/modules/` dizinine kaydedilir; dizinin web sunucusu tarafından yazılabilir olması gerekir.
- Bayiler `Premium Modüller` menüsünden aktif paketleri görür, cüzdan bakiyesiyle anında ödeme yapabilir veya havale yöntemiyle beklemeye alabilir.
- İndirme bağlantıları 10 dakika geçerliliği olan imzalı URL'lerle oluşturulur ve yalnızca ilgili bayi tarafından kullanılabilir.
- Ödeme sağlayıcısından başarılı dönüş almak için `webhooks/premium-module-callback.php` adresine `purchase_id` ve `status=paid` alanlarını içeren bir POST isteği gönderin. İstek header'ına `X-API-KEY` olarak `PREMIUM_MODULE_API_KEY` değerini eklemeyi unutmayın.

## Günlükler
`app/ErrorLogger` sınıfı tüm uyarı ve hataları `storage/logs/error.log` dosyasına yönlendirir. Uygulama günlükleri ise `storage/logs/app.log` dosyasında tutulur.

## Hızlı Kontrol Listesi
1. Veritabanı bağlantısı ve tablo oluşturma tamamlandı mı?
2. Yönetici hesabıyla giriş yapılıyor mu?
3. Paket siparişi oluşturulup banka havalesi talebi düşüyor mu?
4. Telegram bildirimleri istenilen olaylar için ulaşıyor mu?
5. Demo hesabı aktif/pasif geçişi çalışıyor mu?

## Lisans
Bu proje MIT lisansı ile dağıtılmaktadır. Detaylar için `LICENSE` dosyasına bakınız.
