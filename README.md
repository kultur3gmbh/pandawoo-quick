# PandaWoo Quick

PandaWoo Quick is the complete free, mobile-first WooCommerce product capture plugin. A phone is paired with a WordPress installation using a short-lived, single-use QR code and receives a revocable app session instead of a WordPress password.

## Features

- Installable mobile web app without the WordPress administration interface
- One active WooCommerce shop connection per Quick installation
- Product list, search, creation and editing
- Up to twelve product images with touch-friendly sorting
- Hierarchical categories and optional WooCommerce brand assignment
- Price, short description and detailed description
- Immediate publishing, scheduled publishing and drafts
- Illustrated Apple and Android installation guidance
- Updates through the official WordPress.org plugin update system
- English source language plus German, German formal and German (Switzerland) translations
- Official PandaWoo Quick mascot, connected-shop logo and refined typography
- Animated, touch-friendly image reordering with reduced-motion support

PandaWoo Quick works without ContactPanda, an external account or a paid extension. It does not send product data to an external service.

## Requirements

- WordPress 6.4 or newer
- WooCommerce 8.0 or newer
- PHP 7.4 or newer
- HTTPS is recommended and required for normal installable-web-app behavior outside local development

## Installation

1. Install PandaWoo Quick from the WordPress plugin directory.
2. Activate the plugin together with WooCommerce.
3. Open **PandaWoo → Quick** and scan the QR code.

## Updates

Updates are supplied by the official WordPress.org plugin update system. The distributed plugin contains no external updater and requires no GitHub token or ContactPanda component.

## Privacy and security

- Pairing links expire after ten minutes and can be used only once.
- Device secrets are stored as SHA-256 hashes.
- Connected devices can be revoked from WordPress or disconnected in the app.
- Product and media data remain native WooCommerce and WordPress records.
- No analytics, tracking or external account is required.

## Translations

English is the source language. The repository ships a gettext POT template plus PO and compiled MO catalogs for:

- German (`de_DE`)
- German formal (`de_DE_formal`)
- German (Switzerland) (`de_CH`)

Run `python3 tools/make_i18n.py` after changing user-facing strings. It regenerates the POT, PO and compiled MO catalogs.

## License

PandaWoo Quick is licensed under the GNU General Public License, version 2 or later (`GPL-2.0-or-later`). See the license declaration in the plugin header and the [GPL 2.0 text](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html).

## Releases

Release ZIP assets must be named exactly:

```text
pandawoo-quick-{version}.zip
```

Example: `pandawoo-quick-0.2.0.zip`.

