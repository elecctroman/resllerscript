# Lotus Ürün Aktarıcı Chrome Eklentisi

Lotus Partner Panelindeki tüm kategori ve alt kategori sayfalarını dolaşarak ürün isimlerini ve kredi fiyatlarını CSV olarak indiren bir Chrome eklentisi.

## Özellikler

- Kategori ve alt kategorilerdeki tüm "Ürünler" bağlantılarını otomatik olarak keşfeder.
- Kart veya tablo düzenindeki ürünleri algılar.
- Toplanan verileri kategori yolu, ürün adı, fiyat ve kaynak URL bilgileriyle CSV olarak indirir.
- Tarama ilerlemesini eklenti penceresinden takip etmeyi sağlar.

## Kurulum

1. Klasörü indirin veya depoyu klonlayın.
2. Chrome'da `chrome://extensions` adresine gidin.
3. Geliştirici modunu etkinleştirin.
4. "Paketlenmemiş uzantı yükle" seçeneği ile `chrome-extension/lotus-scraper` klasörünü seçin.
5. Lotus Partner Paneline giriş yapıp kategori sayfasını açın.
6. Eklenti simgesine tıklayıp "Taramayı Başlat" butonuna basın.

Tarama tamamlandığında CSV dosyası otomatik olarak indirilecektir.
