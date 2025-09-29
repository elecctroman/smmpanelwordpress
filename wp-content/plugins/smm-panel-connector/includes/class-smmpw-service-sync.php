<?php
/**
 * Handles synchronization between WooCommerce and remote SMM panels.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SMMPW_Service_Sync' ) ) {
    /**
     * Service synchronization class.
     */
    class SMMPW_Service_Sync {
        /**
         * Plugin settings.
         *
         * @var array
         */
        private $settings = array();

        /**
         * Constructor.
         *
         * @param array $settings Plugin settings.
         */
        public function __construct( $settings ) {
            $this->settings = $settings;
        }

        /**
         * Ping the remote API for connectivity checks.
         *
         * @return true|WP_Error
         */
        public function ping() {
            $response = $this->request( 'balance' );

            if ( is_wp_error( $response ) ) {
                return $response;
            }

            $code = wp_remote_retrieve_response_code( $response );
            if ( 200 !== $code ) {
                return new WP_Error( 'smmpw_ping_failed', sprintf( __( 'Unexpected response code: %d', 'smmpw' ), $code ) );
            }

            return true;
        }

        /**
         * Synchronize services with WooCommerce products.
         *
         * @return bool
         */
        public function sync() {
            $services = $this->fetch_services();

            if ( is_wp_error( $services ) ) {
                error_log( 'SMMPW sync error: ' . $services->get_error_message() );
                return false;
            }

            if ( empty( $services ) ) {
                return true;
            }

            foreach ( $services as $service ) {
                $this->upsert_product_from_service( $service );
            }

            return true;
        }

        /**
         * Fetch services from the remote panel.
         *
         * @return array|WP_Error
         */
        private function fetch_services() {
            $response = $this->request( 'services' );

            if ( is_wp_error( $response ) ) {
                return $response;
            }

            $data = json_decode( wp_remote_retrieve_body( $response ), true );

            if ( null === $data ) {
                return new WP_Error( 'smmpw_invalid_response', __( 'Invalid response received from API.', 'smmpw' ) );
            }

            if ( isset( $data['error'] ) ) {
                return new WP_Error( 'smmpw_api_error', $data['error'] );
            }

            // Perfect Panel returns array of services.
            if ( isset( $data['services'] ) ) {
                return $data['services'];
            }

            return $data;
        }

        /**
         * Create or update WooCommerce product based on a service payload.
         *
         * @param array $service Service data.
         */
        private function upsert_product_from_service( $service ) {
            if ( empty( $service['id'] ) ) {
                return;
            }

            $product_id = $this->get_product_by_service_id( $service['id'] );
            $product    = $product_id ? wc_get_product( $product_id ) : new WC_Product_Simple();

            $product->set_name( sanitize_text_field( $service['name'] ?? sprintf( __( 'Service %d', 'smmpw' ), $service['id'] ) ) );
            $product->set_status( 'publish' );
            $product->set_catalog_visibility( 'visible' );
            $product->set_virtual( true );
            $product->set_sold_individually( false );

            $product->update_meta_data( '_smmpw_service_id', absint( $service['id'] ) );
            $product->update_meta_data( '_smmpw_service_type', sanitize_text_field( $service['type'] ?? '' ) );
            $product->update_meta_data( '_smmpw_min', isset( $service['min'] ) ? intval( $service['min'] ) : 0 );
            $product->update_meta_data( '_smmpw_max', isset( $service['max'] ) ? intval( $service['max'] ) : 0 );
            $product->update_meta_data( '_smmpw_description', sanitize_textarea_field( $service['description'] ?? '' ) );

            $price = $this->calculate_price( $service );

            if ( $price > 0 ) {
                $product->set_regular_price( wc_format_decimal( $price ) );
            }

            $stock_behavior = $this->settings['stock_behavior'] ?? 'in_stock';
            switch ( $stock_behavior ) {
                case 'out_of_stock':
                    $product->set_stock_status( 'outofstock' );
                    break;
                case 'remote':
                    if ( isset( $service['status'] ) && 'active' === strtolower( $service['status'] ) ) {
                        $product->set_stock_status( 'instock' );
                    } else {
                        $product->set_stock_status( 'outofstock' );
                    }
                    break;
                case 'in_stock':
                default:
                    $product->set_stock_status( 'instock' );
                    break;
            }

            if ( ! empty( $this->settings['default_category'] ) ) {
                $product->set_category_ids( array( intval( $this->settings['default_category'] ) ) );
            }

            $product->save();
        }

        /**
         * Calculate WooCommerce price based on remote price and markup settings.
         *
         * @param array $service Service data.
         *
         * @return float
         */
        private function calculate_price( $service ) {
            $price_field = isset( $service['rate'] ) ? floatval( $service['rate'] ) : 0.0;

            if ( empty( $this->settings['sync_pricing'] ) ) {
                return $price_field;
            }

            $markup_value = floatval( $this->settings['price_markup_value'] ?? 0 );
            $markup_type  = $this->settings['price_markup_type'] ?? 'percent';

            if ( 'fixed' === $markup_type ) {
                $price_field += $markup_value;
            } else {
                $price_field += ( $price_field * ( $markup_value / 100 ) );
            }

            return max( 0, $price_field );
        }

        /**
         * Finds a product with the given service ID stored in meta.
         *
         * @param int|string $service_id Service identifier.
         *
         * @return int|null
         */
        private function get_product_by_service_id( $service_id ) {
            global $wpdb;

            $meta_key   = '_smmpw_service_id';
            $service_id = absint( $service_id );

            $product_id = $wpdb->get_var( $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %d LIMIT 1",
                $meta_key,
                $service_id
            ) );

            return $product_id ? intval( $product_id ) : null;
        }

        /**
         * Execute a request against the remote API using POST.
         *
         * @param string $action API action (services, balance, add, etc.).
         * @param array  $params Additional parameters.
         *
         * @return array|WP_Error
         */
        private function request( $action, $params = array() ) {
            if ( empty( $this->settings['api_url'] ) || empty( $this->settings['api_key'] ) ) {
                return new WP_Error( 'smmpw_missing_credentials', __( 'API credentials are missing.', 'smmpw' ) );
            }

            $body = wp_parse_args(
                $params,
                array(
                    'key'    => $this->settings['api_key'],
                    'action' => $action,
                )
            );

            $response = wp_remote_post(
                trailingslashit( $this->settings['api_url'] ),
                array(
                    'timeout' => 30,
                    'body'    => $body,
                )
            );

            if ( is_wp_error( $response ) ) {
                return $response;
            }

            return $response;
        }
    }
}
