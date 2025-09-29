<?php
/**
 * Uninstall script for WooCommerce SMM Panel Connector.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'smmpw_settings' );
