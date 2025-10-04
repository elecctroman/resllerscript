    </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function() {
    const toggle = document.getElementById('customerThemeToggle');
    if (!toggle) {
        return;
    }
    toggle.addEventListener('click', function () {
        const current = document.documentElement.getAttribute('data-theme') || 'light';
        const next = current === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        document.body.classList.remove('customer-app-' + current);
        document.body.classList.add('customer-app-' + next);
        document.cookie = 'customer_theme=' + next + '; path=/; max-age=' + (60 * 60 * 24 * 365);
    });
})();
</script>
</body>
</html>
