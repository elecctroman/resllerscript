# Reseller Platform

Bu depo, ePin, lisans ve dijital hesap satış süreçlerini yönetmek için geliştirilen bayi otomasyon yazılımını içerir. Sistem; çoklu dil desteği, TL bazlı ürün kataloğu, favori ürün listeleri, stok bildirimi, talimat yönetimi ve zengin bayi duyuru mekanizması gibi özellikler sunar.

## Özellikler

* Kategoriler, alt kategoriler ve stok durumu ile detaylandırılmış ürün kataloğu
* Favori ürün listeleri ve stok yenilendiğinde Telegram bildirimi
* Çoklu dil desteği ve TL bazlı fiyatlandırma (tek para birimi)
* Talimat ve duyuru yönetimiyle bayilere yönelik bilgilendirme ekranları
* Paket başvurusu, bakiye takibi ve sipariş yönetimi için kapsamlı panel bileşenleri

## Gereksinimler

* PHP 8.1 veya üzeri
* MariaDB 10.6 veya üzeri (InnoDB ve utf8mb4 etkin)
* Composer

## Kurulum

1. `composer install`
2. `cp config/config.sample.php config/config.php` ve veritabanı bilgilerini güncelleyin.
3. `php -S localhost:8000` ile yerel ortamda test edin.

## Sağlayıcı Entegrasyonu

Admin panelinde **Ürün & Stok → Sağlayıcılar** bölümünü kullanarak Lotus Lisans API bilgilerinizi kaydedebilir, bağlantı testi yapabilir ve sağlayıcı ürünlerini panelinize aktarabilirsiniz. Entegrasyon akışı şu adımlardan oluşur:

1. Sağlayıcı adı, temel API adresi ve API anahtarını girerek kaydedin.
2. "API Testi Yap" butonu ile bağlantıyı doğrulayın.
3. "Ürünleri Getir" seçeneği ile sağlayıcı kataloğunu listeleyin.
4. Her bir ürün için kategori seçerek "İçe Aktar" butonu ile ürünü sitenize ekleyin veya güncelleyin.

Postman üzerinden hızlı testler yapmak için `integrations/postman/lotus-provider.postman_collection.json` koleksiyonunu içeri aktarabilirsiniz. Koleksiyonda kullanıcı bilgisi, ürün listesi ve sipariş oluşturma uç noktaları örnek olarak yapılandırılmıştır.
