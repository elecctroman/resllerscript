<?php
require_once __DIR__ . '/../bootstrap.php';

$cart = store_cart_get();
$flash = store_cart_flash_get();

store_render('cart', array(
    'pageTitle' => 'Sepetiniz',
    'cart' => $cart,
    'flash' => $flash,
    'breadcrumb' => array(
        array('label' => 'Mağaza', 'href' => store_url('')),
        array('label' => 'Sepet', 'active' => true),
    ),
    'metaDescription' => (string) get_setting('seo_description', ''),
));
