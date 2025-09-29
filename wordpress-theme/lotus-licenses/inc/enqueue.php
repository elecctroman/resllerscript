<?php
/**
 * Scripts and styles.
 *
 * @package Lotus_Licenses
 */

if ( ! function_exists( 'lotus_licenses_scripts' ) ) {
    /**
     * Enqueue front-end assets.
     */
    function lotus_licenses_scripts() {
        wp_enqueue_style(
            'lotus-licenses-fonts',
            'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap',
            [],
            null
        );

        wp_enqueue_style( 'lotus-licenses-style', get_stylesheet_uri(), [], LOTUS_LICENSES_VERSION );
        wp_enqueue_style( 'lotus-licenses-theme', LOTUS_LICENSES_URI . 'assets/css/theme.css', [ 'lotus-licenses-style' ], LOTUS_LICENSES_VERSION );

        wp_enqueue_script( 'lotus-licenses-theme', LOTUS_LICENSES_URI . 'assets/js/theme.js', [ 'jquery' ], LOTUS_LICENSES_VERSION, true );

        wp_localize_script(
            'lotus-licenses-theme',
            'lotusLicensesSettings',
            [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            ]
        );
    }
}
add_action( 'wp_enqueue_scripts', 'lotus_licenses_scripts' );

if ( ! function_exists( 'lotus_licenses_admin_styles' ) ) {
    /**
     * Load styles for editor and widgets.
     */
    function lotus_licenses_admin_styles() {
        wp_enqueue_style( 'lotus-licenses-admin', LOTUS_LICENSES_URI . 'assets/css/theme.css', [], LOTUS_LICENSES_VERSION );
    }
}
add_action( 'enqueue_block_editor_assets', 'lotus_licenses_admin_styles' );
