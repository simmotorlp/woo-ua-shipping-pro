<?php

namespace WooUaShippingPro\Directory;

use Exception;
use WooUaShippingPro\Plugin;
use WooUaShippingPro\Providers\Contracts\CarrierInterface;
use WooUaShippingPro\Providers\Factory;
use WooUaShippingPro\Settings\Options;

class Sync
{
    private const HOOK_REFRESH = 'woo_ua_shipping_pro_sync_directories';
    private const GROUP = 'woo-ua-shipping-pro';

    private Plugin $plugin;

    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    public function register(): void
    {
        add_action(self::HOOK_REFRESH, [$this, 'handle_sync'], 10, 1);
        add_action('init', [$this, 'maybe_schedule_automatic_refresh']);
    }

    public static function schedule_initial_sync(): void
    {
        if (! function_exists('as_schedule_single_action')) {
            return;
        }

        if (! function_exists('as_next_scheduled_action') || ! as_next_scheduled_action(self::HOOK_REFRESH, null, self::GROUP)) {
            as_schedule_single_action(time() + 10, self::HOOK_REFRESH, [Options::get(Options::CARRIER, 'nova_poshta')], self::GROUP);
        }
    }

    public static function queue_manual_refresh(?string $carrier = null): void
    {
        if (! function_exists('as_schedule_single_action')) {
            return;
        }

        $carrier = $carrier ?: Options::get(Options::CARRIER, 'nova_poshta');
        as_schedule_single_action(time() + 10, self::HOOK_REFRESH, [$carrier], self::GROUP);
    }

    public static function unschedule_events(): void
    {
        if (! function_exists('as_unschedule_all_actions')) {
            return;
        }

        as_unschedule_all_actions(self::HOOK_REFRESH, null, self::GROUP);
    }

    public function maybe_schedule_automatic_refresh(): void
    {
        if (! function_exists('as_schedule_recurring_action') || ! function_exists('as_next_scheduled_action')) {
            return;
        }

        $carrier = Options::get(Options::CARRIER, 'nova_poshta');

        if (Options::get(Options::CACHE_AUTO_REFRESH, 'yes') === 'yes') {
            as_unschedule_all_actions(self::HOOK_REFRESH, null, self::GROUP);
            as_schedule_recurring_action(time() + DAY_IN_SECONDS, DAY_IN_SECONDS, self::HOOK_REFRESH, [$carrier], self::GROUP);
        } else {
            as_unschedule_all_actions(self::HOOK_REFRESH, null, self::GROUP);
        }
    }

    public function handle_sync(?string $carrier = null): void
    {
        $carrier = $carrier ?: Options::get(Options::CARRIER, 'nova_poshta');

        $provider = Factory::make($carrier);

        if (! $provider) {
            return;
        }

        try {
            Store::clear_carrier($carrier);
            $city_refs = $this->sync_cities($carrier, $provider);
            $this->sync_warehouses($carrier, $provider, $city_refs);
            Options::update(Options::LAST_SYNC, current_time('mysql'));
        } catch (Exception $exception) {
            error_log('[UA Shipping Pro] Sync failed: ' . $exception->getMessage());
        }
    }

    /**
     * @param string $carrier
     * @param \WooUaShippingPro\Providers\Contracts\CarrierInterface $provider
     * @return string[]
     */
    private function sync_cities(string $carrier, CarrierInterface $provider): array
    {
        $chunk = [];
        $refs = [];

        foreach ($provider->fetch_cities() as $city) {
            if (empty($city['ref'])) {
                continue;
            }

            $chunk[] = $city;
            $refs[] = $city['ref'];

            if (count($chunk) >= 200) {
                Store::upsert_cities($carrier, $chunk);
                $chunk = [];
            }
        }

        if (! empty($chunk)) {
            Store::upsert_cities($carrier, $chunk);
        }

        return array_values(array_unique($refs));
    }

    /**
     * @param string $carrier
     * @param \WooUaShippingPro\Providers\Contracts\CarrierInterface $provider
     * @param string[] $city_refs
     */
    private function sync_warehouses(string $carrier, CarrierInterface $provider, array $city_refs): void
    {
        foreach ($city_refs as $city_ref) {
            $chunk = [];

            foreach ($provider->fetch_warehouses_for_city($city_ref) as $warehouse) {
                if (empty($warehouse['ref'])) {
                    continue;
                }

                $chunk[] = $warehouse;

                if (count($chunk) >= 200) {
                    Store::upsert_warehouses($carrier, $chunk);
                    $chunk = [];
                }
            }

            if (! empty($chunk)) {
                Store::upsert_warehouses($carrier, $chunk);
            }
        }
    }
}
