<?php
/**
 * Featured product showcase.
 *
 * @package Lotus_Licenses
 */

$products = lotus_licenses_get_featured_products();
?>
<section class="lotus-section lotus-section--products">
    <div class="site-container">
        <div class="lotus-section__heading">
            <span class="lotus-section__eyebrow"><?php esc_html_e( 'High-converting catalog templates', 'lotus-licenses' ); ?></span>
            <h2><?php esc_html_e( 'Showcase your best-selling licenses', 'lotus-licenses' ); ?></h2>
            <p><?php esc_html_e( 'Lotus Licenses ships with bespoke WooCommerce layouts that spotlight value, reduce friction, and maximize average order value.', 'lotus-licenses' ); ?></p>
        </div>
        <?php if ( $products ) : ?>
            <div class="lotus-product-grid">
                <?php foreach ( $products as $product ) : ?>
                    <?php lotus_licenses_render_product_card( $product ); ?>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <div class="lotus-product-placeholder">
                <h3><?php esc_html_e( 'Activate WooCommerce featured products', 'lotus-licenses' ); ?></h3>
                <p><?php esc_html_e( 'Mark products as “Featured” to populate this curated grid automatically once WooCommerce is configured.', 'lotus-licenses' ); ?></p>
                <a class="button button--primary" href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>"><?php esc_html_e( 'Manage products', 'lotus-licenses' ); ?></a>
            </div>
        <?php endif; ?>
    </div>
</section>
