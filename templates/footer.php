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
</body>
</html>
<?php
if ($languageBufferActive) {
    ob_end_flush();
    unset($GLOBALS['app_lang_buffer_started']);
}
?>
