<?php use App\Helpers; ?>
    </div>
</main>
<footer class="public-footer py-4">
    <div class="container text-center text-muted small">
        <?php $footerSiteName = isset($siteName) && $siteName ? $siteName : Helpers::siteName(); ?>
        &copy; <?= date('Y') ?> <?= Helpers::sanitize($footerSiteName) ?>. Tüm hakları saklıdır.
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
