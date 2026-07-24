<?php
/**
 * PRO plugin syncer.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

class Cmatic_Pro_Syncer {

	public const PRO_PLUGIN_FILE = 'chimpmatic/chimpmatic.php';

	public const CRON_HOOK = 'cmatic_lite_pro_recovery_sync';

	public const RETRY_HOOK = 'cmatic_lite_pro_recovery_retry';

	public const LOCK_OPTION = 'cmatic_lite_pro_recovery_lock';

	public const STATE_OPTION = 'cmatic_lite_pro_recovery_state';

	public const OUTCOME_OPTION = 'cmatic_lite_pro_recovery_last';

	public const QUARANTINE = 'cmatic_lite_pro_recovery_quarantine';

	public const HEALTH_PREFIX = 'cmatic_lite_pro_health_';

	private const SOURCE_CEILING = '1.7.0';

	private const SUCCESS_THROTTLE = 21600;

	private const LOCK_TTL = 900;

	private const HEALTH_TTL = 120;

	public static function init(): void {
		add_action( self::CRON_HOOK, array( __CLASS__, 'run_scheduled' ) );
		add_action( self::RETRY_HOOK, array( __CLASS__, 'run_retry' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_sync' ), 999 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_health_route' ) );
		add_action(
			'upgrader_process_complete',
			static function ( $upgrader, $options ): void {
				unset( $upgrader );
				if ( ! is_array( $options ) || 'plugin' !== ( $options['type'] ?? '' ) || 'update' !== ( $options['action'] ?? '' ) ) {
					return;
				}
				$plugins = isset( $options['plugins'] ) ? (array) $options['plugins'] : array();
				if ( isset( $options['plugin'] ) && is_scalar( $options['plugin'] ) ) {
					$plugins[] = (string) $options['plugin'];
				}
				if ( in_array( self::PRO_PLUGIN_FILE, $plugins, true ) ) {
					self::schedule_soon();
				}
			},
			20,
			2
		);

		self::recover_pending();
	}

	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + ( 5 * MINUTE_IN_SECONDS ), 'twicedaily', self::CRON_HOOK );
		}
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		wp_clear_scheduled_hook( self::RETRY_HOOK );
		delete_site_option( self::LOCK_OPTION );
	}

	public static function maybe_sync(): void {
		if ( ! is_admin() || ! current_user_can( 'update_plugins' ) || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		self::synchronize();
	}

	public static function run_scheduled(): void {
		self::synchronize();
	}

	public static function run_retry(): void {
		self::synchronize();
	}

	public static function register_health_route(): void {
		register_rest_route(
			'chimpmatic-lite/v1',
			'/pro-recovery-health',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'callback'            => array( __CLASS__, 'handle_health' ),
			)
		);
	}

	public static function handle_health( WP_REST_Request $request ): WP_REST_Response {
		$body     = $request->get_json_params();
		$body     = is_array( $body ) ? $body : array();
		$id       = isset( $body['id'] ) && is_string( $body['id'] ) ? $body['id'] : '';
		$token    = isset( $body['token'] ) && is_string( $body['token'] ) ? $body['token'] : '';
		$expected = isset( $body['expected'] ) && is_string( $body['expected'] ) ? self::version( $body['expected'] ) : '0';
		if ( ! preg_match( '/\A[a-f0-9]{16}\z/', $id ) || '' === $token ) {
			return new WP_REST_Response(
				array(
					'healthy' => false,
					'reason'  => 'token',
				),
				403
			);
		}

		$key    = self::HEALTH_PREFIX . $id;
		$stored = get_site_transient( $key );
		if (
			! is_array( $stored )
			|| ! isset( $stored['hash'], $stored['target'], $stored['require_active'] )
			|| ! is_string( $stored['hash'] )
			|| ! is_string( $stored['target'] )
		) {
			delete_site_transient( $key );
			return new WP_REST_Response(
				array(
					'healthy' => false,
					'reason'  => 'token',
				),
				403
			);
		}

		delete_site_transient( $key );
		if ( ! hash_equals( $stored['hash'], hash( 'sha256', $token ) ) || ! hash_equals( $stored['target'], $expected ) ) {
			return new WP_REST_Response(
				array(
					'healthy' => false,
					'reason'  => 'token',
				),
				403
			);
		}

		$reason = self::readiness_reason( $expected, (bool) $stored['require_active'] );
		return new WP_REST_Response(
			array(
				'healthy' => 'ok' === $reason,
				'reason'  => $reason,
			),
			200
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
		delete_option( '_site_transient_update_plugins' );
		delete_site_transient( 'update_plugins' );
		return true;
	}

	/**
	 * @return false|object{new_version: string, package: string, slug: string, plugin: string, force_upgrade: bool}
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

		$response = wp_safe_remote_post(
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
		);
	}

	private static function synchronize(): void {
		$current = self::installed_version();
		if ( '0' === $current || version_compare( $current, self::SOURCE_CEILING, '>=' ) ) {
			return;
		}
		if ( self::environment_blocked() ) {
			self::record_deferred( '', $current, 'environment_blocked' );
			return;
		}

		$state = self::state();
		$now   = time();
		if ( self::to_int( $state['next_retry'] ?? 0 ) > $now ) {
			return;
		}
		if (
			'success' === ( $state['stage'] ?? '' )
			&& $current === ( $state['installed'] ?? '' )
			&& ( $now - self::to_int( $state['last_success'] ?? 0 ) ) < self::SUCCESS_THROTTLE
		) {
			return;
		}
		if ( ! self::acquire_lock() ) {
			return;
		}

		try {
			$activation = self::activation_snapshot();
			self::write_state(
				array_merge(
					$activation,
					array(
						'prior_version' => $current,
						'installed'     => $current,
						'stage'         => 'metadata',
						'reason'        => 'pending',
						'started_at'    => time(),
						'last_attempt'  => time(),
					)
				)
			);
			self::sync_license_instance();
			$sync_info = self::query_sync_api( $current );
			if ( false === $sync_info ) {
				self::record_deferred( '', $current, 'metadata_unavailable', $activation );
				return;
			}
			if ( ! $sync_info->force_upgrade ) {
				self::record_deferred( $sync_info->new_version, $current, 'policy_disabled', $activation, 43200 );
				return;
			}
			if ( version_compare( $sync_info->new_version, $current, '<=' ) ) {
				self::record_success( $sync_info->new_version, $current, $activation );
				return;
			}
			if ( self::is_quarantined( $sync_info->new_version ) ) {
				self::record_terminal( $sync_info->new_version, $current, 'quarantined', 'quarantined', $activation );
				return;
			}

			self::perform_sync( $sync_info, $current, $activation );
		} finally {
			delete_site_option( self::LOCK_OPTION );
		}
	}

	/**
	 * @param object{new_version: string, package: string, slug: string, plugin: string, force_upgrade: bool} $sync_info Update information.
	 * @param array<string, mixed> $activation Activation snapshot.
	 */
	private static function perform_sync( $sync_info, string $prior, array $activation ): void {
		$target = self::version( (string) $sync_info->new_version );
		self::write_state(
			array_merge(
				$activation,
				array(
					'target'        => $target,
					'prior_version' => $prior,
					'installed'     => $prior,
					'stage'         => 'upgrading',
					'reason'        => 'pending',
					'started_at'    => time(),
					'last_attempt'  => time(),
				)
			)
		);

		$stored_updates                    = get_site_transient( 'update_plugins' );
		$response                          = is_object( $stored_updates ) && isset( $stored_updates->response ) && is_array( $stored_updates->response ) ? $stored_updates->response : array();
		$response[ self::PRO_PLUGIN_FILE ] = (object) array(
			'slug'        => 'chimpmatic',
			'plugin'      => self::PRO_PLUGIN_FILE,
			'new_version' => $target,
			'package'     => (string) $sync_info->package,
		);
		$updates                           = $stored_updates instanceof stdClass ? clone $stored_updates : new stdClass();
		$updates->response                 = $response;
		set_site_transient( 'update_plugins', $updates );

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php';

		$upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
		try {
			$result = $upgrader->upgrade( self::PRO_PLUGIN_FILE );
		} catch ( Throwable $throwable ) {
			unset( $throwable );
			$result = new WP_Error( 'cmatic_lite_pro_upgrade_exception' );
		} finally {
			if ( false === $stored_updates ) {
				delete_site_transient( 'update_plugins' );
			} else {
				set_site_transient( 'update_plugins', $stored_updates );
			}
		}

		if ( true !== $result ) {
			self::remove_backup_shutdown_hooks( $upgrader );
			if ( self::backup_exists() ) {
				self::rollback_and_record( $upgrader, $target, $prior, 'upgrade_failed', false, $activation );
				return;
			}
			$restored = self::restore_activation( $activation );
			if ( $restored && $prior === self::installed_version() ) {
				self::record_deferred( $target, $prior, 'upgrade_failed', $activation );
				return;
			}
			self::record_terminal( $target, self::installed_version(), 'rollback_failed', 'rollback_failed', $activation );
			return;
		}

		if ( ! self::restore_activation( $activation ) ) {
			self::remove_backup_shutdown_hooks( $upgrader );
			self::rollback_and_record( $upgrader, $target, $prior, 'activation_restore_failed', true, $activation );
			return;
		}
		$actual = self::installed_version();
		if ( $target !== $actual ) {
			self::remove_backup_shutdown_hooks( $upgrader );
			self::rollback_and_record( $upgrader, $target, $prior, 'version_mismatch', true, $activation );
			return;
		}

		self::write_state(
			array_merge(
				$activation,
				array(
					'target'        => $target,
					'prior_version' => $prior,
					'installed'     => $actual,
					'stage'         => 'verifying',
					'reason'        => 'pending',
					'started_at'    => self::to_int( self::state()['started_at'] ?? time() ),
					'last_attempt'  => time(),
				)
			)
		);
		$health = self::probe_health( $target, ! empty( $activation['activation_local'] ) || ! empty( $activation['activation_network'] ) );
		if ( ! $health['healthy'] ) {
			self::remove_backup_shutdown_hooks( $upgrader );
			self::rollback_and_record( $upgrader, $target, $prior, 'health_' . $health['reason'], true, $activation );
			return;
		}

		self::record_success( $target, $actual, $activation );
		self::write_outcome( 'success', $target, 'success' );
		if ( class_exists( 'Cmatic_Options_Repository' ) ) {
			Cmatic_Options_Repository::set_option( 'pro_last_auto_sync', $target );
			Cmatic_Options_Repository::set_option( 'pro_last_auto_sync_at', gmdate( 'Y-m-d H:i:s' ) );
		}
		wp_clean_plugins_cache();
	}

	/**
	 * @param array<string, mixed> $activation Activation snapshot.
	 */
	private static function rollback_and_record( Plugin_Upgrader $upgrader, string $target, string $prior, string $reason, bool $quarantine, array $activation ): void {
		unset( $upgrader );
		if ( ! self::restore_backup( $prior, $activation ) ) {
			self::record_terminal( $target, self::installed_version(), 'rollback_failed', 'rollback_failed', $activation );
			self::write_outcome( 'rollback_failed', $target, $reason );
			return;
		}

		if ( $quarantine ) {
			update_site_option(
				self::QUARANTINE,
				array(
					'version' => self::version( $target ),
					'reason'  => self::reason( $reason ),
					'at'      => time(),
				)
			);
			self::record_terminal( $target, $prior, 'quarantined', 'quarantined', $activation );
		} else {
			self::record_deferred( $target, $prior, $reason, $activation );
		}
		self::write_outcome( 'rolled_back', $target, $reason );
	}

	private static function recover_pending(): void {
		$state = self::state();
		$stage = isset( $state['stage'] ) && is_string( $state['stage'] ) ? $state['stage'] : '';
		if ( ! in_array( $stage, array( 'upgrading', 'verifying' ), true ) ) {
			return;
		}
		$started = self::to_int( $state['started_at'] ?? 0 );
		if ( 0 === $started || ( time() - $started ) <= self::LOCK_TTL ) {
			return;
		}

		$activation = self::activation_from_state( $state );
		$target     = self::state_string( $state, 'target' );
		$prior      = self::state_string( $state, 'prior_version' );
		$actual     = self::installed_version();
		if ( 'verifying' === $stage && $target === $actual && self::restore_activation( $activation ) ) {
			$health = self::probe_health( $target, ! empty( $activation['activation_local'] ) || ! empty( $activation['activation_network'] ) );
			if ( $health['healthy'] ) {
				self::delete_backup();
				self::record_success( $target, $actual, $activation );
				self::write_outcome( 'success', $target, 'stale_committed' );
				delete_site_option( self::LOCK_OPTION );
				return;
			}
		}

		if ( self::backup_exists() && self::restore_backup( $prior, $activation ) ) {
			self::record_deferred( $target, $prior, 'stale_rollback', $activation );
			self::write_outcome( 'rolled_back', $target, 'stale_rollback' );
			delete_site_option( self::LOCK_OPTION );
			return;
		}
		if ( $prior === $actual && self::restore_activation( $activation ) ) {
			self::record_deferred( $target, $prior, 'stale_rollback', $activation );
			self::write_outcome( 'rolled_back', $target, 'stale_rollback' );
			delete_site_option( self::LOCK_OPTION );
			return;
		}

		self::record_terminal( $target, $actual, 'rollback_failed', 'rollback_failed', $activation );
		self::write_outcome( 'rollback_failed', $target, 'stale_rollback' );
		delete_site_option( self::LOCK_OPTION );
	}

	private static function acquire_lock(): bool {
		$now = time();
		if ( add_site_option( self::LOCK_OPTION, $now ) ) {
			return true;
		}
		if ( ( $now - self::to_int( get_site_option( self::LOCK_OPTION, 0 ) ) ) <= self::LOCK_TTL ) {
			return false;
		}

		self::recover_pending();
		delete_site_option( self::LOCK_OPTION );
		return add_site_option( self::LOCK_OPTION, $now );
	}

	/**
	 * @return array{healthy: bool, reason: string}
	 */
	private static function probe_health( string $target, bool $require_active ): array {
		try {
			$id    = bin2hex( random_bytes( 8 ) );
			$token = bin2hex( random_bytes( 24 ) );
		} catch ( Exception $exception ) {
			unset( $exception );
			return array(
				'healthy' => false,
				'reason'  => 'token',
			);
		}

		$key = self::HEALTH_PREFIX . $id;
		set_site_transient(
			$key,
			array(
				'hash'           => hash( 'sha256', $token ),
				'target'         => self::version( $target ),
				'require_active' => $require_active,
			),
			self::HEALTH_TTL
		);

		$body = wp_json_encode(
			array(
				'id'       => $id,
				'token'    => $token,
				'expected' => self::version( $target ),
			)
		);
		if ( ! is_string( $body ) ) {
			delete_site_transient( $key );
			return array(
				'healthy' => false,
				'reason'  => 'encoding',
			);
		}

		$args = array(
			'body'        => $body,
			'headers'     => array(
				'Cache-Control' => 'no-store',
				'Content-Type'  => 'application/json',
			),
			'redirection' => 0,
			'timeout'     => 20,
		);
		if ( function_exists( 'wp_get_environment_type' ) && in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) ) {
			$args['sslverify'] = false;
		}

		$response = wp_remote_post( rest_url( 'chimpmatic-lite/v1/pro-recovery-health' ), $args );
		if ( is_wp_error( $response ) ) {
			delete_site_transient( $key );
			return array(
				'healthy' => false,
				'reason'  => 'transport',
			);
		}
		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			delete_site_transient( $key );
			return array(
				'healthy' => false,
				'reason'  => 'http',
			);
		}

		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $decoded ) || empty( $decoded['healthy'] ) ) {
			return array(
				'healthy' => false,
				'reason'  => self::health_reason( is_array( $decoded ) ? ( $decoded['reason'] ?? 'response' ) : 'response' ),
			);
		}
		return array(
			'healthy' => true,
			'reason'  => 'ok',
		);
	}

	private static function readiness_reason( string $target, bool $require_active ): string {
		if ( $target !== self::installed_version() ) {
			return 'version';
		}
		if ( ! $require_active ) {
			return 'ok';
		}

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$active = is_plugin_active( self::PRO_PLUGIN_FILE )
			|| ( is_multisite() && is_plugin_active_for_network( self::PRO_PLUGIN_FILE ) );
		if ( ! $active ) {
			return 'activation';
		}
		if ( ! defined( 'CMATIC_VERSION' ) || $target !== self::version( (string) CMATIC_VERSION ) || ! function_exists( 'cmatic_process_subscription' ) ) {
			return 'runtime';
		}
		return 'ok';
	}

	/**
	 * @return array<string, int|bool>
	 */
	private static function activation_snapshot(): array {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$local   = self::plugin_basenames( get_option( 'active_plugins', array() ) );
		$index   = array_search( self::PRO_PLUGIN_FILE, $local, true );
		$network = is_multisite() ? get_site_option( 'active_sitewide_plugins', array() ) : array();
		$network = is_array( $network ) ? $network : array();
		return array(
			'activation_local'        => false !== $index,
			'activation_local_index'  => false === $index ? 0 : (int) $index,
			'activation_network'      => isset( $network[ self::PRO_PLUGIN_FILE ] ),
			'activation_network_time' => isset( $network[ self::PRO_PLUGIN_FILE ] ) && is_numeric( $network[ self::PRO_PLUGIN_FILE ] )
				? (int) $network[ self::PRO_PLUGIN_FILE ]
				: 0,
		);
	}

	/**
	 * @param array<string, mixed> $activation Activation snapshot.
	 */
	private static function restore_activation( array $activation ): bool {
		$local = self::plugin_basenames( get_option( 'active_plugins', array() ) );
		$local = array_values( array_diff( $local, array( self::PRO_PLUGIN_FILE ) ) );
		if ( ! empty( $activation['activation_local'] ) ) {
			$index = min( self::to_int( $activation['activation_local_index'] ?? 0 ), count( $local ) );
			array_splice( $local, $index, 0, array( self::PRO_PLUGIN_FILE ) );
		}
		update_option( 'active_plugins', $local );

		if ( is_multisite() ) {
			$network = get_site_option( 'active_sitewide_plugins', array() );
			$network = is_array( $network ) ? $network : array();
			unset( $network[ self::PRO_PLUGIN_FILE ] );
			if ( ! empty( $activation['activation_network'] ) ) {
				$network[ self::PRO_PLUGIN_FILE ] = max( 1, self::to_int( $activation['activation_network_time'] ?? time() ) );
			}
			update_site_option( 'active_sitewide_plugins', $network );
		}

		$current = self::activation_snapshot();
		return (bool) $current['activation_local'] === ! empty( $activation['activation_local'] )
			&& (bool) $current['activation_network'] === ! empty( $activation['activation_network'] );
	}

	/**
	 * WordPress 6.4 and 6.5 cannot restore a named core backup through the public method.
	 *
	 * @param array<string, mixed> $activation Activation snapshot.
	 */
	private static function restore_backup( string $prior, array $activation ): bool {
		global $wp_filesystem;

		require_once ABSPATH . 'wp-admin/includes/file.php';
		if ( ! WP_Filesystem() || ! $wp_filesystem instanceof WP_Filesystem_Base ) {
			return false;
		}

		$backup      = trailingslashit( WP_CONTENT_DIR ) . 'upgrade-temp-backup/plugins/chimpmatic';
		$destination = trailingslashit( WP_PLUGIN_DIR ) . 'chimpmatic';
		if ( ! $wp_filesystem->is_dir( $backup ) ) {
			return false;
		}
		if ( $wp_filesystem->is_dir( $destination ) && ! $wp_filesystem->delete( $destination, true ) ) {
			return false;
		}
		$moved = move_dir( $backup, $destination, true );
		if ( true !== $moved ) {
			return false;
		}
		wp_clean_plugins_cache();
		return $prior === self::installed_version() && self::restore_activation( $activation );
	}

	private static function backup_exists(): bool {
		return is_dir( trailingslashit( WP_CONTENT_DIR ) . 'upgrade-temp-backup/plugins/chimpmatic' );
	}

	private static function delete_backup(): bool {
		global $wp_filesystem;

		require_once ABSPATH . 'wp-admin/includes/file.php';
		if ( ! WP_Filesystem() || ! $wp_filesystem instanceof WP_Filesystem_Base ) {
			return false;
		}
		$backup = trailingslashit( WP_CONTENT_DIR ) . 'upgrade-temp-backup/plugins/chimpmatic';
		return ! $wp_filesystem->is_dir( $backup ) || $wp_filesystem->delete( $backup, true );
	}

	private static function remove_backup_shutdown_hooks( Plugin_Upgrader $upgrader ): void {
		remove_action( 'shutdown', array( $upgrader, 'restore_temp_backup' ), 10 );
		remove_action( 'shutdown', array( $upgrader, 'delete_temp_backup' ), 100 );
	}

	private static function installed_version(): string {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$path = WP_PLUGIN_DIR . '/' . self::PRO_PLUGIN_FILE;
		if ( ! is_readable( $path ) ) {
			return '0';
		}
		$data = get_plugin_data( $path, false, false );
		return self::version( isset( $data['Version'] ) && is_scalar( $data['Version'] ) ? (string) $data['Version'] : '0' );
	}

	private static function environment_blocked(): bool {
		$disabled = defined( 'AUTOMATIC_UPDATER_DISABLED' ) && AUTOMATIC_UPDATER_DISABLED;
		return wp_installing()
			|| ( function_exists( 'wp_is_file_mod_allowed' ) && ! wp_is_file_mod_allowed( 'automatic_updater' ) )
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core hook.
			|| (bool) apply_filters( 'automatic_updater_disabled', $disabled );
	}

	/**
	 * @param array<string, mixed> $activation Activation snapshot.
	 */
	private static function record_deferred( string $target, string $installed, string $reason, array $activation = array(), int $delay = 0 ): void {
		$old      = self::state();
		$attempts = $target === ( $old['target'] ?? '' ) ? self::to_int( $old['attempts'] ?? 0 ) + 1 : 1;
		$delays   = array( 300, 1800, 7200, 43200 );
		$delay    = $delay > 0 ? $delay : $delays[ min( $attempts - 1, 3 ) ];
		$retry_at = time() + $delay;
		self::write_state(
			array_merge(
				empty( $activation ) ? self::activation_from_state( $old ) : $activation,
				array(
					'target'        => $target,
					'prior_version' => self::state_string( $old, 'prior_version', $installed ),
					'installed'     => $installed,
					'attempts'      => $attempts,
					'reason'        => $reason,
					'stage'         => 'deferred',
					'started_at'    => self::to_int( $old['started_at'] ?? time() ),
					'last_attempt'  => time(),
					'next_retry'    => $retry_at,
				)
			)
		);
		wp_clear_scheduled_hook( self::RETRY_HOOK );
		wp_schedule_single_event( $retry_at, self::RETRY_HOOK );
	}

	/**
	 * @param array<string, mixed> $activation Activation snapshot.
	 */
	private static function record_success( string $target, string $installed, array $activation ): void {
		$now = time();
		delete_site_option( self::QUARANTINE );
		wp_clear_scheduled_hook( self::RETRY_HOOK );
		self::write_state(
			array_merge(
				$activation,
				array(
					'target'        => $target,
					'prior_version' => self::state_string( self::state(), 'prior_version', $installed ),
					'installed'     => $installed,
					'attempts'      => 0,
					'reason'        => 'success',
					'stage'         => 'success',
					'started_at'    => self::to_int( self::state()['started_at'] ?? $now ),
					'last_attempt'  => $now,
					'last_success'  => $now,
					'next_retry'    => 0,
				)
			)
		);
	}

	/**
	 * @param array<string, mixed> $activation Activation snapshot.
	 */
	private static function record_terminal( string $target, string $installed, string $reason, string $stage, array $activation ): void {
		wp_clear_scheduled_hook( self::RETRY_HOOK );
		self::write_state(
			array_merge(
				$activation,
				array(
					'target'        => $target,
					'prior_version' => self::state_string( self::state(), 'prior_version', $installed ),
					'installed'     => $installed,
					'attempts'      => self::to_int( self::state()['attempts'] ?? 1 ),
					'reason'        => $reason,
					'stage'         => $stage,
					'started_at'    => self::to_int( self::state()['started_at'] ?? time() ),
					'last_attempt'  => time(),
					'next_retry'    => 0,
				)
			)
		);
	}

	private static function write_outcome( string $stage, string $target, string $reason ): void {
		$allowed = array( 'success', 'rolled_back', 'rollback_failed' );
		update_site_option(
			self::OUTCOME_OPTION,
			array(
				'stage'  => in_array( $stage, $allowed, true ) ? $stage : 'rollback_failed',
				'target' => self::version( $target ),
				'reason' => self::reason( $reason ),
				'at'     => time(),
			)
		);
	}

	private static function is_quarantined( string $target ): bool {
		$value = get_site_option( self::QUARANTINE, array() );
		if ( ! is_array( $value ) || ( $value['version'] ?? '' ) !== $target ) {
			delete_site_option( self::QUARANTINE );
			return false;
		}
		return true;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function state(): array {
		$state = get_site_option( self::STATE_OPTION, array() );
		return is_array( $state ) ? $state : array();
	}

	/**
	 * @param array<string, mixed> $values Recovery state.
	 */
	private static function write_state( array $values ): void {
		$state = array(
			'target'                  => self::version( self::array_string( $values, 'target' ) ),
			'prior_version'           => self::version( self::array_string( $values, 'prior_version' ) ),
			'installed'               => self::version( self::array_string( $values, 'installed' ) ),
			'attempts'                => min( 999, self::to_int( $values['attempts'] ?? 0 ) ),
			'reason'                  => self::reason( self::array_string( $values, 'reason', 'unknown' ) ),
			'stage'                   => self::stage( self::array_string( $values, 'stage', 'idle' ) ),
			'started_at'              => self::to_int( $values['started_at'] ?? 0 ),
			'last_attempt'            => self::to_int( $values['last_attempt'] ?? 0 ),
			'last_success'            => self::to_int( $values['last_success'] ?? 0 ),
			'next_retry'              => self::to_int( $values['next_retry'] ?? 0 ),
			'activation_local'        => ! empty( $values['activation_local'] ),
			'activation_local_index'  => min( 9999, self::to_int( $values['activation_local_index'] ?? 0 ) ),
			'activation_network'      => ! empty( $values['activation_network'] ),
			'activation_network_time' => self::to_int( $values['activation_network_time'] ?? 0 ),
		);
		update_site_option( self::STATE_OPTION, $state );
	}

	/**
	 * @param array<string, mixed> $state Recovery state.
	 * @return array<string, int|bool>
	 */
	private static function activation_from_state( array $state ): array {
		return array(
			'activation_local'        => ! empty( $state['activation_local'] ),
			'activation_local_index'  => self::to_int( $state['activation_local_index'] ?? 0 ),
			'activation_network'      => ! empty( $state['activation_network'] ),
			'activation_network_time' => self::to_int( $state['activation_network_time'] ?? 0 ),
		);
	}

	private static function schedule_soon(): void {
		$next = wp_next_scheduled( self::RETRY_HOOK );
		if ( false === $next || $next > time() + MINUTE_IN_SECONDS ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::RETRY_HOOK );
		}
	}

	/**
	 * @param mixed $value Force flag.
	 */
	private static function force_enabled( $value ): bool {
		return true === $value || 1 === $value || '1' === $value || 'true' === $value;
	}

	private static function health_reason( $value ): string {
		$value   = is_string( $value ) ? sanitize_key( $value ) : 'response';
		$allowed = array( 'ok', 'token', 'encoding', 'transport', 'http', 'response', 'version', 'activation', 'runtime' );
		return in_array( $value, $allowed, true ) ? $value : 'response';
	}

	private static function reason( string $value ): string {
		$value   = sanitize_key( $value );
		$allowed = array(
			'pending',
			'success',
			'metadata_unavailable',
			'policy_disabled',
			'environment_blocked',
			'upgrade_failed',
			'activation_restore_failed',
			'version_mismatch',
			'health_token',
			'health_encoding',
			'health_transport',
			'health_http',
			'health_response',
			'health_version',
			'health_activation',
			'health_runtime',
			'quarantined',
			'rollback_failed',
			'stale_rollback',
			'stale_committed',
		);
		return in_array( $value, $allowed, true ) ? $value : 'unknown';
	}

	private static function stage( string $value ): string {
		$allowed = array( 'idle', 'metadata', 'upgrading', 'verifying', 'success', 'deferred', 'rolled_back', 'quarantined', 'rollback_failed' );
		return in_array( $value, $allowed, true ) ? $value : 'idle';
	}

	private static function version( string $value ): string {
		return preg_match( '/\A[0-9A-Za-z._+-]{1,20}\z/', $value ) ? $value : '0';
	}

	private static function to_int( $value ): int {
		if ( is_int( $value ) ) {
			return max( 0, $value );
		}
		return is_string( $value ) && ctype_digit( $value ) ? (int) $value : 0;
	}

	/**
	 * @param mixed $value Plugin list.
	 * @return array<int, string>
	 */
	private static function plugin_basenames( $value ): array {
		return is_array( $value ) ? array_values( array_filter( $value, 'is_string' ) ) : array();
	}

	/**
	 * @param array<string, mixed> $value Source array.
	 */
	private static function array_string( array $value, string $key, string $fallback = '' ): string {
		return isset( $value[ $key ] ) && is_scalar( $value[ $key ] ) ? (string) $value[ $key ] : $fallback;
	}

	/**
	 * @param array<string, mixed> $value Source array.
	 */
	private static function state_string( array $value, string $key, string $fallback = '' ): string {
		return self::array_string( $value, $key, $fallback );
	}
}
