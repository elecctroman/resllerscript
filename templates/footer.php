<?php

use App\Helpers;

$languageBufferActive = !empty($GLOBALS['app_lang_buffer_started']);
$pageScripts = isset($GLOBALS['pageScripts']) && is_array($GLOBALS['pageScripts']) ? $GLOBALS['pageScripts'] : array();
$pageInlineScripts = isset($GLOBALS['pageInlineScripts']) && is_array($GLOBALS['pageInlineScripts']) ? $GLOBALS['pageInlineScripts'] : array();
?>
        </main>
        <footer class="app-footer">
            <small>© <?= date('Y') ?> <?= Helpers::sanitize(Helpers::siteName()) ?></small>
        </footer>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php foreach ($pageScripts as $script): ?>
    <script src="<?= htmlspecialchars($script, ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endforeach; ?>
<?php foreach ($pageInlineScripts as $inlineScript): ?>
    <script><?= $inlineScript ?></script>
<?php endforeach; ?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var sidebar = document.getElementById('appSidebar');
        if (!sidebar) {
            return;
        }

        var body = document.body;
        var toggles = document.querySelectorAll('[data-sidebar-toggle]');
        var closers = document.querySelectorAll('[data-sidebar-close]');
        var sidebarLinks = sidebar.querySelectorAll('a');

        var closeSidebar = function () {
            if (!body.classList.contains('sidebar-open')) {
                return;
            }
            body.classList.remove('sidebar-open');
            toggles.forEach(function (toggle) {
                toggle.setAttribute('aria-expanded', 'false');
            });
        };

        var openSidebar = function (trigger) {
            body.classList.add('sidebar-open');
            toggles.forEach(function (toggle) {
                toggle.setAttribute('aria-expanded', toggle === trigger ? 'true' : 'false');
            });
        };

        toggles.forEach(function (toggle) {
            toggle.addEventListener('click', function () {
                if (body.classList.contains('sidebar-open')) {
                    closeSidebar();
                } else {
                    openSidebar(toggle);
                }
            });
        });

        closers.forEach(function (closer) {
            closer.addEventListener('click', closeSidebar);
        });

        sidebarLinks.forEach(function (link) {
            link.addEventListener('click', function () {
                if (window.innerWidth < 992) {
                    closeSidebar();
                }
            });
        });

        document.addEventListener('keyup', function (event) {
            if (event.key === 'Escape') {
                closeSidebar();
            }
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth >= 992) {
                closeSidebar();
            }
    });
});
</script>
<script>
    (function () {
        var app = window.App = window.App || {};

        function toNumber(value, fallback) {
            var parsed = parseFloat(value);
            return Number.isFinite(parsed) ? parsed : (fallback || 0);
        }

        function normalizeCurrencyMap(map) {
            var normalized = {};
            if (!map) { return normalized; }
            Object.keys(map).forEach(function (key) {
                var entry = map[key] || {};
                var code = String(entry.code || key).toUpperCase();
                normalized[code] = {
                    code: code,
                    symbol: entry.symbol ? String(entry.symbol) : '',
                    rate: toNumber(entry.rate, 1),
                    decimals: Number.isFinite(parseInt(entry.decimals, 10)) ? parseInt(entry.decimals, 10) : 2,
                    is_default: entry.is_default === true || entry.is_default === 1,
                    is_active: entry.is_active === undefined ? true : !!entry.is_active,
                };
            });

            return normalized;
        }

        app.currency = app.currency || {};
        app.currency.map = normalizeCurrencyMap(app.currency.map || {});
        app.currency.defaultSymbols = app.currency.defaultSymbols || { USD: '$', EUR: '€', TRY: '₺', GBP: '£' };
        app.currency.active = (app.currency.active || '').toUpperCase();
        app.currency.default = (app.currency.default || app.currency.active || 'USD').toUpperCase();

        function applyPairs(value, pairs) {
            if (!value || !pairs || !pairs.length) {
                return value;
            }

            var output = value;
            pairs.forEach(function (pair) {
                if (!pair.source || !pair.target || output.indexOf(pair.source) === -1) {
                    return;
                }
                output = output.split(pair.source).join(pair.target);
            });

            return output;
        }

        app.applyTranslations = function (translations, fallback) {
            translations = translations || {};
            fallback = fallback || {};

            var combined = {};
            Object.keys(fallback).forEach(function (key) {
                if (fallback[key]) {
                    combined[key] = fallback[key];
                }
            });
            Object.keys(translations).forEach(function (key) {
                if (translations[key]) {
                    combined[key] = translations[key];
                }
            });

            var keys = Object.keys(combined);
            if (!keys.length) {
                return;
            }

            keys.sort(function (a, b) {
                return b.length - a.length;
            });

            var pairs = keys.map(function (key) {
                return { source: key, target: combined[key] };
            });

            var skipTags = { SCRIPT: true, STYLE: true, NOSCRIPT: true, IFRAME: true, OBJECT: true, SVG: true, CANVAS: true, TEMPLATE: true };

            if (document.body) {
                var walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, {
                    acceptNode: function (node) {
                        if (!node || !node.nodeValue || !node.nodeValue.trim()) {
                            return NodeFilter.FILTER_REJECT;
                        }

                        var parent = node.parentNode;
                        while (parent && parent.nodeType === Node.TEXT_NODE) {
                            parent = parent.parentNode;
                        }

                        if (!parent || skipTags[parent.nodeName]) {
                            return NodeFilter.FILTER_REJECT;
                        }

                        return NodeFilter.FILTER_ACCEPT;
                    }
                });

                while (walker.nextNode()) {
                    var text = walker.currentNode.nodeValue;
                    var translated = applyPairs(text, pairs);
                    if (translated !== text) {
                        walker.currentNode.nodeValue = translated;
                    }
                }
            }

            ['title', 'placeholder', 'aria-label', 'aria-placeholder', 'data-label'].forEach(function (attribute) {
                document.querySelectorAll('[' + attribute + ']').forEach(function (element) {
                    var original = element.getAttribute(attribute);
                    if (!original) {
                        return;
                    }

                    var translated = applyPairs(original, pairs);
                    if (translated !== original) {
                        element.setAttribute(attribute, translated);
                    }
                });
            });

            document.title = applyPairs(document.title, pairs);
        };

        function symbolFor(currency) {
            var code = (currency || '').toUpperCase();
            if (app.currency.map[code] && app.currency.map[code].symbol) {
                return app.currency.map[code].symbol;
            }
            if (app.currency.defaultSymbols && app.currency.defaultSymbols[code]) {
                return app.currency.defaultSymbols[code];
            }
            return code;
        }

        function convertAmount(amount, from, to) {
            var source = (from || app.currency.default).toUpperCase();
            var target = (to || app.currency.default).toUpperCase();
            var base = app.currency.default;

            if (!app.currency.map[source]) {
                source = base;
            }
            if (!app.currency.map[target]) {
                target = base;
            }

            if (source === target) {
                return amount;
            }

            var value = amount;
            if (source !== base) {
                var rateFrom = toNumber(app.currency.map[source].rate, 1);
                value = value * rateFrom;
            }

            if (target === base) {
                return value;
            }

            var rateTo = toNumber(app.currency.map[target].rate, 1);
            if (rateTo <= 0) {
                rateTo = 1;
            }

            return value / rateTo;
        }

        function formatAmount(amount, currency) {
            var code = (currency || app.currency.active || app.currency.default).toUpperCase();
            var decimals = 2;
            if (app.currency.map[code] && Number.isFinite(app.currency.map[code].decimals)) {
                decimals = Math.max(0, Math.min(6, parseInt(app.currency.map[code].decimals, 10)));
            }

            var fixed = Number(amount).toFixed(decimals);
            var parts = fixed.split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');

            var formatted = decimals > 0 ? parts[0] + '.' + parts[1] : parts[0];

            return symbolFor(code) + formatted;
        }

        app.refreshMoney = function () {
            if (!document.body) {
                return;
            }

            var target = (app.currency.active || app.currency.default || 'USD').toUpperCase();
            if (!app.currency.map[target]) {
                target = app.currency.default;
            }

            var base = (app.currency.default || 'USD').toUpperCase();

            document.querySelectorAll('.app-money').forEach(function (element) {
                var baseAmount = toNumber(element.getAttribute('data-money-base-amount'), 0);
                var baseCurrency = (element.getAttribute('data-money-base') || base).toUpperCase();
                var converted = convertAmount(baseAmount, baseCurrency, target);
                element.textContent = formatAmount(converted, target);
                element.setAttribute('data-money-target', target);
                element.setAttribute('data-money-current-amount', converted.toFixed(6));
            });

            var symbol = symbolFor(target);
            document.querySelectorAll('[data-currency-symbol]').forEach(function (element) {
                element.textContent = symbol;
            });

            window.dispatchEvent(new CustomEvent('app:currency-change', { detail: { currency: target } }));
        };

        var languageSelect = document.getElementById('appLanguageSelect');
        var currencySelect = document.getElementById('appCurrencySelect');

        if (languageSelect) {
            languageSelect.addEventListener('change', function () {
                var locale = this.value;
                if (!locale || locale === app.locale) {
                    return;
                }

                var select = this;
                select.disabled = true;

                fetch('/language.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ locale: locale, csrf_token: app.csrfToken })
                }).then(function (response) {
                    return response.json();
                }).then(function (data) {
                    if (!data || !data.success) {
                        throw new Error(data && data.message ? data.message : 'Dil güncellenemedi.');
                    }

                    app.locale = data.locale || locale;
                    if (data.csrf_token) {
                        app.csrfToken = data.csrf_token;
                    }
                    if (data.translations) {
                        app.translations = data.translations;
                    }
                    if (data.fallback) {
                        app.translationFallback = data.fallback;
                    }
                    if (data.available_locales && Array.isArray(data.available_locales)) {
                        var optionsHtml = data.available_locales.filter(function (item) {
                            return item && item.is_active !== 0 && item.code;
                        }).map(function (item) {
                            var code = String(item.code).toLowerCase();
                            var label = item.native_name || item.name || code.toUpperCase();
                            var selected = code === app.locale ? ' selected' : '';
                            var safeCode = code.replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                            var safeLabel = String(label).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                            return '<option value="' + safeCode + '"' + selected + '>' + safeLabel + '</option>';
                        }).join('');
                        if (optionsHtml) {
                            select.innerHTML = optionsHtml;
                        }
                    }
                    if (data.html_locale) {
                        document.documentElement.setAttribute('lang', data.html_locale);
                    }
                    app.applyTranslations(app.translations || {}, app.translationFallback || {});
                    window.dispatchEvent(new CustomEvent('app:language-change', { detail: { locale: app.locale } }));
                }).catch(function (error) {
                    alert(error && error.message ? error.message : 'Dil güncellenemedi.');
                    select.value = app.locale || select.getAttribute('data-initial-locale') || select.value;
                }).finally(function () {
                    select.disabled = false;
                });
            });
        }

        if (currencySelect) {
            currencySelect.addEventListener('change', function () {
                var targetCurrency = this.value;
                if (!targetCurrency || targetCurrency.toUpperCase() === app.currency.active) {
                    return;
                }

                var select = this;
                select.disabled = true;

                fetch('/currency.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ currency: targetCurrency, csrf_token: app.csrfToken })
                }).then(function (response) {
                    return response.json();
                }).then(function (data) {
                    if (!data || !data.success) {
                        throw new Error(data && data.message ? data.message : 'Para birimi güncellenemedi.');
                    }

                    if (data.csrf_token) {
                        app.csrfToken = data.csrf_token;
                    }
                    if (data.currencies) {
                        app.currency.map = normalizeCurrencyMap(data.currencies);
                    }
                    if (data.default_currency) {
                        app.currency.default = String(data.default_currency).toUpperCase();
                    }
                    app.currency.active = String(data.currency || targetCurrency).toUpperCase();
                    select.value = app.currency.active;
                    app.refreshMoney();
                    window.dispatchEvent(new CustomEvent('app:currency-change', { detail: { currency: app.currency.active } }));
                }).catch(function (error) {
                    alert(error && error.message ? error.message : 'Para birimi güncellenemedi.');
                    select.value = app.currency.active || select.getAttribute('data-initial-currency') || select.value;
                }).finally(function () {
                    select.disabled = false;
                });
            });
        }

        if (app.translations && Object.keys(app.translations).length) {
            app.applyTranslations(app.translations, app.translationFallback || {});
        }

        app.refreshMoney();
    })();
</script>
</body>
</html>
<?php
if ($languageBufferActive) {
    ob_end_flush();
    unset($GLOBALS['app_lang_buffer_started']);
}
?>
