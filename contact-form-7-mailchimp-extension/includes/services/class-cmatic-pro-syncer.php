<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

class Cmatic_Pro_Syncer {

	public const PRO_PLUGIN_FILE = 'chimpmatic/chimpmatic.php';

	public const RELEASE_OPTION = 'cmatic_pro_release';

	public const STATE_OPTION = 'cmatic_pro_current_state';

	private const RELEASE_TTL = 43200;

	private const RELEASE_FLOOR = 3600;

	private const TRIGGER_DELAY = 60;

	private const TRIGGER_FLOOR = 3600;

	private const LEGACY_HOOKS = array( 'cmatic_lite_pro_recovery_sync', 'cmatic_lite_pro_recovery_retry' );

	private const LEGACY_OPTIONS = array(
		'cmatic_lite_pro_recovery_lock',
		'cmatic_lite_pro_recovery_state',
		'cmatic_lite_pro_recovery_last',
		'cmatic_lite_pro_recovery_quarantine',
	);

	public static function init(): void {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'offer' ) );
		add_filter( 'auto_update_plugin', array( __CLASS__, 'decide' ), 10, 2 );
		add_action( 'set_site_transient_update_plugins', array( __CLASS__, 'schedule' ) );
		add_action( 'init', array( __CLASS__, 'retire_legacy' ), 20 );
	}

	public static function deactivate(): void {
		foreach ( self::LEGACY_HOOKS as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
		delete_site_option( 'cmatic_lite_pro_recovery_lock' );
	}

	public static function retire_legacy(): void {
		foreach ( self::LEGACY_HOOKS as $hook ) {
			if ( false !== wp_next_scheduled( $hook ) ) {
				wp_clear_scheduled_hook( $hook );
			}
		}
		foreach ( self::LEGACY_OPTIONS as $option ) {
			if ( false !== get_site_option( $option, false ) ) {
				delete_site_option( $option );
			}
		}
	}

	/**
	 * @param mixed $transient Update list WordPress is about to save.
	 * @return mixed
	 */
	public static function offer( $transient ) {
		if ( ! is_object( $transient ) || ! empty( $transient->response[ self::PRO_PLUGIN_FILE ] ) ) {
			return $transient;
		}
		$installed = self::installed_version();
		if ( '0' === $installed ) {
			return $transient;
		}
		$release = self::release( $installed );
		if ( null === $release || ! $release->force_upgrade || version_compare( $release->new_version, $installed, '==' ) ) {
			return $transient;
		}
		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = array();
		}
		$transient->response[ self::PRO_PLUGIN_FILE ] = (object) array(
			'id'           => self::PRO_PLUGIN_FILE,
			'slug'         => 'chimpmatic',
			'plugin'       => self::PRO_PLUGIN_FILE,
			'new_version'  => $release->new_version,
			'url'          => 'https://chimpmatic.com/pro',
			'package'      => $release->package,
			'tested'       => $release->tested,
			'requires_php' => '7.4',
		);
		if ( isset( $transient->no_update ) && is_array( $transient->no_update ) ) {
			unset( $transient->no_update[ self::PRO_PLUGIN_FILE ] );
		}
		return $transient;
	}

	/**
	 * @param mixed $update Decision WordPress reached so far.
	 * @param mixed $item   Plugin row from the update list.
	 * @return mixed
	 */
	public static function decide( $update, $item ) {
		if ( ! is_object( $item ) ) {
			return $update;
		}
		$plugin = isset( $item->plugin ) && is_string( $item->plugin ) ? $item->plugin : '';
		if ( '' === $plugin && isset( $item->slug ) && 'chimpmatic' === $item->slug ) {
			$plugin = self::PRO_PLUGIN_FILE;
		}
		if ( self::PRO_PLUGIN_FILE !== $plugin ) {
			return $update;
		}
		$release = self::cached_release();
		return null !== $release && $release->force_upgrade ? true : $update;
	}

	/**
	 * @param mixed $transient Update list WordPress just saved.
	 */
	public static function schedule( $transient ): void {
		if ( wp_installing() || doing_action( 'wp_maybe_auto_update' ) ) {
			return;
		}
		if ( ! is_object( $transient ) || empty( $transient->response[ self::PRO_PLUGIN_FILE ] ) ) {
			return;
		}
		$row = $transient->response[ self::PRO_PLUGIN_FILE ];
		if ( true !== self::decide( false, $row ) ) {
			return;
		}
		$offered      = is_object( $row ) && isset( $row->new_version ) ? self::version( (string) $row->new_version ) : '0';
		$state        = get_site_option( self::STATE_OPTION, array() );
		$state        = is_array( $state ) ? $state : array();
		$triggered_at = isset( $state['triggered_at'] ) && is_numeric( $state['triggered_at'] ) ? (int) $state['triggered_at'] : 0;
		if ( ( $state['offered'] ?? '' ) === $offered && ( time() - $triggered_at ) < self::TRIGGER_FLOOR ) {
			return;
		}
		$next = wp_next_scheduled( 'wp_maybe_auto_update' );
		if ( false === $next || $next > time() + self::TRIGGER_DELAY ) {
			wp_schedule_single_event( time() + self::TRIGGER_DELAY, 'wp_maybe_auto_update' );
		}
		update_site_option(
			self::STATE_OPTION,
			array(
				'offered'      => $offered,
				'triggered_at' => time(),
			)
		);
	}

	public static function sync_license_instance(): bool {
		$activation = get_option( 'chimpmatic_license_activation' );
		if ( is_string( $activation ) ) {
			$activation = maybe_unserialize( $activation );
		}
		if ( ! is_array( $activation ) || empty( $activation['instance_id'] ) || ! is_scalar( $activation['instance_id'] ) ) {
			return false;
		}

		$activation_instance = substr( (string) $activation['instance_id'], 0, 128 );
		if ( get_option( 'cmatic_license_instance' ) === $activation_instance ) {
			return false;
		}

		update_option( 'cmatic_license_instance', $activation_instance );
		delete_site_transient( 'update_plugins' );
		return true;
	}

	/**
	 * @return object{new_version: string, package: string, slug: string, plugin: string, force_upgrade: bool, tested: string}|false
	 */
	public static function query_sync_api( string $current_version ) {
		$activation = get_option( 'chimpmatic_license_activation' );
		if ( is_string( $activation ) ) {
			$activation = maybe_unserialize( $activation );
		}
		$activation = is_array( $activation ) ? $activation : array();
		$api_key    = isset( $activation['license_key'] ) && is_scalar( $activation['license_key'] )
			? substr( (string) $activation['license_key'], 0, 256 )
			: 'unlicensed';
		$instance   = isset( $activation['instance_id'] ) && is_scalar( $activation['instance_id'] )
			? substr( (string) $activation['instance_id'], 0, 128 )
			: substr( hash_hmac( 'sha256', home_url( '/' ), wp_salt( 'auth' ) ), 0, 32 );
		$product_id = isset( $activation['product_id'] ) && is_numeric( $activation['product_id'] )
			? max( 1, (int) $activation['product_id'] )
			: 436;
		$host       = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		$response   = wp_safe_remote_post(
			'https://chimpmatic.com/?wc-api=wc-am-api',
			array(
				'body'        => array(
					'wc_am_action'     => 'update',
					'slug'             => 'chimpmatic',
					'plugin_name'      => self::PRO_PLUGIN_FILE,
					'version'          => self::version( $current_version ),
					'product_id'       => $product_id,
					'api_key'          => '' === $api_key ? 'unlicensed' : $api_key,
					'instance'         => $instance,
					'object'           => substr( $host, 0, 253 ),
					'software_version' => self::version( $current_version ),
				),
				'redirection' => 0,
				'timeout'     => 20,
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}
		$data     = json_decode( wp_remote_retrieve_body( $response ), true );
		$package  = is_array( $data ) && ! empty( $data['success'] ) && isset( $data['data']['package'] ) && is_array( $data['data']['package'] )
			? $data['data']['package']
			: array();
		$target   = isset( $package['new_version'] ) && is_scalar( $package['new_version'] ) ? self::version( (string) $package['new_version'] ) : '0';
		$url      = isset( $package['package'] ) && is_scalar( $package['package'] ) ? (string) $package['package'] : '';
		$slug     = isset( $package['slug'] ) && is_scalar( $package['slug'] ) ? (string) $package['slug'] : 'chimpmatic';
		$plugin   = isset( $package['plugin'] ) && is_scalar( $package['plugin'] ) ? (string) $package['plugin'] : self::PRO_PLUGIN_FILE;
		$tested   = isset( $package['tested'] ) && is_scalar( $package['tested'] ) ? (string) $package['tested'] : '';
		$force    = self::force_enabled( $package['force_upgrade'] ?? false );
		$url_host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		if (
			'0' === $target
			|| '' === $url
			|| 'https' !== wp_parse_url( $url, PHP_URL_SCHEME )
			|| ! in_array( $url_host, array( 'chimpmatic.com', 'www.chimpmatic.com' ), true )
			|| 'chimpmatic' !== $slug
			|| self::PRO_PLUGIN_FILE !== $plugin
		) {
			return false;
		}
		return (object) array(
			'new_version'   => $target,
			'package'       => $url,
			'slug'          => $slug,
			'plugin'        => $plugin,
			'force_upgrade' => $force,
			'tested'        => $tested,
		);
	}

	/**
	 * @return object{new_version: string, package: string, force_upgrade: bool, tested: string}|null
	 */
	private static function release( string $installed ): ?object {
		$cached = self::cached_release();
		$stored = get_site_option( self::RELEASE_OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$now    = time();
		if ( null !== $cached && $now < (int) ( $stored['expires_at'] ?? 0 ) ) {
			return $cached;
		}
		if ( ( $now - (int) ( $stored['attempted_at'] ?? 0 ) ) < self::RELEASE_FLOOR ) {
			return $cached;
		}
		$stored['attempted_at'] = $now;
		update_site_option( self::RELEASE_OPTION, $stored );
		self::sync_license_instance();
		$answer = self::query_sync_api( $installed );
		if ( false === $answer ) {
			return $cached;
		}
		update_site_option(
			self::RELEASE_OPTION,
			array(
				'new_version'   => $answer->new_version,
				'package'       => $answer->package,
				'force_upgrade' => $answer->force_upgrade,
				'tested'        => $answer->tested,
				'attempted_at'  => $now,
				'expires_at'    => $now + self::RELEASE_TTL,
			)
		);
		return self::cached_release();
	}

	/**
	 * @return object{new_version: string, package: string, force_upgrade: bool, tested: string}|null
	 */
	private static function cached_release(): ?object {
		$stored = get_site_option( self::RELEASE_OPTION, array() );
		if ( ! is_array( $stored ) || empty( $stored['new_version'] ) || empty( $stored['package'] ) ) {
			return null;
		}
		return (object) array(
			'new_version'   => self::version( (string) $stored['new_version'] ),
			'package'       => (string) $stored['package'],
			'force_upgrade' => ! empty( $stored['force_upgrade'] ),
			'tested'        => isset( $stored['tested'] ) && is_scalar( $stored['tested'] ) ? (string) $stored['tested'] : '',
		);
	}

	/**
	 * @param mixed $value Store flag in any of its accepted spellings.
	 */
	private static function force_enabled( $value ): bool {
		return true === $value || 1 === $value || '1' === $value || 'true' === $value;
	}

	private static function installed_version(): string {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$path = WP_PLUGIN_DIR . '/' . self::PRO_PLUGIN_FILE;
		if ( ! is_readable( $path ) ) {
			return '0';
		}
		$data = get_plugin_data( $path, false, false );
		return self::version( isset( $data['Version'] ) && is_scalar( $data['Version'] ) ? (string) $data['Version'] : '0' );
	}

	private static function version( string $value ): string {
		return preg_match( '/\A[0-9A-Za-z._+-]{1,20}\z/', $value ) ? $value : '0';
	}
}
