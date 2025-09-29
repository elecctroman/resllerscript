<?php
/**
 * Helper template tags.
 *
 * @package Lotus_Licenses
 */

if ( ! function_exists( 'lotus_licenses_get_highlights' ) ) {
    /**
     * Return hero highlights array.
     *
     * @return array
     */
    function lotus_licenses_get_highlights() {
        $highlights = get_theme_mod( 'lotus_licenses_highlight_metrics', '' );

        if ( empty( $highlights ) ) {
            return [];
        }

        $parts = array_map( 'trim', explode( '•', $highlights ) );

        return array_filter( $parts );
    }
}

if ( ! function_exists( 'lotus_licenses_get_featured_products' ) ) {
    /**
     * Fetch the featured WooCommerce products.
     *
     * @param int $per_page Number of products.
     *
     * @return WC_Product[]
     */
    function lotus_licenses_get_featured_products( $per_page = 6 ) {
        if ( ! class_exists( 'WC_Product_Query' ) ) {
            return [];
        }

        $query = new WC_Product_Query(
            [
                'limit'     => $per_page,
                'status'    => 'publish',
                'featured'  => true,
                'orderby'   => 'menu_order',
                'order'     => 'ASC',
                'return'    => 'objects',
            ]
        );

        return $query->get_products();
    }
}

if ( ! function_exists( 'lotus_licenses_render_product_card' ) ) {
    /**
     * Output a WooCommerce product card for the front page.
     *
     * @param WC_Product $product Product instance.
     */
    function lotus_licenses_render_product_card( $product ) {
        if ( ! class_exists( 'WC_Product' ) ) {
            return;
        }

        if ( ! $product instanceof WC_Product ) {
            return;
        }

        $permalink = get_permalink( $product->get_id() );
        ?>
        <article class="lotus-product-card">
            <a class="lotus-product-card__link" href="<?php echo esc_url( $permalink ); ?>">
                <div class="lotus-product-card__badge"><?php esc_html_e( 'Instant Delivery', 'lotus-licenses' ); ?></div>
                <h3 class="lotus-product-card__title"><?php echo esc_html( $product->get_name() ); ?></h3>
                <p class="lotus-product-card__excerpt"><?php echo wp_kses_post( wp_trim_words( $product->get_short_description(), 18 ) ); ?></p>
                <div class="lotus-product-card__meta">
                    <span class="lotus-product-card__price"><?php echo wp_kses_post( $product->get_price_html() ); ?></span>
                    <span class="lotus-product-card__cta"><?php esc_html_e( 'View details', 'lotus-licenses' ); ?> →</span>
                </div>
            </a>
        </article>
        <?php
    }
}

if ( ! function_exists( 'lotus_licenses_menu_fallback' ) ) {
    /**
     * Fallback for primary menu.
     */
    function lotus_licenses_menu_fallback() {
        echo '<ul class="primary-menu">';
        wp_list_pages( [ 'title_li' => '' ] );
        echo '</ul>';
    }
}
