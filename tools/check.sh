#!/bin/sh
set -eu
root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
php_file="$root/includes/class-pandawoo-quick-core.php"
updater_file="$root/includes/class-pandawoo-quick-updater.php"
js_file="$root/assets/app.js"

for needle in "new WC_Product_Simple" "wc_get_products" "pandawoo_quick_pair_" "secret_hash" "shop_id" "maxShops" "ajax_disconnect" "ajax_delete" "set_manage_stock" "pwq-stock-mode" "confirmDelete" "array_map( 'absint'" "contactpanda_available" "dismiss_product_info" "taxonomy_choices" "product_brand" "ajax_pair_status" "ajax_report_device" "beforeinstallprompt" "pwq-install-fallback" "genericMenuText" "guide-ios-share.svg" "pointerdown" "font-size:16px" "preparePhoto" "response.text" "ERROR_LOG_OPTION" "pwq-schedule\[hidden\]" "pre_set_site_transient_update_plugins" "plugins_api" "pandawoo-quick-' . \$version . '.zip" "api.github.com/repos"; do
	if ! grep -q "$needle" "$php_file" "$updater_file" "$js_file" "$root/assets/app.css"; then
		echo "Missing required implementation marker: $needle" >&2
		exit 1
	fi
done

if grep -Eiq 'CREATE[[:space:]]+TABLE' "$php_file"; then
	echo "PandaWoo Quick must not create a parallel product table." >&2
	exit 1
fi

for catalog in \
	"$root/languages/pandawoo-quick.pot" \
	"$root/languages/pandawoo-quick-de_DE.po" \
	"$root/languages/pandawoo-quick-de_DE.mo" \
	"$root/languages/pandawoo-quick-de_DE_formal.po" \
	"$root/languages/pandawoo-quick-de_DE_formal.mo" \
	"$root/languages/pandawoo-quick-de_CH.po" \
	"$root/languages/pandawoo-quick-de_CH.mo"; do
	if [ ! -s "$catalog" ]; then
		echo "Missing or empty translation catalog: $catalog" >&2
		exit 1
	fi
done

for asset in pandawoo-quick-header-96.png pandawoo-quick-app-icon-192.png pandawoo-quick-app-icon-512.png pandawoo-quick-mascot-transparent.png pandawoo-quick-icon-mark.svg; do
	if [ ! -s "$root/assets/$asset" ]; then
		echo "Missing official brand asset: $asset" >&2
		exit 1
	fi
done

if grep -Eq '[ÄÖÜäöüß]|Home-Bildschirm|Diesen Shop|Veröffentlicht' "$js_file" "$root"/assets/guide-*.svg; then
	echo "Hard-coded German UI text found outside gettext catalogs." >&2
	exit 1
fi

if ! grep -q "Version: 0.3.0" "$root/pandawoo-quick.php" || ! grep -q "PANDAWOO_QUICK_VERSION', '0.3.0'" "$root/pandawoo-quick.php" || ! grep -q "Stable tag: 0.3.0" "$root/readme.txt"; then
	echo "Version metadata is not synchronized at 0.3.0." >&2
	exit 1
fi

if command -v php >/dev/null 2>&1; then
	php -l "$root/pandawoo-quick.php"
	php -l "$php_file"
	php -l "$updater_file"
else
	echo "PHP CLI unavailable; PHP runtime lint skipped."
fi

if command -v node >/dev/null 2>&1; then
	node --check "$js_file"
else
	echo "Node unavailable; JavaScript syntax check skipped."
fi

echo "PandaWoo Quick structural checks passed."

