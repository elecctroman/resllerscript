<?php

use App\Helpers;

$languageBufferActive = !empty($GLOBALS['app_lang_buffer_started']);
?>
        </main>
        <footer class="app-footer">
            <small>© <?= date('Y') ?> <?= Helpers::sanitize('Bayi Yönetim Sistemi') ?></small>
        </footer>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
if ($languageBufferActive) {
    ob_end_flush();
    unset($GLOBALS['app_lang_buffer_started']);
}
?>
