<?php
/**
 * Hero section.
 *
 * @package Lotus_Licenses
 */

$headline     = get_theme_mod( 'lotus_licenses_hero_headline', __( 'Enterprise-Grade License Automation', 'lotus-licenses' ) );
$subheadline  = get_theme_mod( 'lotus_licenses_hero_subheadline', __( 'Launch a dazzling storefront that syncs your digital licenses in real time and converts visitors into lifelong customers.', 'lotus-licenses' ) );
$default_url  = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop' );
$primary_url  = get_theme_mod( 'lotus_licenses_hero_primary_url', $default_url );
$primary_text = get_theme_mod( 'lotus_licenses_hero_primary_label', __( 'Explore Products', 'lotus-licenses' ) );
$highlights   = lotus_licenses_get_highlights();
?>
<section class="lotus-hero">
    <div class="site-container lotus-hero__inner">
        <div class="lotus-hero__content">
            <div class="lotus-hero__eyebrow"><?php esc_html_e( 'Premium WooCommerce Theme', 'lotus-licenses' ); ?></div>
            <h1 class="lotus-hero__headline"><?php echo esc_html( $headline ); ?></h1>
            <p class="lotus-hero__subheadline"><?php echo esc_html( $subheadline ); ?></p>
            <div class="lotus-hero__actions">
                <a class="button button--primary" href="<?php echo esc_url( $primary_url ); ?>"><?php echo esc_html( $primary_text ); ?></a>
                <a class="button button--ghost" href="<?php echo esc_url( site_url( '/contact' ) ); ?>"><?php esc_html_e( 'Request a demo', 'lotus-licenses' ); ?></a>
            </div>
            <?php if ( $highlights ) : ?>
                <ul class="lotus-hero__metrics">
                    <?php foreach ( $highlights as $highlight ) : ?>
                        <li><?php echo esc_html( $highlight ); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <div class="lotus-hero__media" aria-hidden="true">
            <div class="lotus-hero__card">
                <span class="lotus-hero__badge"><?php esc_html_e( 'Auto Fulfilment', 'lotus-licenses' ); ?></span>
                <h3><?php esc_html_e( 'Digital License Hub', 'lotus-licenses' ); ?></h3>
                <p><?php esc_html_e( 'Synchronize keys from your distributors, validate orders, and dispatch delivery emails within seconds.', 'lotus-licenses' ); ?></p>
                <ul>
                    <li><?php esc_html_e( 'AI-powered fraud shield', 'lotus-licenses' ); ?></li>
                    <li><?php esc_html_e( 'Instant license provisioning', 'lotus-licenses' ); ?></li>
                    <li><?php esc_html_e( 'White-labeled communications', 'lotus-licenses' ); ?></li>
                </ul>
            </div>
        </div>
    </div>
</section>
