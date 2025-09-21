<?php

namespace WooUaShippingPro\Providers\Contracts;

interface CarrierInterface
{
    public function get_slug(): string;

    public function get_label(): string;

    /**
     * @return iterable<int, array<string, mixed>>
     */
    public function fetch_cities(): iterable;

    /**
     * @param string $city_ref
     * @return iterable<int, array<string, mixed>>
     */
    public function fetch_warehouses_for_city(string $city_ref): iterable;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function create_ttn(array $payload): array;
}
