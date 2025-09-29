<?php
/**
 * Theme setup.
 *
 * @package Lotus_Licenses
 */

if ( ! function_exists( 'lotus_licenses_setup' ) ) {
    /**
     * Register theme supports and global settings.
     */
    function lotus_licenses_setup() {
        load_theme_textdomain( 'lotus-licenses', LOTUS_LICENSES_PATH . 'languages' );

        add_theme_support( 'automatic-feed-links' );
        add_theme_support( 'title-tag' );
        add_theme_support( 'post-thumbnails' );
        add_theme_support( 'woocommerce' );
        add_theme_support( 'align-wide' );
        add_theme_support( 'responsive-embeds' );
        add_theme_support( 'editor-styles' );
        add_editor_style( 'assets/css/theme.css' );

        register_nav_menus(
            [
                'primary'   => __( 'Primary Menu', 'lotus-licenses' ),
                'secondary' => __( 'Secondary Menu', 'lotus-licenses' ),
                'footer'    => __( 'Footer Menu', 'lotus-licenses' ),
            ]
        );

        add_theme_support(
            'custom-logo',
            [
                'height'      => 64,
                'width'       => 240,
                'flex-height' => true,
                'flex-width'  => true,
            ]
        );
    }
}
add_action( 'after_setup_theme', 'lotus_licenses_setup' );

if ( ! function_exists( 'lotus_licenses_widgets_init' ) ) {
    /**
     * Register widget areas.
     */
    function lotus_licenses_widgets_init() {
        register_sidebar(
            [
                'name'          => __( 'Footer Column 1', 'lotus-licenses' ),
                'id'            => 'footer-1',
                'description'   => __( 'Displayed in the first column of the footer.', 'lotus-licenses' ),
                'before_widget' => '<div id="%1$s" class="widget %2$s">',
                'after_widget'  => '</div>',
                'before_title'  => '<h3 class="widget-title">',
                'after_title'   => '</h3>',
            ]
        );

        register_sidebar(
            [
                'name'          => __( 'Footer Column 2', 'lotus-licenses' ),
                'id'            => 'footer-2',
                'description'   => __( 'Displayed in the second column of the footer.', 'lotus-licenses' ),
                'before_widget' => '<div id="%1$s" class="widget %2$s">',
                'after_widget'  => '</div>',
                'before_title'  => '<h3 class="widget-title">',
                'after_title'   => '</h3>',
            ]
        );

        register_sidebar(
            [
                'name'          => __( 'Footer Column 3', 'lotus-licenses' ),
                'id'            => 'footer-3',
                'description'   => __( 'Displayed in the third column of the footer.', 'lotus-licenses' ),
                'before_widget' => '<div id="%1$s" class="widget %2$s">',
                'after_widget'  => '</div>',
                'before_title'  => '<h3 class="widget-title">',
                'after_title'   => '</h3>',
            ]
        );
    }
}
add_action( 'widgets_init', 'lotus_licenses_widgets_init' );
