document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-accordion] details').forEach(detail => {
        detail.addEventListener('toggle', () => {
            if (detail.open) {
                detail.parentElement.querySelectorAll('details').forEach(other => {
                    if (other !== detail) {
                        other.removeAttribute('open');
                    }
                });
            }
        });
    });
});

const yearEl = document.getElementById('doc-year');
if (yearEl) {
    yearEl.textContent = new Date().getFullYear();
}
