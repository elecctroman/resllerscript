<?php
/**
 * Theme header.
 *
 * @package Lotus_Licenses
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<header class="site-header">
    <div class="site-container site-header__inner">
        <div class="site-branding">
            <?php if ( has_custom_logo() ) : ?>
                <div class="site-logo">
                    <?php the_custom_logo(); ?>
                </div>
            <?php else : ?>
                <a class="site-title" href="<?php echo esc_url( home_url( '/' ) ); ?>">
                    <?php echo esc_html( get_bloginfo( 'name' ) ); ?>
                </a>
            <?php endif; ?>
            <?php $description = get_bloginfo( 'description', 'display' ); ?>
            <?php if ( $description ) : ?>
                <p class="site-description"><?php echo esc_html( $description ); ?></p>
            <?php endif; ?>
        </div>
        <nav class="site-navigation" aria-label="<?php esc_attr_e( 'Primary navigation', 'lotus-licenses' ); ?>">
            <?php
            wp_nav_menu(
                [
                    'theme_location' => 'primary',
                    'menu_class'     => 'primary-menu',
                    'container'      => false,
                    'fallback_cb'    => 'lotus_licenses_menu_fallback',
                ]
            );
            ?>
        </nav>
        <div class="site-header__cta">
            <?php if ( class_exists( 'WooCommerce' ) && function_exists( 'wc_get_cart_url' ) ) : ?>
                <a class="site-header__cta-link" href="<?php echo esc_url( wc_get_cart_url() ); ?>">
                    <?php esc_html_e( 'View Cart', 'lotus-licenses' ); ?>
                </a>
            <?php endif; ?>
            <?php $cta_url = get_theme_mod( 'lotus_licenses_hero_primary_url', function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop' ) ); ?>
            <a class="site-header__cta-link site-header__cta-link--primary" href="<?php echo esc_url( $cta_url ); ?>">
                <?php echo esc_html( get_theme_mod( 'lotus_licenses_hero_primary_label', __( 'Explore Products', 'lotus-licenses' ) ) ); ?>
            </a>
        </div>
    </div>
</header>
<main id="content" class="site-main">
