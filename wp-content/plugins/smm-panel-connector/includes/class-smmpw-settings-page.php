<?php
/**
 * Admin settings page for SMM Panel connector.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SMMPW_Settings_Page' ) ) {
    /**
     * Handles the WooCommerce settings page integration.
     */
    class SMMPW_Settings_Page {
        /**
         * @var SMMPW_Settings_Page
         */
        private static $instance;

        /**
         * Singleton.
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
         * Hook registration.
         */
        private function __construct() {
            add_action( 'admin_menu', array( $this, 'add_menu_page' ), 99 );
            add_action( 'admin_init', array( $this, 'register_fields' ) );
        }

        /**
         * Adds the settings page under WooCommerce.
         */
        public function add_menu_page() {
            add_submenu_page(
                'woocommerce',
                __( 'SMM Panel Connector', 'smmpw' ),
                __( 'SMM Panel', 'smmpw' ),
                'manage_woocommerce',
                'smmpw-settings',
                array( $this, 'render_page' )
            );
        }

        /**
         * Register settings sections and fields for the settings page.
         */
        public function register_fields() {
            add_settings_section(
                'smmpw_api_settings',
                __( 'API Settings', 'smmpw' ),
                '__return_false',
                'smmpw-settings'
            );

            add_settings_field(
                'api_url',
                __( 'API URL', 'smmpw' ),
                array( $this, 'render_text_field' ),
                'smmpw-settings',
                'smmpw_api_settings',
                array(
                    'label_for' => 'smmpw_api_url',
                    'option'    => 'api_url',
                    'type'      => 'url',
                    'help'      => __( 'Example: https://panel.example.com/api/v2', 'smmpw' ),
                )
            );

            add_settings_field(
                'api_key',
                __( 'API Key', 'smmpw' ),
                array( $this, 'render_text_field' ),
                'smmpw-settings',
                'smmpw_api_settings',
                array(
                    'label_for' => 'smmpw_api_key',
                    'option'    => 'api_key',
                    'type'      => 'password',
                    'help'      => __( 'Enter the API key generated in your SMM panel.', 'smmpw' ),
                )
            );

            add_settings_field(
                'default_category',
                __( 'Default Product Category', 'smmpw' ),
                array( $this, 'render_category_dropdown' ),
                'smmpw-settings',
                'smmpw_api_settings'
            );

            add_settings_section(
                'smmpw_sync_settings',
                __( 'Synchronization Options', 'smmpw' ),
                '__return_false',
                'smmpw-settings'
            );

            add_settings_field(
                'sync_pricing',
                __( 'Synchronize Pricing', 'smmpw' ),
                array( $this, 'render_checkbox_field' ),
                'smmpw-settings',
                'smmpw_sync_settings',
                array(
                    'label_for' => 'smmpw_sync_pricing',
                    'option'    => 'sync_pricing',
                    'description' => __( 'Update WooCommerce product prices with each synchronization.', 'smmpw' ),
                )
            );

            add_settings_field(
                'price_markup_type',
                __( 'Price Markup Type', 'smmpw' ),
                array( $this, 'render_select_field' ),
                'smmpw-settings',
                'smmpw_sync_settings',
                array(
                    'label_for' => 'smmpw_price_markup_type',
                    'option'    => 'price_markup_type',
                    'options'   => array(
                        'percent' => __( 'Percentage', 'smmpw' ),
                        'fixed'   => __( 'Fixed amount', 'smmpw' ),
                    ),
                )
            );

            add_settings_field(
                'price_markup_value',
                __( 'Markup Value', 'smmpw' ),
                array( $this, 'render_text_field' ),
                'smmpw-settings',
                'smmpw_sync_settings',
                array(
                    'label_for' => 'smmpw_price_markup_value',
                    'option'    => 'price_markup_value',
                    'type'      => 'number',
                    'step'      => '0.01',
                    'help'      => __( 'Value used with the selected markup type.', 'smmpw' ),
                )
            );

            add_settings_field(
                'stock_behavior',
                __( 'Stock Management', 'smmpw' ),
                array( $this, 'render_select_field' ),
                'smmpw-settings',
                'smmpw_sync_settings',
                array(
                    'label_for' => 'smmpw_stock_behavior',
                    'option'    => 'stock_behavior',
                    'options'   => array(
                        'in_stock'     => __( 'Always in stock', 'smmpw' ),
                        'out_of_stock' => __( 'Always out of stock', 'smmpw' ),
                        'remote'       => __( 'Use remote availability (if provided)', 'smmpw' ),
                    ),
                )
            );

            add_settings_field(
                'sync_schedule',
                __( 'Sync Schedule', 'smmpw' ),
                array( $this, 'render_select_field' ),
                'smmpw-settings',
                'smmpw_sync_settings',
                array(
                    'label_for' => 'smmpw_sync_schedule',
                    'option'    => 'sync_schedule',
                    'options'   => array(
                        'quarter_hour' => __( 'Every 15 minutes', 'smmpw' ),
                        'hourly'       => __( 'Hourly', 'smmpw' ),
                        'twicedaily'   => __( 'Twice Daily', 'smmpw' ),
                        'daily'        => __( 'Daily', 'smmpw' ),
                    ),
                )
            );
        }

        /**
         * Render the plugin settings page contents.
         */
        public function render_page() {
            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                wp_die( esc_html__( 'You are not allowed to view this page.', 'smmpw' ) );
            }

            $settings = SMMPW_Plugin::instance()->get_settings();
            ?>
            <div class="wrap">
                <h1><?php esc_html_e( 'WooCommerce SMM Panel Connector', 'smmpw' ); ?></h1>
                <?php if ( isset( $_GET['synced'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
                    <?php if ( '1' === $_GET['synced'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
                        <div id="message" class="updated notice notice-success is-dismissible"><p><?php esc_html_e( 'Synchronization completed successfully.', 'smmpw' ); ?></p></div>
                    <?php else : ?>
                        <div id="message" class="error notice notice-error is-dismissible"><p><?php esc_html_e( 'Synchronization failed. Check logs for details.', 'smmpw' ); ?></p></div>
                    <?php endif; ?>
                <?php endif; ?>

                <form method="post" action="options.php">
                    <?php
                    settings_fields( 'smmpw_settings' );
                    do_settings_sections( 'smmpw-settings' );
                    submit_button();
                    ?>
                </form>

                <hr />

                <h2><?php esc_html_e( 'Manual Synchronization', 'smmpw' ); ?></h2>
                <p><?php esc_html_e( 'Use this option to immediately fetch services from your panel and sync them with WooCommerce.', 'smmpw' ); ?></p>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'smmpw_manual_sync' ); ?>
                    <input type="hidden" name="action" value="smmpw_manual_sync" />
                    <?php submit_button( __( 'Run Sync Now', 'smmpw' ), 'secondary' ); ?>
                </form>

                <h2><?php esc_html_e( 'Connection Test', 'smmpw' ); ?></h2>
                <p><?php esc_html_e( 'Click the button below to test the API credentials and ensure the connection is working.', 'smmpw' ); ?></p>
                <button class="button" id="smmpw-test-connection" data-nonce="<?php echo esc_attr( wp_create_nonce( 'smmpw_test_connection' ) ); ?>">
                    <?php esc_html_e( 'Test Connection', 'smmpw' ); ?>
                </button>
                <span id="smmpw-test-response"></span>
            </div>
            <script>
                (function($){
                    $('#smmpw-test-connection').on('click', function(e){
                        e.preventDefault();
                        var $button = $(this);
                        var $response = $('#smmpw-test-response');

                        $button.prop('disabled', true);
                        $response.text('<?php echo esc_js( __( 'Testing connection...', 'smmpw' ) ); ?>');

                        $.post(ajaxurl, {
                            action: 'smmpw_test_connection',
                            nonce: $button.data('nonce')
                        }).done(function(result){
                            if(result.success){
                                $response.text(result.data);
                            } else {
                                $response.text(result.data);
                            }
                        }).fail(function(){
                            $response.text('<?php echo esc_js( __( 'Unable to connect. Check your credentials.', 'smmpw' ) ); ?>');
                        }).always(function(){
                            $button.prop('disabled', false);
                        });
                    });
                })(jQuery);
            </script>
            <?php
        }

        /**
         * Render text field.
         *
         * @param array $args Field arguments.
         */
        public function render_text_field( $args ) {
            $settings = SMMPW_Plugin::instance()->get_settings();
            $option   = $args['option'];
            $type     = $args['type'] ?? 'text';
            $value    = $settings[ $option ] ?? '';
            $step     = $args['step'] ?? '';
            ?>
            <input type="<?php echo esc_attr( $type ); ?>" id="<?php echo esc_attr( $args['label_for'] ); ?>" name="<?php echo esc_attr( SMMPW_Plugin::OPTION_SETTINGS . '[' . $option . ']' ); ?>" value="<?php echo esc_attr( $value ); ?>" <?php if ( $step ) : ?>step="<?php echo esc_attr( $step ); ?>"<?php endif; ?> class="regular-text" />
            <?php if ( ! empty( $args['help'] ) ) : ?>
                <p class="description"><?php echo esc_html( $args['help'] ); ?></p>
            <?php endif;
        }

        /**
         * Render category dropdown field.
         */
        public function render_category_dropdown() {
            $settings   = SMMPW_Plugin::instance()->get_settings();
            $categories = get_terms( array(
                'taxonomy'   => 'product_cat',
                'hide_empty' => false,
            ) );
            ?>
            <select id="smmpw_default_category" name="<?php echo esc_attr( SMMPW_Plugin::OPTION_SETTINGS . '[default_category]' ); ?>">
                <option value="0" <?php selected( 0, $settings['default_category'] ); ?>><?php esc_html_e( '— Select —', 'smmpw' ); ?></option>
                <?php foreach ( $categories as $category ) : ?>
                    <option value="<?php echo esc_attr( $category->term_id ); ?>" <?php selected( $category->term_id, $settings['default_category'] ); ?>><?php echo esc_html( $category->name ); ?></option>
                <?php endforeach; ?>
            </select>
            <p class="description"><?php esc_html_e( 'Choose which category synchronized products will be assigned to by default.', 'smmpw' ); ?></p>
            <?php
        }

        /**
         * Render checkbox field.
         *
         * @param array $args Field arguments.
         */
        public function render_checkbox_field( $args ) {
            $settings = SMMPW_Plugin::instance()->get_settings();
            $option   = $args['option'];
            $value    = ! empty( $settings[ $option ] );
            ?>
            <label>
                <input type="checkbox" id="<?php echo esc_attr( $args['label_for'] ); ?>" name="<?php echo esc_attr( SMMPW_Plugin::OPTION_SETTINGS . '[' . $option . ']' ); ?>" value="1" <?php checked( $value ); ?> />
                <?php if ( ! empty( $args['description'] ) ) : ?>
                    <?php echo esc_html( $args['description'] ); ?>
                <?php endif; ?>
            </label>
            <?php
        }

        /**
         * Render select field.
         *
         * @param array $args Field arguments.
         */
        public function render_select_field( $args ) {
            $settings = SMMPW_Plugin::instance()->get_settings();
            $option   = $args['option'];
            $value    = $settings[ $option ] ?? '';
            ?>
            <select id="<?php echo esc_attr( $args['label_for'] ); ?>" name="<?php echo esc_attr( SMMPW_Plugin::OPTION_SETTINGS . '[' . $option . ']' ); ?>">
                <?php foreach ( $args['options'] as $key => $label ) : ?>
                    <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $value ); ?>><?php echo esc_html( $label ); ?></option>
                <?php endforeach; ?>
            </select>
            <?php
        }
    }
}
