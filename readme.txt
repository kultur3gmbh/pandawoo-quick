=== PandaWoo Quick ===
Contributors: contactpanda
Tags: woocommerce, products, mobile, pwa
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.2.7
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Mobile-first WooCommerce product capture and editing through a securely paired installable web app.

== Description ==

PandaWoo Quick connects a phone to a WooCommerce installation using a short-lived, single-use QR code. The paired phone receives a revocable session rather than a WordPress password.

It is fully usable without ContactPanda, without an external account and without a paid extension. It does not send tracking or product data to an external service.

Updates are retrieved through the standard WordPress plugin update screen from public, token-free GitHub releases at https://github.com/kultur3gmbh/pandawoo-quick. Only the exactly named release asset `pandawoo-quick-{version}.zip` is accepted.

PandaWoo Quick 0.2.0 supports:

* Product list, search and status filtering.
* Creating and editing simple WooCommerce products.
* Up to twelve JPEG, PNG or WebP images.
* Pointer-based image sorting for touch, pen and mouse.
* Product name, category, price, short description and detailed description.
* Hierarchical category paths and optional WooCommerce brand assignment.
* Immediate publishing, scheduled publishing and drafts.
* Installation as a standalone web app.
* One active shop connection per Quick Free app/device installation.
* Secure disconnection followed by pairing with a different shop.

The session and API contracts carry a stable shop identifier. This keeps the core ready for a future multi-shop PandaWoo client while the Quick Free interface intentionally has no shop switcher.

Optional information about ContactPanda and future PandaWoo editions appears only in a separate, dismissible section in WordPress. It never interrupts the mobile product workflow.

== Frequently Asked Questions ==

= Is ContactPanda required? =

No. PandaWoo Quick works independently. Optional integrations are activated only when compatible ContactPanda features are detected.

= Does the plugin require an external account? =

No. Pairing and product management take place between the phone and the connected WordPress installation.

= Can Quick Free connect to several shops at once? =

No. One app/device installation has one active shop connection. You can securely disconnect it and then pair the device with a different shop. Because browser-installed web apps are bound to their original web address, the old home-screen shortcut must be replaced when the new shop uses another domain.

= Are product features restricted unless I upgrade? =

No. The only edition limit is one simultaneous shop connection. Product capture and editing remain available.

== Installation ==

1. Install and activate WooCommerce.
2. Install and activate PandaWoo Quick.
3. Open WooCommerce > PandaWoo Quick.
4. Scan the QR code with the phone.

== Security ==

Pairing codes expire after ten minutes and may be used once. Device secrets are stored only as SHA-256 hashes. Connected devices can be revoked in WordPress.

== Changelog ==

= 0.2.7 =
* Reduces oversized phone photos before upload to avoid server failures.
* Replaces unreadable server responses with a clear message and records a privacy-safe technical error entry.
* Hides the scheduling date unless scheduled publication is selected.

= 0.2.6 =
* Keeps the Android installation action visible even when the browser does not provide a direct installation prompt.
* Shows the manual browser-menu installation path as a fallback.

= 0.2.5 =
* Added English, German, German formal and German (Switzerland) localization catalogs.
* Added the official PandaWoo Quick mascot, app icons and connected-shop logo.
* Refined typography and the mobile header.
* Added animated, reduced-motion-aware image reordering.

= 0.2.0 =
* Added automatic iPhone/iPad and Android recognition after pairing.
* Added a large illustrated desktop installation guide tailored to the connected phone.
* Added the direct browser installation prompt on supported Android browsers.
* Added token-free updates from public GitHub release assets through the standard WordPress updater.

= 0.1.0 =
* Initial local test version.

