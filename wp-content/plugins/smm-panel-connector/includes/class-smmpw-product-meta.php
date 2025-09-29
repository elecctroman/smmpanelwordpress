<?php
/**
 * Product metadata integration for mapping WooCommerce products to API services.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SMMPW_Product_Meta' ) ) {
    /**
     * Adds meta boxes and saves data for products available through the API.
     */
    class SMMPW_Product_Meta {
        /**
         * Singleton instance.
         *
         * @var SMMPW_Product_Meta|null
         */
        private static $instance = null;

        /**
         * Retrieve singleton instance.
         *
         * @return SMMPW_Product_Meta
         */
        public static function instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        /**
         * Constructor registers WooCommerce hooks.
         */
        private function __construct() {
            add_action( 'woocommerce_product_options_general_product_data', array( $this, 'add_product_fields' ) );
            add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_product_fields' ), 10, 1 );
            add_filter( 'manage_edit-product_columns', array( $this, 'add_product_column' ) );
            add_action( 'manage_product_posts_custom_column', array( $this, 'render_product_column' ), 10, 2 );
        }

        /**
         * Display custom product fields for API configuration.
         */
        public function add_product_fields() {
            echo '<div class="options_group">';

            woocommerce_wp_checkbox(
                array(
                    'id'          => '_smmpw_api_enabled',
                    'label'       => __( 'Expose via SMM API', 'smmpw' ),
                    'description' => __( 'Allow this product to appear in the Perfect Panel compatible API responses.', 'smmpw' ),
                )
            );

            woocommerce_wp_text_input(
                array(
                    'id'          => '_smmpw_api_service_id',
                    'label'       => __( 'Service ID', 'smmpw' ),
                    'description' => __( 'Optional override for the service ID shown to resellers. Leave empty to use the product ID.', 'smmpw' ),
                    'desc_tip'    => true,
                )
            );

            woocommerce_wp_text_input(
                array(
                    'id'                => '_smmpw_api_min',
                    'label'             => __( 'Minimum quantity', 'smmpw' ),
                    'type'              => 'number',
                    'description'       => __( 'Smallest quantity you are willing to accept from the API.', 'smmpw' ),
                    'custom_attributes' => array(
                        'min'  => '0',
                        'step' => '1',
                    ),
                    'desc_tip'          => true,
                )
            );

            woocommerce_wp_text_input(
                array(
                    'id'                => '_smmpw_api_max',
                    'label'             => __( 'Maximum quantity', 'smmpw' ),
                    'type'              => 'number',
                    'description'       => __( 'Largest quantity the API should accept for a single order.', 'smmpw' ),
                    'custom_attributes' => array(
                        'min'  => '0',
                        'step' => '1',
                    ),
                    'desc_tip'          => true,
                )
            );

            woocommerce_wp_text_input(
                array(
                    'id'                => '_smmpw_api_rate',
                    'label'             => __( 'API rate (per 1000)', 'smmpw' ),
                    'type'              => 'number',
                    'description'       => __( 'Rate charged per 1000 units for API orders. Leave blank to use the product price.', 'smmpw' ),
                    'custom_attributes' => array(
                        'min'  => '0',
                        'step' => '0.0001',
                    ),
                    'desc_tip'          => true,
                )
            );

            echo '</div>';
        }

        /**
         * Persist product field values.
         *
         * @param WC_Product $product The product object being saved.
         */
        public function save_product_fields( $product ) {
            $enabled = isset( $_POST['_smmpw_api_enabled'] ) ? 'yes' : 'no'; // phpcs:ignore WordPress.Security.NonceVerification
            $product->update_meta_data( '_smmpw_api_enabled', $enabled );

            $service_id = isset( $_POST['_smmpw_api_service_id'] ) ? sanitize_text_field( wp_unslash( $_POST['_smmpw_api_service_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
            $product->update_meta_data( '_smmpw_api_service_id', $service_id );

            $min = isset( $_POST['_smmpw_api_min'] ) ? absint( wp_unslash( $_POST['_smmpw_api_min'] ) ) : '';// phpcs:ignore WordPress.Security.NonceVerification
            $product->update_meta_data( '_smmpw_api_min', $min );

            $max = isset( $_POST['_smmpw_api_max'] ) ? absint( wp_unslash( $_POST['_smmpw_api_max'] ) ) : '';// phpcs:ignore WordPress.Security.NonceVerification
            $product->update_meta_data( '_smmpw_api_max', $max );

            $rate = isset( $_POST['_smmpw_api_rate'] ) ? floatval( wp_unslash( $_POST['_smmpw_api_rate'] ) ) : '';// phpcs:ignore WordPress.Security.NonceVerification
            $product->update_meta_data( '_smmpw_api_rate', $rate );
        }

        /**
         * Add custom column to product list table.
         *
         * @param array $columns Existing columns.
         *
         * @return array
         */
        public function add_product_column( $columns ) {
            $columns['smmpw_api'] = __( 'SMM API', 'smmpw' );

            return $columns;
        }

        /**
         * Render the custom column content.
         *
         * @param string $column Column key.
         * @param int    $post_id Post ID.
         */
        public function render_product_column( $column, $post_id ) {
            if ( 'smmpw_api' !== $column ) {
                return;
            }

            $enabled    = get_post_meta( $post_id, '_smmpw_api_enabled', true );
            $service_id = get_post_meta( $post_id, '_smmpw_api_service_id', true );

            if ( 'yes' !== $enabled ) {
                echo '&mdash;';

                return;
            }

            if ( empty( $service_id ) ) {
                $service_id = $post_id;
            }

            printf(
                '<strong>%1$s</strong><br /><small>%2$s</small>',
                esc_html__( 'Enabled', 'smmpw' ),
                sprintf( esc_html__( 'Service ID: %s', 'smmpw' ), esc_html( $service_id ) )
            );
        }
    }
}
