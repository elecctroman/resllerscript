<?php
/**
 * Theme footer.
 *
 * @package Lotus_Licenses
 */
?>
</main>
<footer class="site-footer">
    <div class="site-container">
        <div class="site-footer__grid">
            <div class="site-footer__brand">
                <div class="site-footer__logo"><?php bloginfo( 'name' ); ?></div>
                <?php $description = get_bloginfo( 'description', 'display' ); ?>
                <?php if ( $description ) : ?>
                    <p><?php echo esc_html( $description ); ?></p>
                <?php endif; ?>
                <p><?php esc_html_e( 'Delivering premium license fulfillment with automated workflows, instant digital delivery, and bulletproof customer support.', 'lotus-licenses' ); ?></p>
            </div>
            <div class="site-footer__menu">
                <h3><?php esc_html_e( 'Quick Links', 'lotus-licenses' ); ?></h3>
                <?php
                wp_nav_menu(
                    [
                        'theme_location' => 'footer',
                        'container'      => false,
                        'fallback_cb'    => 'lotus_licenses_menu_fallback',
                    ]
                );
                ?>
            </div>
            <div class="site-footer__widgets">
                <?php if ( is_active_sidebar( 'footer-1' ) || is_active_sidebar( 'footer-2' ) || is_active_sidebar( 'footer-3' ) ) : ?>
                    <div class="widget-area widget-area--footer">
                        <?php dynamic_sidebar( 'footer-1' ); ?>
                        <?php dynamic_sidebar( 'footer-2' ); ?>
                        <?php dynamic_sidebar( 'footer-3' ); ?>
                    </div>
                <?php else : ?>
                    <p><?php esc_html_e( 'Add widgets to the footer columns to personalize your trust signals and contact details.', 'lotus-licenses' ); ?></p>
                <?php endif; ?>
            </div>
            <div class="site-footer__cta">
                <h3><?php esc_html_e( 'Need a tailored plan?', 'lotus-licenses' ); ?></h3>
                <p><?php esc_html_e( 'Our specialists craft bespoke reseller programs with automated billing, SLA-backed support, and enterprise analytics.', 'lotus-licenses' ); ?></p>
                <a href="<?php echo esc_url( site_url( '/contact' ) ); ?>">
                    <?php esc_html_e( 'Book a discovery call', 'lotus-licenses' ); ?>
                </a>
            </div>
        </div>
        <div class="site-footer__bottom">
            <span>&copy; <?php echo esc_html( date_i18n( 'Y' ) ); ?> <?php echo esc_html( get_bloginfo( 'name' ) ); ?>. <?php esc_html_e( 'All rights reserved.', 'lotus-licenses' ); ?></span>
            <span><?php esc_html_e( 'Crafted for WooCommerce excellence • PCI-DSS ready • GDPR compliant', 'lotus-licenses' ); ?></span>
        </div>
    </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
