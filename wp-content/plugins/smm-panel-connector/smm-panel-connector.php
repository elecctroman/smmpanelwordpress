<?php
/**

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


        /**
         * Singleton instance.
         *

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

         */
        private function __construct() {
            $this->define_constants();
            $this->includes();

            register_activation_hook( __FILE__, array( 'SMMPW_Plugin', 'activate' ) );
            register_deactivation_hook( __FILE__, array( 'SMMPW_Plugin', 'deactivate' ) );

            add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

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

         */
        public function load_textdomain() {
            load_plugin_textdomain( 'smmpw', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
        }

        /**

SMMPW_Plugin::instance();
