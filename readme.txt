=== Hippoo Shippo Integration for WooCommerce ===

Contributors: Hippooo
Tags: WooCommerce, Shippo, shipping, labels, e-commerce, carriers, goshippo, WooCommerce label generate, shipping rates, WooCommerce shipping, carrier integration, shipping label generator
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.2.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==

Hippoo Shippo Integration seamlessly integrates the Shippo service into your WooCommerce dashboard, enabling you to generate shipping labels directly from your WooCommerce admin panel.

This plugin also works in conjunction with the **Hippoo WooCommerce App**, a powerful tool designed to enhance your e-commerce operations with streamlined order management, inventory tracking, and advanced analytics. Together, they make your WooCommerce store more efficient and user-friendly.

= Key Features =

- **Generate shipping labels:** Create labels directly from your WooCommerce dashboard without switching between apps.  
- **Seamless integration with Hippoo App:** Use advanced shipping and order management tools within the Hippoo ecosystem.  
- **Real-time shipping rates:** Display accurate carrier rates at checkout.  
- **Save time and boost efficiency:** Simplify your shipping workflow with just a few clicks.  
- **Real-time shipment tracking:** Show live tracking updates to customers in My Account and within WooCommerce order details.  
- **Multiple live rates at checkout:** Display all available real-time shipping rates that are already configured in the Shippo Rates at Checkout panel.

= What is Hippoo WooCommerce App? =

**Hippoo App** is a next-generation tool for WooCommerce users that provides a comprehensive solution for managing your online store. With features like inventory management, order tracking, customer insights, and seamless plugin integrations like Shippo, Google analytics and more. Hippoo App helps you scale your business efficiently.

Learn more at [Hippoo.app](https://hippoo.app).

### **Download hippoo mobile application**

* **Hippoo iOS Version:** [https://apps.apple.com/ee/app/hippoo-woocommerce-admin-app/id1667265325](https://apps.apple.com/ee/app/hippoo-woocommerce-admin-app/id1667265325)
* **Hippoo Android Version:** [https://play.google.com/store/apps/details?id=io.hippo&pli=1](https://play.google.com/store/apps/details?id=io.hippo&pli=1)

---

== Installation ==

= From your WordPress Dashboard =

1. Navigate to **Plugins > Add New**.
2. Search for **Hippoo Shippo Integration**.
3. Click **"Install Now"** and then **"Activate"**.

= Manual Installation =

1. Download the plugin zip file.
2. Upload it to your WordPress `wp-content/plugins/` directory via FTP.
3. Activate the plugin through the Plugins menu in WordPress.

= Usage =

<h3>Setup Instructions</h3>

<p>Follow these steps to configure and start using the Hippoo Shippo Integration for WooCommerce:</p>

<h4>Step 1: Configure Shippo Settings</h4>
<ol>
  <li>Go to <strong>WooCommerce > Settings > Shipping > Shippo</strong> or navigate to the URL directly:<br>
      <code>/wp-admin/admin.php?page=wc-settings&tab=shipping&section=shippo</code></li>
  <li>Enter your <strong>Shippo API token</strong> to authenticate your account.</li>
  <li>Define your <strong>shipping address</strong> and package details.</li>
  <li>Save the settings.</li>
</ol>

<h4>Step 2: Generate Shipping Labels</h4>
<ol>
  <li>Navigate to <strong>WooCommerce > Orders</strong>.</li>
  <li>Open an order that requires shipping.</li>
  <li>Click the <strong>“Generate Shipping Label”</strong> button.</li>
  <li>A shipping barcode will be generated for the order.</li>
</ol>

<p>That’s it! Your WooCommerce store is now integrated with Shippo, allowing you to manage shipping efficiently.</p>

== External services ==

This plugin communicates with the Shippo API (https://docs.goshippo.com/) to generate shipping labels and retrieve real-time carrier rates for WooCommerce. Shippo is an external service that handles shipping operations, and its usage is subject to its own terms and policies.

For more details, refer to Shippo’s terms of use: https://privacy.goshippo.com/policies?name=terms-of-use.

== Frequently Asked Questions ==

**Do I need a Shippo account?**

Yes, you will need an active Shippo account to use this plugin. You can sign up at [https://www.goshippo.com](https://www.goshippo.com).

**Is this plugin free?**

Yes, the plugin is free to use. However, Shippo services may have associated costs based on their pricing plans.

**What is Hippoo App, and how does it work with this plugin?**

Hippoo App is a WooCommerce companion tool that streamlines store management with features like order tracking, inventory management, and analytics. This plugin integrates with Hippoo App to provide a unified experience for shipping and order management.

== Screenshots ==

1. WooCommerce dashboard view: Generate labels easily.
2. Declear custom for international orders
3. Get real-time rate and generate label
4. Ability to see the real-time shipment tracking in customer my account
5. Ability to generate label in mobile within the hippoo woocommerce app (with premium subscription)

== Changelog ==

= 1.2.6 =
* Bug fixes and stability improvements

= 1.2.5 =
* Minor address bug fix

= 1.2.4 =
* Minor address bug fix

= 1.2.3 =
* Minor address bug fix

= 1.2.2 =
* Minor Improvements

= 1.2.0 =
* Minor Improvements
* Added option for customers to track shipping in My Account and WooCommerce order details.
* Added ability to display a checkout shipping calculator for specific countries.
* Improved the shipping calculator to support multiple shipping options on the checkout page.
* Fixed various performance and UX issues.

= 1.1.5 =
* Minor Improvements

= 1.1.4 =
* Order customer address bug fix

= 1.1.3 =
* Order details bug fix

= 1.1.2 =
* Compatibility with HPOS (High-Performance Order Storage)

= 1.1.1 =
* Minor Improvements 

= 1.0.0 =
* Initial release.
* Seamless Shippo integration with WooCommerce.
* Label generation from the WooCommerce dashboard.

== Support ==

If you have any issues or need assistance, please contact us at [info@hippoo.app](mailto:info@hippoo.app).