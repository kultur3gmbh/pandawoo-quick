<?php

defined( 'ABSPATH' ) || exit;

/**
 * Token-free updater for public GitHub releases.
 */
final class PandaWoo_Quick_Updater {
	const OWNER = 'kultur3gmbh';
	const REPOSITORY = 'pandawoo-quick';
	const SLUG = 'pandawoo-quick';
	const SUCCESS_TTL = 21600; // Six hours.
	const FAILURE_TTL = 900; // Fifteen minutes.

	public static function boot() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'check_updates' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_information' ), 20, 3 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'after_upgrade' ), 10, 2 );
	}

	private static function plugin_file() {
		return plugin_basename( PANDAWOO_QUICK_PATH . 'pandawoo-quick.php' );
	}

	private static function cache_key() {
		return 'pandawoo_quick_release_' . md5( PANDAWOO_QUICK_VERSION . '|' . self::OWNER . '/' . self::REPOSITORY );
	}

	private static function force_refresh() {
		return is_admin() && current_user_can( 'update_plugins' ) && isset( $_GET['force-check'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only cache bypass used by WordPress update screen.
	}

	private static function release() {
		$key = self::cache_key();
		if ( self::force_refresh() ) delete_site_transient( $key );
		$cached = get_site_transient( $key );
		if ( is_array( $cached ) && isset( $cached['available'] ) ) return $cached['available'] ? $cached : false;

		$url = sprintf( 'https://api.github.com/repos/%s/%s/releases/latest', rawurlencode( self::OWNER ), rawurlencode( self::REPOSITORY ) );
		$response = wp_safe_remote_get( $url, array(
			'timeout' => 8,
			'redirection' => 3,
			'headers' => array( 'Accept' => 'application/vnd.github+json', 'X-GitHub-Api-Version' => '2022-11-28' ),
			'user-agent' => 'PandaWoo-Quick/' . PANDAWOO_QUICK_VERSION,
		) );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			set_site_transient( $key, array( 'available' => false ), self::FAILURE_TTL );
			return false;
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$version = isset( $data['tag_name'] ) ? ltrim( sanitize_text_field( $data['tag_name'] ), "vV \t\n\r\0\x0B" ) : '';
		if ( ! preg_match( '/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version ) || ! empty( $data['draft'] ) || ! empty( $data['prerelease'] ) ) {
			set_site_transient( $key, array( 'available' => false ), self::FAILURE_TTL );
			return false;
		}
		$expected_asset = 'pandawoo-quick-' . $version . '.zip';
		$package = '';
		foreach ( (array) ( $data['assets'] ?? array() ) as $asset ) {
			if ( $expected_asset === ( $asset['name'] ?? '' ) ) {
				$package = esc_url_raw( $asset['browser_download_url'] ?? '' );
				break;
			}
		}
		$host = strtolower( (string) wp_parse_url( $package, PHP_URL_HOST ) );
		if ( ! $package || 'github.com' !== $host ) {
			set_site_transient( $key, array( 'available' => false ), self::FAILURE_TTL );
			return false;
		}
		$release = array(
			'available' => true,
			'version' => $version,
			'package' => $package,
			'url' => esc_url_raw( $data['html_url'] ?? 'https://github.com/' . self::OWNER . '/' . self::REPOSITORY ),
			'published_at' => sanitize_text_field( $data['published_at'] ?? '' ),
			'body' => sanitize_textarea_field( $data['body'] ?? '' ),
		);
		set_site_transient( $key, $release, self::SUCCESS_TTL );
		return $release;
	}

	public static function check_updates( $transient ) {
		if ( ! is_object( $transient ) ) return $transient;
		$release = self::release();
		if ( ! $release || version_compare( $release['version'], PANDAWOO_QUICK_VERSION, '<=' ) ) return $transient;
		$item = (object) array(
			'id' => 'https://github.com/' . self::OWNER . '/' . self::REPOSITORY,
			'slug' => self::SLUG,
			'plugin' => self::plugin_file(),
			'new_version' => $release['version'],
			'url' => $release['url'],
			'package' => $release['package'],
			'requires_php' => '7.4',
		);
		$transient->response[ self::plugin_file() ] = $item;
		return $transient;
	}

	public static function plugin_information( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || self::SLUG !== $args->slug ) return $result;
		$release = self::release();
		if ( ! $release ) return $result;
		return (object) array(
			'name' => 'PandaWoo Quick',
			'slug' => self::SLUG,
			'version' => $release['version'],
			'author' => '<a href="https://github.com/' . esc_attr( self::OWNER ) . '">Kultur³ GmbH</a>',
			'homepage' => 'https://github.com/' . self::OWNER . '/' . self::REPOSITORY,
			'requires' => '6.4',
			'requires_php' => '7.4',
			'download_link' => $release['package'],
			'last_updated' => $release['published_at'],
			'sections' => array(
				'description' => esc_html__( 'Mobile-first WooCommerce product capture through a securely paired installable web app.', 'pandawoo-quick' ),
				'changelog' => wpautop( esc_html( $release['body'] ?: sprintf( __( 'Version %s', 'pandawoo-quick' ), $release['version'] ) ) ),
			),
		);
	}

	public static function after_upgrade( $upgrader, $options ) {
		if ( 'plugin' !== ( $options['type'] ?? '' ) ) return;
		$plugins = (array) ( $options['plugins'] ?? array() );
		if ( ! $plugins && ! empty( $options['plugin'] ) ) $plugins[] = $options['plugin'];
		if ( in_array( self::plugin_file(), $plugins, true ) ) delete_site_transient( self::cache_key() );
	}
}

