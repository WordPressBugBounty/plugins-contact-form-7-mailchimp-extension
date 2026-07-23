<?php
/**
 * Product runtime coordinator.
 *
 * @package signls-sdk
 * @license GPL-3.0-or-later
 */

namespace Signls\Sdk\V1;

defined( 'ABSPATH' ) || exit;

final class Runtime {

	private static $products = array();

	private static $site_identity;

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

			$state    = new StateStore( $product );
			$consent  = new Consent( $state );
			$identity = new Identity( $state );
			if ( ! self::$site_identity instanceof SiteIdentity ) {
				self::$site_identity = new SiteIdentity();
			}
			$scheduler = new Scheduler( $product, $state );
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
			}
			return true;
		} catch ( \Throwable $error ) {
			return false;
		}
	}

	public static function enable( string $product, string $source, int $notice_version ): bool {
		$runtime = self::product( $product );
		if ( null === $runtime || ! $runtime['consent']->enable( $source, $notice_version ) ) {
			return false;
		}
		CounterStore::ensure_schema();
		$runtime['scheduler']->activate( $runtime['adapter']->install_id(), $runtime['identity']->device_id() );
		return true;
	}

	public static function disable( string $product, string $source, int $notice_version ): bool {
		$runtime = self::product( $product );
		if ( null === $runtime || ! $runtime['consent']->disable( $source, $notice_version ) ) {
			return false;
		}
		$runtime['scheduler']->clear();
		$runtime['identity']->forget_device();
		return true;
	}

	public static function relevant_change( string $product ): void {
		$runtime = self::product( $product );
		if ( null !== $runtime && $runtime['consent']->enabled() ) {
			$runtime['scheduler']->relevant_change();
		}
	}

	public static function run( string $product ): array {
		$runtime = self::product( $product );
		if ( null === $runtime || ! $runtime['consent']->enabled() || ! $runtime['adapter']->signal_sharing_enabled() ) {
			return array(
				'ok'        => false,
				'class'     => 'consent_required',
				'status'    => 0,
				'permanent' => true,
			);
		}

		try {
			$payload = Collector::collect( $runtime['adapter'] );
			$result  = $runtime['transport']->deliver( $runtime['adapter'], $payload );
			if ( ! $result['ok'] && empty( $result['permanent'] ) ) {
				$runtime['scheduler']->schedule_retry( (int) $runtime['state']->get( 'next_retry_at', time() + 900 ) );
			}
			return $result;
		} catch ( \Throwable $error ) {
			return array(
				'ok'        => false,
				'class'     => 'runtime_failure',
				'status'    => 0,
				'permanent' => false,
			);
		}
	}

	public static function state( string $product ): array {
		$runtime = self::product( $product );
		return null === $runtime ? array() : $runtime['state']->all();
	}

	public static function cleanup( string $product ): void {
		$runtime = self::product( $product );
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
}
