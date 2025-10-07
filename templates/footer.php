<?php

use App\Helpers;

$languageBufferActive = !empty($GLOBALS['app_lang_buffer_started']);
$pageScripts = isset($GLOBALS['pageScripts']) && is_array($GLOBALS['pageScripts']) ? $GLOBALS['pageScripts'] : array();
$pageInlineScripts = isset($GLOBALS['pageInlineScripts']) && is_array($GLOBALS['pageInlineScripts']) ? $GLOBALS['pageInlineScripts'] : array();
?>
            </div>
        </div>
    </main>
    <footer class="app-footer text-center py-4 mt-auto">
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
        var app = window.App = window.App || {};

        if (window.bootstrap) {
            var navbarElement = document.getElementById('appNavbar');
            if (navbarElement) {
                var dropdownToggles = Array.prototype.slice.call(navbarElement.querySelectorAll('.dropdown-toggle'));
                dropdownToggles.forEach(function (toggle) {
                    toggle.addEventListener('keydown', function (event) {
                        var key = event.key || event.code;
                        if (key === ' ' || key === 'Spacebar') {
                            key = 'Space';
                        }
                        if (key === 'Enter' || key === 'Space' || key === 'ArrowDown') {
                            event.preventDefault();
                            var dropdown = bootstrap.Dropdown.getOrCreateInstance(toggle, { autoClose: 'outside' });
                            dropdown.show();
                            var menu = toggle.nextElementSibling;
                            if (menu) {
                                var firstItem = menu.querySelector('.dropdown-item');
                                if (firstItem) {
                                    setTimeout(function () {
                                        firstItem.focus();
                                    }, 10);
                                }
                            }
                        } else if (key === 'ArrowUp') {
                            event.preventDefault();
                            var dropdownUp = bootstrap.Dropdown.getOrCreateInstance(toggle, { autoClose: 'outside' });
                            dropdownUp.show();
                            var menuUp = toggle.nextElementSibling;
                            if (menuUp) {
                                var items = menuUp.querySelectorAll('.dropdown-item');
                                if (items.length) {
                                    setTimeout(function () {
                                        items[items.length - 1].focus();
                                    }, 10);
                                }
                            }
                        }
                    });

                    var menuElement = toggle.nextElementSibling;
                    if (menuElement && !menuElement.hasAttribute('data-app-dropdown-keyboard')) {
                        menuElement.setAttribute('data-app-dropdown-keyboard', 'true');
                        menuElement.addEventListener('keydown', function (event) {
                            if (event.key === 'Escape' || event.key === 'Esc') {
                                event.preventDefault();
                                var dropdownInstance = bootstrap.Dropdown.getOrCreateInstance(toggle, { autoClose: 'outside' });
                                dropdownInstance.hide();
                                toggle.focus();
                            }
                        });
                    }
                });
            }
        }

        function toPairs(dictionary) {
            if (!dictionary) {
                return [];
            }
            return Object.keys(dictionary).filter(function (key) {
                return dictionary[key];
            }).sort(function (a, b) {
                return b.length - a.length;
            }).map(function (key) {
                return { source: key, target: dictionary[key] };
            });
        }

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
            var pairs = toPairs(Object.assign({}, fallback || {}, translations || {}));
            if (!pairs.length || !document.body) {
                return;
            }

            var skipTags = { SCRIPT: true, STYLE: true, NOSCRIPT: true, IFRAME: true, OBJECT: true, SVG: true, CANVAS: true, TEMPLATE: true };
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

        var languageSelect = document.getElementById('appLanguageSelect');
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

        if (app.translations && Object.keys(app.translations).length) {
            app.applyTranslations(app.translations, app.translationFallback || {});
        }
    });
</script>
</body>
</html>
<?php
if ($languageBufferActive) {
    ob_end_flush();
    unset($GLOBALS['app_lang_buffer_started']);
}
?>
