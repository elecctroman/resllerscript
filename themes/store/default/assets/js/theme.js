(function () {
    'use strict';

    function initCarousel(root) {
        if (!root) {
            return;
        }

        var slides = Array.prototype.slice.call(root.querySelectorAll('[data-carousel-slide]'));
        if (!slides.length) {
            return;
        }

        var track = root.querySelector('[data-carousel-track]');
        var nextButton = root.querySelector('[data-carousel-next]');
        var prevButton = root.querySelector('[data-carousel-prev]');
        var indicators = Array.prototype.slice.call(root.querySelectorAll('[data-carousel-indicator]'));
        var interval = parseInt(root.getAttribute('data-interval'), 10);
        if (isNaN(interval) || interval <= 0) {
            interval = 4000;
        }

        var index = slides.findIndex(function (slide) { return slide.classList.contains('is-active'); });
        if (index < 0) {
            index = 0;
            slides[0].classList.add('is-active');
        }

        function setActive(nextIndex) {
            if (nextIndex === index || nextIndex < 0 || nextIndex >= slides.length) {
                return;
            }

            slides[index].classList.remove('is-active');
            slides[nextIndex].classList.add('is-active');
            if (indicators[index]) {
                indicators[index].classList.remove('is-active');
            }
            if (indicators[nextIndex]) {
                indicators[nextIndex].classList.add('is-active');
            }
            index = nextIndex;
        }

        function go(step) {
            var nextIndex = (index + step + slides.length) % slides.length;
            setActive(nextIndex);
        }

        var timer = null;
        function start() {
            stop();
            timer = window.setInterval(function () {
                go(1);
            }, interval);
        }

        function stop() {
            if (timer !== null) {
                window.clearInterval(timer);
                timer = null;
            }
        }

        if (nextButton) {
            nextButton.addEventListener('click', function () {
                go(1);
                start();
            });
        }

        if (prevButton) {
            prevButton.addEventListener('click', function () {
                go(-1);
                start();
            });
        }

        indicators.forEach(function (indicator) {
            indicator.addEventListener('click', function () {
                var target = parseInt(indicator.getAttribute('data-carousel-index'), 10);
                if (!isNaN(target)) {
                    setActive(target);
                    start();
                }
            });
        });

        var pointerStart = null;
        var pointerActive = false;

        function onPointerDown(event) {
            pointerActive = true;
            pointerStart = event.clientX || (event.touches && event.touches[0] ? event.touches[0].clientX : 0);
            stop();
        }

        function onPointerMove(event) {
            if (!pointerActive || pointerStart === null) {
                return;
            }
            var current = event.clientX || (event.touches && event.touches[0] ? event.touches[0].clientX : 0);
            var delta = current - pointerStart;
            if (Math.abs(delta) > 60) {
                go(delta > 0 ? -1 : 1);
                pointerActive = false;
                start();
            }
        }

        function onPointerUp() {
            pointerActive = false;
            pointerStart = null;
            start();
        }

        if (track) {
            track.addEventListener('mousedown', onPointerDown);
            track.addEventListener('touchstart', onPointerDown, { passive: true });
            track.addEventListener('mousemove', onPointerMove);
            track.addEventListener('touchmove', onPointerMove, { passive: true });
            track.addEventListener('mouseup', onPointerUp);
            track.addEventListener('mouseleave', onPointerUp);
            track.addEventListener('touchend', onPointerUp);
        }

        root.addEventListener('mouseenter', stop);
        root.addEventListener('mouseleave', start);

        start();
    }

    document.querySelectorAll('[data-carousel]').forEach(function (carousel) {
        initCarousel(carousel);
    });

    (function initHeaderShadow() {
        var navbar = document.querySelector('[data-store-header]');
        if (!navbar) {
            return;
        }

        function handleShadow() {
            if (window.scrollY > 4) {
                navbar.classList.add('has-shadow');
            } else {
                navbar.classList.remove('has-shadow');
            }
        }

        handleShadow();
        window.addEventListener('scroll', handleShadow, { passive: true });
    })();

    document.querySelectorAll('.cat-chips, .collection-list').forEach(function (container) {
        container.addEventListener('wheel', function (event) {
            if (!event.shiftKey && Math.abs(event.deltaY) > Math.abs(event.deltaX)) {
                event.preventDefault();
                container.scrollLeft += event.deltaY;
            }
        }, { passive: false });
    });

    (function initMegaMenu() {
        var toggle = document.querySelector('[data-mega-toggle]');
        var panel = document.getElementById('megaMenuPanel');
        if (!toggle || !panel) {
            return;
        }

        var hoverTimeout = null;

        function openPanel() {
            panel.removeAttribute('hidden');
            panel.classList.add('show');
            toggle.setAttribute('aria-expanded', 'true');
            document.addEventListener('keydown', handleKeyClose);
            document.addEventListener('click', handleOutsideClick);
        }

        function closePanel() {
            panel.classList.remove('show');
            panel.setAttribute('hidden', 'hidden');
            toggle.setAttribute('aria-expanded', 'false');
            document.removeEventListener('keydown', handleKeyClose);
            document.removeEventListener('click', handleOutsideClick);
        }

        function handleKeyClose(event) {
            if (event.key === 'Escape') {
                closePanel();
            }
        }

        function handleOutsideClick(event) {
            if (!panel.contains(event.target) && event.target !== toggle) {
                closePanel();
            }
        }

        toggle.addEventListener('click', function (event) {
            event.preventDefault();
            if (panel.hasAttribute('hidden')) {
                openPanel();
            } else {
                closePanel();
            }
        });

        toggle.addEventListener('mouseenter', function () {
            if (window.matchMedia('(min-width: 992px)').matches) {
                window.clearTimeout(hoverTimeout);
                openPanel();
            }
        });

        panel.addEventListener('mouseenter', function () {
            window.clearTimeout(hoverTimeout);
        });

        function scheduleClose() {
            if (!window.matchMedia('(min-width: 992px)').matches) {
                return;
            }
            hoverTimeout = window.setTimeout(closePanel, 160);
        }

        toggle.addEventListener('mouseleave', scheduleClose);
        panel.addEventListener('mouseleave', scheduleClose);
    })();

    (function initSearchSuggest() {
        var input = document.querySelector('[data-search-suggest]');
        if (!input) {
            return;
        }

        var endpoint = input.getAttribute('data-search-endpoint') || '/api/search';
        var suggestBox = input.parentElement.querySelector('.store-search__suggestions');
        if (!suggestBox) {
            return;
        }

        var listElement = document.createElement('ul');
        suggestBox.appendChild(listElement);
        var debounceTimer = null;
        var activeIndex = -1;
        var results = [];

        function clearSuggestions() {
            results = [];
            listElement.innerHTML = '';
            suggestBox.setAttribute('hidden', 'hidden');
            activeIndex = -1;
        }

        function renderSuggestions(items) {
            results = items || [];
            listElement.innerHTML = '';
            if (!results.length) {
                clearSuggestions();
                return;
            }

            results.forEach(function (item, index) {
                var li = document.createElement('li');
                li.setAttribute('role', 'option');
                li.setAttribute('id', 'search-option-' + index);
                li.textContent = item.label;
                li.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                    window.location.href = item.url;
                });
                listElement.appendChild(li);
            });

            suggestBox.removeAttribute('hidden');
            activeIndex = -1;
        }

        function fetchSuggestions(query) {
            if (!query || query.length < 2) {
                clearSuggestions();
                return;
            }

            var url = endpoint + (endpoint.indexOf('?') > -1 ? '&' : '?') + 'q=' + encodeURIComponent(query);
            fetch(url, { headers: { 'Accept': 'application/json' } })
                .then(function (response) { return response.ok ? response.json() : Promise.reject(); })
                .then(function (payload) {
                    if (!payload || !Array.isArray(payload.results)) {
                        clearSuggestions();
                        return;
                    }
                    renderSuggestions(payload.results);
                })
                .catch(function () {
                    clearSuggestions();
                });
        }

        input.addEventListener('input', function () {
            var value = input.value.trim();
            window.clearTimeout(debounceTimer);
            debounceTimer = window.setTimeout(function () {
                fetchSuggestions(value);
            }, 250);
        });

        input.addEventListener('keydown', function (event) {
            if (!results.length || suggestBox.hasAttribute('hidden')) {
                return;
            }

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                activeIndex = (activeIndex + 1) % results.length;
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                activeIndex = (activeIndex - 1 + results.length) % results.length;
            } else if (event.key === 'Enter') {
                if (activeIndex >= 0 && results[activeIndex]) {
                    event.preventDefault();
                    window.location.href = results[activeIndex].url;
                }
                return;
            } else if (event.key === 'Escape') {
                clearSuggestions();
                return;
            } else {
                return;
            }

            listElement.querySelectorAll('li').forEach(function (li, index) {
                if (index === activeIndex) {
                    li.setAttribute('aria-selected', 'true');
                    input.setAttribute('aria-activedescendant', li.id);
                } else {
                    li.removeAttribute('aria-selected');
                }
            });
        });

        input.addEventListener('focus', function () {
            if (results.length) {
                suggestBox.removeAttribute('hidden');
            }
        });

        input.addEventListener('blur', function () {
            window.setTimeout(clearSuggestions, 120);
        });
    })();

    var actionButtons = document.querySelectorAll('[data-action="favorite"], [data-action="compare"]');
    actionButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            var action = button.getAttribute('data-action');
            if (!action) {
                return;
            }

            var message = action === 'favorite'
                ? 'Favorilere eklemek için giriş yapmalısınız.'
                : 'Karşılaştırma listesi yakında aktif olacak.';

            var toast = document.createElement('div');
            toast.className = 'toast align-items-center text-white bg-primary border-0 position-fixed bottom-0 end-0 m-3';
            toast.setAttribute('role', 'alert');
            toast.setAttribute('aria-live', 'assertive');
            toast.setAttribute('aria-atomic', 'true');
            toast.innerHTML = '<div class="d-flex"><div class="toast-body">' + message + '</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Kapat"></button></div>';
            document.body.appendChild(toast);

            if (window.bootstrap && window.bootstrap.Toast) {
                var bsToast = new window.bootstrap.Toast(toast, { delay: 2500 });
                bsToast.show();
                toast.addEventListener('hidden.bs.toast', function () {
                    toast.remove();
                });
            } else {
                window.setTimeout(function () {
                    toast.remove();
                }, 2500);
            }
        });
    });

    document.querySelectorAll('[data-license]').forEach(function (button) {
        button.addEventListener('click', function () {
            var value = button.getAttribute('data-license');
            if (!value) {
                return;
            }

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(value).then(function () {
                    button.classList.remove('btn-outline');
                    button.classList.add('btn-primary');
                    button.textContent = 'Kopyalandı';
                    window.setTimeout(function () {
                        button.classList.remove('btn-primary');
                        button.classList.add('btn-outline');
                        button.textContent = 'Lisansı Kopyala';
                    }, 2000);
                }).catch(function () {
                    window.alert('Lisans anahtarı kopyalanamadı.');
                });
            } else {
                window.prompt('Lisans anahtarını kopyalayın:', value);
            }
        });
    });
})();
