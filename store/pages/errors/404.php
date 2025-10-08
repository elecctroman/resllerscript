<?php
require_once __DIR__ . '/../../bootstrap.php';

store_render('errors/404', array(
    'pageTitle' => 'Sayfa Bulunamadı',
    'metaDescription' => (string) get_setting('seo_description', ''),
));
