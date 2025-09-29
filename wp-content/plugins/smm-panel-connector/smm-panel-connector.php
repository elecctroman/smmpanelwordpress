<?php
/**
 * Plugin Name:       WooCommerce SMM Panel Connector
 * Plugin URI:        https://example.com/
 * Description:       Connect your WooCommerce store to your SMM panel or Perfect Panel instance, sync services, and sell them as WooCommerce products.
 * Version:           1.0.0
 * Author:            Your Company
 * Author URI:        https://example.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       smmpw
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SMMPW_Plugin' ) ) {
    /**
     * Main plugin class responsible for bootstrapping admin UI and synchronization logic.
     */
    final class SMMPW_Plugin {
        const VERSION          = '1.0.0';
        const OPTION_SETTINGS  = 'smmpw_settings';
        const CRON_HOOK        = 'smmpw_sync_products_event';

        /**
         * Singleton instance.
         *
         * @var SMMPW_Plugin
         */
        private static $instance;

        /**
         * Plugin bootstrap.
         *
         * @return SMMPW_Plugin
         */
        public static function instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        /**
         * Constructor registers hooks.
         */
        private function __construct() {
            $this->define_constants();
            $this->includes();

            register_activation_hook( __FILE__, array( 'SMMPW_Plugin', 'activate' ) );
            register_deactivation_hook( __FILE__, array( 'SMMPW_Plugin', 'deactivate' ) );

            add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
            add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
            add_action( 'admin_init', array( $this, 'register_settings' ) );
            add_action( 'admin_post_smmpw_manual_sync', array( $this, 'handle_manual_sync' ) );
            add_action( self::CRON_HOOK, array( $this, 'sync_products' ) );

            if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
                add_action( 'wp_ajax_smmpw_test_connection', array( $this, 'ajax_test_connection' ) );
            }
        }

        /**
         * Define plugin constants.
         */
        private function define_constants() {
            if ( ! defined( 'SMMPW_PLUGIN_FILE' ) ) {
                define( 'SMMPW_PLUGIN_FILE', __FILE__ );
            }

            if ( ! defined( 'SMMPW_PLUGIN_PATH' ) ) {
                define( 'SMMPW_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
            }

            if ( ! defined( 'SMMPW_PLUGIN_URL' ) ) {
                define( 'SMMPW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
            }
        }

        /**
         * Load dependencies.
         */
        private function includes() {
            require_once SMMPW_PLUGIN_PATH . 'includes/class-smmpw-settings-page.php';
            require_once SMMPW_PLUGIN_PATH . 'includes/class-smmpw-service-sync.php';
        }

        /**
         * Load plugin textdomain.
         */
        public function load_textdomain() {
            load_plugin_textdomain( 'smmpw', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
        }

        /**
         * Register the settings page in the WooCommerce submenu.
         */
        public function register_settings_page() {
            SMMPW_Settings_Page::instance();
        }

        /**
         * Register plugin settings.
         */
        public function register_settings() {
            register_setting( 'smmpw_settings', self::OPTION_SETTINGS, array( $this, 'sanitize_settings' ) );
        }

        /**
         * Validate and sanitize plugin settings before saving.
         *
         * @param array $input Settings submitted via admin form.
         *
         * @return array Sanitized settings.
         */
        public function sanitize_settings( $input ) {
            $output = array();

            $output['api_url'] = isset( $input['api_url'] ) ? esc_url_raw( $input['api_url'] ) : '';
            $output['api_key'] = isset( $input['api_key'] ) ? sanitize_text_field( $input['api_key'] ) : '';
            $output['default_category'] = isset( $input['default_category'] ) ? absint( $input['default_category'] ) : 0;
            $output['sync_pricing'] = ! empty( $input['sync_pricing'] );
            $output['price_markup_type'] = in_array( $input['price_markup_type'] ?? 'percent', array( 'percent', 'fixed' ), true ) ? $input['price_markup_type'] : 'percent';
            $output['price_markup_value'] = isset( $input['price_markup_value'] ) ? floatval( $input['price_markup_value'] ) : 0.0;
            $output['stock_behavior'] = in_array( $input['stock_behavior'] ?? 'in_stock', array( 'in_stock', 'out_of_stock', 'remote' ), true ) ? $input['stock_behavior'] : 'in_stock';

            $allowed_schedules = array( 'quarter_hour', 'hourly', 'twicedaily', 'daily' );
            $requested_schedule = isset( $input['sync_schedule'] ) ? sanitize_text_field( $input['sync_schedule'] ) : 'hourly';
            $output['sync_schedule'] = in_array( $requested_schedule, $allowed_schedules, true ) ? $requested_schedule : 'hourly';

            $this->maybe_reschedule_event( $output['sync_schedule'] );

            return $output;
        }

        /**
         * Handle manual synchronization request.
         */
        public function handle_manual_sync() {
            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                wp_die( esc_html__( 'You are not allowed to perform this action.', 'smmpw' ) );
            }

            check_admin_referer( 'smmpw_manual_sync' );

            $result = $this->sync_products();

            $redirect_url = add_query_arg(
                array(
                    'page'   => 'smmpw-settings',
                    'synced' => $result ? '1' : '0',
                ),
                admin_url( 'admin.php' )
            );

            wp_safe_redirect( $redirect_url );
            exit;
        }

        /**
         * Test API connectivity via AJAX.
         */
        public function ajax_test_connection() {
            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                wp_send_json_error( __( 'You are not allowed to perform this action.', 'smmpw' ), 403 );
            }

            check_ajax_referer( 'smmpw_test_connection', 'nonce' );

            $settings = $this->get_settings();
            $api      = new SMMPW_Service_Sync( $settings );

            $response = $api->ping();

            if ( is_wp_error( $response ) ) {
                wp_send_json_error( $response->get_error_message() );
            }

            wp_send_json_success( __( 'Connection successful.', 'smmpw' ) );
        }

        /**
         * Schedule synchronization event on activation.
         */
        public static function activate() {
            $settings = get_option( self::OPTION_SETTINGS, array( 'sync_schedule' => 'hourly' ) );
            $schedule = $settings['sync_schedule'] ?? 'hourly';

            if ( ! wp_next_scheduled( self::CRON_HOOK ) && self::is_valid_schedule( $schedule ) ) {
                wp_schedule_event( time() + MINUTE_IN_SECONDS, $schedule, self::CRON_HOOK );
            }
        }

        /**
         * Clear scheduled event on deactivation.
         */
        public static function deactivate() {
            $timestamp = wp_next_scheduled( self::CRON_HOOK );
            if ( $timestamp ) {
                wp_unschedule_event( $timestamp, self::CRON_HOOK );
            }
        }

        /**
         * Ensure cron schedule matches saved setting.
         *
         * @param string $schedule Saved schedule.
         */
        private function maybe_reschedule_event( $schedule ) {
            $timestamp = wp_next_scheduled( self::CRON_HOOK );
            if ( $timestamp ) {
                wp_unschedule_event( $timestamp, self::CRON_HOOK );
            }

            if ( ! empty( $schedule ) && self::is_valid_schedule( $schedule ) ) {
                wp_schedule_event( time() + MINUTE_IN_SECONDS, $schedule, self::CRON_HOOK );
            }
        }

        /**
         * Validate that the provided schedule exists.
         *
         * @param string $schedule Cron schedule key.
         *
         * @return bool
         */
        private static function is_valid_schedule( $schedule ) {
            $schedules = wp_get_schedules();

            return isset( $schedules[ $schedule ] );
        }

        /**
         * Retrieve plugin settings with defaults.
         *
         * @return array
         */
        public function get_settings() {
            $defaults = array(
                'api_url'            => '',
                'api_key'            => '',
                'default_category'   => 0,
                'sync_pricing'       => true,
                'price_markup_type'  => 'percent',
                'price_markup_value' => 25,
                'stock_behavior'     => 'in_stock',
                'sync_schedule'      => 'hourly',
            );

            $settings = get_option( self::OPTION_SETTINGS, array() );

            return wp_parse_args( $settings, $defaults );
        }

        /**
         * Synchronize products with remote SMM panel.
         *
         * @return bool|
         */
        public function sync_products() {
            if ( ! class_exists( 'WooCommerce' ) ) {
                return false;
            }

            $settings = $this->get_settings();

            if ( empty( $settings['api_url'] ) || empty( $settings['api_key'] ) ) {
                return false;
            }

            $sync = new SMMPW_Service_Sync( $settings );

            return $sync->sync();
        }
    }
}

// Register custom schedules.
add_filter( 'cron_schedules', function ( $schedules ) {
    if ( ! isset( $schedules['quarter_hour'] ) ) {
        $schedules['quarter_hour'] = array(
            'interval' => 15 * MINUTE_IN_SECONDS,
            'display'  => __( 'Every 15 Minutes', 'smmpw' ),
        );
    }

    if ( ! isset( $schedules['twice_daily'] ) ) {
        $schedules['twice_daily'] = array(
            'interval' => 12 * HOUR_IN_SECONDS,
            'display'  => __( 'Twice Daily', 'smmpw' ),
        );
    }

    return $schedules;
} );

// Bootstrap plugin.
SMMPW_Plugin::instance();
