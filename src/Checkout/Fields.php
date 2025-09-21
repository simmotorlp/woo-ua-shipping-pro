<?php

namespace WooUaShippingPro\Checkout;

use WooUaShippingPro\Directory\Store;
use WooUaShippingPro\Providers\Registry;
use WooUaShippingPro\Settings\Options;
use WC_Order;

class Fields
{
    private const AJAX_ACTION_SEARCH_CITIES = 'ua_shipping_pro_search_cities';
    private const AJAX_ACTION_GET_WAREHOUSES = 'ua_shipping_pro_get_warehouses';

    public function register(): void
    {
        add_filter('woocommerce_checkout_fields', [$this, 'inject_fields']);
        add_filter('woocommerce_checkout_posted_data', [$this, 'map_posted_data']);
        add_action('woocommerce_after_checkout_validation', [$this, 'validate'], 10, 2);
        add_action('woocommerce_checkout_create_order', [$this, 'persist_order_meta'], 10, 2);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_' . self::AJAX_ACTION_SEARCH_CITIES, [$this, 'ajax_search_cities']);
        add_action('wp_ajax_nopriv_' . self::AJAX_ACTION_SEARCH_CITIES, [$this, 'ajax_search_cities']);
        add_action('wp_ajax_' . self::AJAX_ACTION_GET_WAREHOUSES, [$this, 'ajax_get_warehouses']);
        add_action('wp_ajax_nopriv_' . self::AJAX_ACTION_GET_WAREHOUSES, [$this, 'ajax_get_warehouses']);
    }

    /**
     * @param array<string, array<string, array<string, mixed>>> $fields
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function inject_fields(array $fields): array
    {
        $default_carrier = Options::get(Options::CARRIER, 'nova_poshta');
        $carrier_value = $this->get_session_value('ua_shipping_carrier') ?: $default_carrier;
        $required = Options::get(Options::CHECKOUT_REQUIRE, 'yes') === 'yes';

        $city_value = $this->get_session_value('ua_shipping_city');
        $city_label = $this->get_session_value('ua_shipping_city_label');
        $warehouse_value = $this->get_session_value('ua_shipping_warehouse');
        $warehouse_label = $this->get_session_value('ua_shipping_warehouse_label');

        $carrier_options = ['' => __('Select carrier', 'woo-ua-shipping-pro')] + Registry::choices();

        $carrier_field = [
            'type' => 'select',
            'required' => true,
            'label' => __('Carrier', 'woo-ua-shipping-pro'),
            'placeholder' => __('Select carrier', 'woo-ua-shipping-pro'),
            'options' => $carrier_options,
            'class' => ['form-row-wide', 'ua-shipping-carrier'],
            'priority' => 65,
            'default' => $carrier_value,
        ];

        $city_options = ['' => __('Select city', 'woo-ua-shipping-pro')];
        if ($city_value && $city_label) {
            $city_options[$city_value] = $city_label;
        }

        $warehouse_options = ['' => __('Select warehouse', 'woo-ua-shipping-pro')];
        if ($warehouse_value && $warehouse_label) {
            $warehouse_options[$warehouse_value] = $warehouse_label;
        }

        $city_field = [
            'type' => 'select',
            'required' => false,
            'label' => __('City', 'woo-ua-shipping-pro'),
            'placeholder' => __('Select city', 'woo-ua-shipping-pro'),
            'options' => $city_options,
            'class' => ['form-row-wide', 'ua-shipping-city'],
            'priority' => 70,
            'input_class' => ['ua-shipping-enhanced'],
            'default' => $city_value,
        ];

        $warehouse_field = [
            'type' => 'select',
            'required' => false,
            'label' => __('Warehouse', 'woo-ua-shipping-pro'),
            'placeholder' => __('Select warehouse', 'woo-ua-shipping-pro'),
            'options' => $warehouse_options,
            'class' => ['form-row-wide', 'ua-shipping-warehouse'],
            'priority' => 75,
            'input_class' => ['ua-shipping-enhanced'],
            'default' => $warehouse_value,
        ];

        $hidden_fields = [
            'ua_shipping_city_label' => [
                'type' => 'hidden',
                'required' => false,
                'default' => $city_label,
            ],
            'ua_shipping_warehouse_label' => [
                'type' => 'hidden',
                'required' => false,
                'default' => $warehouse_label,
            ],
        ];

        $fields['shipping']['ua_shipping_carrier'] = $carrier_field;
        $fields['shipping']['ua_shipping_city'] = $city_field;
        $fields['shipping']['ua_shipping_city_label'] = $hidden_fields['ua_shipping_city_label'];
        $fields['shipping']['ua_shipping_warehouse'] = $warehouse_field;
        $fields['shipping']['ua_shipping_warehouse_label'] = $hidden_fields['ua_shipping_warehouse_label'];

        return $fields;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function map_posted_data(array $data): array
    {
        if (! empty($data['ua_shipping_city_label'])) {
            $data['shipping_city'] = $data['ua_shipping_city_label'];
        }

        if (! empty($data['ua_shipping_warehouse_label'])) {
            $data['shipping_address_1'] = $data['ua_shipping_warehouse_label'];
        }

        $carrier = isset($data['ua_shipping_carrier']) ? sanitize_text_field($data['ua_shipping_carrier']) : Options::get(Options::CARRIER, 'nova_poshta');
        if (! in_array($carrier, array_keys(Registry::choices()), true)) {
            $carrier = Options::get(Options::CARRIER, 'nova_poshta');
        }

        $this->store_session_value('ua_shipping_city', $data['ua_shipping_city'] ?? '');
        $this->store_session_value('ua_shipping_city_label', $data['ua_shipping_city_label'] ?? '');
        $this->store_session_value('ua_shipping_warehouse', $data['ua_shipping_warehouse'] ?? '');
        $this->store_session_value('ua_shipping_warehouse_label', $data['ua_shipping_warehouse_label'] ?? '');
        $this->store_session_value('ua_shipping_carrier', $carrier);

        return $data;
    }

    /**
     * @param array<string, string> $fields
     */
    public function validate(array $fields, \WP_Error $errors): void
    {
        $required = Options::get(Options::CHECKOUT_REQUIRE, 'yes') === 'yes';
        $carriers = array_keys(Registry::choices());
        $carrier = $fields['ua_shipping_carrier'] ?? '';
        $supports_directories = Registry::supports_directories($carrier);

        if (empty($fields['ua_shipping_carrier']) || ! in_array($fields['ua_shipping_carrier'], $carriers, true)) {
            $errors->add('ua_shipping_carrier', __('Please choose a carrier.', 'woo-ua-shipping-pro'));
        }

        if ($required && $supports_directories && empty($fields['ua_shipping_city'])) {
            $errors->add('ua_shipping_city', __('Please choose a city for pickup.', 'woo-ua-shipping-pro'));
        }

        if ($required && $supports_directories && empty($fields['ua_shipping_warehouse'])) {
            $errors->add('ua_shipping_warehouse', __('Please choose a warehouse for pickup.', 'woo-ua-shipping-pro'));
        }
    }

    public function persist_order_meta(\WC_Order $order, array $data): void
    {
        $carrier = sanitize_text_field($data['ua_shipping_carrier'] ?? Options::get(Options::CARRIER, 'nova_poshta'));
        if (! in_array($carrier, array_keys(Registry::choices()), true)) {
            $carrier = Options::get(Options::CARRIER, 'nova_poshta');
        }
        $city_ref = sanitize_text_field($data['ua_shipping_city'] ?? '');
        $warehouse_ref = sanitize_text_field($data['ua_shipping_warehouse'] ?? '');

        $city_label = sanitize_text_field($data['ua_shipping_city_label'] ?? '');
        $warehouse_label = sanitize_text_field($data['ua_shipping_warehouse_label'] ?? '');

        if ($city_label === '' && $city_ref !== '') {
            $city = Store::get_city($carrier, $city_ref);
            $city_label = $city['label'] ?? '';
        }

        if ($warehouse_label === '' && $warehouse_ref !== '') {
            $warehouse = Store::get_warehouse($carrier, $warehouse_ref);
            $warehouse_label = $warehouse['label'] ?? '';
        }

        $order->update_meta_data('_ua_carrier', $carrier);
        $order->update_meta_data('_ua_city_ref', $city_ref);
        $order->update_meta_data('_ua_city_label', $city_label);
        $order->update_meta_data('_ua_wh_ref', $warehouse_ref);
        $order->update_meta_data('_ua_wh_label', $warehouse_label);
    }

    public function enqueue_assets(): void
    {
        if (! function_exists('is_checkout') || ! is_checkout()) {
            return;
        }

        $enable_select2 = Options::get(Options::CHECKOUT_SELECT2, 'yes') === 'yes';
        $current_carrier = $this->get_session_value('ua_shipping_carrier') ?: Options::get(Options::CARRIER, 'nova_poshta');

        wp_register_script(
            'ua-shipping-pro-checkout',
            plugins_url('assets/js/checkout.js', UA_SHIPPING_PRO_PLUGIN_FILE),
            ['jquery', 'selectWoo'],
            UA_SHIPPING_PRO_VERSION,
            true
        );

        wp_register_style(
            'ua-shipping-pro-checkout',
            plugins_url('assets/css/checkout.css', UA_SHIPPING_PRO_PLUGIN_FILE),
            [],
            UA_SHIPPING_PRO_VERSION
        );

        wp_enqueue_style('ua-shipping-pro-checkout');

        if ($enable_select2) {
            if (wp_script_is('selectWoo', 'registered')) {
                wp_enqueue_script('selectWoo');
            }

            if (wp_script_is('select2', 'registered')) {
                wp_enqueue_script('select2');
            }

            if (wp_style_is('select2', 'registered')) {
                wp_enqueue_style('select2');
            }
        }

        wp_enqueue_script('ua-shipping-pro-checkout');

        wp_localize_script('ua-shipping-pro-checkout', 'uaShippingPro', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ua-shipping-pro'),
            'carrier' => $current_carrier,
            'defaultCarrier' => Options::get(Options::CARRIER, 'nova_poshta'),
            'directoryCarriers' => array_values(array_filter(array_keys(Registry::choices()), fn(string $slug): bool => Registry::supports_directories($slug))),
            'enableSelect' => $enable_select2,
            'actions' => [
                'searchCities' => self::AJAX_ACTION_SEARCH_CITIES,
                'getWarehouses' => self::AJAX_ACTION_GET_WAREHOUSES,
            ],
            'strings' => [
                'cityPlaceholder' => __('Start typing city name…', 'woo-ua-shipping-pro'),
                'warehousePlaceholder' => __('Select warehouse…', 'woo-ua-shipping-pro'),
                'noResults' => __('Nothing found', 'woo-ua-shipping-pro'),
                'carrierUnsupported' => __('Directory search is not yet available for the selected carrier.', 'woo-ua-shipping-pro'),
            ],
        ]);
    }

    public function ajax_search_cities(): void
    {
        $this->verify_ajax();

        $term = isset($_GET['term']) ? sanitize_text_field(wp_unslash((string) $_GET['term'])) : '';
        $carrier = isset($_GET['carrier']) ? sanitize_text_field(wp_unslash((string) $_GET['carrier'])) : Options::get(Options::CARRIER, 'nova_poshta');
        $results = Store::search_cities($carrier, $term);

        wp_send_json_success([
            'results' => array_map(static function (array $row): array {
                return [
                    'id' => $row['ref'],
                    'text' => $row['label'],
                    'region' => $row['region'],
                ];
            }, $results),
        ]);
    }

    public function ajax_get_warehouses(): void
    {
        $this->verify_ajax();

        $carrier = isset($_GET['carrier']) ? sanitize_text_field(wp_unslash((string) $_GET['carrier'])) : Options::get(Options::CARRIER, 'nova_poshta');
        $city_ref = isset($_GET['city_ref']) ? sanitize_text_field(wp_unslash((string) $_GET['city_ref'])) : '';
        $term = isset($_GET['term']) ? sanitize_text_field(wp_unslash((string) $_GET['term'])) : '';

        if ($city_ref === '') {
            wp_send_json_success(['results' => []]);
        }

        $warehouses = Store::get_warehouses($carrier, $city_ref, $term);

        wp_send_json_success([
            'results' => array_map(static function (array $row): array {
                return [
                    'id' => $row['ref'],
                    'text' => $row['label'],
                    'number' => $row['number'],
                    'type' => $row['type'],
                ];
            }, $warehouses),
        ]);
    }

    private function verify_ajax(): void
    {
        $nonce = $_REQUEST['nonce'] ?? '';

        if (! wp_verify_nonce($nonce, 'ua-shipping-pro')) {
            wp_send_json_error(['message' => __('Invalid security token.', 'woo-ua-shipping-pro')], 403);
        }
    }

    private function get_session_value(string $key): string
    {
        if (function_exists('WC') && WC()->session) {
            return (string) WC()->session->get($key, '');
        }

        return '';
    }

    private function store_session_value(string $key, $value): void
    {
        if (! function_exists('WC') || ! WC()->session) {
            return;
        }

        if ($value === '') {
            WC()->session->__unset($key);

            return;
        }

        WC()->session->set($key, $value);
    }
}
