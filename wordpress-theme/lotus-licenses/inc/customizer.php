<?php
/**
 * Customizer configuration.
 *
 * @package Lotus_Licenses
 */

if ( ! function_exists( 'lotus_licenses_customize_register' ) ) {
    /**
     * Register customizer panels and settings.
     *
     * @param WP_Customize_Manager $wp_customize Manager instance.
     */
    function lotus_licenses_customize_register( $wp_customize ) {
        $wp_customize->add_section(
            'lotus_licenses_hero',
            [
                'title'       => __( 'Hero Section', 'lotus-licenses' ),
                'description' => __( 'Control the primary hero area displayed on the front page.', 'lotus-licenses' ),
                'priority'    => 30,
            ]
        );

        $wp_customize->add_setting(
            'lotus_licenses_hero_headline',
            [
                'default'           => __( 'Enterprise-Grade License Automation', 'lotus-licenses' ),
                'sanitize_callback' => 'sanitize_text_field',
            ]
        );

        $wp_customize->add_control(
            'lotus_licenses_hero_headline',
            [
                'label'       => __( 'Headline', 'lotus-licenses' ),
                'section'     => 'lotus_licenses_hero',
                'type'        => 'text',
            ]
        );

        $wp_customize->add_setting(
            'lotus_licenses_hero_subheadline',
            [
                'default'           => __( 'Launch a dazzling storefront that syncs your digital licenses in real time and converts visitors into lifelong customers.', 'lotus-licenses' ),
                'sanitize_callback' => 'sanitize_textarea_field',
            ]
        );

        $wp_customize->add_control(
            'lotus_licenses_hero_subheadline',
            [
                'label'   => __( 'Subheadline', 'lotus-licenses' ),
                'section' => 'lotus_licenses_hero',
                'type'    => 'textarea',
            ]
        );

        $wp_customize->add_setting(
            'lotus_licenses_hero_primary_label',
            [
                'default'           => __( 'Explore Products', 'lotus-licenses' ),
                'sanitize_callback' => 'sanitize_text_field',
            ]
        );

        $wp_customize->add_control(
            'lotus_licenses_hero_primary_label',
            [
                'label'   => __( 'Primary Button Label', 'lotus-licenses' ),
                'section' => 'lotus_licenses_hero',
                'type'    => 'text',
            ]
        );

        $shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop' );

        $wp_customize->add_setting(
            'lotus_licenses_hero_primary_url',
            [
                'default'           => $shop_url,
                'sanitize_callback' => 'esc_url_raw',
            ]
        );

        $wp_customize->add_control(
            'lotus_licenses_hero_primary_url',
            [
                'label'   => __( 'Primary Button URL', 'lotus-licenses' ),
                'section' => 'lotus_licenses_hero',
                'type'    => 'url',
            ]
        );

        $wp_customize->add_setting(
            'lotus_licenses_highlight_metrics',
            [
                'default'           => __( '24/7 Automated Fulfilment • Fraud Screening • Reseller API • Instant Key Delivery', 'lotus-licenses' ),
                'sanitize_callback' => 'sanitize_text_field',
            ]
        );

        $wp_customize->add_control(
            'lotus_licenses_highlight_metrics',
            [
                'label'       => __( 'Hero Highlights', 'lotus-licenses' ),
                'description' => __( 'Separate multiple highlights with a bullet (•).', 'lotus-licenses' ),
                'section'     => 'lotus_licenses_hero',
                'type'        => 'text',
            ]
        );
    }
}
add_action( 'customize_register', 'lotus_licenses_customize_register' );
