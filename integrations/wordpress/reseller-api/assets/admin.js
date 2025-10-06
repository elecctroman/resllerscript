(function ($) {
    'use strict';

    function logMessage(message, type) {
        var consoleBox = $('#reseller-api-console');
        if (!consoleBox.length) {
            return;
        }

        var className = 'notice notice-' + (type || 'info');
        var time = new Date().toLocaleTimeString();
        consoleBox.prepend('<div class="' + className + '"><p><strong>' + time + ':</strong> ' + message + '</p></div>');
    }

    function escapeHtml(value) {
        return $('<div>').text(value).html();
    }

    function handleAjax(action, extraData, successCallback, failureCallback) {
        if (typeof ResellerAPI === 'undefined') {
            return;
        }

        var data = $.extend({
            action: action,
            nonce: ResellerAPI.nonce
        }, extraData || {});

        if (window.resellerApiStrings && window.resellerApiStrings.sending) {
            logMessage(window.resellerApiStrings.sending, 'info');
        } else {
            logMessage('Processing request…', 'info');
        }

        return $.post(ResellerAPI.ajaxUrl, data)
            .done(function (response) {
                if (response && response.success) {
                    var raw = typeof response.data === 'object' ? JSON.stringify(response.data, null, 2) : String(response.data || '');
                    logMessage('<pre>' + escapeHtml(raw) + '</pre>', 'success');
                    if (typeof successCallback === 'function') {
                        successCallback(response.data);
                    }
                } else {
                    var message = response && response.data && response.data.message ? response.data.message : (window.resellerApiStrings && window.resellerApiStrings.error ? window.resellerApiStrings.error : 'Unexpected error.');
                    logMessage(message, 'error');
                    if (typeof failureCallback === 'function') {
                        failureCallback(response);
                    }
                }
            })
            .fail(function () {
                var msg = window.resellerApiStrings && window.resellerApiStrings.error ? window.resellerApiStrings.error : 'Unexpected error.';
                logMessage(msg, 'error');
                if (typeof failureCallback === 'function') {
                    failureCallback();
                }
            });
    }

    function renderStatus(success) {
        var statusEl = $('[data-reseller-api="status"]');
        var hintEl = $('[data-reseller-api="status-hint"]');
        if (!statusEl.length) {
            return;
        }

        statusEl.removeClass('status-ok status-error');
        if (success) {
            statusEl.text(window.resellerApiStrings && window.resellerApiStrings.connection_ok ? window.resellerApiStrings.connection_ok : 'Connection verified.');
            statusEl.addClass('status-ok');
            if (hintEl.length) {
                hintEl.text(ResellerAPI.homeDomain);
            }
        } else {
            statusEl.text(window.resellerApiStrings && window.resellerApiStrings.connection_fail ? window.resellerApiStrings.connection_fail : 'Connection failed.');
            statusEl.addClass('status-error');
        }
    }

    function renderBalance(data) {
        var balanceEl = $('[data-reseller-api="balance"]');
        var emailEl = $('[data-reseller-api="balance-email"]');
        if (!balanceEl.length) {
            return;
        }

        if (data && typeof data.balance !== 'undefined') {
            balanceEl.text(window.resellerApiStrings && window.resellerApiStrings.balance_label ? window.resellerApiStrings.balance_label + ': ' + data.balance : 'Balance: ' + data.balance);
        }
        if (emailEl.length && data && data.email) {
            emailEl.text(data.email);
        }
    }

    function renderOrders(response) {
        var table = $('[data-reseller-api="orders-table"]');
        if (!table.length) {
            return;
        }

        var body = table.find('tbody');
        body.empty();

        var rows = [];
        if (response && response.data) {
            if ($.isArray(response.data.orders)) {
                rows = response.data.orders;
            } else if ($.isArray(response.data)) {
                rows = response.data;
            }
        }

        if (!rows.length) {
            body.append('<tr class="placeholder"><td colspan="5">' + (window.resellerApiStrings && window.resellerApiStrings.orders_label ? window.resellerApiStrings.orders_label : 'No orders available.') + '</td></tr>');
            return;
        }

        rows.slice(0, 10).forEach(function (item) {
            var orderId = item.id || item.order_id || '';
            var product = item.product_name || item.product_title || '';
            var status = item.status || '';
            var total = item.price || item.amount || '';
            var created = item.created_at || '';

            body.append('<tr>' +
                '<td>' + escapeHtml(orderId) + '</td>' +
                '<td>' + escapeHtml(product) + '</td>' +
                '<td>' + escapeHtml(status) + '</td>' +
                '<td>' + escapeHtml(total) + '</td>' +
                '<td>' + escapeHtml(created) + '</td>' +
            '</tr>');
        });
    }

    function dispatchAction(action) {
        switch (action) {
            case 'test':
                handleAjax('reseller_api_test_connection', {}, function (data) {
                    renderStatus(data && data.success);
                }, function () {
                    renderStatus(false);
                });
                break;
            case 'sync':
                handleAjax('reseller_api_sync_products');
                break;
            case 'balance':
                handleAjax('reseller_api_fetch_balance', {}, function (data) {
                    renderBalance(data);
                });
                break;
            case 'orders':
                handleAjax('reseller_api_fetch_orders', {}, function (data) {
                    renderOrders(data);
                });
                break;
        }
    }

    $(document).on('click', '[data-reseller-api-action]', function (event) {
        event.preventDefault();
        var action = $(this).data('reseller-api-action');
        if (action) {
            dispatchAction(String(action));
        }
    });

    $(function () {
        if (typeof ResellerAPI === 'undefined') {
            return;
        }

        var wrap = $('.reseller-api-wrap');
        if (!wrap.length) {
            return;
        }

        var statusEl = $('[data-reseller-api="status"]');
        if (statusEl.length) {
            statusEl.text(window.resellerApiStrings && window.resellerApiStrings.sending ? window.resellerApiStrings.sending : 'Checking connection…');
        }

        // Auto-refresh critical widgets when the settings page is opened.
        dispatchAction('test');
        dispatchAction('balance');
        dispatchAction('orders');
    });
})(jQuery);

