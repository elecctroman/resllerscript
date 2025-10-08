<?php
require_once __DIR__ . '/../bootstrap.php';

$cart = isset($_SESSION['storefront_cart']) && is_array($_SESSION['storefront_cart'])
    ? $_SESSION['storefront_cart']
    : array('items' => array(), 'total' => 0);

store_render('checkout', array(
    'pageTitle' => 'Ödeme',
    'cart' => $cart,
    'breadcrumb' => array(
        array('label' => 'Mağaza', 'href' => '/magaza/index.php'),
        array('label' => 'Ödeme', 'active' => true),
    ),
));
