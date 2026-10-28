# PandaWoo Quick Free

PandaWoo Quick Free is a mobile-first WooCommerce product capture plugin. A phone is paired with a WordPress installation using a short-lived, single-use QR code and receives a revocable app session instead of a WordPress password.

## Features

- Installable mobile web app without the WordPress administration interface
- One active WooCommerce shop connection per Quick Free installation
- Product list, search, creation and editing
- Up to twelve product images with touch-friendly sorting
- Hierarchical categories and optional WooCommerce brand assignment
- Price, short description and detailed description
- Immediate publishing, scheduled publishing and drafts
- Illustrated Apple and Android installation guidance
- Public, token-free GitHub release updates through WordPress

PandaWoo Quick works without ContactPanda, an external account or a paid extension. It does not send product data to an external service.

## Requirements

- WordPress 6.4 or newer
- WooCommerce 8.0 or newer
- PHP 7.4 or newer
- HTTPS is recommended and required for normal installable-web-app behavior outside local development

## Installation

1. Download `pandawoo-quick-{version}.zip` from the latest public GitHub release.
2. In WordPress, open **Plugins → Add New Plugin → Upload Plugin**.
3. Upload the ZIP and activate PandaWoo Quick.
4. Open **WooCommerce → PandaWoo Quick** and scan the QR code.

## Updates

The plugin checks the public GitHub API for the latest stable release of `kultur3gmbh/pandawoo-quick`. It accepts only the exact release asset name `pandawoo-quick-{version}.zip` and integrates with the standard WordPress plugin update screen.

No GitHub token or ContactPanda component is required. Successful checks are cached for six hours; failed checks for fifteen minutes. A manual WordPress update check bypasses the cache. Network or GitHub failures leave the installed plugin fully operational.

## Privacy and security

- Pairing links expire after ten minutes and can be used only once.
- Device secrets are stored as SHA-256 hashes.
- Connected devices can be revoked from WordPress or disconnected in the app.
- Product and media data remain native WooCommerce and WordPress records.
- No analytics, tracking or external account is required.

## License

PandaWoo Quick Free is licensed under the GNU General Public License, version 2 or later (`GPL-2.0-or-later`). See the license declaration in the plugin header and the [GPL 2.0 text](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html).

## Releases

Release ZIP assets must be named exactly:

```text
pandawoo-quick-{version}.zip
```

Example: `pandawoo-quick-0.2.0.zip`.

