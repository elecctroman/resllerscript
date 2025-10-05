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

    function handleAjax(action, extraData, successCallback) {
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

        $.post(ResellerAPI.ajaxUrl, data)
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
                }
            })
            .fail(function () {
                var msg = window.resellerApiStrings && window.resellerApiStrings.error ? window.resellerApiStrings.error : 'Unexpected error.';
                logMessage(msg, 'error');
            });
    }

    $(document).on('click', '#reseller-api-test', function (event) {
        event.preventDefault();
        handleAjax('reseller_api_test_connection');
    });

    $(document).on('click', '#reseller-api-sync', function (event) {
        event.preventDefault();
        handleAjax('reseller_api_sync_products');
    });

    $(document).on('click', '#reseller-api-balance', function (event) {
        event.preventDefault();
        handleAjax('reseller_api_fetch_balance');
    });

    $(document).on('click', '#reseller-api-orders', function (event) {
        event.preventDefault();
        handleAjax('reseller_api_fetch_orders');
    });
})(jQuery);

