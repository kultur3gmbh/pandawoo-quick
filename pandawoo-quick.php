<?php
/**
 * Plugin Name: PandaWoo Quick
 * Description: Mobile WooCommerce product capture with a securely paired installable web app.
 * Version: 0.2.8
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * Requires Plugins: woocommerce
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: pandawoo-quick
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'PANDAWOO_QUICK_VERSION', '0.2.8' );
define( 'PANDAWOO_QUICK_PATH', plugin_dir_path( __FILE__ ) );
define( 'PANDAWOO_QUICK_URL', plugin_dir_url( __FILE__ ) );

require_once PANDAWOO_QUICK_PATH . 'includes/class-pandawoo-quick-core.php';
require_once PANDAWOO_QUICK_PATH . 'includes/class-pandawoo-quick-updater.php';

PandaWoo_Quick_Core::boot();
PandaWoo_Quick_Updater::boot();

