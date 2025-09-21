<?php

namespace WooUaShippingPro\Directory;

use WooUaShippingPro\Settings\Options;

class Store
{
    public const TABLE_CITIES = 'ua_dirs_cities';
    public const TABLE_WAREHOUSES = 'ua_dirs_warehouses';

    public static function create_tables(): void
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $cities_table = $wpdb->prefix . self::TABLE_CITIES;
        $warehouses_table = $wpdb->prefix . self::TABLE_WAREHOUSES;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $cities_sql = "CREATE TABLE {$cities_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            carrier varchar(40) NOT NULL,
            ref varchar(64) NOT NULL,
            name_uk varchar(255) NOT NULL,
            name_en varchar(255) DEFAULT '',
            name_ru varchar(255) DEFAULT '',
            region varchar(255) DEFAULT '',
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY carrier (carrier),
            UNIQUE KEY carrier_ref (carrier, ref)
        ) {$charset_collate};";

        $warehouses_sql = "CREATE TABLE {$warehouses_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            carrier varchar(40) NOT NULL,
            city_ref varchar(64) NOT NULL,
            ref varchar(64) NOT NULL,
            number varchar(20) DEFAULT '',
            name_uk varchar(255) NOT NULL,
            name_en varchar(255) DEFAULT '',
            name_ru varchar(255) DEFAULT '',
            type varchar(60) DEFAULT '',
            lat decimal(12,8) DEFAULT NULL,
            lng decimal(12,8) DEFAULT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY carrier (carrier),
            KEY carrier_city (carrier, city_ref),
            UNIQUE KEY carrier_ref (carrier, ref)
        ) {$charset_collate};";

        dbDelta($cities_sql);
        dbDelta($warehouses_sql);
    }

    public static function clear_carrier(string $carrier): void
    {
        global $wpdb;

        $wpdb->delete($wpdb->prefix . self::TABLE_CITIES, ['carrier' => $carrier]);
        $wpdb->delete($wpdb->prefix . self::TABLE_WAREHOUSES, ['carrier' => $carrier]);
    }

    /**
     * @param array<int, array<string, mixed>> $cities
     */
    public static function upsert_cities(string $carrier, array $cities): void
    {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE_CITIES;

        foreach ($cities as $city) {
            $wpdb->replace($table, [
                'carrier' => $carrier,
                'ref' => $city['ref'],
                'name_uk' => $city['name_uk'] ?? '',
                'name_en' => $city['name_en'] ?? '',
                'name_ru' => $city['name_ru'] ?? '',
                'region' => $city['region'] ?? '',
                'updated_at' => current_time('mysql'),
            ], ['%s', '%s', '%s', '%s', '%s', '%s', '%s']);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $warehouses
     */
    public static function upsert_warehouses(string $carrier, array $warehouses): void
    {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE_WAREHOUSES;

        foreach ($warehouses as $warehouse) {
            $wpdb->replace($table, [
                'carrier' => $carrier,
                'city_ref' => $warehouse['city_ref'],
                'ref' => $warehouse['ref'],
                'number' => $warehouse['number'] ?? '',
                'name_uk' => $warehouse['name_uk'] ?? '',
                'name_en' => $warehouse['name_en'] ?? '',
                'name_ru' => $warehouse['name_ru'] ?? '',
                'type' => $warehouse['type'] ?? '',
                'lat' => $warehouse['lat'] ?? null,
                'lng' => $warehouse['lng'] ?? null,
                'updated_at' => current_time('mysql'),
            ], ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%s']);
        }
    }

    /**
     * @return array<int, array<string, string>>
     */
    public static function search_cities(string $carrier, string $term, int $limit = 20): array
    {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE_CITIES;
        $like = '%' . $wpdb->esc_like($term) . '%';
        $sql = $wpdb->prepare(
            "SELECT ref, name_uk, name_en, name_ru, region FROM {$table} WHERE carrier = %s AND (name_uk LIKE %s OR name_en LIKE %s OR name_ru LIKE %s) ORDER BY name_uk ASC LIMIT %d",
            $carrier,
            $like,
            $like,
            $like,
            $limit
        );

        $rows = $wpdb->get_results($sql, ARRAY_A);

        return array_map(static function (array $row): array {
            return [
                'ref' => $row['ref'],
                'label' => self::compose_label($row),
                'region' => $row['region'],
            ];
        }, $rows ?: []);
    }

    /**
     * @return array<int, array<string, string>>
     */
    public static function get_warehouses(string $carrier, string $city_ref, string $term = '', int $limit = 50): array
    {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE_WAREHOUSES;

        $where = 'carrier = %s AND city_ref = %s';
        $params = [$carrier, $city_ref];

        if ($term !== '') {
            $like = '%' . $wpdb->esc_like($term) . '%';
            $where .= ' AND (name_uk LIKE %s OR name_en LIKE %s OR name_ru LIKE %s OR number LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $params[] = $limit;

        $sql = $wpdb->prepare(
            "SELECT ref, number, name_uk, name_en, name_ru, type FROM {$table} WHERE {$where} ORDER BY number+0 ASC, name_uk ASC LIMIT %d",
            $params
        );

        $rows = $wpdb->get_results($sql, ARRAY_A);

        return array_map(static function (array $row): array {
            return [
                'ref' => $row['ref'],
                'label' => self::compose_label($row),
                'number' => $row['number'],
                'type' => $row['type'],
            ];
        }, $rows ?: []);
    }

    public static function compose_label(array $row): string
    {
        $languages = self::get_language_priority();

        foreach ($languages as $lang) {
            $key = 'name_' . $lang;
            if (! empty($row[$key])) {
                return $row[$key];
            }
        }

        if (! empty($row['name_uk'])) {
            return $row['name_uk'];
        }

        if (! empty($row['name_en'])) {
            return $row['name_en'];
        }

        return $row['name_ru'] ?? '';
    }

    public static function get_city(string $carrier, string $ref): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_CITIES;
        $sql = $wpdb->prepare("SELECT * FROM {$table} WHERE carrier = %s AND ref = %s LIMIT 1", $carrier, $ref);
        $row = $wpdb->get_row($sql, ARRAY_A);

        if (! $row) {
            return null;
        }

        $row['label'] = self::compose_label($row);

        return $row;
    }

    public static function get_warehouse(string $carrier, string $ref): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_WAREHOUSES;
        $sql = $wpdb->prepare("SELECT * FROM {$table} WHERE carrier = %s AND ref = %s LIMIT 1", $carrier, $ref);
        $row = $wpdb->get_row($sql, ARRAY_A);

        if (! $row) {
            return null;
        }

        $row['label'] = self::compose_label($row);

        return $row;
    }

    /**
     * @return string[]
     */
    private static function get_language_priority(): array
    {
        $priority = Options::get(Options::LANGUAGE_PRIORITY, 'uk,en,ru');
        $parts = array_map('trim', explode(',', strtolower((string) $priority)));
        $parts = array_filter($parts, static fn(string $part): bool => $part !== '');

        return array_unique($parts ?: ['uk', 'en', 'ru']);
    }
}
