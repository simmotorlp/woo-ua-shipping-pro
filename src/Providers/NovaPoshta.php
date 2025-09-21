<?php

namespace WooUaShippingPro\Providers;

use WooUaShippingPro\Providers\Contracts\CarrierInterface;

class NovaPoshta implements CarrierInterface
{
    private const API_URL = 'https://api.novaposhta.ua/v2.0/json/';

    private string $api_key;

    public function __construct(string $api_key)
    {
        $this->api_key = $api_key;
    }

    public function get_slug(): string
    {
        return 'nova_poshta';
    }

    public function get_label(): string
    {
        return __('Nova Poshta', 'woo-ua-shipping-pro');
    }

    public function fetch_cities(): iterable
    {
        $page = 1;
        $limit = 100;

        while (true) {
            $response = $this->request('AddressGeneral', 'getCities', [
                'Page' => $page,
                'Limit' => $limit,
            ]);

            if (empty($response['data'])) {
                break;
            }

            foreach ($response['data'] as $item) {
                yield [
                    'ref' => $item['Ref'] ?? '',
                    'name_uk' => $item['Description'] ?? '',
                    'name_en' => $item['DescriptionTranslit'] ?? '',
                    'name_ru' => $item['DescriptionRu'] ?? '',
                    'region' => $item['AreaDescription'] ?? '',
                ];
            }

            if (count($response['data']) < $limit) {
                break;
            }

            $page++;
        }
    }

    public function fetch_warehouses_for_city(string $city_ref): iterable
    {
        $page = 1;
        $limit = 100;

        while (true) {
            $response = $this->request('AddressGeneral', 'getWarehouses', [
                'CityRef' => $city_ref,
                'Page' => $page,
                'Limit' => $limit,
            ]);

            if (empty($response['data'])) {
                break;
            }

            foreach ($response['data'] as $item) {
                yield [
                    'city_ref' => $city_ref,
                    'ref' => $item['Ref'] ?? '',
                    'number' => $item['Number'] ?? '',
                    'name_uk' => $item['Description'] ?? '',
                    'name_en' => $item['DescriptionTranslit'] ?? '',
                    'name_ru' => $item['DescriptionRu'] ?? '',
                    'type' => $item['TypeOfWarehouse'] ?? '',
                    'lat' => isset($item['Latitude']) ? (float) $item['Latitude'] : null,
                    'lng' => isset($item['Longitude']) ? (float) $item['Longitude'] : null,
                ];
            }

            if (count($response['data']) < $limit) {
                break;
            }

            $page++;
        }
    }

    public function create_ttn(array $payload): array
    {
        if (empty($this->api_key)) {
            return [
                'success' => false,
                'error' => __('Nova Poshta API key is missing.', 'woo-ua-shipping-pro'),
            ];
        }

        $response = $this->request('InternetDocument', 'save', $payload, true);

        if (! empty($response['success'])) {
            return [
                'success' => true,
                'data' => $response['data'][0] ?? [],
            ];
        }

        $error = $response['errors'][0] ?? __('Failed to create TTN.', 'woo-ua-shipping-pro');

        return [
            'success' => false,
            'error' => $error,
        ];
    }

    /**
     * @param array<string, mixed> $method_properties
     * @return array<string, mixed>
     */
    private function request(string $model, string $method, array $method_properties = [], bool $require_key = false): array
    {
        if ($require_key && empty($this->api_key)) {
            return [
                'success' => false,
                'errors' => [__('API key is required for this operation.', 'woo-ua-shipping-pro')],
            ];
        }

        $body = [
            'apiKey' => $this->api_key,
            'modelName' => $model,
            'calledMethod' => $method,
            'methodProperties' => $method_properties,
        ];

        $response = wp_remote_post(self::API_URL, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($body),
            'timeout' => 45,
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'errors' => [$response->get_error_message()],
            ];
        }

        $decoded = json_decode(wp_remote_retrieve_body($response), true);

        if (! is_array($decoded)) {
            return [
                'success' => false,
                'errors' => [__('Unexpected response from Nova Poshta API.', 'woo-ua-shipping-pro')],
            ];
        }

        return $decoded;
    }
}
