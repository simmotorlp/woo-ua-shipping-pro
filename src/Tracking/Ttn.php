<?php

namespace WooUaShippingPro\Tracking;

use WC_Order;

class Ttn
{
    public static function get(WC_Order $order): string
    {
        return (string) $order->get_meta('_ua_ttn', true);
    }

    public static function set(WC_Order $order, string $value): void
    {
        if ($value === '') {
            self::clear($order);
            return;
        }

        $order->update_meta_data('_ua_ttn', $value);
    }

    public static function clear(WC_Order $order): void
    {
        $order->delete_meta_data('_ua_ttn');
    }
}
