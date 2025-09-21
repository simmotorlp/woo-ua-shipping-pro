<?php

namespace WooUaShippingPro\Providers;

use WooUaShippingPro\Providers\Contracts\CarrierInterface;

class Ukrposhta implements CarrierInterface
{
    private string $api_key;

    public function __construct(string $api_key)
    {
        $this->api_key = $api_key;
    }

    public function get_slug(): string
    {
        return 'ukrposhta';
    }

    public function get_label(): string
    {
        return __('Ukrposhta', 'woo-ua-shipping-pro');
    }

    public function fetch_cities(): iterable
    {
        return []; // Ukrposhta directory sync is planned for v1.1
    }

    public function fetch_warehouses_for_city(string $city_ref): iterable
    {
        return []; // Ukrposhta directory sync is planned for v1.1
    }

    public function create_ttn(array $payload): array
    {
        return [
            'success' => false,
            'error' => __('Ukrposhta API integration is not available yet.', 'woo-ua-shipping-pro'),
        ];
    }
}
