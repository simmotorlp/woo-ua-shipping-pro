<?php

namespace WooUaShippingPro\Admin;

use WooUaShippingPro\Providers\Factory;
use WooUaShippingPro\Providers\Registry;
use WooUaShippingPro\Settings\Options;
use WooUaShippingPro\Tracking\Ttn;
use WC_Order;
use WP_Post;

class OrderMeta
{
    private const AJAX_CREATE_TTN = 'ua_shipping_pro_create_ttn';

    public function register(): void
    {
        add_action('add_meta_boxes', [$this, 'register_meta_box']);
        add_action('woocommerce_process_shop_order_meta', [$this, 'save_meta_box'], 20, 2);
        add_action('save_post_shop_order', [$this, 'save_meta_box'], 20, 2);
        add_action('woocommerce_after_order_details', [$this, 'render_hpos_panel']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_' . self::AJAX_CREATE_TTN, [$this, 'ajax_create_ttn']);
    }

    public function register_meta_box(): void
    {
        add_meta_box(
            'ua-shipping-pro-meta',
            __('UA Shipping Pro', 'woo-ua-shipping-pro'),
            [$this, 'render_meta_box'],
            'shop_order',
            'side',
            'default'
        );
    }

    public function render_meta_box(WP_Post $post): void
    {
        $order = wc_get_order($post->ID);

        if (! $order instanceof WC_Order) {
            echo esc_html__('Order not found.', 'woo-ua-shipping-pro');
            return;
        }

        $this->render_content($order);
    }

    public function render_hpos_panel(WC_Order $order): void
    {
        if (! class_exists('Automattic\\WooCommerce\\Utilities\\OrderUtil') || ! \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
            return;
        }

        echo '<div class="ua-shipping-pro-panel">';
        echo '<h3>' . esc_html__('UA Shipping Pro', 'woo-ua-shipping-pro') . '</h3>';
        $this->render_content($order);
        echo '</div>';
    }

    public function save_meta_box(int $order_id, $order = null): void
    {
        if (! isset($_POST['ua_shipping_pro_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ua_shipping_pro_nonce'])), 'ua-shipping-pro-order-meta')) {
            return;
        }

        if (! current_user_can('edit_shop_order', $order_id)) {
            return;
        }

        $value = isset($_POST['ua_shipping_ttn']) ? sanitize_text_field(wp_unslash($_POST['ua_shipping_ttn'])) : '';

        $order = $order instanceof WC_Order ? $order : wc_get_order($order_id);

        if (! $order instanceof WC_Order) {
            return;
        }

        Ttn::set($order, $value);
        $order->save();
    }

    public function enqueue_assets(string $hook): void
    {
        if ($hook !== 'post.php' && $hook !== 'post-new.php' && strpos($hook, 'woocommerce_page_wc-orders') !== 0) {
            return;
        }

        wp_enqueue_script(
            'ua-shipping-pro-admin-order',
            plugins_url('assets/js/admin-order.js', UA_SHIPPING_PRO_PLUGIN_FILE),
            ['jquery', 'wp-util'],
            UA_SHIPPING_PRO_VERSION,
            true
        );

        wp_localize_script('ua-shipping-pro-admin-order', 'uaShippingProAdmin', [
            'nonce' => wp_create_nonce('ua-shipping-pro-admin'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'messages' => [
                'creating' => __('Creating document…', 'woo-ua-shipping-pro'),
                'created' => __('TTN created', 'woo-ua-shipping-pro'),
                'copySuccess' => __('TTN copied to clipboard', 'woo-ua-shipping-pro'),
                'copyFail' => __('Failed to copy TTN', 'woo-ua-shipping-pro'),
                'error' => __('Unable to create TTN. Check the order data and carrier settings.', 'woo-ua-shipping-pro'),
            ],
        ]);
    }

    public function ajax_create_ttn(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('You are not allowed to perform this action.', 'woo-ua-shipping-pro')], 403);
        }

        $nonce = $_POST['nonce'] ?? '';
        if (! wp_verify_nonce($nonce, 'ua-shipping-pro-admin')) {
            wp_send_json_error(['message' => __('Invalid security token.', 'woo-ua-shipping-pro')], 403);
        }

        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $order = $order_id ? wc_get_order($order_id) : null;

        if (! $order instanceof WC_Order) {
            wp_send_json_error(['message' => __('Order not found.', 'woo-ua-shipping-pro')], 404);
        }

        $carrier = $order->get_meta('_ua_carrier') ?: Options::get(Options::CARRIER, 'nova_poshta');
        $provider = Factory::make($carrier);

        if (! $provider) {
            wp_send_json_error(['message' => __('Carrier integration is not available.', 'woo-ua-shipping-pro')]);
        }

        $payload = $this->build_ttn_payload($order);
        $result = $provider->create_ttn($payload);

        if (! empty($result['success'])) {
            $ttn = $result['data']['Ref'] ?? $result['data']['IntDocNumber'] ?? '';

            if ($ttn) {
                Ttn::set($order, $ttn);
                $order->save();
            }

            wp_send_json_success([
                'ttn' => $ttn,
                'message' => __('TTN created successfully.', 'woo-ua-shipping-pro'),
            ]);
        }

        $message = $result['error'] ?? __('Could not create TTN. Please check carrier settings.', 'woo-ua-shipping-pro');
        wp_send_json_error(['message' => $message]);
    }

    private function render_content(WC_Order $order): void
    {
        $carrier = $order->get_meta('_ua_carrier');
        $city = $order->get_meta('_ua_city_label');
        $warehouse = $order->get_meta('_ua_wh_label');
        $ttn = Ttn::get($order);

        wp_nonce_field('ua-shipping-pro-order-meta', 'ua_shipping_pro_nonce');

        $carrier_slug = $carrier ?: Options::get(Options::CARRIER, 'nova_poshta');
        echo '<p><strong>' . esc_html__('Carrier', 'woo-ua-shipping-pro') . ':</strong> ' . esc_html(Registry::label($carrier_slug)) . '</p>';

        if ($city) {
            echo '<p><strong>' . esc_html__('City', 'woo-ua-shipping-pro') . ':</strong> ' . esc_html($city) . '</p>';
        }

        if ($warehouse) {
            echo '<p><strong>' . esc_html__('Warehouse', 'woo-ua-shipping-pro') . ':</strong> ' . esc_html($warehouse) . '</p>';
        }

        echo '<p><label for="ua_shipping_ttn"><strong>' . esc_html__('TTN Number', 'woo-ua-shipping-pro') . '</strong></label><input type="text" name="ua_shipping_ttn" id="ua_shipping_ttn" value="' . esc_attr($ttn) . '" class="widefat" /></p>';

        $create_label = __('Create TTN', 'woo-ua-shipping-pro');
        $copy_label = __('Copy', 'woo-ua-shipping-pro');

        echo '<p class="ua-shipping-pro-actions">';
        echo '<button type="button" class="button button-secondary ua-shipping-pro-create" data-order-id="' . esc_attr((string) $order->get_id()) . '" data-default-label="' . esc_attr($create_label) . '">' . esc_html($create_label) . '</button> ';
        echo '<button type="button" class="button ua-shipping-pro-copy" data-target="#ua_shipping_ttn" data-default-label="' . esc_attr($copy_label) . '">' . esc_html($copy_label) . '</button>';
        echo '</p>';
    }

    private function build_ttn_payload(WC_Order $order): array
    {
        $weight = (float) $order->get_meta('_cart_weight');
        if (! $weight) {
            $weight = (float) $order->get_total_weight();
        }
        if ($weight <= 0) {
            $weight = 0.5;
        }

        $recipient_name = trim($order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name());
        $recipient_phone = preg_replace('/[^0-9\+]/', '', $order->get_billing_phone());

        return [
            'NewAddress' => '1',
            'PayerType' => 'Recipient',
            'PaymentMethod' => 'Cash',
            'CargoType' => 'Parcel',
            'ServiceType' => 'WarehouseWarehouse',
            'Description' => sprintf(__('Order #%s', 'woo-ua-shipping-pro'), $order->get_order_number()),
            'Weight' => (string) $weight,
            'SeatsAmount' => '1',
            'Cost' => (string) $order->get_total(),
            'RecipientCityRef' => $order->get_meta('_ua_city_ref'),
            'RecipientWarehouseRef' => $order->get_meta('_ua_wh_ref'),
            'RecipientAddressName' => $order->get_meta('_ua_wh_label'),
            'RecipientName' => $recipient_name,
            'RecipientType' => 'PrivatePerson',
            'RecipientsPhone' => $recipient_phone,
        ];
    }
}
