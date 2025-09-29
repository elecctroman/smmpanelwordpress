<?php
/**
 * Uninstall script for WooCommerce SMM Provider API.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'smmpw_provider_settings' );
delete_option( 'smmpw_api_keys' );
