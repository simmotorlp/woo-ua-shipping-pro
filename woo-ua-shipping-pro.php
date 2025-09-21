<?php
/**
 * Plugin Name: UA Shipping Pro
 * Description: Checkout enhancements for Ukrainian carriers in WooCommerce.
 * Version: 1.0.0
 * Author: simmotorlp
 * Text Domain: woo-ua-shipping-pro
 * Domain Path: /languages
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! defined('UA_SHIPPING_PRO_PLUGIN_FILE')) {
    define('UA_SHIPPING_PRO_PLUGIN_FILE', __FILE__);
}

if (! defined('UA_SHIPPING_PRO_VERSION')) {
    define('UA_SHIPPING_PRO_VERSION', '1.0.0');
}

require_once __DIR__ . '/includes/bootstrap.php';
