<?php

defined( 'ABSPATH' ) || exit;

/**
 * Shared PandaWoo Quick core. ContactPanda adapters can boot this class and
 * replace branding/AI behavior through the documented filters.
 */
final class PandaWoo_Quick_Core {
	const PAGE_SLUG = 'pandawoo-quick';
	const APP_QUERY = 'pandawoo_quick_app';
	const DEVICE_OPTION = 'pandawoo_quick_devices';
	const ERROR_LOG_OPTION = 'pandawoo_quick_error_log';
	const DEVICE_COOKIE = 'pandawoo_quick_device';
	const NONCE_ACTION = 'pandawoo_quick';
	const PAIR_ACTION = 'pandawoo_quick_pair';
	const MAX_IMAGES = 12;
	const SESSION_DAYS = 90;

	private static $page_hook = '';
	private static $standalone = false;
	private static $active_device = array();

	public static function boot() {
		add_action( 'init', array( __CLASS__, 'load_textdomain' ), 1 );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );
		add_action( 'template_redirect', array( __CLASS__, 'standalone_app' ), 0 );
		add_action( 'admin_post_' . self::PAIR_ACTION, array( __CLASS__, 'pair_device' ) );
		add_action( 'admin_post_nopriv_' . self::PAIR_ACTION, array( __CLASS__, 'pair_device' ) );
		add_action( 'admin_post_pandawoo_quick_revoke', array( __CLASS__, 'revoke_device' ) );
		add_action( 'admin_post_pandawoo_quick_dismiss_product_info', array( __CLASS__, 'dismiss_product_info' ) );
		add_action( 'admin_post_pandawoo_quick_manifest', array( __CLASS__, 'manifest' ) );
		add_action( 'admin_post_nopriv_pandawoo_quick_manifest', array( __CLASS__, 'manifest' ) );
		add_action( 'admin_post_pandawoo_quick_service_worker', array( __CLASS__, 'service_worker' ) );
		add_action( 'admin_post_nopriv_pandawoo_quick_service_worker', array( __CLASS__, 'service_worker' ) );
		foreach ( array( 'list', 'get', 'save', 'disconnect', 'report_device', 'client_error' ) as $operation ) {
			add_action( 'wp_ajax_pandawoo_quick_' . $operation, array( __CLASS__, 'ajax_' . $operation ) );
			add_action( 'wp_ajax_nopriv_pandawoo_quick_' . $operation, array( __CLASS__, 'ajax_' . $operation ) );
		}
		add_action( 'wp_ajax_pandawoo_quick_pair_status', array( __CLASS__, 'ajax_pair_status' ) );
	}

	public static function load_textdomain() {
		load_plugin_textdomain( 'pandawoo-quick', false, dirname( plugin_basename( PANDAWOO_QUICK_PATH . 'pandawoo-quick.php' ) ) . '/languages' );
	}

	private static function capability() {
		return (string) apply_filters( 'pandawoo_quick_capability', 'edit_products' );
	}

	private static function available() {
		return class_exists( 'WooCommerce' ) && class_exists( 'WC_Product_Simple' );
	}

	public static function menu() {
		self::$page_hook = add_submenu_page( 'woocommerce', __( 'PandaWoo Quick', 'pandawoo-quick' ), __( 'PandaWoo Quick', 'pandawoo-quick' ), self::capability(), self::PAGE_SLUG, array( __CLASS__, 'admin_page' ) );
	}

	public static function admin_assets( $hook ) {
		if ( self::$page_hook === $hook ) self::enqueue_assets();
	}

	private static function enqueue_assets() {
		wp_enqueue_style( 'pandawoo-quick', PANDAWOO_QUICK_URL . 'assets/app.css', array(), PANDAWOO_QUICK_VERSION );
		if ( ! wp_script_is( 'wc-qrcode', 'registered' ) && function_exists( 'WC' ) && WC() ) {
			wp_register_script( 'wc-qrcode', WC()->plugin_url() . '/assets/js/jquery-qrcode/jquery.qrcode.min.js', array( 'jquery' ), defined( 'WC_VERSION' ) ? WC_VERSION : PANDAWOO_QUICK_VERSION, true );
		}
		wp_enqueue_script( 'pandawoo-quick', PANDAWOO_QUICK_URL . 'assets/app.js', array( 'jquery', 'wc-qrcode' ), PANDAWOO_QUICK_VERSION, true );
		wp_localize_script( 'pandawoo-quick', 'PANDAWOO_QUICK', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'appUrl' => self::app_url(),
			'nonce' => wp_create_nonce( self::NONCE_ACTION ),
			'deviceNonce' => self::$active_device['csrf'] ?? '',
			'serviceWorkerUrl' => add_query_arg( 'action', 'pandawoo_quick_service_worker', admin_url( 'admin-post.php' ) ),
			'serviceWorkerScope' => wp_parse_url( home_url( '/' ), PHP_URL_PATH ) ?: '/',
			'assetUrl' => PANDAWOO_QUICK_URL . 'assets/',
			'currency' => function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '',
			'edition' => (string) apply_filters( 'pandawoo_quick_edition', 'free' ),
			'maxShops' => max( 1, absint( apply_filters( 'pandawoo_quick_max_shops', 1 ) ) ),
			'shop' => self::shop_context(),
			'i18n' => array(
				'cover' => __( 'Cover image', 'pandawoo-quick' ),
				'remove' => __( 'Remove image', 'pandawoo-quick' ),
				'move' => __( 'Drag to change the image order', 'pandawoo-quick' ),
				'previous' => __( 'Move image earlier', 'pandawoo-quick' ),
				'next' => __( 'Move image later', 'pandawoo-quick' ),
				'photos' => __( '%d photos selected', 'pandawoo-quick' ),
				'preparingPhotos' => __( 'Preparing photos…', 'pandawoo-quick' ),
				'saving' => __( 'Saving product…', 'pandawoo-quick' ),
				'loading' => __( 'Loading products…', 'pandawoo-quick' ),
				'network' => __( 'The server could not be reached. Please try again.', 'pandawoo-quick' ),
				'invalidResponse' => __( 'The server returned an unreadable response. Please try again. If this happens again, check the technical error log in PandaWoo Quick.', 'pandawoo-quick' ),
				'photoRequired' => __( 'Please add at least one product photo.', 'pandawoo-quick' ),
				'newProduct' => __( 'New product', 'pandawoo-quick' ),
				'editProduct' => __( 'Edit product', 'pandawoo-quick' ),
				'statusPublished' => __( 'Published', 'pandawoo-quick' ),
				'statusDraft' => __( 'Draft', 'pandawoo-quick' ),
				'statusScheduled' => __( 'Scheduled', 'pandawoo-quick' ),
				'statusPending' => __( 'Pending', 'pandawoo-quick' ),
				'statusPrivate' => __( 'Private', 'pandawoo-quick' ),
				'phoneConnected' => __( 'Phone connected successfully', 'pandawoo-quick' ),
				'installAndroidTitle' => __( 'Install PandaWoo Quick on Android', 'pandawoo-quick' ),
				'installIosTitle' => __( 'Install PandaWoo Quick on iPhone', 'pandawoo-quick' ),
				'installGenericTitle' => __( 'Add PandaWoo Quick to the Home Screen', 'pandawoo-quick' ),
				'keepGuideOpen' => __( 'Keep these instructions open on the computer and follow the steps on your phone.', 'pandawoo-quick' ),
				'smartphone' => __( 'Smartphone', 'pandawoo-quick' ),
				'iosShareTitle' => __( 'Open Share', 'pandawoo-quick' ),
				'iosShareText' => __( 'In Safari, tap the square with the upward arrow. Depending on your Safari layout, it is at the top or bottom.', 'pandawoo-quick' ),
				'iosHomeTitle' => __( 'Add to Home Screen', 'pandawoo-quick' ),
				'iosHomeText' => __( 'Scroll through the list and tap “Add to Home Screen”.', 'pandawoo-quick' ),
				'iosWebAppTitle' => __( 'Open as Web App', 'pandawoo-quick' ),
				'iosWebAppText' => __( 'Make sure “Open as Web App” is enabled.', 'pandawoo-quick' ),
				'iosAddTitle' => __( 'Add', 'pandawoo-quick' ),
				'iosAddText' => __( 'Tap “Add” in the top-right corner.', 'pandawoo-quick' ),
				'finishedTitle' => __( 'Done', 'pandawoo-quick' ),
				'iosFinishedText' => __( 'Now open the PandaWoo Quick icon on your Home Screen.', 'pandawoo-quick' ),
				'androidInstallTitle' => __( 'Confirm installation', 'pandawoo-quick' ),
				'androidInstallText' => __( 'The “Install app” button appears on your phone. Tap it and confirm the browser dialog.', 'pandawoo-quick' ),
				'androidOpenTitle' => __( 'Open the app', 'pandawoo-quick' ),
				'androidOpenText' => __( 'Then open PandaWoo Quick using the new icon on your Home Screen.', 'pandawoo-quick' ),
				'genericMenuTitle' => __( 'Open the browser menu', 'pandawoo-quick' ),
				'genericMenuText' => __( 'Open the browser menu on your phone and choose “Add to Home Screen” or “Install app”.', 'pandawoo-quick' ),
				'installOptional' => __( 'Product capture already works. Installing it on the Home Screen is a convenient shortcut.', 'pandawoo-quick' ),
				'pairExpired' => __( 'The QR code has expired. Reload the page to create a new code.', 'pandawoo-quick' ),
				'noProducts' => __( 'No matching products found.', 'pandawoo-quick' ),
				'confirmDisconnect' => __( 'Really disconnect this shop from PandaWoo Quick?', 'pandawoo-quick' ),
			),
		) );
	}

	/**
	 * Stable shop boundary shared by sessions, API responses and future adapters.
	 * A future multi-shop client may keep several of these records; Quick Free
	 * deliberately exposes only the single context returned by this installation.
	 */
	private static function shop_context() {
		$home = untrailingslashit( home_url( '/' ) );
		$context = array(
			'id' => hash( 'sha256', strtolower( $home ) ),
			'name' => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'url' => $home,
		);
		return (array) apply_filters( 'pandawoo_quick_shop_context', $context );
	}

	private static function app_url() {
		return add_query_arg( self::APP_QUERY, '1', home_url( '/' ) );
	}

	private static function shop_logo_url() {
		$custom_logo_id = absint( get_theme_mod( 'custom_logo' ) );
		if ( $custom_logo_id ) {
			$url = wp_get_attachment_image_url( $custom_logo_id, 'thumbnail' );
			if ( $url ) return $url;
		}
		return get_site_icon_url( 96, PANDAWOO_QUICK_URL . 'assets/pandawoo-quick-icon-mark.svg' );
	}

	private static function pairing_session() {
		$token = wp_generate_password( 48, false, false );
		$pairing_id = strtolower( wp_generate_password( 24, false, false ) );
		set_transient( 'pandawoo_quick_pair_' . hash( 'sha256', $token ), array( 'user_id' => get_current_user_id(), 'pairing_id' => $pairing_id ), 10 * MINUTE_IN_SECONDS );
		set_transient( 'pandawoo_quick_pair_status_' . $pairing_id, array( 'user_id' => get_current_user_id(), 'status' => 'waiting' ), 10 * MINUTE_IN_SECONDS );
		return array( 'id' => $pairing_id, 'url' => add_query_arg( array( 'action' => self::PAIR_ACTION, 'token' => rawurlencode( $token ) ), admin_url( 'admin-post.php' ) ) );
	}

	public static function admin_page() {
		if ( ! current_user_can( self::capability() ) ) wp_die( esc_html__( 'You do not have permission to use PandaWoo Quick.', 'pandawoo-quick' ) );
		if ( ! self::available() ) wp_die( esc_html__( 'PandaWoo Quick requires WooCommerce.', 'pandawoo-quick' ) );
		self::render_shell( true );
	}

	private static function render_shell( $admin = false ) {
		$pairing = $admin ? self::pairing_session() : array( 'id' => '', 'url' => '' );
		echo '<div class="pwq ' . ( $admin ? 'pwq-admin wrap' : 'pwq-app' ) . '">';
		if ( $admin ) {
			echo '<header class="pwq-admin-head"><div><h1>PandaWoo Quick</h1><p>' . esc_html__( 'Capture and maintain WooCommerce products from your phone.', 'pandawoo-quick' ) . '</p></div></header>';
			echo '<section class="pwq-card pwq-pair" data-pairing-id="' . esc_attr( $pairing['id'] ) . '"><div><h2>' . esc_html__( 'Connect a phone', 'pandawoo-quick' ) . '</h2><p>' . esc_html__( 'Scan this one-time code. It expires after ten minutes and does not contain your WordPress password.', 'pandawoo-quick' ) . '</p><p class="pwq-pair-state" aria-live="polite">' . esc_html__( 'Waiting for the phone…', 'pandawoo-quick' ) . '</p></div><div><div class="pwq-qr" data-url="' . esc_attr( $pairing['url'] ) . '"></div><small>' . esc_html__( 'The code can be used once.', 'pandawoo-quick' ) . '</small></div></section>';
			echo '<section class="pwq-install-guide" hidden aria-live="polite"></section>';
			self::render_devices();
			self::render_error_log();
			self::render_product_info();
		}
		$shop = self::shop_context();
		echo '<div class="pwq-topbar"><div class="pwq-shop-identity"><img class="pwq-shop-logo" src="' . esc_url( self::shop_logo_url() ) . '" alt=""><div class="pwq-brand"><strong>PandaWoo Quick</strong><small class="pwq-active-shop">' . esc_html( $shop['name'] ?? $shop['url'] ) . '</small></div></div><div class="pwq-topbar-actions">' . ( self::$standalone ? '<button type="button" class="pwq-button pwq-install-app" aria-expanded="false" hidden>' . esc_html__( 'Install app', 'pandawoo-quick' ) . '</button>' : '' ) . '<button type="button" class="pwq-button pwq-new">+ ' . esc_html__( 'New product', 'pandawoo-quick' ) . '</button>' . ( self::$standalone ? '<button type="button" class="pwq-disconnect">' . esc_html__( 'Disconnect shop', 'pandawoo-quick' ) . '</button>' : '' ) . '<img class="pwq-header-mascot" src="' . esc_url( PANDAWOO_QUICK_URL . 'assets/pandawoo-quick-header-96.png' ) . '" alt="PandaWoo Quick"></div></div>';
		if ( self::$standalone ) echo '<div class="pwq-install-fallback" role="status" hidden><strong>' . esc_html__( 'Open the browser menu', 'pandawoo-quick' ) . '</strong><span>' . esc_html__( 'Open the browser menu on your phone and choose “Add to Home Screen” or “Install app”.', 'pandawoo-quick' ) . '</span></div>';
		echo '<main class="pwq-main"><section class="pwq-list-view"><div class="pwq-list-tools"><label><span class="screen-reader-text">' . esc_html__( 'Search products', 'pandawoo-quick' ) . '</span><input class="pwq-product-search" type="search" placeholder="' . esc_attr__( 'Search products…', 'pandawoo-quick' ) . '"></label><select class="pwq-status-filter" aria-label="' . esc_attr__( 'Filter by status', 'pandawoo-quick' ) . '"><option value="">' . esc_html__( 'All statuses', 'pandawoo-quick' ) . '</option><option value="publish">' . esc_html__( 'Published', 'pandawoo-quick' ) . '</option><option value="draft">' . esc_html__( 'Drafts', 'pandawoo-quick' ) . '</option><option value="future">' . esc_html__( 'Scheduled', 'pandawoo-quick' ) . '</option></select></div><p class="pwq-list-message" aria-live="polite"></p><div class="pwq-product-list"></div><button type="button" class="pwq-button pwq-more" hidden>' . esc_html__( 'Load more', 'pandawoo-quick' ) . '</button></section>';
		self::render_editor();
		echo '</main></div>';
	}

	private static function render_editor() {
		$categories = self::taxonomy_choices( 'product_cat' );
		$brands = taxonomy_exists( 'product_brand' ) ? self::taxonomy_choices( 'product_brand' ) : array();
		$min = ( new DateTimeImmutable( '+5 minutes', wp_timezone() ) )->format( 'Y-m-d\TH:i' );
		echo '<section class="pwq-editor-view" hidden><button type="button" class="pwq-back">← ' . esc_html__( 'Products', 'pandawoo-quick' ) . '</button><h1 class="pwq-editor-title">' . esc_html__( 'New product', 'pandawoo-quick' ) . '</h1><form class="pwq-form" enctype="multipart/form-data"><input type="hidden" name="product_id" value="0">';
		echo '<section class="pwq-card"><h2>1. ' . esc_html__( 'Photos', 'pandawoo-quick' ) . '</h2><label class="pwq-dropzone"><strong>' . esc_html__( 'Take or select photos', 'pandawoo-quick' ) . '</strong><small>' . esc_html__( 'JPEG, PNG or WebP; up to 12 photos.', 'pandawoo-quick' ) . '</small><input class="pwq-images" type="file" accept="image/jpeg,image/png,image/webp" multiple></label><p class="pwq-photo-summary" aria-live="polite"></p><div class="pwq-photo-list"></div></section>';
		echo '<section class="pwq-card"><h2>2. ' . esc_html__( 'Product', 'pandawoo-quick' ) . '</h2><div class="pwq-fields"><label class="wide"><span>' . esc_html__( 'Product name', 'pandawoo-quick' ) . '</span><input name="product_name" type="text" maxlength="190" required></label><label><span>' . esc_html__( 'Search category', 'pandawoo-quick' ) . '</span><input class="pwq-category-search" type="search" autocomplete="off"></label><label><span>' . esc_html__( 'Category', 'pandawoo-quick' ) . '</span><select class="pwq-category" name="category_id" required><option value="">' . esc_html__( 'Please select', 'pandawoo-quick' ) . '</option>';
		foreach ( $categories as $category ) echo '<option value="' . esc_attr( $category['id'] ) . '">' . esc_html( $category['label'] ) . '</option>';
		echo '</select></label>';
		if ( taxonomy_exists( 'product_brand' ) ) {
			echo '<label><span>' . esc_html__( 'Brand', 'pandawoo-quick' ) . '</span><select class="pwq-brand" name="brand_id"><option value="">' . esc_html__( 'No brand', 'pandawoo-quick' ) . '</option>';
			foreach ( $brands as $brand ) echo '<option value="' . esc_attr( $brand['id'] ) . '">' . esc_html( $brand['label'] ) . '</option>';
			echo '</select></label>';
		}
		echo '<label><span>' . esc_html__( 'Regular price', 'pandawoo-quick' ) . '</span><div class="pwq-price"><input name="regular_price" type="number" min="0" step="0.01" inputmode="decimal" required><b>' . esc_html( get_woocommerce_currency_symbol() ) . '</b></div></label><label class="wide"><span>' . esc_html__( 'Short description', 'pandawoo-quick' ) . '</span><textarea name="short_description" rows="3"></textarea></label><label class="wide"><span>' . esc_html__( 'Detailed description', 'pandawoo-quick' ) . '</span><textarea name="description" rows="7"></textarea></label></div></section>';
		echo '<section class="pwq-card"><h2>3. ' . esc_html__( 'Publication', 'pandawoo-quick' ) . '</h2><div class="pwq-modes"><label><input type="radio" name="publish_mode" value="publish" checked><strong>' . esc_html__( 'Publish now', 'pandawoo-quick' ) . '</strong></label><label><input type="radio" name="publish_mode" value="future"><strong>' . esc_html__( 'Schedule', 'pandawoo-quick' ) . '</strong></label><label><input type="radio" name="publish_mode" value="draft"><strong>' . esc_html__( 'Save as draft', 'pandawoo-quick' ) . '</strong></label></div><label class="pwq-schedule" hidden><span>' . esc_html__( 'Publication date and time', 'pandawoo-quick' ) . '</span><input type="datetime-local" name="publish_at" min="' . esc_attr( $min ) . '"></label></section>';
		echo '<div class="pwq-submit"><button class="pwq-button primary" type="submit">' . esc_html__( 'Save product', 'pandawoo-quick' ) . '</button><p class="pwq-message" aria-live="polite"></p></div></form></section>';
	}

	private static function taxonomy_choices( $taxonomy ) {
		$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC' ) );
		if ( is_wp_error( $terms ) ) return array();
		$by_parent = array();
		foreach ( $terms as $term ) $by_parent[ absint( $term->parent ) ][] = $term;
		$choices = array();
		$append = function ( $parent, $path = array() ) use ( &$append, &$choices, $by_parent ) {
			foreach ( $by_parent[ $parent ] ?? array() as $term ) {
				$current_path = array_merge( $path, array( $term->name ) );
				$choices[] = array( 'id' => absint( $term->term_id ), 'label' => implode( ' › ', $current_path ), 'depth' => count( $path ) );
				$append( absint( $term->term_id ), $current_path );
			}
		};
		$append( 0 );
		return $choices;
	}

	private static function device_records() {
		$records = (array) get_option( self::DEVICE_OPTION, array() );
		foreach ( $records as $id => $record ) if ( empty( $record['expires_at'] ) || absint( $record['expires_at'] ) < time() ) unset( $records[ $id ] );
		update_option( self::DEVICE_OPTION, $records, false );
		return $records;
	}

	private static function authenticated_device() {
		$cookie = sanitize_text_field( wp_unslash( $_COOKIE[ self::DEVICE_COOKIE ] ?? '' ) );
		$parts = explode( '.', $cookie, 2 );
		if ( 2 !== count( $parts ) ) return false;
		$records = self::device_records();
		$record = $records[ $parts[0] ] ?? array();
		if ( ! $record || ! hash_equals( (string) ( $record['secret_hash'] ?? '' ), hash( 'sha256', $parts[1] ) ) ) return false;
		$shop = self::shop_context();
		if ( empty( $record['shop_id'] ) || ! hash_equals( (string) $record['shop_id'], (string) ( $shop['id'] ?? '' ) ) ) return false;
		if ( ! get_user_by( 'id', absint( $record['user_id'] ?? 0 ) ) ) return false;
		if ( absint( $record['last_used'] ?? 0 ) < time() - HOUR_IN_SECONDS ) {
			$records[ $parts[0] ]['last_used'] = time();
			update_option( self::DEVICE_OPTION, $records, false );
		}
		$record['id'] = $parts[0];
		return $record;
	}

	private static function require_access() {
		$device = self::authenticated_device();
		if ( $device ) {
			$provided = sanitize_text_field( wp_unslash( $_REQUEST['device_nonce'] ?? '' ) );
			if ( ! $provided || ! hash_equals( (string) $device['csrf'], $provided ) ) wp_send_json_error( array( 'message' => __( 'The app session could not be verified.', 'pandawoo-quick' ) ), 403 );
			wp_set_current_user( absint( $device['user_id'] ) );
		} else {
			check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		}
		if ( ! current_user_can( self::capability() ) || ! current_user_can( 'upload_files' ) ) wp_send_json_error( array( 'message' => __( 'You do not have permission to edit products and upload photos.', 'pandawoo-quick' ) ), 403 );
		if ( ! self::available() ) wp_send_json_error( array( 'message' => __( 'WooCommerce is unavailable.', 'pandawoo-quick' ) ), 409 );
	}

	public static function pair_device() {
		$token = sanitize_text_field( wp_unslash( $_GET['token'] ?? '' ) );
		$key = 'pandawoo_quick_pair_' . hash( 'sha256', $token );
		$pair = $token ? get_transient( $key ) : false;
		if ( ! is_array( $pair ) || empty( $pair['user_id'] ) ) wp_die( esc_html__( 'This pairing code has expired or has already been used.', 'pandawoo-quick' ), '', array( 'response' => 410 ) );
		delete_transient( $key );
		$user = get_user_by( 'id', absint( $pair['user_id'] ) );
		if ( ! $user || ! user_can( $user, self::capability() ) || ! user_can( $user, 'upload_files' ) ) wp_die( esc_html__( 'This user may not use PandaWoo Quick.', 'pandawoo-quick' ), '', array( 'response' => 403 ) );
		$id = strtolower( wp_generate_password( 20, false, false ) );
		$secret = wp_generate_password( 64, false, false );
		$records = self::device_records();
		$shop = self::shop_context();
		$pairing_id = sanitize_key( $pair['pairing_id'] ?? '' );
		$platform = self::platform_from_user_agent( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ) );
		$records[ $id ] = array( 'user_id' => absint( $user->ID ), 'shop_id' => (string) $shop['id'], 'shop_name' => sanitize_text_field( $shop['name'] ?? '' ), 'shop_url' => esc_url_raw( $shop['url'] ?? '' ), 'pairing_id' => $pairing_id, 'platform' => $platform, 'label' => sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? 'Mobile device' ) ), 'secret_hash' => hash( 'sha256', $secret ), 'csrf' => wp_generate_password( 48, false, false ), 'created_at' => time(), 'last_used' => time(), 'expires_at' => time() + self::SESSION_DAYS * DAY_IN_SECONDS );
		update_option( self::DEVICE_OPTION, $records, false );
		if ( $pairing_id ) set_transient( 'pandawoo_quick_pair_status_' . $pairing_id, array( 'user_id' => absint( $user->ID ), 'status' => 'unknown' === $platform ? 'identifying' : 'connected', 'device_id' => $id, 'platform' => $platform ), 30 * MINUTE_IN_SECONDS );
		setcookie( self::DEVICE_COOKIE, $id . '.' . $secret, array( 'expires' => $records[ $id ]['expires_at'], 'path' => '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
		wp_safe_redirect( add_query_arg( 'pandawoo_paired', '1', self::app_url() ) );
		exit;
	}

	private static function platform_from_user_agent( $user_agent ) {
		if ( preg_match( '/iPhone|iPad|iPod/i', $user_agent ) ) return 'ios';
		if ( preg_match( '/Android/i', $user_agent ) ) return 'android';
		return 'unknown';
	}

	public static function ajax_report_device() {
		self::require_access();
		$device = self::authenticated_device();
		if ( ! $device || empty( $device['id'] ) ) wp_send_json_error( array( 'message' => __( 'No connected device was found.', 'pandawoo-quick' ) ), 404 );
		$platform = sanitize_key( $_POST['platform'] ?? 'unknown' );
		if ( ! in_array( $platform, array( 'ios', 'android', 'unknown' ), true ) ) $platform = 'unknown';
		$records = self::device_records();
		$records[ $device['id'] ]['platform'] = $platform;
		update_option( self::DEVICE_OPTION, $records, false );
		if ( ! empty( $device['pairing_id'] ) ) set_transient( 'pandawoo_quick_pair_status_' . $device['pairing_id'], array( 'user_id' => absint( $device['user_id'] ), 'status' => 'connected', 'device_id' => $device['id'], 'platform' => $platform ), 30 * MINUTE_IN_SECONDS );
		wp_send_json_success( array( 'platform' => $platform ) );
	}

	public static function ajax_pair_status() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( self::capability() ) ) wp_send_json_error( array( 'message' => __( 'Permission denied.', 'pandawoo-quick' ) ), 403 );
		$pairing_id = sanitize_key( $_POST['pairing_id'] ?? '' );
		$status = $pairing_id ? get_transient( 'pandawoo_quick_pair_status_' . $pairing_id ) : false;
		if ( ! is_array( $status ) || absint( $status['user_id'] ?? 0 ) !== get_current_user_id() ) wp_send_json_success( array( 'status' => 'expired', 'platform' => 'unknown' ) );
		wp_send_json_success( array( 'status' => sanitize_key( $status['status'] ?? 'waiting' ), 'platform' => sanitize_key( $status['platform'] ?? 'unknown' ) ) );
	}

	public static function revoke_device() {
		if ( ! current_user_can( self::capability() ) ) wp_die( esc_html__( 'Permission denied.', 'pandawoo-quick' ), '', array( 'response' => 403 ) );
		check_admin_referer( 'pandawoo_quick_revoke' );
		$records = self::device_records();
		unset( $records[ sanitize_key( wp_unslash( $_GET['device_id'] ?? '' ) ) ] );
		update_option( self::DEVICE_OPTION, $records, false );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
		exit;
	}

	private static function render_devices() {
		$records = self::device_records();
		if ( ! $records ) return;
		echo '<section class="pwq-card pwq-devices"><h2>' . esc_html__( 'Connected phones', 'pandawoo-quick' ) . '</h2><ul>';
		foreach ( $records as $id => $record ) {
			$url = wp_nonce_url( add_query_arg( array( 'action' => 'pandawoo_quick_revoke', 'device_id' => $id ), admin_url( 'admin-post.php' ) ), 'pandawoo_quick_revoke' );
			echo '<li><span><strong>' . esc_html( wp_trim_words( $record['label'] ?? 'Mobile device', 8 ) ) . '</strong><small>' . esc_html( wp_date( 'd.m.Y H:i', absint( $record['last_used'] ?? 0 ) ) ) . '</small></span><a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Disconnect', 'pandawoo-quick' ) . '</a></li>';
		}
		echo '</ul></section>';
	}

	private static function record_error( $context, $message ) {
		$records = get_option( self::ERROR_LOG_OPTION, array() );
		if ( ! is_array( $records ) ) $records = array();
		$records[] = array(
			'time' => time(),
			'context' => sanitize_key( $context ),
			'message' => wp_html_excerpt( sanitize_text_field( wp_strip_all_tags( (string) $message ) ), 500, '…' ),
		);
		update_option( self::ERROR_LOG_OPTION, array_slice( $records, -20 ), false );
	}

	private static function render_error_log() {
		$records = get_option( self::ERROR_LOG_OPTION, array() );
		if ( ! is_array( $records ) || ! $records ) return;
		echo '<section class="pwq-card pwq-error-log"><h2>' . esc_html__( 'Technical error log', 'pandawoo-quick' ) . '</h2><p>' . esc_html__( 'Recent errors detected by PandaWoo Quick. No product or customer data is stored here.', 'pandawoo-quick' ) . '</p><ul>';
		foreach ( array_reverse( array_slice( $records, -10 ) ) as $record ) {
			echo '<li><strong>' . esc_html( wp_date( 'd.m.Y H:i', absint( $record['time'] ?? 0 ) ) ) . '</strong> <code>' . esc_html( $record['context'] ?? 'unknown' ) . '</code> ' . esc_html( $record['message'] ?? '' ) . '</li>';
		}
		echo '</ul></section>';
	}

	private static function contactpanda_available() {
		return defined( 'CONTACTPANDA_VERSION' ) || class_exists( 'ContactPanda' ) || class_exists( 'ContactPanda_Admin' );
	}

	private static function render_product_info() {
		if ( get_user_meta( get_current_user_id(), 'pandawoo_quick_hide_product_info', true ) ) return;
		$dismiss = wp_nonce_url( add_query_arg( 'action', 'pandawoo_quick_dismiss_product_info', admin_url( 'admin-post.php' ) ), 'pandawoo_quick_dismiss_product_info' );
		echo '<aside class="pwq-card pwq-product-info"><div class="pwq-product-info-head"><div><p class="pwq-kicker">' . esc_html__( 'Optional extensions', 'pandawoo-quick' ) . '</p><h2>' . esc_html__( 'More tools when your shop grows', 'pandawoo-quick' ) . '</h2></div><a href="' . esc_url( $dismiss ) . '" aria-label="' . esc_attr__( 'Hide these recommendations', 'pandawoo-quick' ) . '">×</a></div><div class="pwq-product-info-grid">';
		if ( self::contactpanda_available() ) {
			echo '<section><strong>' . esc_html__( 'ContactPanda is active', 'pandawoo-quick' ) . '</strong><p>' . esc_html__( 'PandaWoo Quick remains independent. Available ContactPanda integrations are enabled only when their required features are detected.', 'pandawoo-quick' ) . '</p></section>';
		} else {
			echo '<section><strong>' . esc_html__( 'ContactPanda Basic', 'pandawoo-quick' ) . '</strong><p>' . esc_html__( 'An optional free CRM foundation for customer and contact management. PandaWoo Quick works fully without it.', 'pandawoo-quick' ) . '</p></section>';
		}
		echo '<section><strong>' . esc_html__( 'ContactPanda Plus and Pro', 'pandawoo-quick' ) . '</strong><p>' . esc_html__( 'Optional CRM, communication and automation features for businesses that need more than mobile product capture.', 'pandawoo-quick' ) . '</p></section><section><strong>' . esc_html__( 'PandaWoo multi-shop', 'pandawoo-quick' ) . '</strong><p>' . esc_html__( 'A future upgrade for managing several shops at the same time. Quick Free keeps all product capture features and supports one active shop connection.', 'pandawoo-quick' ) . '</p></section></div><p class="pwq-product-info-note">' . esc_html__( 'No account, tracking or paid extension is required to use PandaWoo Quick.', 'pandawoo-quick' ) . '</p></aside>';
	}

	public static function dismiss_product_info() {
		if ( ! current_user_can( self::capability() ) ) wp_die( esc_html__( 'Permission denied.', 'pandawoo-quick' ), '', array( 'response' => 403 ) );
		check_admin_referer( 'pandawoo_quick_dismiss_product_info' );
		update_user_meta( get_current_user_id(), 'pandawoo_quick_hide_product_info', 1 );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
		exit;
	}

	public static function standalone_app() {
		if ( ! isset( $_GET[ self::APP_QUERY ] ) ) return;
		$device = self::authenticated_device();
		if ( ! $device ) {
			status_header( 401 );
			echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>PandaWoo Quick</title></head><body><main style="font-family:system-ui;max-width:40rem;margin:10vh auto;padding:2rem"><h1>PandaWoo Quick</h1><p>' . esc_html__( 'This phone is not connected. Scan a new QR code in WordPress.', 'pandawoo-quick' ) . '</p></main></body></html>';
			exit;
		}
		self::$standalone = true;
		self::$active_device = $device;
		wp_set_current_user( absint( $device['user_id'] ) );
		self::enqueue_assets();
		$manifest = add_query_arg( 'action', 'pandawoo_quick_manifest', admin_url( 'admin-post.php' ) );
		echo '<!doctype html><html ' . get_language_attributes() . '><head><meta charset="' . esc_attr( get_bloginfo( 'charset' ) ) . '"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><link rel="manifest" href="' . esc_url( $manifest ) . '"><link rel="apple-touch-icon" href="' . esc_url( PANDAWOO_QUICK_URL . 'assets/pandawoo-quick-app-icon-192.png' ) . '"><meta name="theme-color" content="#ffd300"><meta name="apple-mobile-web-app-capable" content="yes"><title>PandaWoo Quick</title>';
		wp_head();
		echo '</head><body class="pwq-body">';
		self::render_shell( false );
		wp_footer();
		echo '</body></html>';
		exit;
	}

	private static function product_data( $product ) {
		$image_ids = array_values( array_filter( array_merge( array( $product->get_image_id() ), $product->get_gallery_image_ids() ) ) );
		$images = array();
		foreach ( $image_ids as $id ) $images[] = array( 'id' => absint( $id ), 'url' => wp_get_attachment_image_url( $id, 'medium' ) ?: wp_get_attachment_url( $id ) );
		$status = $product->get_status();
		$publish_at = 'future' === $status ? mysql2date( 'Y-m-d\TH:i', get_post_field( 'post_date', $product->get_id() ), false ) : '';
		$brand_ids = taxonomy_exists( 'product_brand' ) ? wp_get_object_terms( $product->get_id(), 'product_brand', array( 'fields' => 'ids' ) ) : array();
		if ( is_wp_error( $brand_ids ) ) $brand_ids = array();
		return array( 'id' => $product->get_id(), 'name' => $product->get_name(), 'price' => $product->get_regular_price(), 'shortDescription' => $product->get_short_description(), 'description' => $product->get_description(), 'categoryId' => (string) ( $product->get_category_ids()[0] ?? '' ), 'brandId' => (string) ( $brand_ids[0] ?? '' ), 'status' => $status, 'publishAt' => $publish_at, 'images' => $images, 'editUrl' => get_edit_post_link( $product->get_id(), 'raw' ) );
	}

	public static function ajax_list() {
		self::require_access();
		$page = max( 1, absint( $_REQUEST['page'] ?? 1 ) );
		$args = array( 'limit' => 20, 'page' => $page, 'paginate' => true, 'orderby' => 'date', 'order' => 'DESC', 'return' => 'objects', 'type' => 'simple', 'status' => array( 'publish', 'draft', 'future', 'pending', 'private' ) );
		$search = sanitize_text_field( wp_unslash( $_REQUEST['search'] ?? '' ) );
		$status = sanitize_key( $_REQUEST['status'] ?? '' );
		if ( $search ) $args['s'] = $search;
		if ( in_array( $status, array( 'publish', 'draft', 'future' ), true ) ) $args['status'] = $status;
		$result = wc_get_products( $args );
		$items = array();
		foreach ( $result->products as $product ) $items[] = self::product_data( $product );
		wp_send_json_success( array( 'shop' => self::shop_context(), 'products' => $items, 'page' => $page, 'pages' => absint( $result->max_num_pages ) ) );
	}

	public static function ajax_get() {
		self::require_access();
		$product = wc_get_product( absint( $_REQUEST['product_id'] ?? 0 ) );
		if ( ! $product || ! is_a( $product, 'WC_Product_Simple' ) || ! current_user_can( 'edit_post', $product->get_id() ) ) wp_send_json_error( array( 'message' => __( 'Product not found.', 'pandawoo-quick' ) ), 404 );
		wp_send_json_success( array( 'shop' => self::shop_context(), 'product' => self::product_data( $product ) ) );
	}

	private static function request_files() {
		if ( empty( $_FILES['new_images']['name'] ) || ! is_array( $_FILES['new_images']['name'] ) ) return array();
		$source = $_FILES['new_images'];
		$files = array();
		foreach ( $source['name'] as $key => $name ) if ( '' !== (string) $name ) $files[ sanitize_key( $key ) ] = array( 'name' => sanitize_file_name( $name ), 'type' => $source['type'][ $key] ?? '', 'tmp_name' => $source['tmp_name'][ $key ] ?? '', 'error' => $source['error'][ $key ] ?? UPLOAD_ERR_NO_FILE, 'size' => absint( $source['size'][ $key ] ?? 0 ) );
		return array_slice( $files, 0, self::MAX_IMAGES, true );
	}

	private static function upload_files( $files, $title ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$uploaded = array();
		foreach ( $files as $key => $file ) {
			if ( UPLOAD_ERR_OK !== (int) $file['error'] ) { self::delete_attachments( $uploaded ); return new WP_Error( 'upload', __( 'At least one photo could not be uploaded.', 'pandawoo-quick' ) ); }
			$check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
			if ( empty( $check['type'] ) || ! in_array( $check['type'], array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) { self::delete_attachments( $uploaded ); return new WP_Error( 'type', __( 'Only JPEG, PNG and WebP images are allowed.', 'pandawoo-quick' ) ); }
			$_FILES['pandawoo_quick_image'] = $file;
			$id = media_handle_upload( 'pandawoo_quick_image', 0, array( 'post_title' => $title ) );
			unset( $_FILES['pandawoo_quick_image'] );
			if ( is_wp_error( $id ) ) { self::delete_attachments( $uploaded ); return $id; }
			$uploaded[ $key ] = absint( $id );
		}
		return $uploaded;
	}

	private static function delete_attachments( $attachments ) {
		foreach ( (array) $attachments as $attachment_id ) wp_delete_attachment( absint( $attachment_id ), true );
	}

	public static function ajax_save() {
		self::require_access();
		$id = absint( $_POST['product_id'] ?? 0 );
		$is_new = ! $id;
		$product = $id ? wc_get_product( $id ) : new WC_Product_Simple();
		if ( ! $product || ! is_a( $product, 'WC_Product_Simple' ) || ( $id && ! current_user_can( 'edit_post', $id ) ) ) wp_send_json_error( array( 'message' => __( 'Product not found or not editable.', 'pandawoo-quick' ) ), 404 );
		$name = sanitize_text_field( wp_unslash( $_POST['product_name'] ?? '' ) );
		$price = wc_format_decimal( wp_unslash( $_POST['regular_price'] ?? '' ) );
		$category = absint( $_POST['category_id'] ?? 0 );
		$brand = taxonomy_exists( 'product_brand' ) ? absint( $_POST['brand_id'] ?? 0 ) : 0;
		$mode = sanitize_key( $_POST['publish_mode'] ?? 'draft' );
		if ( ! $name || '' === $price || ! is_numeric( $price ) || (float) $price < 0 || ! term_exists( $category, 'product_cat' ) ) wp_send_json_error( array( 'message' => __( 'Please complete all required product fields.', 'pandawoo-quick' ) ), 422 );
		if ( $brand && ! term_exists( $brand, 'product_brand' ) ) wp_send_json_error( array( 'message' => __( 'Please select a valid brand.', 'pandawoo-quick' ) ), 422 );
		if ( ! in_array( $mode, array( 'publish', 'future', 'draft' ), true ) ) $mode = 'draft';
		$scheduled_date = null;
		if ( 'future' === $mode ) {
			$scheduled_date = DateTimeImmutable::createFromFormat( 'Y-m-d\TH:i', sanitize_text_field( wp_unslash( $_POST['publish_at'] ?? '' ) ), wp_timezone() );
			if ( ! $scheduled_date || $scheduled_date->getTimestamp() <= time() + 60 ) wp_send_json_error( array( 'message' => __( 'Please choose a future publication time.', 'pandawoo-quick' ) ), 422 );
		}
		$existing_ids = array_values( array_filter( array_map( 'absint', (array) ( $_POST['existing_images'] ?? array() ) ) ) );
		$allowed_existing = $id ? array_values( array_filter( array_merge( array( $product->get_image_id() ), $product->get_gallery_image_ids() ) ) ) : array();
		foreach ( $existing_ids as $attachment_id ) if ( ! in_array( $attachment_id, $allowed_existing, true ) || 'attachment' !== get_post_type( $attachment_id ) || ! wp_attachment_is_image( $attachment_id ) ) wp_send_json_error( array( 'message' => __( 'An image selection is invalid.', 'pandawoo-quick' ) ), 422 );
		$uploaded = array();
		try {
			$new_files = self::request_files();
			$uploaded = self::upload_files( $new_files, $name );
			if ( is_wp_error( $uploaded ) ) {
				self::record_error( 'upload', $uploaded->get_error_message() );
				wp_send_json_error( array( 'message' => $uploaded->get_error_message() ), 422 );
			}
			$order = array_map( 'sanitize_text_field', (array) ( $_POST['image_order'] ?? array() ) );
			$images = array();
			foreach ( $order as $token ) {
				if ( 0 === strpos( $token, 'existing:' ) ) { $candidate = absint( substr( $token, 9 ) ); if ( in_array( $candidate, $existing_ids, true ) ) $images[] = $candidate; }
				if ( 0 === strpos( $token, 'new:' ) ) { $key = sanitize_key( substr( $token, 4 ) ); if ( isset( $uploaded[ $key ] ) ) $images[] = $uploaded[ $key ]; }
			}
			$images = array_values( array_unique( array_slice( $images, 0, self::MAX_IMAGES ) ) );
			if ( ! $images ) { self::delete_attachments( $uploaded ); wp_send_json_error( array( 'message' => __( 'Please add at least one product photo.', 'pandawoo-quick' ) ), 422 ); }
			$product->set_name( $name );
			$product->set_regular_price( (string) $price );
			$product->set_short_description( wp_kses_post( wp_unslash( $_POST['short_description'] ?? '' ) ) );
			$product->set_description( wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) ) );
			$product->set_category_ids( array( $category ) );
			$product->set_image_id( array_shift( $images ) );
			$product->set_gallery_image_ids( $images );
			$product->set_status( 'publish' === $mode ? 'publish' : 'draft' );
			$id = $product->save();
			if ( ! $id ) throw new RuntimeException( __( 'WooCommerce could not save the product.', 'pandawoo-quick' ) );
			if ( taxonomy_exists( 'product_brand' ) ) {
				$brand_result = wp_set_object_terms( $id, $brand ? array( $brand ) : array(), 'product_brand', false );
				if ( is_wp_error( $brand_result ) ) throw new RuntimeException( $brand_result->get_error_message() );
			}
			$all_images = array_merge( array( $product->get_image_id() ), $images );
			foreach ( $uploaded as $attachment_id ) wp_update_post( array( 'ID' => $attachment_id, 'post_parent' => $id ) );
			if ( 'future' === $mode ) {
				$local = $scheduled_date->format( 'Y-m-d H:i:s' );
				wp_update_post( array( 'ID' => $id, 'post_status' => 'future', 'post_date' => $local, 'post_date_gmt' => get_gmt_from_date( $local ) ) );
			}
			wp_send_json_success( array( 'shop' => self::shop_context(), 'message' => $is_new ? __( 'The product has been created.', 'pandawoo-quick' ) : __( 'The product has been saved.', 'pandawoo-quick' ), 'product' => self::product_data( wc_get_product( $id ) ) ) );
		} catch ( Throwable $error ) {
			if ( $is_new && $id ) wp_delete_post( $id, true );
			self::delete_attachments( $uploaded );
			self::record_error( 'save', $error->getMessage() );
			wp_send_json_error( array( 'message' => $error->getMessage() ), 422 );
		}
	}

	public static function ajax_client_error() {
		self::require_access();
		$operation = sanitize_key( wp_unslash( $_POST['operation'] ?? 'unknown' ) );
		$status = absint( $_POST['status'] ?? 0 );
		self::record_error( 'response_' . $operation, 'HTTP ' . $status . ' / JSON' );
		wp_send_json_success();
	}

	public static function ajax_disconnect() {
		self::require_access();
		$device = self::authenticated_device();
		if ( ! $device || empty( $device['id'] ) ) wp_send_json_error( array( 'message' => __( 'No connected shop session was found.', 'pandawoo-quick' ) ), 404 );
		$records = self::device_records();
		unset( $records[ $device['id'] ] );
		update_option( self::DEVICE_OPTION, $records, false );
		setcookie( self::DEVICE_COOKIE, '', array( 'expires' => time() - HOUR_IN_SECONDS, 'path' => '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
		wp_send_json_success( array( 'message' => __( 'The shop has been disconnected. You can now pair this device with another shop.', 'pandawoo-quick' ) ) );
	}

	public static function manifest() {
		if ( ! current_user_can( self::capability() ) && ! self::authenticated_device() ) { status_header( 403 ); exit; }
		nocache_headers();
		header( 'Content-Type: application/manifest+json; charset=utf-8' );
		echo wp_json_encode( array( 'name' => 'PandaWoo Quick', 'short_name' => 'PandaWoo', 'start_url' => self::app_url(), 'scope' => home_url( '/' ), 'display' => 'standalone', 'background_color' => '#fffdf7', 'theme_color' => '#ffd300', 'icons' => array( array( 'src' => PANDAWOO_QUICK_URL . 'assets/pandawoo-quick-app-icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable' ), array( 'src' => PANDAWOO_QUICK_URL . 'assets/pandawoo-quick-app-icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable' ) ) ) );
		exit;
	}

	public static function service_worker() {
		if ( ! current_user_can( self::capability() ) && ! self::authenticated_device() ) { status_header( 403 ); exit; }
		nocache_headers();
		header( 'Content-Type: application/javascript; charset=utf-8' );
		header( 'Service-Worker-Allowed: ' . ( wp_parse_url( home_url( '/' ), PHP_URL_PATH ) ?: '/' ) );
		echo "self.addEventListener('install',function(){self.skipWaiting()});self.addEventListener('activate',function(e){e.waitUntil(self.clients.claim())});self.addEventListener('fetch',function(){});";
		exit;
	}
}

