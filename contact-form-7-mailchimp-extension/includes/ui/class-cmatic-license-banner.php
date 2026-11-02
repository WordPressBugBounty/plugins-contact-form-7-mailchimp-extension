<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

final class Cmatic_License_Banner {

	private const AMNESTY_DEFAULT_UNTIL = 1788235199;

	private const CAMPAIGNS = array( 'activate', 'winback', 'renew' );

	private const META_KEY = 'cmatic_banner_dismissals';

	private const SNOOZE = array(
		'unlicensed'        => 7 * DAY_IN_SECONDS,
		'unlicensed_urgent' => DAY_IN_SECONDS,
		'unlicensed_over'   => DAY_IN_SECONDS,
		'expired'           => 30 * DAY_IN_SECONDS,
		'invalid'           => 7 * DAY_IN_SECONDS,
		'expiring'          => 30 * DAY_IN_SECONDS,
	);

	public static function init(): void {
		\Cmatic\Lite\Admin\Notices::boot();
	}

	public static function resolve_state(): string {
		if ( ! class_exists( 'Cmatic_License_State_Resolver' ) ) {
			return '';
		}

		$license_state = Cmatic_License_State_Resolver::resolve();
		if ( '' === $license_state ) {
			return '';
		}

		if ( 'active' === $license_state ) {
			$expires = self::license_expires_at();
			if ( $expires > 0 && ( $expires - time() ) <= 30 * DAY_IN_SECONDS && $expires > time() ) {
				return 'expiring';
			}
			return '';
		}

		if ( 'expired' === $license_state ) {
			return 'expired';
		}

		if ( 'invalid' === $license_state ) {
			return 'invalid';
		}

		$deadline = self::amnesty_until();
		if ( time() > $deadline ) {
			return 'unlicensed_over';
		}
		if ( ( $deadline - time() ) <= 14 * DAY_IN_SECONDS ) {
			return 'unlicensed_urgent';
		}
		return 'unlicensed';
	}

	private static function amnesty_until(): int {
		$cached = (int) get_option( 'chimpmatic_amnesty_until', 0 );
		return $cached > 0 ? $cached : self::AMNESTY_DEFAULT_UNTIL;
	}

	private static function license_expires_at(): int {
		return class_exists( 'Cmatic_License_State_Resolver' )
			? Cmatic_License_State_Resolver::expires_at()
			: 0;
	}

	public static function render(): void {}

	public static function handle_dismiss( string $state ) {
		if ( ! isset( self::SNOOZE[ $state ] ) ) {
			return false;
		}
		$user_id    = get_current_user_id();
		$dismissals = get_user_meta( $user_id, self::META_KEY, true );
		if ( ! is_array( $dismissals ) ) {
			$dismissals = array();
		}
		$dismissals[ $state ] = time() + self::SNOOZE[ $state ];
		update_user_meta( $user_id, self::META_KEY, $dismissals );
		return $dismissals[ $state ];
	}
}
