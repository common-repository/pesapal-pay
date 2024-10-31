=== PesaPal Pay ===
Contributors: rixeo, alloykenya
Donate link: https://www.paypal.me/paultitude
Tags: Pesapal, e-commerce, ecommerce, woocommerce, ecommerce, gateway, payment, wptravel
Requires at least: 4.4.0
Tested up to: 5.2
Requires PHP: 5.6
Stable tag: 3.2.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

PesaPal Pay allows you to easily integrate Pesapal to any ecommerce website

== Description ==

Important. We will not be adding more features to this version, but we will focus more on [PesaPress](https://wordpress.org/plugins/pesapress/ "PesaPress") which will support more features.

A quick way to integrate PesaPal to your website to handle the payment process. All you need to do is set up what parameters to capture from the form and the plugin will do the rest via the shortcode [pesapal_pay_button] where you can add the attribute button_name to be the text on the button. You can now alos accept donations via the PesaPal Donate Widget or using the shortcode [pesapal_donate].
There is now a meta box on a post of page to allow automatic shortcode addition.
You can manage the payment form by adding your own custom fields or arranging the form elements to suit your view

Main Features:

* Set up PesaPal credentials
* Set up fields to be captured
* Log PesaPal transactions
* Allows calling of a function before the pesapal transaction
* Accept Donations
* Automatically add the PesaPal Pay button on any post of page using a meta box


We are still working to make this excellent

== Installation ==

1. Upload the pesapal_pay folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Once the plugin is activated, Naigate to the admin menu "Pesapal Pay"

== Frequently Asked Questions ==

= How do I make transactions? =

Just post the parameters to a payment page that has the pesapal shortcode on it


= How do I use the shortcode? =
Just put the shortcode on a page after the products page. The shortcode will look for the parameters you set up in the previous page and set them to be used in the transaction to PesaPal

= How do I add a pay button to a page? =
There is a meta box on each page and post that allows automatic addition of the shortcode at the botton of the page



== Changelog ==

= 3.2.2 =
* Add: WP Travel script to show book button on PesaPal
* Fix: WP Travel integration when other gateways are active

= 3.2.1 =
* Fix : Fatal error

= 3.2 =
* Added : WP Travel Integration
* Added : WooCommerce Integration
* Added : Payment actions and hooks
* Added : New function to integrate with external plugins
* Fixed : PHP Errors 
* Fixed : Support for PHP 7.0

= 3.1.3 =
* Refactor PesaPal to use WordPress HTTP API
* Other code fixes

= 3.1.2.2 =
* Added : Zambia Kwacha
* Minor code fixes

= 3.1.2.1 =
Version bump

= 3.1.2 =
Version bump


= 3.1 =
Version change and preparation for version 3.2

= 3.0 =
* Dynamic checkout fields. You can now add custom fields to the payment form and order the arrangment of the form. The extra custom fields can be called using the meta tag 'user_info' for those wordpress developers
* Modifications the shortcode pesapal_pay_button. Form variables are now preset
* Removed the form field option settings. This in now done dynamically

What comming up
* We will be adding plugins to do alot of things so please help us by donating to keep development ongoing.

= 2.3.4 =
Added new checkout function where you can define your own custom return URL. This is good to use the plugin code in external applications

= 2.3.3 =
Added Malawian Kwacha

= 2.3.2 =
Added Ugandan Shilling and Tanzania Shilling

= 2.3.1 =
IPN corrections

= 2.3 =
* PesaPal Json response on IPN callback.
* More Complicated Transaction IDS

= 2.2.8 =
Fix on callback

= 2.2.7 =
Versioning

= 2.2.6 =
Fix on updating transaction status

= 2.2.5 =
IPN Fix

= 2.2.4 =
Code Fixes

= 2.2.3 =
CSS for Full width body height

= 2.2.2 =
* Added option to load PesaPal frame on entire page
* Changed donation form from using tables to divs
* Added CSS tags to elements

= 2.2.1 =
Height of iframe put 100% width

= 2.2 =
Added Currencies

= 2.1 =
Fix on thank you page

= 2.0 =
* Changed how we save trasnactions. Transactions are now saved as a custom post type. There is an update function that wil take care of the migration
* Admin page now has a section to explain the shortcodes better
* Meta box present on Pages and Posts to allow for easy addition of the button shortcode to any page
* Removed Javascript function


= 1.3.3 =
Default Form options

= 1.3.2 =
You can now use the shortcode [pesapal_pay_button] multiple times on the same page

= 1.3.1 =
Small Fix

= 1.3 =

 * Fixed a bug on saving trasnactions. 
 * Added an option to pass amount on the payment button

= 1.2.7 =
Pesapal no longer support Sandbox testing. Removed the option in code

= 1.2.6 =
Invoice ID fix

= 1.2.5 =
Javascript bug fix

= 1.2.4 =
Javascript bug fix

= 1.2.3 =
Added Javascript for external use

= 1.2.2 =
Automatic Invoice generation

= 1.2.1 =
Author information

= 1.2 =
Added Pesapal Donate Widget and shortcode [pesapal_donate]. You can now accept donations via PesaPal.

= 1.1 =
Documentation

= 1.0 =
PesaPal Pay is brand new.  As such you won't be upgrading but joining our handsomely awesome family. We will be upgrading and fixes bugs as we improve the plugin