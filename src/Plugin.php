<?php

namespace WooUaShippingPro;

use WooUaShippingPro\Admin\OrderMeta;
use WooUaShippingPro\Checkout\Fields;
use WooUaShippingPro\Directory\Store;
use WooUaShippingPro\Directory\Sync;
use WooUaShippingPro\Settings\Page;

class Plugin
{
    private bool $booted = false;

    /**
     * @var array<string, object>
     */
    private array $services = [];

    /**
     * @var string[]
     */
    private array $requirement_errors = [];

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;

        $this->register_hooks();
        $this->register_services();
    }

    public static function activate(): void
    {
        if (! function_exists('activate_plugin')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        Store::create_tables();
        Sync::schedule_initial_sync();
    }

    public static function deactivate(): void
    {
        Sync::unschedule_events();
    }

    public function check_requirements(): bool
    {
        if (version_compare(PHP_VERSION, UA_SHIPPING_PRO_MIN_PHP, '<')) {
            $this->requirement_errors[] = sprintf(
                /* translators: 1: current PHP version, 2: minimum PHP version */
                __('UA Shipping Pro requires PHP %2$s or higher. Current version: %1$s', 'woo-ua-shipping-pro'),
                PHP_VERSION,
                UA_SHIPPING_PRO_MIN_PHP
            );
        }

        global $wp_version;

        if (isset($wp_version) && version_compare($wp_version, UA_SHIPPING_PRO_MIN_WP, '<')) {
            $this->requirement_errors[] = sprintf(
                /* translators: 1: current WP version, 2: minimum WP version */
                __('UA Shipping Pro requires WordPress %2$s or higher. Current version: %1$s', 'woo-ua-shipping-pro'),
                $wp_version,
                UA_SHIPPING_PRO_MIN_WP
            );
        }

        if (! defined('WC_VERSION')) {
            $this->requirement_errors[] = __('WooCommerce must be active to use UA Shipping Pro.', 'woo-ua-shipping-pro');
        } elseif (version_compare(WC_VERSION, UA_SHIPPING_PRO_MIN_WC, '<')) {
            $this->requirement_errors[] = sprintf(
                /* translators: 1: current WC version, 2: minimum WC version */
                __('UA Shipping Pro requires WooCommerce %2$s or higher. Current version: %1$s', 'woo-ua-shipping-pro'),
                WC_VERSION,
                UA_SHIPPING_PRO_MIN_WC
            );
        }

        if (! empty($this->requirement_errors)) {
            add_action('admin_notices', [$this, 'render_notice']);

            return false;
        }

        return true;
    }

    public function render_notice(): void
    {
        if (empty($this->requirement_errors)) {
            return;
        }

        echo '<div class="notice notice-error"><p>' . esc_html($this->requirement_errors[0]) . '</p></div>';
    }

    public function get_plugin_path(): string
    {
        return plugin_dir_path(UA_SHIPPING_PRO_PLUGIN_FILE);
    }

    public function get_plugin_url(): string
    {
        return plugin_dir_url(UA_SHIPPING_PRO_PLUGIN_FILE);
    }

    public function version(): string
    {
        return UA_SHIPPING_PRO_VERSION;
    }

    private function register_hooks(): void
    {
        add_action('init', [$this, 'load_textdomain']);
        add_action('before_woocommerce_init', [$this, 'declare_feature_support']);
    }

    private function register_services(): void
    {
        $this->services['settings'] = new Page($this);
        $this->services['directories.sync'] = new Sync($this);
        $this->services['checkout.fields'] = new Fields($this);
        $this->services['admin.order_meta'] = new OrderMeta($this);

        foreach ($this->services as $service) {
            if (method_exists($service, 'register')) {
                $service->register();
            }
        }
    }

    public function get_service(string $id): ?object
    {
        return $this->services[$id] ?? null;
    }

    public function load_textdomain(): void
    {
        load_plugin_textdomain('woo-ua-shipping-pro', false, dirname(plugin_basename(UA_SHIPPING_PRO_PLUGIN_FILE)) . '/languages');
    }

    public function declare_feature_support(): void
    {
        if (class_exists('Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', UA_SHIPPING_PRO_PLUGIN_FILE, true);
        }
    }
}
