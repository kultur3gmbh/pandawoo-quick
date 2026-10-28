#!/bin/sh
set -eu
root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
php_file="$root/includes/class-pandawoo-quick-core.php"
updater_file="$root/includes/class-pandawoo-quick-updater.php"
js_file="$root/assets/app.js"

for needle in "new WC_Product_Simple" "wc_get_products" "pandawoo_quick_pair_" "secret_hash" "shop_id" "maxShops" "ajax_disconnect" "contactpanda_available" "dismiss_product_info" "taxonomy_choices" "product_brand" "ajax_pair_status" "ajax_report_device" "beforeinstallprompt" "guide-ios-share.svg" "pointerdown" "font-size:16px" "pre_set_site_transient_update_plugins" "plugins_api" "pandawoo-quick-' . \$version . '.zip" "api.github.com/repos"; do
	if ! grep -q "$needle" "$php_file" "$updater_file" "$js_file" "$root/assets/app.css"; then
		echo "Missing required implementation marker: $needle" >&2
		exit 1
	fi
done

if grep -Eiq 'CREATE[[:space:]]+TABLE' "$php_file"; then
	echo "PandaWoo Quick must not create a parallel product table." >&2
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

