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

## Entegrasyonlar

Sistem, kendi ürün ve stok yönetiminiz için tasarlanmıştır. Harici sağlayıcı bağlantıları bu sürümde bulunmamaktadır.
