<?php
/**
 * Lotus Licenses theme bootstrap.
 *
 * @package Lotus_Licenses
 */

define( 'LOTUS_LICENSES_VERSION', '1.0.0' );

define( 'LOTUS_LICENSES_PATH', trailingslashit( get_template_directory() ) );

define( 'LOTUS_LICENSES_URI', trailingslashit( get_template_directory_uri() ) );

require_once LOTUS_LICENSES_PATH . 'inc/setup.php';
require_once LOTUS_LICENSES_PATH . 'inc/enqueue.php';
require_once LOTUS_LICENSES_PATH . 'inc/customizer.php';
require_once LOTUS_LICENSES_PATH . 'inc/template-tags.php';
require_once LOTUS_LICENSES_PATH . 'inc/tgmpa.php';

