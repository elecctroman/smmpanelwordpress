<?php
/**
 * Admin settings page for the WooCommerce SMM provider API.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SMMPW_Settings_Page' ) ) {
    /**
     * Handles admin UI for managing API keys and general options.
     */
    class SMMPW_Settings_Page {
        /**
         * Singleton instance.
         *
         * @var SMMPW_Settings_Page|null
         */
        private static $instance = null;

        /**
         * Retrieve the singleton instance.
         *
         * @return SMMPW_Settings_Page
         */
        public static function instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        /**
         * Constructor registers admin hooks.
         */
        private function __construct() {
            add_action( 'admin_menu', array( $this, 'register_menu' ) );
            add_action( 'admin_init', array( $this, 'register_settings' ) );
            add_action( 'admin_post_smmpw_add_key', array( $this, 'handle_add_key' ) );
            add_action( 'admin_post_smmpw_revoke_key', array( $this, 'handle_revoke_key' ) );
        }

        /**
         * Register the submenu page under WooCommerce.
         */
        public function register_menu() {
            add_submenu_page(
                'woocommerce',
                __( 'SMM Provider API', 'smmpw' ),
                __( 'SMM Provider API', 'smmpw' ),
                'manage_woocommerce',
                'smmpw-provider-settings',
                array( $this, 'render_page' )
            );
        }

        /**
         * Register settings handled by the Settings API.
         */
        public function register_settings() {
            register_setting(
                'smmpw_provider_settings_group',
                SMMPW_Plugin::OPTION_GENERAL_SETTINGS,
                array( $this, 'sanitize_general_settings' )
            );

            add_settings_section(
                'smmpw_provider_general_section',
                __( 'General Settings', 'smmpw' ),
                '__return_false',
                'smmpw-provider-settings'
            );

            add_settings_field(
                'smmpw_order_status',
                __( 'API order status', 'smmpw' ),
                array( $this, 'render_order_status_field' ),
                'smmpw-provider-settings',
                'smmpw_provider_general_section'
            );

            add_settings_field(
                'smmpw_default_email',
                __( 'Default customer email', 'smmpw' ),
                array( $this, 'render_default_email_field' ),
                'smmpw-provider-settings',
                'smmpw_provider_general_section'
            );

            add_settings_field(
                'smmpw_default_name',
                __( 'Default customer name', 'smmpw' ),
                array( $this, 'render_default_name_field' ),
                'smmpw-provider-settings',
                'smmpw_provider_general_section'
            );
        }

        /**
         * Sanitize general settings.
         *
         * @param array $settings Raw settings.
         *
         * @return array
         */
        public function sanitize_general_settings( $settings ) {
            $current  = $this->get_general_settings();
            $settings = is_array( $settings ) ? $settings : array();

            $allowed_statuses = wc_get_order_statuses();
            $status           = isset( $settings['order_status'] ) ? 'wc-' . sanitize_key( str_replace( 'wc-', '', $settings['order_status'] ) ) : 'wc-processing';
            if ( ! isset( $allowed_statuses[ $status ] ) ) {
                $status = 'wc-processing';
            }

            $current['order_status']          = $status;
            $current['default_customer_email'] = isset( $settings['default_customer_email'] ) ? sanitize_email( wp_unslash( $settings['default_customer_email'] ) ) : '';
            $current['default_customer_name']  = isset( $settings['default_customer_name'] ) ? sanitize_text_field( wp_unslash( $settings['default_customer_name'] ) ) : '';

            return $current;
        }

        /**
         * Render order status select field.
         */
        public function render_order_status_field() {
            $settings = $this->get_general_settings();
            $value    = isset( $settings['order_status'] ) ? $settings['order_status'] : 'wc-processing';
            $statuses = wc_get_order_statuses();
            ?>
            <select name="<?php echo esc_attr( SMMPW_Plugin::OPTION_GENERAL_SETTINGS ); ?>[order_status]">
                <?php foreach ( $statuses as $status_key => $status_label ) : ?>
                    <option value="<?php echo esc_attr( $status_key ); ?>" <?php selected( $value, $status_key ); ?>><?php echo esc_html( $status_label ); ?></option>
                <?php endforeach; ?>
            </select>
            <p class="description"><?php esc_html_e( 'Orders created via the API will use this status.', 'smmpw' ); ?></p>
            <?php
        }

        /**
         * Render default customer email field.
         */
        public function render_default_email_field() {
            $settings = $this->get_general_settings();
            $value    = isset( $settings['default_customer_email'] ) ? $settings['default_customer_email'] : get_option( 'admin_email' );
            ?>
            <input type="email" class="regular-text" name="<?php echo esc_attr( SMMPW_Plugin::OPTION_GENERAL_SETTINGS ); ?>[default_customer_email]" value="<?php echo esc_attr( $value ); ?>" />
            <p class="description"><?php esc_html_e( 'Used as the billing email for automatically created orders when no customer email is provided.', 'smmpw' ); ?></p>
            <?php
        }

        /**
         * Render default customer name field.
         */
        public function render_default_name_field() {
            $settings = $this->get_general_settings();
            $value    = isset( $settings['default_customer_name'] ) ? $settings['default_customer_name'] : __( 'API Client', 'smmpw' );
            ?>
            <input type="text" class="regular-text" name="<?php echo esc_attr( SMMPW_Plugin::OPTION_GENERAL_SETTINGS ); ?>[default_customer_name]" value="<?php echo esc_attr( $value ); ?>" />
            <p class="description"><?php esc_html_e( 'Displayed as the billing name on API generated orders.', 'smmpw' ); ?></p>
            <?php
        }

        /**
         * Handle the add-key form submission.
         */
        public function handle_add_key() {
            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                wp_die( esc_html__( 'You are not allowed to do that.', 'smmpw' ) );
            }

            check_admin_referer( 'smmpw_add_key' );

            $label    = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
            $api_keys = $this->get_api_keys();
            $key      = strtolower( wp_generate_password( 40, false, false ) );

            $api_keys[ $key ] = array(
                'label'   => $label,
                'created' => time(),
                'status'  => 'active',
            );

            update_option( SMMPW_Plugin::OPTION_API_KEYS, $api_keys );

            wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=smmpw-provider-settings' ) );
            exit;
        }

        /**
         * Handle API key revocation.
         */
        public function handle_revoke_key() {
            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                wp_die( esc_html__( 'You are not allowed to do that.', 'smmpw' ) );
            }

            check_admin_referer( 'smmpw_revoke_key' );

            $key      = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
            $api_keys = $this->get_api_keys();

            if ( isset( $api_keys[ $key ] ) ) {
                unset( $api_keys[ $key ] );
                update_option( SMMPW_Plugin::OPTION_API_KEYS, $api_keys );
            }

            wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=smmpw-provider-settings' ) );
            exit;
        }

        /**
         * Render the settings page.
         */
        public function render_page() {
            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                wp_die( esc_html__( 'You are not allowed to view this page.', 'smmpw' ) );
            }

            $endpoint_url = add_query_arg( 'smmpw-api', '1', home_url( '/' ) );
            $api_keys     = $this->get_api_keys();
            ?>
            <div class="wrap">
                <h1><?php esc_html_e( 'SMM Provider API', 'smmpw' ); ?></h1>

                <p><?php esc_html_e( 'Share the following API endpoint with your resellers. They can connect using any Perfect Panel compatible client.', 'smmpw' ); ?></p>
                <p><code><?php echo esc_html( $endpoint_url ); ?></code></p>

                <hr />

                <form action="options.php" method="post">
                    <?php
                    settings_fields( 'smmpw_provider_settings_group' );
                    do_settings_sections( 'smmpw-provider-settings' );
                    submit_button();
                    ?>
                </form>

                <hr />

                <h2><?php esc_html_e( 'API Keys', 'smmpw' ); ?></h2>
                <p><?php esc_html_e( 'Generate a unique key for each client to monitor access and revoke it if needed.', 'smmpw' ); ?></p>

                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Label', 'smmpw' ); ?></th>
                            <th><?php esc_html_e( 'API Key', 'smmpw' ); ?></th>
                            <th><?php esc_html_e( 'Created', 'smmpw' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'smmpw' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $api_keys ) ) : ?>
                            <tr>
                                <td colspan="4"><?php esc_html_e( 'No API keys generated yet.', 'smmpw' ); ?></td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ( $api_keys as $key => $data ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $data['label'] ?: __( 'Unnamed key', 'smmpw' ) ); ?></td>
                                    <td><code><?php echo esc_html( $key ); ?></code></td>
                                    <td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $data['created'] ) ); ?></td>
                                    <td>
                                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                            <?php wp_nonce_field( 'smmpw_revoke_key' ); ?>
                                            <input type="hidden" name="action" value="smmpw_revoke_key" />
                                            <input type="hidden" name="api_key" value="<?php echo esc_attr( $key ); ?>" />
                                            <?php submit_button( __( 'Revoke', 'smmpw' ), 'delete', 'submit', false ); ?>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <h3><?php esc_html_e( 'Create new API key', 'smmpw' ); ?></h3>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'smmpw_add_key' ); ?>
                    <input type="hidden" name="action" value="smmpw_add_key" />
                    <p>
                        <label for="smmpw-label" class="screen-reader-text"><?php esc_html_e( 'Key label', 'smmpw' ); ?></label>
                        <input type="text" id="smmpw-label" name="label" class="regular-text" placeholder="<?php esc_attr_e( 'Reseller name or note', 'smmpw' ); ?>" />
                        <?php submit_button( __( 'Generate API key', 'smmpw' ), 'primary', 'submit', false ); ?>
                    </p>
                </form>
            </div>
            <?php
        }

        /**
         * Retrieve saved API keys.
         *
         * @return array
         */
        public function get_api_keys() {
            $keys = get_option( SMMPW_Plugin::OPTION_API_KEYS, array() );
            if ( ! is_array( $keys ) ) {
                $keys = array();
            }

            return $keys;
        }

        /**
         * Retrieve general settings.
         *
         * @return array
         */
        public function get_general_settings() {
            $defaults = array(
                'order_status'           => 'wc-processing',
                'default_customer_email' => get_option( 'admin_email' ),
                'default_customer_name'  => __( 'API Client', 'smmpw' ),
            );

            $settings = get_option( SMMPW_Plugin::OPTION_GENERAL_SETTINGS, array() );
            if ( ! is_array( $settings ) ) {
                $settings = array();
            }

            return wp_parse_args( $settings, $defaults );
        }
    }
}
