<?php
/**
 * Front-end customer tools for retrieving API credentials.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SMMPW_Customer_Keys' ) ) {
    /**
     * Adds a "API Key" endpoint to the WooCommerce account area.
     */
    class SMMPW_Customer_Keys {
        const USER_META_KEY = '_smmpw_api_key';
        const ENDPOINT      = 'smmpw-api';

        /**
         * Singleton instance.
         *
         * @var SMMPW_Customer_Keys|null
         */
        private static $instance = null;

        /**
         * Retrieve the singleton instance.
         *
         * @return SMMPW_Customer_Keys
         */
        public static function instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        /**
         * Register rewrite endpoint on activation.
         */
        public static function activate() {
            add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
            flush_rewrite_rules();
        }

        /**
         * Constructor wires up hooks for the My Account endpoint.
         */
        private function __construct() {
            add_action( 'init', array( $this, 'register_endpoint' ) );
            add_filter( 'woocommerce_get_query_vars', array( $this, 'register_query_var' ) );
            add_filter( 'woocommerce_account_menu_items', array( $this, 'register_menu_item' ), PHP_INT_MAX );
            add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( $this, 'render_endpoint' ) );
            add_action( 'woocommerce_account_dashboard', array( $this, 'render_dashboard_panel' ) );
            add_shortcode( 'smmpw_customer_api_credentials', array( $this, 'render_shortcode' ) );
            add_action( 'admin_post_smmpw_generate_user_key', array( $this, 'handle_generate_key' ) );
            add_action( 'admin_post_nopriv_smmpw_generate_user_key', array( $this, 'handle_generate_key' ) );
        }

        /**
         * Register the pretty permalink endpoint.
         */
        public function register_endpoint() {
            add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
        }

        /**
         * Register endpoint query var so WooCommerce recognises it even with custom menus.
         *
         * @param array $query_vars Existing query vars.
         *
         * @return array
         */
        public function register_query_var( $query_vars ) {
            $query_vars[ self::ENDPOINT ] = self::ENDPOINT;

            return $query_vars;
        }

        /**
         * Add the "API Key" item to the WooCommerce account navigation.
         *
         * @param array $items Existing items.
         *
         * @return array
         */
        public function register_menu_item( $items ) {
            $label = __( 'API Anahtarı', 'smmpw' );

            if ( isset( $items[ self::ENDPOINT ] ) ) {
                return $items;
            }

            if ( isset( $items['customer-logout'] ) ) {
                $logout = $items['customer-logout'];
                unset( $items['customer-logout'] );

                $items[ self::ENDPOINT ] = $label;
                $items['customer-logout'] = $logout;

                return $items;
            }

            $items[ self::ENDPOINT ] = $label;

            return $items;
        }

        /**
         * Render the endpoint content.
         */
        public function render_endpoint() {
            if ( ! is_user_logged_in() ) {
                esc_html_e( 'API anahtarınızı görüntülemek için giriş yapmalısınız.', 'smmpw' );
                return;
            }

            $user_id = get_current_user_id();
            $api_key = $this->get_user_key( $user_id );
            $api_url = $this->get_api_url();

            if ( function_exists( 'wc_print_notices' ) ) {
                wc_print_notices();
            }
            $this->render_credentials_section(
                $api_key,
                $api_url,
                array(
                    'include_heading'     => true,
                    'show_description'    => true,
                    'show_generate_form'  => true,
                    'show_view_link'      => false,
                )
            );
        }

        /**
         * Show a quick access panel on the default account dashboard.
         */
        public function render_dashboard_panel() {
            if ( ! is_user_logged_in() ) {
                return;
            }

            $user_id      = get_current_user_id();
            $api_key      = $this->get_user_key( $user_id );
            $api_url      = $this->get_api_url();
            $endpoint_url = function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( self::ENDPOINT ) : add_query_arg( self::ENDPOINT, '', home_url( '/' ) );

            $this->render_credentials_section(
                $api_key,
                $api_url,
                array(
                    'include_heading'     => true,
                    'show_description'    => false,
                    'show_generate_form'  => false,
                    'show_view_link'      => $endpoint_url,
                    'extra_classes'       => 'smmpw-dashboard-credentials',
                )
            );
        }

        /**
         * Render the credentials via shortcode output.
         *
         * @return string
         */
        public function render_shortcode() {
            if ( ! is_user_logged_in() ) {
                return '';
            }

            $user_id = get_current_user_id();
            $api_key = $this->get_user_key( $user_id );
            $api_url = $this->get_api_url();

            ob_start();
            $this->render_credentials_section(
                $api_key,
                $api_url,
                array(
                    'include_heading'     => true,
                    'show_description'    => true,
                    'show_generate_form'  => true,
                    'show_view_link'      => false,
                    'extra_classes'       => 'smmpw-shortcode-credentials',
                )
            );

            return ob_get_clean();
        }

        /**
         * Handle the generate/regenerate key form submission.
         */
        public function handle_generate_key() {
            if ( ! is_user_logged_in() ) {
                $redirect = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url();
                wp_safe_redirect( $redirect );
                exit;
            }

            check_admin_referer( 'smmpw_generate_user_key' );

            $user_id = get_current_user_id();
            $new_key = strtolower( wp_generate_password( 40, false, false ) );

            update_user_meta( $user_id, self::USER_META_KEY, $new_key );

            if ( function_exists( 'wc_add_notice' ) ) {
                wc_add_notice( __( 'API anahtarınız başarıyla güncellendi.', 'smmpw' ), 'success' );
            }

            $endpoint_url = function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( self::ENDPOINT ) : home_url();

            wp_safe_redirect( $endpoint_url );
            exit;
        }

        /**
         * Retrieve the stored API key for a user.
         *
         * @param int $user_id User ID.
         *
         * @return string
         */
        private function get_user_key( $user_id ) {
            $key = get_user_meta( $user_id, self::USER_META_KEY, true );

            return is_string( $key ) ? $key : '';
        }

        /**
         * Retrieve the base API URL for customer usage.
         *
         * @return string
         */
        private function get_api_url() {
            return add_query_arg( 'smmpw-api', '1', home_url( '/' ) );
        }

        /**
         * Shared renderer for the credentials table.
         *
         * @param string $api_key API key value.
         * @param string $api_url API URL value.
         * @param array  $args    Rendering options.
         */
        private function render_credentials_section( $api_key, $api_url, $args ) {
            $defaults = array(
                'include_heading'    => true,
                'show_description'   => false,
                'show_generate_form' => false,
                'show_view_link'     => false,
                'extra_classes'      => '',
            );

            $args = wp_parse_args( $args, $defaults );

            $classes = array( 'smmpw-api-credentials' );

            if ( ! empty( $args['extra_classes'] ) ) {
                $classes[] = $args['extra_classes'];
            }

            ?>
            <section class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
                <?php if ( $args['include_heading'] ) : ?>
                    <h3><?php esc_html_e( 'API Bilgileri', 'smmpw' ); ?></h3>
                <?php endif; ?>

                <?php if ( $args['show_description'] ) : ?>
                    <p><?php esc_html_e( 'Bu bilgilerle Perfect Panel ve diğer paneller üzerinden sipariş oluşturabilirsiniz.', 'smmpw' ); ?></p>
                <?php endif; ?>

                <table class="shop_table shop_table_responsive">
                    <tbody>
                        <tr>
                            <th><?php esc_html_e( 'API URL', 'smmpw' ); ?></th>
                            <td><code><?php echo esc_html( $api_url ); ?></code></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'API Key', 'smmpw' ); ?></th>
                            <td>
                                <?php if ( $api_key ) : ?>
                                    <code><?php echo esc_html( $api_key ); ?></code>
                                <?php else : ?>
                                    <em><?php esc_html_e( 'Henüz bir API anahtarınız yok.', 'smmpw' ); ?></em>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php if ( $args['show_generate_form'] ) : ?>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <?php wp_nonce_field( 'smmpw_generate_user_key' ); ?>
                        <input type="hidden" name="action" value="smmpw_generate_user_key" />
                        <button type="submit" class="button button-primary">
                            <?php echo $api_key ? esc_html__( 'API Anahtarını Yenile', 'smmpw' ) : esc_html__( 'API Anahtarı Oluştur', 'smmpw' ); ?>
                        </button>
                    </form>
                <?php elseif ( ! empty( $args['show_view_link'] ) ) : ?>
                    <p>
                        <a class="button" href="<?php echo esc_url( $args['show_view_link'] ); ?>">
                            <?php esc_html_e( 'Detayları Görüntüle', 'smmpw' ); ?>
                        </a>
                    </p>
                <?php endif; ?>
            </section>
            <?php
        }
    }
}
