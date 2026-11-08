<?php
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'Panda_Public_Updater' ) ) require_once __DIR__ . '/class-panda-public-updater.php';
new Panda_Public_Updater( plugin_basename( PANDAWOO_QUICK_PATH . 'pandawoo-quick.php' ), PANDAWOO_QUICK_VERSION, array(
	'repository' => 'kultur3gmbh/pandawoo-quick', 'slug' => 'pandawoo-quick', 'tag_prefix' => 'quick-v',
	'name' => 'PandaWoo Quick', 'asset_prefix' => 'pandawoo-quick', 'manifest' => 'pandawoo-quick-update.json',
) );
