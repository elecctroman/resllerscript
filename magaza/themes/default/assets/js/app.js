(function () {
    'use strict';

    var buttons = document.querySelectorAll('[data-action="favorite"], [data-action="compare"]');
    buttons.forEach(function (button) {
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
            var bootstrapToast = new bootstrap.Toast(toast, { delay: 2500 });
            bootstrapToast.show();
            toast.addEventListener('hidden.bs.toast', function () {
                toast.remove();
            });
        });
    });
})();
