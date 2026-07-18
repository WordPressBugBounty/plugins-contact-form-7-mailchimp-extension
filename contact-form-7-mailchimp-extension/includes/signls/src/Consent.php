<?php
/**
 * Explicit product consent state.
 *
 * @package signls-sdk
 * @license GPL-3.0-or-later
 */

namespace Signls\Sdk\V1;

defined( 'ABSPATH' ) || exit;

final class Consent {

	const VERSION = 1;

	private $state;

	public function __construct( StateStore $state ) {
		$this->state = $state;
	}

	public function status(): string {
		$status = (string) $this->state->get( 'consent_status', 'unset' );
		return in_array( $status, array( 'unset', 'enabled', 'disabled' ), true ) ? $status : 'unset';
	}

	public function enabled(): bool {
		return 'enabled' === $this->status();
	}

	public function enable( string $source, int $notice_version ): bool {
		$now           = time();
		$first_enabled = (int) $this->state->get( 'consent_first_enabled_at', 0 );
		if ( $first_enabled <= 0 ) {
			$first_enabled = $now;
		}

		return $this->state->set_many(
			array(
				'consent_status'           => 'enabled',
				'consent_version'          => self::VERSION,
				'consent_first_enabled_at' => $first_enabled,
				'consent_last_changed_at'  => $now,
				'consent_source'           => Sanitizer::slug( $source, 48, 'unknown' ),
				'consent_notice_version'   => max( 1, $notice_version ),
			)
		);
	}

	public function disable( string $source, int $notice_version ): bool {
		return $this->state->set_many(
			array(
				'consent_status'          => 'disabled',
				'consent_version'         => self::VERSION,
				'consent_last_changed_at' => time(),
				'consent_source'          => Sanitizer::slug( $source, 48, 'unknown' ),
				'consent_notice_version'  => max( 1, $notice_version ),
			)
		);
	}
}
