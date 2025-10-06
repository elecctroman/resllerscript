jQuery(function ($) {
    function handleAjax(action, button, callback) {
        if (button) {
            button.prop('disabled', true).addClass('is-busy');
        }

        $.post(ResellerApiSettings.ajaxUrl, {
            action: action,
            nonce: ResellerApiSettings.nonce
        })
            .done(function (response) {
                if (response.success) {
                    callback(true, response.data);
                } else {
                    callback(false, response.data || {});
                }
            })
            .fail(function () {
                callback(false, {message: 'Beklenmeyen bir hata oluştu.'});
            })
            .always(function () {
                if (button) {
                    button.prop('disabled', false).removeClass('is-busy');
                }
            });
    }

    const testButton = $('#reseller-api-test');
    const syncButton = $('#reseller-api-sync');
    const ordersButton = $('#reseller-api-orders');
    const ordersTable = $('#reseller-api-orders-table tbody');
    const notice = $('#reseller-api-notice');

    function showNotice(type, message) {
        notice.removeClass('notice-error notice-success').addClass('notice notice-' + type).text(message).show();
    }

    if (testButton.length) {
        testButton.on('click', function (e) {
            e.preventDefault();
            const button = $(this);
            handleAjax('reseller_api_test', button, function (success, data) {
                if (success) {
                    showNotice('success', data.message || 'Bağlantı başarılı.');
                } else {
                    showNotice('error', data.message || 'Bağlantı kurulamadı.');
                }
            });
        });
    }

    if (syncButton.length) {
        syncButton.on('click', function (e) {
            e.preventDefault();
            const button = $(this);
            handleAjax('reseller_api_sync_products', button, function (success, data) {
                if (success) {
                    showNotice('success', data.message || 'Ürünler güncellendi.');
                } else {
                    showNotice('error', data.message || 'Ürünler alınamadı.');
                }
            });
        });
    }

    if (ordersButton.length) {
        ordersButton.on('click', function (e) {
            e.preventDefault();
            const button = $(this);
            handleAjax('reseller_api_fetch_orders', button, function (success, data) {
                if (!success) {
                    showNotice('error', data.message || 'Siparişler alınamadı.');
                    return;
                }

                ordersTable.empty();
                if (!data.orders || !data.orders.length) {
                    ordersTable.append('<tr><td colspan="4">Sipariş bulunamadı.</td></tr>');
                    return;
                }

                data.orders.forEach(function (order) {
                    ordersTable.append('<tr>' +
                        '<td>' + (order.id || '-') + '</td>' +
                        '<td>' + (order.product_title || '-') + '</td>' +
                        '<td>' + (order.status || '-') + '</td>' +
                        '<td>' + (order.created_at || '-') + '</td>' +
                        '</tr>');
                });
            });
        });
    }
});

