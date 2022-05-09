=== BlockChyp WooCommerce ===
Contributors: blockchyp
Tags: payments
Requires at least: 4.4
Tested up to: 5.9.3
Stable tag: 1.0.6
Requires PHP: 7.0
License: MIT License
License URI: https://opensource.org/licenses/MIT

Integrate BlockChyp with your WooCommerce store.

== Description ==

This plugin allows BlockChyp merchants to accept all major credit cards and BlockChyp gift cards via their WooCommerce store.

== Installation ==

First make sure that the WooCommerce plugin is installed by searching the plugin directory for WooCommerce.  Install and Activate the WooCommerce plugin if you haven't already.

Next determine if you're going to use automatic or manual installation.

= Automatic Installation =

Search the WordPress plugin directory for BlockChyp.  Click Install and Activate to initialize the plugin.

= Manual Installation =

Download the plugin source from [Github](https://github.com/blockchyp/blockchyp-woocommerce).

Once you have the source code, run `composer install` from the plugin's root directory in order download the plugin's dependencies.

Open up your list of installed plugins and click "Activate".

== Setup and Configuration ==

Once you have the plugin installed, Open up the WooCommerce Setting page and click the Payments tab.

Enable BlockChyp - Credit Card and click "setup".

Copy the API credentials and tokenizing keys from your BlockChyp merchant account and click "Test Mode" if you're using a test merchant account.

== Frequently Asked Questions ==

= What if I don't have a merchant account? =

Visit [BlockChyp's Web Site](https://blockchyp.com) to learn more about BlockChyp and open a merchant account.

= What countries does BlockChyp support? =

As of this writing, BlockChyp processes payments in the US only.

== Screenshots ==

1. The BlockChyp Credit Card payments configuration screen.
2. Example BlockChyp Credit Card entry form.

== Changelog ==

= 1.0.6
* Update Wordpress and PHP compatibility

= 1.0.5
* Fix place order event interception.

= 1.0.3
* Fix a big related to gateway routing.

= 1.0.2
* Fine tuned postal code / AVS behavior for will call shipping methods.

= 1.0.1 =
* Add release automation.

= 1.0.0 =
* Debut release.

== Upgrade Notice ==

= 1.0.0 =
Initial release of the BlockChyp WooCommerce plugin.
