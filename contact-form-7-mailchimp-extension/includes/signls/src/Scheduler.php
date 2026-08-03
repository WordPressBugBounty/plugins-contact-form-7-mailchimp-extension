<?php
/**
 * Product-scoped durable scheduling.
 *
 * @package signls-sdk
 * @license GPL-3.0-or-later
 */

namespace Signls\Sdk\V1;

defined( 'ABSPATH' ) || exit;

final class Scheduler {

	private $product;

	private $state;

	private $adapter;

	private $routine_hook;

	private $refresh_hook;

	public function __construct( string $product, StateStore $state, ProductAdapterInterface $adapter ) {
		$hash               = substr( hash( 'sha256', $product ), 0, 12 );
		$this->product      = $product;
		$this->state        = $state;
		$this->adapter      = $adapter;
		$this->routine_hook = 'signls_sdk_v1_' . $hash . '_routine';
		$this->refresh_hook = 'signls_sdk_v1_' . $hash . '_refresh';
	}

	public function register(): void {
		add_action( $this->routine_hook, array( $this, 'run' ) );
		add_action( $this->refresh_hook, array( $this, 'run' ) );
		add_action( 'admin_init', array( $this, 'failsafe' ), 999 );
	}

	public function activate( string $install_id, string $device_id ): void {
		if ( 'enabled' !== $this->state->get( 'consent_status', 'unset' ) ) {
			return;
		}
		if ( wp_next_scheduled( $this->routine_hook ) ) {
			return;
		}
		$delay = 300 + self::jitter( $this->product . '|' . $install_id . '|' . $device_id, 21300 );
		wp_schedule_single_event( time() + $delay, $this->routine_hook );
	}

	public function relevant_change(): void {
		$this->state->set_many(
			array(
				'last_relevant_activity' => time(),
				'cadence_mode'           => 'daily',
			)
		);
		if ( $this->quarantine_deferred() ) {
			return;
		}
		if ( ! wp_next_scheduled( $this->refresh_hook ) ) {
			wp_schedule_single_event( time() + 300 + self::jitter( $this->product . '|refresh', 600 ), $this->refresh_hook );
		}
	}

	public function run(): void {
		if ( 'enabled' !== $this->state->get( 'consent_status', 'unset' ) ) {
			return;
		}
		$result = Runtime::run( $this->product );
		if ( in_array( $result['class'], array( 'delivery_busy', 'delivery_lock_unavailable' ), true ) ) {
			return;
		}
		$this->schedule_next();
	}

	public function schedule_next(): void {
		if ( 'enabled' !== $this->state->get( 'consent_status', 'unset' ) ) {
			return;
		}
		if ( wp_next_scheduled( $this->routine_hook ) ) {
			wp_clear_scheduled_hook( $this->routine_hook );
		}
		$last_activity = (int) $this->state->get( 'last_relevant_activity', time() );
		$sparse        = time() - $last_activity >= 30 * DAY_IN_SECONDS;
		$interval      = $sparse ? WEEK_IN_SECONDS : DAY_IN_SECONDS;
		$mode          = $sparse ? 'sparse' : 'daily';
		$this->state->set( 'cadence_mode', $mode );
		wp_schedule_single_event( time() + $interval + self::jitter( $this->product . '|' . gmdate( 'Y-m-d' ), 3600 ), $this->routine_hook );
	}

	public function schedule_retry( int $when ): void {
		if ( 'enabled' !== $this->state->get( 'consent_status', 'unset' ) ) {
			return;
		}
		if ( $this->quarantine_deferred() ) {
			return;
		}
		if ( ! wp_next_scheduled( $this->refresh_hook ) ) {
			wp_schedule_single_event( max( time() + 60, $when ), $this->refresh_hook );
		}
	}

	public function clear(): void {
		wp_clear_scheduled_hook( $this->routine_hook );
		wp_clear_scheduled_hook( $this->refresh_hook );
	}

	public function failsafe(): void {
		if ( 'enabled' !== $this->state->get( 'consent_status', 'unset' ) ) {
			return;
		}
		$last = (int) $this->state->get( 'last_acknowledged_at', 0 );
		$mode = (string) $this->state->get( 'cadence_mode', 'daily' );
		$age  = 'sparse' === $mode ? 9 * DAY_IN_SECONDS : 36 * HOUR_IN_SECONDS;
		if ( $last > 0 && time() - $last < $age ) {
			return;
		}
		if ( $this->quarantine_deferred() ) {
			return;
		}
		if ( ! wp_next_scheduled( $this->refresh_hook ) ) {
			wp_schedule_single_event( time() + 300 + self::jitter( $this->product . '|failsafe', 600 ), $this->refresh_hook );
		}
	}

	public function routine_hook(): string {
		return $this->routine_hook;
	}

	public function refresh_hook(): string {
		return $this->refresh_hook;
	}

	private static function jitter( string $seed, int $maximum ): int {
		$hex = substr( hash( 'sha256', $seed ), 0, 8 );
		return (int) ( hexdec( $hex ) % ( max( 1, $maximum ) + 1 ) );
	}

	private function quarantine_deferred(): bool {
		if (
			'' === (string) $this->state->get( 'pending_body', '' )
			|| '' === (string) $this->state->get( 'pending_quarantine_class', '' )
			|| (int) $this->state->get( 'pending_quarantine_probe_at', 0 ) <= time()
		) {
			return false;
		}

		try {
			$contract = $this->adapter->contract();
			$revision = isset( $contract['snapshot_payload_revision'] ) ? (int) $contract['snapshot_payload_revision'] : 1;
			$revision = $revision > 0 ? $revision : 1;
			return (
				Transport::version() === (string) $this->state->get( 'pending_sdk_version', '' )
				&& $this->adapter->product_version() === (string) $this->state->get( 'pending_product_version', '' )
				&& $revision === (int) $this->state->get( 'pending_payload_revision', 1 )
			);
		} catch ( \Throwable $error ) {
			return false;
		}
	}
}
