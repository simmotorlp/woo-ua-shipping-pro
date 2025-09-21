<?php

namespace WooUaShippingPro\Settings;

class Options
{
    public const PREFIX = 'woo_ua_shipping_pro_';

    public const CARRIER = self::PREFIX . 'carrier';

    public const API_KEY_NP = self::PREFIX . 'nova_poshta_key';

    public const API_KEY_UP = self::PREFIX . 'ukrposhta_key';

    public const LANGUAGE_PRIORITY = self::PREFIX . 'language_priority';

    public const CACHE_AUTO_REFRESH = self::PREFIX . 'cache_auto_refresh';

    public const CHECKOUT_REQUIRE = self::PREFIX . 'checkout_require_fields';

    public const CHECKOUT_SELECT2 = self::PREFIX . 'checkout_enable_select2';

    public const LAST_SYNC = self::PREFIX . 'last_sync';

    public static function get(string $option, $default = '')
    {
        return get_option($option, $default);
    }

    public static function update(string $option, $value): void
    {
        update_option($option, $value);
    }
}
