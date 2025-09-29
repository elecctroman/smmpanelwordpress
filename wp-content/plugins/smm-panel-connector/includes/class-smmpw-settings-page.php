<?php
/**

 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SMMPW_Settings_Page' ) ) {
    /**

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

                array( $this, 'render_page' )
            );
        }

        /**

         */
        public function render_page() {
            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                wp_die( esc_html__( 'You are not allowed to view this page.', 'smmpw' ) );
            }


                    submit_button();
                    ?>
                </form>

                <hr />


            <?php
        }

        /**

        }
    }
}
