<?php
/**
 * Plugin Name:       WooCommerce SMM Provider API
 * Plugin URI:        https://example.com/
 * Description:       Expose your WooCommerce services through a Perfect Panel compatible SMM provider API endpoint.
 * Version:           1.1.0
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

if ( class_exists( 'SMMPW_Plugin' ) ) {
    return;
}

/**
 * Bootstrap class for the SMM provider plugin.
 */
final class SMMPW_Plugin {
        const VERSION                 = '1.1.0';
        const OPTION_GENERAL_SETTINGS = 'smmpw_provider_settings';
        const OPTION_API_KEYS         = 'smmpw_api_keys';

        /**
         * Singleton instance.
         *
         * @var SMMPW_Plugin|null
         */
        private static $instance = null;

        /**
         * Retrieve the singleton instance.
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
         * Register activation hook.
         */
        public static function activate() {
            if ( class_exists( 'SMMPW_Customer_Keys' ) ) {
                SMMPW_Customer_Keys::activate();
            }
        }

        /**
         * Register deactivation hook.
         */
        public static function deactivate() {
            // Nothing special yet, but keep hook for future use.
        }

        /**
         * Constructor. Defines constants, loads dependencies, and registers hooks.
         */
        private function __construct() {
            $this->define_constants();
            $this->includes();

            register_activation_hook( __FILE__, array( 'SMMPW_Plugin', 'activate' ) );
            register_deactivation_hook( __FILE__, array( 'SMMPW_Plugin', 'deactivate' ) );

            add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
            add_action( 'admin_init', array( $this, 'maybe_show_missing_wc_notice' ) );

            if ( class_exists( 'WooCommerce' ) ) {
                SMMPW_Settings_Page::instance();
                SMMPW_Product_Meta::instance();
                SMMPW_Customer_Keys::instance();
                SMMPW_API_Endpoint::instance();
            }
        }

        /**
         * Define reusable constants.
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
         * Include required class files.
         */
        private function includes() {
            require_once SMMPW_PLUGIN_PATH . 'includes/class-smmpw-settings-page.php';
            require_once SMMPW_PLUGIN_PATH . 'includes/class-smmpw-product-meta.php';
            require_once SMMPW_PLUGIN_PATH . 'includes/class-smmpw-customer-keys.php';
            require_once SMMPW_PLUGIN_PATH . 'includes/class-smmpw-api-endpoint.php';
        }

        /**
         * Load plugin translations.
         */
        public function load_textdomain() {
            load_plugin_textdomain( 'smmpw', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
        }

        /**
         * Display an admin notice if WooCommerce is not active.
         */
        public function maybe_show_missing_wc_notice() {
            if ( class_exists( 'WooCommerce' ) ) {
                return;
            }

            add_action( 'admin_notices', array( $this, 'render_missing_wc_notice' ) );
        }

        /**
         * Render the WooCommerce missing notice.
         */
        public function render_missing_wc_notice() {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                esc_html__( 'WooCommerce SMM Provider API requires WooCommerce to be installed and active.', 'smmpw' )
            );
        }
    }

SMMPW_Plugin::instance();
