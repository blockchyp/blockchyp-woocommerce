=== BlockChyp Payment Gateway ===
Contributors: blockchyp
Tags: payments, blockchyp, credit card, woocommerce
Requires at least: 6.4
Tested up to: 6.5
Stable tag: 1.3.0
Requires PHP: 7.4
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Integrate BlockChyp with your WooCommerce store.

== Description ==

This plugin allows BlockChyp merchants to accept all major credit cards and BlockChyp gift cards via their WooCommerce store.

= What is BlockChyp? =

BlockChyp is a payment processing platform that integrates with point-of-sale systems to provide a robust integrated payments experience.

= BlockChyp Services used in Plugin =

The BlockChyp WooCommerce Plugin uses the BlockChyp PHP SDK to process the payments through the BlockChyp Payment Gateway.  Visit the attached [BlockChyp PHP SDK Repository](https://github.com/blockchyp/blockchyp-php) to learn more about the SDK and the terms of use. The SDK uses [BlockChyp Gateway Host URL](https://api.blockchyp.com) and [BlockChyp Test Gateway Host URL](https://test.blockchyp.com) to properly connect to the BlockChyp Gateway for either live or test transactions.

The BlockChyp WooCommerce Plugin also implements the BlockChyp Web Tokenizer to connect e-commerce payments pages to BlockChyp in a secure way that minimizes PCI Scope.  Visit the attached [BlockChyp Web Tokenizer Repository](https://github.com/blockchyp/blockchyp-tokenizer) to learn more about the Web Tokenizer and the terms of use.

== Installation ==

First make sure that the WooCommerce plugin is installed by searching the plugin directory for WooCommerce.  Install and Activate the WooCommerce plugin if you haven't already.

Next determine if you're going to use automatic or manual installation.

= Automatic Installation =

Search the WordPress plugin directory for BlockChyp.  Click Install and Activate to initialize the plugin.

= Manual Installation =

Download the plugin source from [Github](https://github.com/blockchyp/blockchyp-payment-gateway).

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

= Does BlockChyp support Checkout Blocks? = 

At this time BlockChyp does not support Checkout Blocks.  Please watch for updates as to when blocks will be supported.

If your current checkout page uses Blocks you will need to disable blocks and use Classic ShortCodes.

While editing your site:
    * Navigate to the checkout page
    * Click on the List View (three lines) at the top of the screen
    * Expand Content then click on the Checkout tab, this will highlight the checkout block
    * In the top left corner of the click on the Checkout Box and select Classic ShortCodes
    * Click the save button in the top right corner, confirm the save and enter your site

= Does BlockChyp support Preauth and Capture? = 

Yes, BlockChyp supports Preauth and Capture mode.  In the BlockChyp Payment Gateway Settings Page, simply check the "Preauth/Capture Mode" checkbox.  Selecting this mode will issue a pre-authorization at checkout, changing the order status to "On hold". The payment will automatically be captured on shipment assuming that the order status is changed to either "Processing" or "Completed" as part of the shipping process.  You can manually capture the payment by changing the order status to either "Processing" or "Completed".

If the "Preauth/Capture Mode" checkbox is not selected, which is default, payments will be both preauthed and captured at the same time at checkout. 

== Screenshots ==

1. The BlockChyp Credit Card payments configuration screen.
2. Example BlockChyp Credit Card entry form.
3. How to disable Checkout Blocks.

== Changelog ==

= 1.3.0 =
Added functionality for Preauth/Capture model

= 1.2.0 =
Fixed Plugin when running with Production Creds

= 1.1.0 =
Wordpress Plugin Approval and Initial Release

= 1.0.0 =
New release of the BlockChyp WooCommerce plugin.
