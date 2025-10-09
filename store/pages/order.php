<?php
require_once __DIR__ . '/../bootstrap.php';

$order = isset($_SESSION['storefront_last_order']) && is_array($_SESSION['storefront_last_order'])
    ? $_SESSION['storefront_last_order']
    : array('reference' => strtoupper(substr(md5(uniqid('', true)), 0, 8)));

store_render('order', array(
    'pageTitle' => 'Sipariş Özeti',
    'order' => $order,
    'breadcrumb' => array(
        array('label' => 'Mağaza', 'href' => store_url('')),
        array('label' => 'Sipariş', 'active' => true),
    ),
    'metaDescription' => (string) get_setting('seo_description', ''),
));
