<?php
/**
 * Lightweight plugin bootstrapper executed on theme activation.
 *
 * @package Lotus_Licenses
 */

if ( ! function_exists( 'lotus_licenses_install_plugins' ) ) {
    /**
     * Install and activate the required plugins when the theme is enabled.
     */
    function lotus_licenses_install_plugins() {
        if ( ! current_user_can( 'install_plugins' ) ) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';

        $plugins = [
            [
                'name'        => __( 'WooCommerce', 'lotus-licenses' ),
                'slug'        => 'woocommerce',
                'file'        => 'woocommerce/woocommerce.php',
                'is_required' => true,
            ],
            [
                'name'        => __( 'Elementor Website Builder', 'lotus-licenses' ),
                'slug'        => 'elementor',
                'file'        => 'elementor/elementor.php',
                'is_required' => false,
            ],
            [
                'name'        => __( 'Contact Form 7', 'lotus-licenses' ),
                'slug'        => 'contact-form-7',
                'file'        => 'contact-form-7/wp-contact-form-7.php',
                'is_required' => false,
            ],
            [
                'name'        => __( 'Yoast SEO', 'lotus-licenses' ),
                'slug'        => 'wordpress-seo',
                'file'        => 'wordpress-seo/wp-seo.php',
                'is_required' => false,
            ],
        ];

        $notices = [];

        foreach ( $plugins as $plugin ) {
            $plugin_file = WP_PLUGIN_DIR . '/' . $plugin['file'];

            if ( file_exists( $plugin_file ) ) {
                $result = activate_plugin( $plugin['file'] );
                if ( is_wp_error( $result ) ) {
                    $notices[] = sprintf( __( 'Could not activate %1$s: %2$s', 'lotus-licenses' ), $plugin['name'], $result->get_error_message() );
                }
                continue;
            }

            $api = plugins_api(
                'plugin_information',
                [
                    'slug'   => $plugin['slug'],
                    'fields' => [ 'sections' => false ],
                ]
            );

            if ( is_wp_error( $api ) || empty( $api->download_link ) ) {
                $notices[] = sprintf( __( 'Unable to retrieve the download link for %s.', 'lotus-licenses' ), $plugin['name'] );
                continue;
            }

            $skin     = new Automatic_Upgrader_Skin();
            $upgrader = new Plugin_Upgrader( $skin );
            $result   = $upgrader->install( $api->download_link );

            if ( is_wp_error( $result ) ) {
                $notices[] = sprintf( __( 'Installation failed for %1$s: %2$s', 'lotus-licenses' ), $plugin['name'], $result->get_error_message() );
                continue;
            }

            $activate = activate_plugin( $plugin['file'] );
            if ( is_wp_error( $activate ) ) {
                $notices[] = sprintf( __( 'Installed but could not activate %1$s: %2$s', 'lotus-licenses' ), $plugin['name'], $activate->get_error_message() );
            }
        }

        if ( ! empty( $notices ) ) {
            set_transient( 'lotus_licenses_plugin_notices', $notices, MINUTE_IN_SECONDS * 30 );
        }
    }
}
add_action( 'after_switch_theme', 'lotus_licenses_install_plugins' );

if ( ! function_exists( 'lotus_licenses_plugin_admin_notices' ) ) {
    /**
     * Display any installation or activation notices.
     */
    function lotus_licenses_plugin_admin_notices() {
        $notices = get_transient( 'lotus_licenses_plugin_notices' );

        if ( empty( $notices ) ) {
            return;
        }

        delete_transient( 'lotus_licenses_plugin_notices' );
        ?>
        <div class="notice notice-warning is-dismissible">
            <p><strong><?php esc_html_e( 'Lotus Licenses Theme Notice', 'lotus-licenses' ); ?></strong></p>
            <ul>
                <?php foreach ( $notices as $notice ) : ?>
                    <li><?php echo wp_kses_post( $notice ); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
    }
}
add_action( 'admin_notices', 'lotus_licenses_plugin_admin_notices' );
