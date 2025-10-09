(function () {
    'use strict';

    var DESKTOP_QUERY = window.matchMedia('(min-width: 992px)');

    function initCarousel(root) {
        if (!root) { return; }

        var slides = Array.prototype.slice.call(root.querySelectorAll('[data-carousel-slide]'));
        if (!slides.length) { return; }

        var indicators = Array.prototype.slice.call(root.querySelectorAll('[data-carousel-indicator]'));
        var nextButton = root.querySelector('[data-carousel-next]');
        var prevButton = root.querySelector('[data-carousel-prev]');
        var interval = parseInt(root.getAttribute('data-interval'), 10);
        if (isNaN(interval) || interval <= 0) { interval = 4000; }

        var activeIndex = slides.findIndex(function (slide) { return slide.classList.contains('is-active'); });
        if (activeIndex < 0) { activeIndex = 0; slides[0].classList.add('is-active'); }

        function setActive(nextIndex) {
            if (nextIndex === activeIndex || nextIndex < 0 || nextIndex >= slides.length) { return; }
            slides[activeIndex].classList.remove('is-active');
            slides[nextIndex].classList.add('is-active');
            if (indicators[activeIndex]) { indicators[activeIndex].classList.remove('is-active'); }
            if (indicators[nextIndex]) { indicators[nextIndex].classList.add('is-active'); }
            activeIndex = nextIndex;
        }

        function go(step) {
            var target = (activeIndex + step + slides.length) % slides.length;
            setActive(target);
        }

        var timer = null;
        function start() {
            stop();
            timer = window.setInterval(function () { go(1); }, interval);
        }
        function stop() {
            if (timer !== null) {
                window.clearInterval(timer);
                timer = null;
            }
        }

        if (nextButton) { nextButton.addEventListener('click', function () { go(1); start(); }); }
        if (prevButton) { prevButton.addEventListener('click', function () { go(-1); start(); }); }
        indicators.forEach(function (indicator) {
            indicator.addEventListener('click', function () {
                var index = parseInt(indicator.getAttribute('data-carousel-index'), 10);
                if (!isNaN(index)) { setActive(index); start(); }
            });
        });

        var pointerStart = null;
        function onPointerDown(event) {
            pointerStart = event.clientX || (event.touches && event.touches[0] ? event.touches[0].clientX : null);
            if (pointerStart !== null) { stop(); }
        }
        function onPointerMove(event) {
            if (pointerStart === null) { return; }
            var current = event.clientX || (event.touches && event.touches[0] ? event.touches[0].clientX : null);
            if (current === null) { return; }
            var delta = current - pointerStart;
            if (Math.abs(delta) > 60) {
                go(delta > 0 ? -1 : 1);
                pointerStart = current;
            }
        }
        function onPointerUp() {
            pointerStart = null;
            start();
        }

        root.addEventListener('mousedown', onPointerDown);
        root.addEventListener('touchstart', onPointerDown, { passive: true });
        root.addEventListener('mousemove', onPointerMove);
        root.addEventListener('touchmove', onPointerMove, { passive: true });
        root.addEventListener('mouseup', onPointerUp);
        root.addEventListener('mouseleave', onPointerUp);
        root.addEventListener('touchend', onPointerUp);
        root.addEventListener('mouseenter', stop);
        root.addEventListener('mouseleave', start);

        start();
    }

    document.querySelectorAll('[data-carousel]').forEach(initCarousel);

    (function initHeaderShadow() {
        var header = document.querySelector('[data-store-header]');
        if (!header) { return; }
        function toggleShadow() {
            if (window.scrollY > 6) {
                header.classList.add('has-shadow');
            } else {
                header.classList.remove('has-shadow');
            }
        }
        toggleShadow();
        window.addEventListener('scroll', toggleShadow, { passive: true });
    })();

    (function initMegaNav() {
        var items = Array.prototype.slice.call(document.querySelectorAll('.store-nav__item'));
        if (!items.length) { return; }

        function closeAll(except) {
            items.forEach(function (item) {
                if (item !== except) {
                    item.classList.remove('is-open');
                    var button = item.querySelector('[data-nav-button]');
                    var dropdown = item.querySelector('.store-nav__dropdown');
                    if (button) { button.setAttribute('aria-expanded', 'false'); }
                    if (dropdown) { dropdown.setAttribute('hidden', 'hidden'); }
                }
            });
        }

        function openItem(item) {
            if (!item) { return; }
            closeAll(item);
            var button = item.querySelector('[data-nav-button]');
            var dropdown = item.querySelector('.store-nav__dropdown');
            item.classList.add('is-open');
            if (button) { button.setAttribute('aria-expanded', 'true'); }
            if (dropdown) { dropdown.removeAttribute('hidden'); }
        }

        function closeItem(item) {
            if (!item) { return; }
            item.classList.remove('is-open');
            var button = item.querySelector('[data-nav-button]');
            var dropdown = item.querySelector('.store-nav__dropdown');
            if (button) { button.setAttribute('aria-expanded', 'false'); }
            if (dropdown) { dropdown.setAttribute('hidden', 'hidden'); }
        }

        items.forEach(function (item) {
            var button = item.querySelector('[data-nav-button]');
            if (!button) { return; }

            button.addEventListener('click', function (event) {
                event.preventDefault();
                if (item.classList.contains('is-open')) {
                    closeItem(item);
                } else {
                    openItem(item);
                }
            });

            item.addEventListener('mouseenter', function () {
                if (!DESKTOP_QUERY.matches) { return; }
                openItem(item);
            });

            item.addEventListener('mouseleave', function () {
                if (!DESKTOP_QUERY.matches) { return; }
                closeItem(item);
            });
        });

        document.addEventListener('click', function (event) {
            var target = event.target;
            var isInside = items.some(function (item) { return item.contains(target); });
            if (!isInside) {
                closeAll(null);
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeAll(null);
            }
        });
    })();

    (function initSearchSuggest() {
        var input = document.querySelector('[data-search-suggest]');
        if (!input) { return; }
        var endpoint = input.getAttribute('data-search-endpoint');
        if (!endpoint) { return; }

        var suggestions = input.parentElement.querySelector('.store-search__suggestions');
        var controller = null;
        var activeIndex = -1;
        var items = [];

        function hideSuggestions() {
            if (suggestions) {
                suggestions.innerHTML = '';
                suggestions.setAttribute('hidden', 'hidden');
            }
            activeIndex = -1;
            items = [];
        }

        function showSuggestions(html) {
            if (!suggestions) { return; }
            suggestions.innerHTML = html;
            suggestions.removeAttribute('hidden');
            items = Array.prototype.slice.call(suggestions.querySelectorAll('[data-suggest-item]'));
            activeIndex = -1;
        }

        function highlight(index) {
            if (!items.length) { return; }
            items.forEach(function (el, idx) {
                if (idx === index) {
                    el.classList.add('is-active');
                    el.setAttribute('aria-selected', 'true');
                } else {
                    el.classList.remove('is-active');
                    el.setAttribute('aria-selected', 'false');
                }
            });
            activeIndex = index;
        }

        var debounceTimer = null;
        input.addEventListener('input', function () {
            var value = input.value.trim();
            if (debounceTimer) { window.clearTimeout(debounceTimer); }
            if (value.length < 2) {
                hideSuggestions();
                return;
            }
            debounceTimer = window.setTimeout(function () {
                if (controller) { controller.abort(); }
                controller = new AbortController();
                fetch(endpoint + '?q=' + encodeURIComponent(value), { signal: controller.signal })
                    .then(function (response) {
                        if (!response.ok) { throw new Error('Arama başarısız'); }
                        return response.json();
                    })
                    .then(function (data) {
                        if (!data || !Array.isArray(data.results) || !data.results.length) {
                            hideSuggestions();
                            return;
                        }
                        var html = '';
                        data.results.forEach(function (item) {
                            var type = item.type || 'product';
                            html += suggestionRow(item.label || '', item.url || '#', item.price_formatted || null, type === 'category');
                        });
                        if (html === '') {
                            html = '<button type="button" class="text-muted" data-suggest-item disabled>Sonuç bulunamadı</button>';
                        }
                        showSuggestions(html);
                    })
                    .catch(function () {
                        hideSuggestions();
                    });
            }, 250);
        });

        function suggestionRow(name, url, price, isCategory) {
            var label = name ? String(name) : '';
            var priceHtml = price ? '<span class="ms-auto text-muted">' + price + '</span>' : '';
            var icon = isCategory ? 'bi-folder2-open' : 'bi-bag';
            return '<a href="' + escapeAttribute(url || '#') + '" data-suggest-item role="option"><i class="bi ' + icon + '"></i><span>' + escapeHtml(label) + '</span>' + priceHtml + '</a>';
        }

        input.addEventListener('keydown', function (event) {
            if (!items.length) { return; }
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                highlight((activeIndex + 1) % items.length);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                highlight((activeIndex - 1 + items.length) % items.length);
            } else if (event.key === 'Enter' && activeIndex >= 0 && items[activeIndex]) {
                event.preventDefault();
                window.location.href = items[activeIndex].getAttribute('href');
            } else if (event.key === 'Escape') {
                hideSuggestions();
            }
        });

        document.addEventListener('click', function (event) {
            if (!suggestions || suggestions.contains(event.target) || event.target === input) {
                return;
            }
            hideSuggestions();
        });
    })();

    var miniCart = (function initMiniCart() {
        var root = document.querySelector('[data-mini-cart]');
        if (!root) {
            return {
                open: function () {}
            };
        }

        var body = root.querySelector('[data-mini-cart-body]');
        var summary = root.querySelector('[data-mini-cart-summary]');

        function close() {
            root.classList.remove('is-visible');
            root.setAttribute('hidden', 'hidden');
            document.body.style.overflow = '';
        }

        root.addEventListener('click', function (event) {
            if (event.target && event.target.hasAttribute('data-mini-cart-close')) {
                close();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && root.classList.contains('is-visible')) {
                close();
            }
        });

        function open(payload) {
            if (!body) {
                return;
            }

            var item = payload && payload.item ? payload.item : null;
            if (item) {
                var template = '' +
                    '<div class="store-mini-cart__item">' +
                        '<img src="' + escapeAttribute(item.image || '') + '" alt="' + escapeAttribute(item.name || 'Ürün') + '">' +
                        '<div>' +
                            '<a class="fw-semibold d-block mb-1" href="' + escapeAttribute(item.url || '#') + '">' + escapeHtml(item.name || 'Ürün') + '</a>' +
                            '<div class="text-muted small">' + escapeHtml(item.price || '') + ' · ' + escapeHtml(item.quantity_label || '') + '</div>' +
                        '</div>' +
                    '</div>';
                body.innerHTML = template;
            } else {
                body.innerHTML = '<p class="text-muted mb-0">Sepete ürün eklendi.</p>';
            }

            if (summary) {
                var total = payload && payload.cart_total_formatted ? payload.cart_total_formatted : '';
                summary.textContent = total !== '' ? 'Sepet toplamı: ' + total : '';
            }

            root.removeAttribute('hidden');
            root.classList.add('is-visible');
            document.body.style.overflow = 'hidden';
        }

        return {
            open: open,
            close: close
        };
    })();

    window.storeMiniCart = miniCart;

    (function initCartForms() {
        var badge = document.querySelector('[data-cart-count]');

        function updateBadge(count) {
            if (badge) {
                badge.textContent = String(count);
            }
            document.querySelectorAll('[data-cart-count]').forEach(function (el) {
                el.textContent = String(count);
            });
        }

        function showToast(type, message) {
            var container = document.querySelector('.store-toast-container');
            if (!container) {
                container = document.createElement('div');
                container.className = 'store-toast-container';
                document.body.appendChild(container);
            }
            var toast = document.createElement('div');
            toast.className = 'store-toast store-toast--' + type;
            toast.textContent = message;
            container.appendChild(toast);
            window.setTimeout(function () {
                toast.classList.add('is-hiding');
                toast.style.opacity = '0';
                window.setTimeout(function () {
                    toast.remove();
                }, 300);
            }, 2800);
        }

        function handleQuantityButtons(root) {
            if (!root) { return; }
            var decrement = root.querySelector('[data-cart-decrement]');
            var increment = root.querySelector('[data-cart-increment]');
            var input = root.querySelector('input[name="quantity"]');
            if (!input) { return; }
            if (decrement) {
                decrement.addEventListener('click', function () {
                    var value = parseInt(input.value, 10) || 1;
                    value = Math.max(1, value - 1);
                    input.value = String(value);
                });
            }
            if (increment) {
                increment.addEventListener('click', function () {
                    var value = parseInt(input.value, 10) || 1;
                    input.value = String(value + 1);
                });
            }
        }

        document.querySelectorAll('[data-cart-add]').forEach(function (form) {
            handleQuantityButtons(form);
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                submitCartForm(form, 'cart/add');
            });
        });

        document.querySelectorAll('[data-cart-update]').forEach(function (form) {
            handleQuantityButtons(form);
            var submit = form.closest('tr') ? form.closest('tr').querySelector('[data-cart-update-submit]') : null;
            if (submit) {
                submit.addEventListener('click', function () {
                    submitCartForm(form, 'cart/update');
                });
            }
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                submitCartForm(form, 'cart/update');
            });
        });

        document.querySelectorAll('[data-cart-remove]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                submitCartForm(form, 'cart/remove', function (response) {
                    var row = form.closest('tr');
                    if (row) { row.remove(); }
                    if (response.cart_count === 0) {
                        window.location.reload();
                    }
                });
            });
        });

        function submitCartForm(form, endpoint, onSuccess) {
            var action = form.getAttribute('action') || ('/' + endpoint);
            var formData = new FormData(form);
            fetch(action, {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: formData
            })
                .then(function (response) { return response.json(); })
                .then(function (json) {
                    if (!json) { throw new Error('Beklenmeyen yanıt'); }
                    updateBadge(json.cart_count || 0);
                    showToast(json.success ? 'success' : 'error', json.message || (json.success ? 'İşlem tamamlandı.' : 'İşlem başarısız.'));
                    if (json.success && typeof onSuccess === 'function') {
                        onSuccess(json);
                    }
                    if (json.success && endpoint === 'cart/add') {
                        if (window.storeMiniCart && typeof window.storeMiniCart.open === 'function') {
                            window.storeMiniCart.open(json);
                        }
                        var modalElement = document.getElementById('storeMobileMenu');
                        var Offcanvas = window.bootstrap && window.bootstrap.Offcanvas;
                        if (modalElement && Offcanvas) {
                            var instance = Offcanvas.getInstance(modalElement);
                            if (instance) {
                                instance.hide();
                            }
                        }
                    }
                })
                .catch(function () {
                    showToast('error', 'İşlem sırasında hata oluştu.');
                });
        }
    })();

    (function initShowcaseLoad() {
        window.requestAnimationFrame(function () {
            document.querySelectorAll('[data-product-grid]').forEach(function (grid) {
                grid.classList.remove('is-loading');
            });
        });
    })();

    function escapeHtml(value) {
        return String(value).replace(/[&<>"]/g, function (char) {
            switch (char) {
                case '&': return '&amp;';
                case '<': return '&lt;';
                case '>': return '&gt;';
                case '"': return '&quot;';
                default: return char;
            }
        });
    }

    function escapeAttribute(value) {
        return String(value).replace(/[&"<]/g, function (char) {
            if (char === '&') { return '&amp;'; }
            if (char === '"') { return '&quot;'; }
            return '&lt;';
        });
    }
})();
