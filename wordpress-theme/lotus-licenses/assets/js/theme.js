(function ($) {
    'use strict';

    const body = document.body;

    body.classList.add('lotus-js-enabled');

    const carousel = document.querySelector('[data-behavior="testimonial-slider"]');
    if (carousel) {
        let currentIndex = 0;
        const slides = Array.from(carousel.children);

        function showSlide(index) {
            slides.forEach((slide, idx) => {
                slide.style.opacity = idx === index ? '1' : '0.25';
                slide.style.transform = idx === index ? 'translateY(0)' : 'translateY(12px)';
            });
        }

        function cycle() {
            currentIndex = (currentIndex + 1) % slides.length;
            showSlide(currentIndex);
        }

        showSlide(currentIndex);
        setInterval(cycle, 6500);
    }

    const header = document.querySelector('.site-header');
    if (header) {
        let lastScrollY = window.scrollY;
        window.addEventListener('scroll', () => {
            const direction = window.scrollY > lastScrollY ? 'down' : 'up';
            lastScrollY = window.scrollY;
            if (direction === 'down' && window.scrollY > 120) {
                header.classList.add('site-header--hidden');
            } else {
                header.classList.remove('site-header--hidden');
            }
        });
    }
})(jQuery);
