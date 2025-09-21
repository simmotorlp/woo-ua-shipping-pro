<?php

use WooUaShippingPro\Plugin;

if (! defined('ABSPATH')) {
    exit;
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'WooUaShippingPro\\';

    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $relative_path = str_replace('\\', DIRECTORY_SEPARATOR, $relative);
    $file = __DIR__ . '/../src/' . $relative_path . '.php';

    if (is_readable($file)) {
        require_once $file;
    }
});

if (! function_exists('woo_ua_shipping_pro')) {
    function woo_ua_shipping_pro(): Plugin
    {
        static $instance;

        if (! $instance instanceof Plugin) {
            $instance = new Plugin();
        }

        return $instance;
    }
}

define('UA_SHIPPING_PRO_MIN_WP', '6.5');

define('UA_SHIPPING_PRO_MIN_WC', '8.0');

define('UA_SHIPPING_PRO_MIN_PHP', '7.4');

register_activation_hook(UA_SHIPPING_PRO_PLUGIN_FILE, [Plugin::class, 'activate']);

register_deactivation_hook(UA_SHIPPING_PRO_PLUGIN_FILE, [Plugin::class, 'deactivate']);

add_action('plugins_loaded', static function (): void {
    if (! woo_ua_shipping_pro()->check_requirements()) {
        return;
    }

    woo_ua_shipping_pro()->boot();
});
