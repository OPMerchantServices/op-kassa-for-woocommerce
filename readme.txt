=== Checkout POS for WooCommerce ===
Contributors: loueranta
Donate link: https://www.checkout.fi/pos
Tags: woocommerce
Requires at least: 4.9
Tested up to: 5.7
Stable tag: 3.0.0
Requires PHP: 7.1
License: MIT
License URI: https://opensource.org/licenses/MIT

Connect your [Checkout POS](https://www.checkout.fi/pos) (formerly OP Kassa) and WooCommerce to synchronize products, orders and stock levels between the systems.

== Description ==

[Checkout POS](https://www.checkout.fi/pos) is easy to use point of sale system for your omnichannel business with modern payment terminals, fast tablet based cashier and online admin system with extensive reporting. Checkout POS for WooCommerce allows you to synchronize products, orders and stock levels in realtime between your WooCommerce based online store and your physical stores.

== Installation ==

Follow these easy steps to install the plugin:

1. Log on to WordPress admin area and navigate to Plugins -> Add New.
1. Type "Checkout POS for WooCommerce" to search field.
1. Install and activate the plugin from search results.
1. Head over to WooCommerce -> Settings and click on the "Checkout POS" tab to configure the plugin.

== Frequently Asked Questions ==

= I can't connect to Checkout POS? =

Head over to Checkout POS admin panel and make sure that you have activated the WooCommerce addon.

== Changelog ==

= 3.0.0 =
* New settings page
* Rebranding from "OP Kassa" to "Checkout POS"

= 2.0.1 =
* Added OP Kassa integration authentication related settings.

= 2.0.0 =
* Replaced OAuth based authentication with the WooCommerce Rest API authentication.

= 1.0.6 =
* It is now possible to choose whether Woo tax calculation is used on orders synced from OP Kassa or if OP Kassa tax calculation is used instead.

= 1.0.5 =
* OP Kassa is now disconnected gracefully if the Woo instance domain is changed. 

= 1.0.4 =
* Fixed a bug relating to Kassa oauth callback url 

= 1.0.3 =
* Updated installation instructions

= 1.0.2 =
* Released to WordPress.org directory
