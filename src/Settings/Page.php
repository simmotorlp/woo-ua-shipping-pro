<?php

namespace WooUaShippingPro\Settings;

use WooUaShippingPro\Directory\Sync;
use WooUaShippingPro\Plugin;

class Page
{
    public const SECTION_ID = 'ua_shipping_pro';

    private Plugin $plugin;

    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    public function register(): void
    {
        add_filter('woocommerce_get_sections_shipping', [$this, 'register_section']);
        add_filter('woocommerce_get_settings_shipping', [$this, 'register_settings'], 10, 2);
        add_action('woocommerce_admin_field_ua_shipping_refresh', [$this, 'render_refresh_button']);
        add_action('admin_post_ua_shipping_pro_refresh_directories', [$this, 'handle_refresh_request']);
        add_action('admin_notices', [$this, 'maybe_render_success_notice']);
    }

    public function register_section(array $sections): array
    {
        $sections[self::SECTION_ID] = __('UA Shipping Pro', 'woo-ua-shipping-pro');

        return $sections;
    }

    /**
     * @param array<int, array<string, mixed>> $settings
     * @param string $current_section
     *
     * @return array<int, array<string, mixed>>
     */
    public function register_settings(array $settings, string $current_section): array
    {
        if ($current_section !== self::SECTION_ID) {
            return $settings;
        }

        $settings = [
            [
                'title' => __('UA Shipping Pro', 'woo-ua-shipping-pro'),
                'type' => 'title',
                'desc' => __('Configure carrier integration and checkout behaviour for Ukrainian shipping providers.', 'woo-ua-shipping-pro'),
                'id' => 'ua_shipping_pro_general_title',
            ],
            [
                'title' => __('Carrier', 'woo-ua-shipping-pro'),
                'type' => 'select',
                'desc' => __('Choose default carrier that will provide directory data and API operations.', 'woo-ua-shipping-pro'),
                'id' => Options::CARRIER,
                'options' => [
                    'nova_poshta' => __('Nova Poshta', 'woo-ua-shipping-pro'),
                    'ukrposhta' => __('Ukrposhta', 'woo-ua-shipping-pro'),
                ],
                'default' => 'nova_poshta',
            ],
            [
                'title' => __('Nova Poshta API Key', 'woo-ua-shipping-pro'),
                'type' => 'text',
                'desc_tip' => __('API key issued by Nova Poshta. Required for directory sync and TTN creation.', 'woo-ua-shipping-pro'),
                'id' => Options::API_KEY_NP,
            ],
            [
                'title' => __('Ukrposhta API Key', 'woo-ua-shipping-pro'),
                'type' => 'text',
                'desc_tip' => __('API key issued by Ukrposhta.', 'woo-ua-shipping-pro'),
                'id' => Options::API_KEY_UP,
            ],
            [
                'title' => __('Language Priority', 'woo-ua-shipping-pro'),
                'type' => 'text',
                'desc' => __('Comma separated list defining language fallback, e.g. uk,en,ru.', 'woo-ua-shipping-pro'),
                'id' => Options::LANGUAGE_PRIORITY,
                'default' => 'uk,en,ru',
            ],
            [
                'title' => __('Auto-refresh daily', 'woo-ua-shipping-pro'),
                'type' => 'checkbox',
                'desc' => __('When enabled directories will be refreshed automatically once per day.', 'woo-ua-shipping-pro'),
                'id' => Options::CACHE_AUTO_REFRESH,
                'default' => 'yes',
            ],
            [
                'type' => 'ua_shipping_refresh',
                'desc' => __('Manually trigger a directory refresh task in the background.', 'woo-ua-shipping-pro'),
                'id' => 'ua_shipping_pro_refresh_button',
            ],
            [
                'type' => 'sectionend',
                'id' => 'ua_shipping_pro_general_title',
            ],
            [
                'title' => __('Checkout', 'woo-ua-shipping-pro'),
                'type' => 'title',
                'id' => 'ua_shipping_pro_checkout_title',
            ],
            [
                'title' => __('Require city and warehouse', 'woo-ua-shipping-pro'),
                'type' => 'checkbox',
                'id' => Options::CHECKOUT_REQUIRE,
                'default' => 'yes',
                'desc' => __('Disallow order placement if the shipping city and warehouse are not selected.', 'woo-ua-shipping-pro'),
            ],
            [
                'title' => __('Enable Select2 UI', 'woo-ua-shipping-pro'),
                'type' => 'checkbox',
                'id' => Options::CHECKOUT_SELECT2,
                'default' => 'yes',
                'desc' => __('Use Select2 search inputs for city and warehouse fields (requires frontend assets).', 'woo-ua-shipping-pro'),
            ],
            [
                'type' => 'sectionend',
                'id' => 'ua_shipping_pro_checkout_title',
            ],
        ];

        return $settings;
    }

    public function render_refresh_button(array $field): void
    {
        $url = wp_nonce_url(admin_url('admin-post.php?action=ua_shipping_pro_refresh_directories'), 'ua_shipping_pro_refresh');
        $last_sync = Options::get(Options::LAST_SYNC);
        $button_label = __('Refresh directories now', 'woo-ua-shipping-pro');

        echo '<tr valign="top"><th scope="row">' . esc_html__('Directories', 'woo-ua-shipping-pro') . '</th><td>';
        echo '<a class="button" href="' . esc_url($url) . '">' . esc_html($button_label) . '</a><br />';

        if ($last_sync) {
            printf('<p class="description">%s</p>', esc_html(sprintf(__('Last synced at %s', 'woo-ua-shipping-pro'), $last_sync)));
        }

        echo '</td></tr>';
    }

    public function maybe_render_success_notice(): void
    {
        if (! isset($_GET['page'], $_GET['tab'], $_GET['section'], $_GET['ua_refresh'])) {
            return;
        }

        if ($_GET['ua_refresh'] !== '1' || $_GET['page'] !== 'wc-settings' || $_GET['tab'] !== 'shipping' || $_GET['section'] !== self::SECTION_ID) {
            return;
        }

        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Directory refresh dispatched.', 'woo-ua-shipping-pro') . '</p></div>';
    }

    public function handle_refresh_request(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have permission to perform this action.', 'woo-ua-shipping-pro'));
        }

        check_admin_referer('ua_shipping_pro_refresh');

        Sync::queue_manual_refresh();

        wp_safe_redirect(add_query_arg([
            'page' => 'wc-settings',
            'tab' => 'shipping',
            'section' => self::SECTION_ID,
            'ua_refresh' => '1',
        ], admin_url('admin.php')));
        exit;
    }
}
