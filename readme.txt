=== UA Shipping Pro ===
Contributors: ua-shipping-pro
Tags: woocommerce, nova poshta, ukraine, shipping
Requires at least: 6.5
Tested up to: 6.5
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Checkout enhancements for Ukrainian carriers in WooCommerce: Nova Poshta and Ukrposhta directory search, TTN management, and background sync.

== Description ==

UA Shipping Pro extends WooCommerce checkout with Ukrainian carrier directories. It lets customers pick a carrier warehouse, stores the selection in the order, and gives managers quick tools to generate or copy TTNs from the order screen.

* Select2-powered fields for city and warehouse lookup right in checkout.
* Background synchronization of carrier directories (cities, warehouses) using Action Scheduler.
* Dedicated settings page under WooCommerce → Shipping for carrier credentials, cache refresh, and UI preferences.
* Order meta box with TTN field plus "Create TTN" and "Copy" shortcuts.
* HPOS compatible (custom order tables).

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install via the Plugins screen.
2. Activate the plugin through the "Plugins" screen in WordPress.
3. Head to **WooCommerce → Settings → Shipping → UA Shipping Pro** to configure carrier, API keys, and cache settings.
4. Run an initial directory refresh to populate cities and warehouses.

== Roadmap ==

* **v1.0.0**: Checkout fields, directory caching, Nova Poshta TTN creation stub.
* **v1.1–1.2**: Ukrposhta directory parity and TTN actions.
* **v1.5**: TTN tracking, notifications, and label printing workflows.

== Changelog ==

= 1.0.0 =
* Initial MVP implementation.
