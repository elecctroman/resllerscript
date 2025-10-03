<div class="provider-api-docs">
    <style>
        .provider-api-docs { font-size: 0.95rem; line-height: 1.6; }
        .provider-api-docs h2 { font-weight: 600; margin-top: 1.5rem; color: #0d6efd; }
        .provider-api-docs h3 { font-weight: 600; margin-top: 1.25rem; color: #212529; }
        .provider-api-docs table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .provider-api-docs table thead th { background: #f1f3f5; font-weight: 600; }
        .provider-api-docs table th,
        .provider-api-docs table td { border: 1px solid #dee2e6; padding: 0.5rem; text-align: left; }
        .provider-api-docs code { background: #212529; color: #f8f9fa; padding: 0.4rem 0.6rem; border-radius: 0.35rem; display: block; white-space: pre-wrap; }
        .provider-api-docs .badge-status { font-weight: 600; }
        .provider-api-docs .status-200 { color: #198754; }
        .provider-api-docs .status-400 { color: #fd7e14; }
        .provider-api-docs .status-401 { color: #dc3545; }
        .provider-api-docs .status-404 { color: #0d6efd; }
        .provider-api-docs .status-500 { color: #6c757d; }
        .provider-api-docs .note { background: #fff3cd; color: #856404; border-left: 4px solid #ffe69c; padding: 0.75rem 1rem; border-radius: 0.35rem; margin-top: 1rem; }
        @media (max-width: 768px) {
            .provider-api-docs table { display: block; overflow-x: auto; white-space: nowrap; }
        }
    </style>
    <h2>API Dokümantasyonu</h2>
    <p>Lotus Lisans sağlayıcısı ile entegrasyon için temel uç noktalar aşağıda özetlenmiştir. Tüm çağrılar <code>https://partner.lotuslisans.com.tr</code> taban adresini kullanır.</p>

    <h3>Kimlik Doğrulama</h3>
    <p>Her istekte API anahtarını ya <strong>X-API-Key</strong> başlığına ya da <code>?apikey=YOUR_KEY</code> sorgu parametresine ekleyin. Anahtar eksik veya hatalı ise istek <span class="status-401">401 Unauthorized</span> döner.</p>

    <h3>Genel HTTP Durum Kodları</h3>
    <table>
        <thead>
        <tr>
            <th>Kod</th>
            <th>Anlam</th>
            <th>Açıklama</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td class="status-200">200</td>
            <td>Başarılı</td>
            <td>İstek başarıyla işlendi.</td>
        </tr>
        <tr>
            <td class="status-400">400</td>
            <td>İş Kuralı Hatası</td>
            <td>Doğrulama veya bakiye yetersizliği gibi senaryolar.</td>
        </tr>
        <tr>
            <td class="status-401">401</td>
            <td>Yetkisiz</td>
            <td>API anahtarı geçersiz veya eksik.</td>
        </tr>
        <tr>
            <td class="status-404">404</td>
            <td>Bulunamadı</td>
            <td>İstenen kayıt bulunamadı.</td>
        </tr>
        <tr>
            <td class="status-500">500</td>
            <td>Sunucu Hatası</td>
            <td>Beklenmeyen bir sunucu hatası oluştu.</td>
        </tr>
        </tbody>
    </table>

    <h3>Sipariş Durumları</h3>
    <table>
        <thead>
        <tr>
            <th>Değer</th>
            <th>Açıklama</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td class="status-200">completed</td>
            <td>Stoktan başarıyla teslim edildi.</td>
        </tr>
        <tr>
            <td class="status-400">pending</td>
            <td>Stoğa bağlı olarak beklemede.</td>
        </tr>
        <tr>
            <td class="status-401">cancelled</td>
            <td>Yönetici tarafından iptal edildi.</td>
        </tr>
        <tr>
            <td class="status-500">failed</td>
            <td>Sipariş oluşturulamadı.</td>
        </tr>
        </tbody>
    </table>

    <h3>Uç Noktalar</h3>
    <p><strong>GET /api/user</strong> – API anahtarına ait kullanıcı bilgilerini ve bakiyeyi döndürür.</p>
    <code>GET https://partner.lotuslisans.com.tr/api/user?apikey=YOUR_KEY</code>

    <p><strong>GET /api/products</strong> – Satın alınabilir ürünleri listeler.</p>
    <code>GET https://partner.lotuslisans.com.tr/api/products?apikey=YOUR_KEY</code>

    <p><strong>POST /api/orders</strong> – Ürün siparişi oluşturur.</p>
    <code>curl -X POST "https://partner.lotuslisans.com.tr/api/orders?apikey=YOUR_KEY" \
 -H "Content-Type: application/json" \
 -d '{"product_id": 57, "note": "müşteri notu"}'</code>

    <p><strong>GET /api/orders</strong> – Kullanıcının sipariş geçmişini listeler.</p>
    <code>GET https://partner.lotuslisans.com.tr/api/orders?apikey=YOUR_KEY</code>

    <p><strong>GET /api/orders/{id}</strong> – Tek siparişin detayını getirir.</p>
    <code>GET https://partner.lotuslisans.com.tr/api/orders/123?apikey=YOUR_KEY</code>

    <div class="note">
        <strong>Not:</strong> Aynı ürüne art arda sipariş gönderildiğinde her çağrı ayrı sipariş oluşturur. Telegram bildirimleri başarısız olsa bile sipariş kaydı başarılıysa API yanıtı <em>success</em> döner.
    </div>
</div>
