<?php
/**
 * Product runtime coordinator.
 *
 * @package signls-sdk
 * @license GPL-3.0-or-later
 */

namespace Signls\Sdk\V1;

defined( 'ABSPATH' ) || exit;

/**
 * @phpstan-type ProductRuntime array{
 *     adapter: ProductAdapterInterface,
 *     state: StateStore,
 *     consent: Consent,
 *     identity: Identity,
 *     site: SiteIdentity,
 *     scheduler: Scheduler,
 *     transport: Transport
 * }
 */
final class Runtime {

	private static $products = array();

	private static $site_identity;

	private static $queued_products = array();

	private static $shutdown_registered = false;

	private static $flushing_products = array();

	public static function boot( array $descriptor ): bool {
		try {
			$adapter = self::adapter( $descriptor );
			if ( ! $adapter instanceof ProductAdapterInterface ) {
				return false;
			}
			$product = $adapter->product_slug();
			if ( isset( self::$products[ $product ] ) ) {
				return true;
			}

			$state                    = new StateStore( $product );
			$consent                  = new Consent( $state );
			$identity                 = new Identity( $state );
			$established_signal_state = self::has_established_signal_state( $state );
			if ( ! self::$site_identity instanceof SiteIdentity ) {
				self::$site_identity = new SiteIdentity();
			}
			$scheduler = new Scheduler( $product, $state, $adapter );
			$transport = new Transport( $state, $identity, self::$site_identity );
			$scheduler->register();

			self::$products[ $product ] = array(
				'adapter'   => $adapter,
				'state'     => $state,
				'consent'   => $consent,
				'identity'  => $identity,
				'site'      => self::$site_identity,
				'scheduler' => $scheduler,
				'transport' => $transport,
			);

			if ( $adapter->signal_sharing_enabled() && $consent->enabled() ) {
				CounterStore::ensure_schema();
				$scheduler->activate( $adapter->install_id(), $identity->device_id() );
				self::recognize_product_version( $product, self::$products[ $product ], $established_signal_state );
			}
			return true;
		} catch ( \Throwable $error ) {
			return false;
		}
	}

	public static function enable( string $product, string $source, int $notice_version ): bool {
		$runtime = self::product( $product );
		if ( null === $runtime ) {
			return false;
		}
		$established_signal_state = self::has_established_signal_state( $runtime['state'] );
		if ( ! $runtime['consent']->enable( $source, $notice_version ) ) {
			return false;
		}
		CounterStore::ensure_schema();
		$runtime['scheduler']->activate( $runtime['adapter']->install_id(), $runtime['identity']->device_id() );
		self::recognize_product_version( $product, $runtime, $established_signal_state );
		self::queue_immediate( $product, $runtime );
		return true;
	}

	public static function disable( string $product, string $source, int $notice_version ): bool {
		$runtime = self::product( $product );
		if ( null === $runtime || ! $runtime['consent']->disable( $source, $notice_version ) ) {
			return false;
		}
		unset( self::$queued_products[ $product ] );
		$runtime['state']->clear_delivery_intent_unconditionally();
		$runtime['scheduler']->clear();
		$runtime['identity']->forget_device();
		return true;
	}

	public static function relevant_change( string $product ): void {
		$runtime = self::product( $product );
		if ( null !== $runtime && $runtime['consent']->enabled() ) {
			$runtime['scheduler']->relevant_change();
			self::queue_immediate( $product, $runtime );
		}
	}

	public static function run( string $product ): array {
		return self::run_internal( $product, 0 );
	}

	public static function flush_immediate_deliveries(): void {
		$products              = self::$queued_products;
		self::$queued_products = array();
		foreach ( $products as $product => $intent ) {
			self::flush_immediate_internal( (string) $product, (string) $intent );
		}
	}

	public static function flush_immediate( string $product ): array {
		return self::flush_immediate_internal( $product, '' );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function flush_immediate_internal( string $product, string $required_intent ): array {
		$runtime = self::product( $product );
		if ( null === $runtime || ! $runtime['consent']->enabled() || ! $runtime['adapter']->signal_sharing_enabled() ) {
			return self::consent_required_result();
		}
		if ( isset( self::$flushing_products[ $product ] ) ) {
			return array(
				'ok'        => false,
				'class'     => 'delivery_busy',
				'status'    => 0,
				'permanent' => false,
			);
		}

		unset( self::$queued_products[ $product ] );
		self::$flushing_products[ $product ] = true;
		try {
			$result = self::run_internal( $product, 1, $required_intent );
			$runtime['scheduler']->settle_immediate( $result, '' !== $runtime['state']->delivery_intent() );
			return $result;
		} catch ( \Throwable $error ) {
			$result = array(
				'ok'        => false,
				'class'     => 'runtime_failure',
				'status'    => 0,
				'permanent' => false,
			);
			$runtime['scheduler']->settle_immediate( $result, '' !== $runtime['state']->delivery_intent() );
			return $result;
		} finally {
			unset( self::$flushing_products[ $product ] );
		}
	}

	public static function suspend( string $product ): void {
		$runtime = self::product( $product );
		unset( self::$queued_products[ $product ] );
		if ( null !== $runtime ) {
			$runtime['scheduler']->clear();
		}
	}

	private static function run_internal( string $product, int $lock_wait_seconds, string $required_intent = '' ): array {
		$runtime = self::product( $product );
		if ( null === $runtime || ! $runtime['consent']->enabled() || ! $runtime['adapter']->signal_sharing_enabled() ) {
			return self::consent_required_result();
		}

		$lock = self::acquire_delivery_lock( $product, $lock_wait_seconds );
		if ( ! $lock['ok'] ) {
			$runtime['scheduler']->schedule_retry( time() + 60 );
			return array(
				'ok'        => false,
				'class'     => $lock['class'],
				'status'    => 0,
				'permanent' => false,
			);
		}

		try {
			$runtime['state']->refresh_concurrency_state();
			$intent = $runtime['state']->delivery_intent();
			if ( '' !== $required_intent && ( '' === $intent || ! hash_equals( $required_intent, $intent ) ) ) {
				return self::superseded_delivery_result();
			}
			$observation        = $runtime['state']->product_version_observation();
			$version_transition = self::version_transition_matches_intent(
				$observation,
				$intent,
				$runtime['adapter']->product_version()
			);
			if (
				'' !== $intent
				&& isset( $observation['settled_intent'] )
				&& hash_equals( $observation['settled_intent'], $intent )
			) {
				if ( $runtime['state']->clear_delivery_intent( $intent ) ) {
					$runtime['state']->clear_settled_product_version_intent( $intent );
				}
				return self::superseded_delivery_result();
			}
			$deferred = $runtime['transport']->prepare( $runtime['adapter'] );
			if ( is_array( $deferred ) ) {
				return $deferred;
			}

			$had_pending = $runtime['transport']->has_compatible_pending( $runtime['adapter'] );
			if ( $had_pending ) {
				$result = $runtime['transport']->deliver( $runtime['adapter'], array() );
				if ( ! $result['ok'] || '' === $intent ) {
					self::schedule_failed_delivery( $runtime, $result );
					return $result;
				}
				if ( $version_transition ) {
					$runtime['state']->settle_product_version_transition( $runtime['adapter']->product_version(), $intent );
					if ( $runtime['state']->clear_delivery_intent( $intent ) ) {
						$runtime['state']->clear_settled_product_version_intent( $intent );
					}
					return $result;
				}
			}

			$payload = Collector::collect( $runtime['adapter'] );
			$result  = $runtime['transport']->deliver( $runtime['adapter'], $payload );
			if ( '' !== $intent && self::terminal_delivery_result( $result ) ) {
				$runtime['state']->settle_product_version_transition( $runtime['adapter']->product_version(), $intent );
			} elseif ( '' === $intent && ! empty( $result['ok'] ) ) {
				$runtime['state']->baseline_product_version( $runtime['adapter']->product_version() );
			}
			if (
				'' !== $intent
				&& ( $result['ok'] || $runtime['transport']->has_compatible_pending( $runtime['adapter'] ) )
				&& ( ! $version_transition || self::terminal_delivery_result( $result ) )
			) {
				if ( $runtime['state']->clear_delivery_intent( $intent ) ) {
					$runtime['state']->clear_settled_product_version_intent( $intent );
				}
			}
			self::schedule_failed_delivery( $runtime, $result );
			return $result;
		} catch ( \Throwable $error ) {
			$runtime['scheduler']->schedule_retry( time() + 900 );
			return array(
				'ok'        => false,
				'class'     => 'runtime_failure',
				'status'    => 0,
				'permanent' => false,
			);
		} finally {
			self::release_delivery_lock( $lock['name'] );
		}
	}

	public static function state( string $product ): array {
		$runtime = self::product( $product );
		return null === $runtime ? array() : $runtime['state']->all();
	}

	public static function cleanup( string $product ): void {
		$runtime = self::product( $product );
		unset( self::$queued_products[ $product ], self::$flushing_products[ $product ] );
		if ( null !== $runtime ) {
			$runtime['scheduler']->clear();
			$runtime['state']->delete();
		}
		CounterStore::delete_product( $product );
	}

	private static function adapter( array $descriptor ) {
		if ( isset( $descriptor['adapter'] ) && $descriptor['adapter'] instanceof ProductAdapterInterface ) {
			return $descriptor['adapter'];
		}
		$class = isset( $descriptor['adapter_class'] ) ? (string) $descriptor['adapter_class'] : '';
		return '' !== $class && class_exists( $class ) ? new $class() : null;
	}

	private static function product( string $product ) {
		return isset( self::$products[ $product ] ) ? self::$products[ $product ] : null;
	}

	private static function acquire_delivery_lock( string $product, int $wait_seconds ): array {
		global $wpdb;
		if (
			! is_object( $wpdb )
			|| ! method_exists( $wpdb, 'prepare' )
			|| ! method_exists( $wpdb, 'get_var' )
			|| ! isset( $wpdb->dbname, $wpdb->options )
			|| '' === (string) $wpdb->dbname
			|| '' === (string) $wpdb->options
		) {
			return array(
				'ok'    => false,
				'class' => 'delivery_lock_unavailable',
				'name'  => '',
			);
		}

		$name = 'signls_sdk_delivery_' . substr( hash( 'sha256', (string) $wpdb->dbname . '|' . (string) $wpdb->options . '|' . $product ), 0, 32 );
		try {
			$result = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s,%d)', $name, min( 1, max( 0, $wait_seconds ) ) ) );
			if ( 1 === (int) $result && '1' === (string) $result ) {
				return array(
					'ok'    => true,
					'class' => '',
					'name'  => $name,
				);
			}
			return array(
				'ok'    => false,
				'class' => '0' === (string) $result ? 'delivery_busy' : 'delivery_lock_unavailable',
				'name'  => '',
			);
		} catch ( \Throwable $error ) {
			return array(
				'ok'    => false,
				'class' => 'delivery_lock_unavailable',
				'name'  => '',
			);
		}
	}

	private static function release_delivery_lock( string $name ): void {
		if ( '' === $name ) {
			return;
		}
		global $wpdb;
		try {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) );
		} catch ( \Throwable $error ) {
			return;
		}
	}

	private static function queue_immediate( string $product, array $runtime ): void {
		$intent = $runtime['state']->mark_delivery_intent();
		if ( '' === $intent ) {
			return;
		}
		$runtime['state']->rebind_product_version_transition( $intent );
		self::queue_immediate_intent( $product, $intent );
	}

	/**
	 * @param ProductRuntime $runtime Product runtime.
	 */
	private static function queue_existing_immediate( string $product, array $runtime ): void {
		$intent = $runtime['state']->ensure_delivery_intent();
		if ( '' === $intent ) {
			return;
		}
		$runtime['state']->rebind_product_version_transition( $intent );
		self::queue_immediate_intent( $product, $intent );
	}

	private static function queue_immediate_intent( string $product, string $intent ): void {
		self::$queued_products[ $product ] = $intent;
		if ( self::$shutdown_registered ) {
			return;
		}
		self::$shutdown_registered = true;
		add_action( 'shutdown', array( self::class, 'flush_immediate_deliveries' ), PHP_INT_MAX );
	}

	/**
	 * @param ProductRuntime $runtime Product runtime.
	 */
	private static function recognize_product_version( string $product, array $runtime, bool $established_signal_state ): void {
		$version = $runtime['adapter']->product_version();
		if (
			'' === $version
			|| strlen( $version ) > 64
			|| 1 !== preg_match( '/^[\x20-\x7e]+$/D', $version )
		) {
			return;
		}

		$lock = self::acquire_delivery_lock( $product, 0 );
		if ( ! $lock['ok'] ) {
			return;
		}
		try {
			$runtime['state']->refresh_concurrency_state();
			$observation = $runtime['state']->product_version_observation();
			$observed    = isset( $observation['observed'] ) ? (string) $observation['observed'] : '';
			$pending     = isset( $observation['pending'] ) ? (string) $observation['pending'] : '';

			if ( '' === $observed && '' === $pending && ! $established_signal_state ) {
				$runtime['state']->baseline_product_version( $version );
				return;
			}
			if ( '' !== $observed && hash_equals( $observed, $version ) && '' === $pending ) {
				return;
			}

			$intent = $runtime['state']->ensure_delivery_intent();
			if ( '' === $intent ) {
				return;
			}
			if ( '' === $pending || ! hash_equals( $pending, $version ) ) {
				$runtime['state']->begin_product_version_transition( $version, $intent );
			} else {
				$runtime['state']->rebind_product_version_transition( $intent );
			}
		} finally {
			self::release_delivery_lock( $lock['name'] );
		}

		$runtime['scheduler']->relevant_change();
		self::queue_existing_immediate( $product, $runtime );
	}

	private static function has_established_signal_state( StateStore $state ): bool {
		return (int) $state->get( 'last_acknowledged_at', 0 ) > 0
			|| '' !== (string) $state->get( 'device_id', '' )
			|| '' !== (string) $state->get( 'credential_id', '' )
			|| '' !== (string) $state->get( 'pending_body', '' );
	}

	/**
	 * @param array<string, mixed> $result Delivery result.
	 */
	private static function terminal_delivery_result( array $result ): bool {
		return ! empty( $result['ok'] ) || ! empty( $result['permanent'] ) || ! empty( $result['quarantined'] );
	}

	/**
	 * @param array{observed?: string, pending?: string, intent?: string, settled_intent?: string} $observation Product version observation.
	 */
	private static function version_transition_matches_intent( array $observation, string $intent, string $version ): bool {
		return '' !== $intent
			&& isset( $observation['pending'], $observation['intent'] )
			&& hash_equals( (string) $observation['pending'], $version )
			&& hash_equals( (string) $observation['intent'], $intent );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function superseded_delivery_result(): array {
		return array(
			'ok'         => true,
			'class'      => 'delivery_superseded',
			'status'     => 0,
			'permanent'  => false,
			'superseded' => true,
		);
	}

	private static function schedule_failed_delivery( array $runtime, array $result ): void {
		if ( ! $result['ok'] && empty( $result['permanent'] ) && empty( $result['quarantined'] ) ) {
			$runtime['scheduler']->schedule_retry( (int) $runtime['state']->get( 'next_retry_at', time() + 900 ) );
		}
	}

	private static function consent_required_result(): array {
		return array(
			'ok'        => false,
			'class'     => 'consent_required',
			'status'    => 0,
			'permanent' => true,
		);
	}
}
