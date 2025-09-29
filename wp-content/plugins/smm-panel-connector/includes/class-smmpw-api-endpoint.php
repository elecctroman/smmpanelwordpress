<?php
/**
 * Front-end API endpoint for Perfect Panel compatible integrations.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SMMPW_API_Endpoint' ) ) {
    /**
     * Handles incoming API requests and converts them to WooCommerce orders.
     */
    class SMMPW_API_Endpoint {
        /**
         * Singleton instance.
         *
         * @var SMMPW_API_Endpoint|null
         */
        private static $instance = null;

        /**
         * Retrieve singleton instance.
         *
         * @return SMMPW_API_Endpoint
         */
        public static function instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        /**
         * Constructor wires up hooks.
         */
        private function __construct() {
            add_action( 'template_redirect', array( $this, 'maybe_handle_request' ), 0 );
        }

        /**
         * Check if the current request targets the API and process it if so.
         */
        public function maybe_handle_request() {
            if ( ! isset( $_GET['smmpw-api'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
                return;
            }

            $this->handle_request();
        }

        /**
         * Process the API request.
         */
        private function handle_request() {
            if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
                $params = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification
            } else {
                $params = wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification
            }

            $key = isset( $params['key'] ) ? sanitize_text_field( $params['key'] ) : '';
            if ( empty( $key ) || ! $this->is_key_valid( $key ) ) {
                $this->send_error( __( 'Invalid API key.', 'smmpw' ), 403 );
            }

            $action = isset( $params['action'] ) ? sanitize_key( $params['action'] ) : '';
            if ( empty( $action ) ) {
                $this->send_error( __( 'Missing API action.', 'smmpw' ) );
            }

            switch ( $action ) {
                case 'services':
                    $this->send_response( $this->get_services_response() );
                    break;
                case 'add':
                    $this->handle_add_action( $params );
                    break;
                case 'status':
                    $this->handle_status_action( $params );
                    break;
                case 'balance':
                    $this->handle_balance_action();
                    break;
                default:
                    $this->send_error( __( 'Unsupported action.', 'smmpw' ) );
            }
        }

        /**
         * Ensure the provided API key exists.
         *
         * @param string $key Raw API key.
         *
         * @return bool
         */
        private function is_key_valid( $key ) {
            $keys = get_option( SMMPW_Plugin::OPTION_API_KEYS, array() );

            if ( isset( $keys[ $key ] ) ) {
                return true;
            }

            if ( class_exists( 'SMMPW_Customer_Keys' ) ) {
                $user_ids = get_users(
                    array(
                        'meta_key'   => SMMPW_Customer_Keys::USER_META_KEY,
                        'meta_value' => $key,
                        'fields'     => 'ids',
                        'number'     => 1,
                    )
                );

                if ( ! empty( $user_ids ) ) {
                    return true;
                }
            }

            return false;
        }

        /**
         * Build the services response payload.
         *
         * @return array
         */
        private function get_services_response() {
            $products = wc_get_products(
                array(
                    'limit'      => -1,
                    'status'     => array( 'publish' ),
                    'meta_query' => array(
                        array(
                            'key'   => '_smmpw_api_enabled',
                            'value' => 'yes',
                        ),
                    ),
                )
            );

            $services = array();
            $currency = get_woocommerce_currency();

            foreach ( $products as $product ) {
                $product_id = $product->get_id();
                $service_id = get_post_meta( $product_id, '_smmpw_api_service_id', true );
                $min        = get_post_meta( $product_id, '_smmpw_api_min', true );
                $max        = get_post_meta( $product_id, '_smmpw_api_max', true );
                $rate       = get_post_meta( $product_id, '_smmpw_api_rate', true );

                if ( '' === $service_id ) {
                    $service_id = (string) $product_id;
                }

                if ( '' === $rate ) {
                    $price = (float) $product->get_price();
                    $rate  = $price * 1000;
                }

                $categories = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'names' ) );

                $services[] = array(
                    'service'     => is_numeric( $service_id ) ? (int) $service_id : $service_id,
                    'name'        => $product->get_name(),
                    'category'    => ! empty( $categories ) ? $categories[0] : __( 'Uncategorized', 'smmpw' ),
                    'type'        => 'default',
                    'rate'        => number_format( (float) $rate, 4, '.', '' ),
                    'min'         => (int) $min,
                    'max'         => (int) $max,
                    'description' => wp_strip_all_tags( $product->get_short_description() ),
                    'currency'    => $currency,
                );
            }

            return $services;
        }

        /**
         * Handle `action=add` requests.
         *
         * @param array $params Request parameters.
         */
        private function handle_add_action( $params ) {
            $service_id = isset( $params['service'] ) ? sanitize_text_field( $params['service'] ) : '';
            $quantity   = isset( $params['quantity'] ) ? floatval( $params['quantity'] ) : 0;
            $link       = isset( $params['link'] ) ? sanitize_text_field( $params['link'] ) : '';

            if ( empty( $service_id ) ) {
                $this->send_error( __( 'Service parameter is required.', 'smmpw' ) );
            }

            if ( $quantity <= 0 ) {
                $this->send_error( __( 'Quantity must be greater than zero.', 'smmpw' ) );
            }

            if ( empty( $link ) ) {
                $this->send_error( __( 'Link parameter is required.', 'smmpw' ) );
            }

            $product_id = $this->get_product_id_by_service_id( $service_id );
            if ( ! $product_id ) {
                $this->send_error( __( 'Service not found.', 'smmpw' ) );
            }

            $min = (float) get_post_meta( $product_id, '_smmpw_api_min', true );
            $max = (float) get_post_meta( $product_id, '_smmpw_api_max', true );

            if ( $min > 0 && $quantity < $min ) {
                $this->send_error( sprintf( __( 'Quantity must be at least %s.', 'smmpw' ), $min ) );
            }

            if ( $max > 0 && $quantity > $max ) {
                $this->send_error( sprintf( __( 'Quantity must be lower than or equal to %s.', 'smmpw' ), $max ) );
            }

            $charge = $this->calculate_charge( $product_id, $quantity );
            if ( $charge <= 0 ) {
                $this->send_error( __( 'Unable to calculate order charge.', 'smmpw' ) );
            }

            $order_id = $this->create_order( $product_id, $quantity, $charge, $link, $params );

            $this->send_response(
                array(
                    'order'  => $order_id,
                    'charge' => $charge,
                    'currency' => get_woocommerce_currency(),
                )
            );
        }

        /**
         * Handle `action=status` requests.
         *
         * @param array $params Request parameters.
         */
        private function handle_status_action( $params ) {
            $order_id = isset( $params['order'] ) ? absint( $params['order'] ) : 0;
            if ( ! $order_id ) {
                $this->send_error( __( 'Order parameter is required.', 'smmpw' ) );
            }

            $order = wc_get_order( $order_id );
            if ( ! $order || 'yes' !== $order->get_meta( '_smmpw_api_order', true ) ) {
                $this->send_error( __( 'Order not found.', 'smmpw' ) );
            }

            $response = array(
                'order'    => $order_id,
                'status'   => $order->get_status(),
                'charge'   => (float) $order->get_total(),
                'link'     => $order->get_meta( '_smmpw_api_link', true ),
                'quantity' => (float) $order->get_meta( '_smmpw_api_quantity', true ),
                'currency' => $order->get_currency(),
            );

            $this->send_response( $response );
        }

        /**
         * Handle `action=balance` requests. Since WooCommerce does not track reseller balances,
         * we simply return zero to indicate pay-as-you-go behavior.
         */
        private function handle_balance_action() {
            $this->send_response(
                array(
                    'balance'  => 0,
                    'currency' => get_woocommerce_currency(),
                )
            );
        }

        /**
         * Calculate the total charge for an API order.
         *
         * @param int   $product_id Product ID.
         * @param float $quantity   Requested quantity.
         *
         * @return float
         */
        private function calculate_charge( $product_id, $quantity ) {
            $rate = get_post_meta( $product_id, '_smmpw_api_rate', true );
            if ( '' === $rate ) {
                $product = wc_get_product( $product_id );
                if ( ! $product ) {
                    return 0;
                }

                $price = (float) $product->get_price();
                $rate  = $price * 1000;
            }

            $charge = (float) $rate * ( (float) $quantity / 1000 );

            return round( $charge, 4 );
        }

        /**
         * Create a WooCommerce order for the API request.
         *
         * @param int   $product_id Product ID.
         * @param float $quantity   Requested quantity.
         * @param float $charge     Calculated charge.
         * @param string $link      Target link submitted by the reseller.
         * @param array  $params    Original request parameters for metadata.
         *
         * @return int Order ID.
         */
        private function create_order( $product_id, $quantity, $charge, $link, $params ) {
            $settings = get_option( SMMPW_Plugin::OPTION_GENERAL_SETTINGS, array() );
            $status   = isset( $settings['order_status'] ) ? $settings['order_status'] : 'wc-processing';

            $order = wc_create_order();

            if ( is_wp_error( $order ) ) {
                $this->send_error( __( 'Failed to create order.', 'smmpw' ), 500 );
            }

            $item = new WC_Order_Item_Product();
            $item->set_product_id( $product_id );
            $item->set_quantity( 1 );
            $item->set_total( $charge );
            $item->set_subtotal( $charge );
            $order->add_item( $item );

            $order->update_meta_data( '_smmpw_api_order', 'yes' );
            $order->update_meta_data( '_smmpw_api_service_id', $this->get_product_service_id( $product_id ) );
            $order->update_meta_data( '_smmpw_api_quantity', $quantity );
            $order->update_meta_data( '_smmpw_api_link', $link );
            $order->update_meta_data( '_smmpw_api_raw_request', wp_json_encode( $this->sanitize_for_storage( $params ) ) );
            if ( isset( $params['key'] ) ) {
                $order->update_meta_data( '_smmpw_api_client_key', sanitize_text_field( $params['key'] ) );
            }

            $order->set_currency( get_woocommerce_currency() );
            $order->set_payment_method( 'smmpw_api' );
            $order->set_payment_method_title( __( 'SMM API', 'smmpw' ) );

            $customer_name  = isset( $settings['default_customer_name'] ) ? $settings['default_customer_name'] : __( 'API Client', 'smmpw' );
            $customer_email = isset( $settings['default_customer_email'] ) ? $settings['default_customer_email'] : get_option( 'admin_email' );

            $name_parts = explode( ' ', trim( $customer_name ), 2 );

            $billing = array(
                'first_name' => $name_parts[0],
                'last_name'  => isset( $name_parts[1] ) ? $name_parts[1] : '',
                'email'      => $customer_email,
            );

            $order->set_address( $billing, 'billing' );
            $order->set_total( $charge );
            $order->calculate_taxes();
            $order->save();

            if ( 0 === strpos( $status, 'wc-' ) ) {
                $status = substr( $status, 3 );
            }

            $order->update_status( $status );

            /**
             * Fires after an order has been created via the SMM API.
             *
             * @param int   $order_id Newly created order ID.
             * @param array $params   Original request parameters.
             */
            do_action( 'smmpw_api_order_created', $order->get_id(), $this->sanitize_for_storage( $params ) );

            return $order->get_id();
        }

        /**
         * Convert request parameters into something safe for storage.
         *
         * @param array $params Request parameters.
         *
         * @return array
         */
        private function sanitize_for_storage( $params ) {
            $safe = array();
            foreach ( $params as $key => $value ) {
                $safe_key = sanitize_key( $key );

                if ( is_scalar( $value ) ) {
                    $safe[ $safe_key ] = sanitize_text_field( (string) $value );
                }
            }

            return $safe;
        }

        /**
         * Resolve a product ID from the exposed service ID.
         *
         * @param string $service_id Service identifier shared with clients.
         *
         * @return int|false
         */
        private function get_product_id_by_service_id( $service_id ) {
            global $wpdb;

            $service_id = trim( $service_id );

            $post_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
                    '_smmpw_api_service_id',
                    $service_id
                )
            );

            if ( $post_id ) {
                return (int) $post_id;
            }

            if ( ctype_digit( $service_id ) ) {
                $product = wc_get_product( (int) $service_id );
                if ( $product && 'yes' === $product->get_meta( '_smmpw_api_enabled', true ) ) {
                    return $product->get_id();
                }
            }

            return false;
        }

        /**
         * Determine the service ID exposed to clients for a given product.
         *
         * @param int $product_id Product ID.
         *
         * @return string
         */
        private function get_product_service_id( $product_id ) {
            $service_id = get_post_meta( $product_id, '_smmpw_api_service_id', true );

            if ( '' === $service_id ) {
                $service_id = (string) $product_id;
            }

            return $service_id;
        }

        /**
         * Send a JSON response and exit.
         *
         * @param array $data Response payload.
         */
        private function send_response( $data ) {
            nocache_headers();
            wp_send_json( $data );
        }

        /**
         * Send an error response and exit.
         *
         * @param string $message Human-readable error message.
         * @param int    $status  HTTP status code.
         */
        private function send_error( $message, $status = 400 ) {
            nocache_headers();
            status_header( $status );
            wp_send_json( array( 'error' => $message ) );
        }
    }
}
