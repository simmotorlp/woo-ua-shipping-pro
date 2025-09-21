<?php

namespace WooUaShippingPro\Providers;

class Registry
{
    /**
     * @return array<string, string>
     */
    public static function choices(): array
    {
        return [
            'nova_poshta' => __('Nova Poshta', 'woo-ua-shipping-pro'),
            'ukrposhta' => __('Ukrposhta', 'woo-ua-shipping-pro'),
        ];
    }

    public static function label(string $carrier): string
    {
        $choices = self::choices();

        return $choices[$carrier] ?? $carrier;
    }

    public static function supports_directories(string $carrier): bool
    {
        return in_array($carrier, ['nova_poshta'], true);
    }
}
