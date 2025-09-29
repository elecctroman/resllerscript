<?php
/**
 * Final call to action.
 *
 * @package Lotus_Licenses
 */
?>
<section class="lotus-section lotus-section--cta">
    <div class="site-container lotus-cta">
        <div class="lotus-cta__content">
            <h2><?php esc_html_e( 'Launch your license empire today', 'lotus-licenses' ); ?></h2>
            <p><?php esc_html_e( 'Install the Lotus Licenses theme, sync WooCommerce, and unlock a blazing-fast storefront that mirrors the Lotus Lisans experience.', 'lotus-licenses' ); ?></p>
        </div>
        <div class="lotus-cta__actions">
            <?php $shop_link = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop' ); ?>
            <a class="button button--primary" href="<?php echo esc_url( $shop_link ); ?>"><?php esc_html_e( 'Browse catalog', 'lotus-licenses' ); ?></a>
            <a class="button button--ghost" href="<?php echo esc_url( admin_url( 'customize.php' ) ); ?>"><?php esc_html_e( 'Customize theme', 'lotus-licenses' ); ?></a>
        </div>
    </div>
</section>
