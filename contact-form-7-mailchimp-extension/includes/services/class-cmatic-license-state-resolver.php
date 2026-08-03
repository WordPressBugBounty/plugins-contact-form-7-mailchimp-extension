<?php
/**
 * Resolve ChimpMatic Pro license state across released licensing generations.
 *
 * This is a display-only compatibility boundary for Lite's admin banner. Pro
 * remains the authority for feature access and license enforcement.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

final class Cmatic_License_State_Resolver {

	private const MODERN_VERSION = '1.8.0.00';

	private const STATE_ACTIVE  = 'active';
	private const STATE_EXPIRED = 'expired';
	private const STATE_INVALID = 'invalid';
	private const STATE_NONE    = 'none';

	/**
	 * Resolve the current Pro license state.
	 *
	 * @return string Empty when ChimpMatic Pro is not loaded, otherwise active,
	 *                expired, invalid, or none.
	 */
	public static function resolve(): string {
		$version = self::pro_version();
		if ( '' === $version ) {
			return '';
		}

		if ( version_compare( $version, self::MODERN_VERSION, '>=' ) ) {
			return self::resolve_modern();
		}

		if ( defined( 'CMATIC_VERSION' ) || defined( 'CHIMPMATIC_VERSION' ) ) {
			return self::resolve_transitional();
		}

		return self::resolve_legacy();
	}

	/**
	 * Return the current activation expiry as a Unix timestamp.
	 */
	public static function expires_at(): int {
		$value      = null;
		$activation = self::activation();

		if ( is_object( $activation ) && method_exists( $activation, 'get_expires_at' ) ) {
			try {
				$value = $activation->get_expires_at();
			} catch ( \Throwable $error ) {
				unset( $error );
			}
		}

		if ( null === $value || '' === $value ) {
			$stored = self::activation_data();
			$value  = $stored['expires_at'] ?? null;
		}

		return self::timestamp( $value );
	}

	/**
	 * Resolve signed-entitlement releases (1.8.0.00+).
	 */
	private static function resolve_modern(): string {
		if ( get_option( 'chimpmatic_test_expired_license' ) ) {
			return self::STATE_EXPIRED;
		}

		$runtime_state = self::runtime_state();
		if ( in_array( $runtime_state, array( self::STATE_ACTIVE, self::STATE_EXPIRED, self::STATE_INVALID ), true ) ) {
			return $runtime_state;
		}

		$stored_state = self::option_string( 'chimpmatic_license_status' );
		$error_state  = self::option_string( 'chimpmatic_license_error_state' );

		if ( 'expired' === $stored_state || 'expired' === $error_state ) {
			return self::STATE_EXPIRED;
		}

		if ( in_array( $error_state, array( 'invalid', 'no_activations' ), true ) ) {
			return self::STATE_INVALID;
		}

		$activation = self::activation_data();
		if ( ! empty( $activation['license_key'] ) ) {
			return self::STATE_INVALID;
		}

		return self::STATE_NONE;
	}

	/**
	 * Resolve releases that used both an activation object and unified options.
	 */
	private static function resolve_transitional(): string {
		if ( get_option( 'chimpmatic_test_expired_license' ) ) {
			return self::STATE_EXPIRED;
		}

		if ( function_exists( 'cmatic_is_blessed' ) ) {
			try {
				if ( cmatic_is_blessed() ) {
					return self::STATE_ACTIVE;
				}
			} catch ( \Throwable $error ) {
				unset( $error );
			}
		}

		$activation = self::activation();
		if ( is_object( $activation ) && method_exists( $activation, 'is_activated' ) ) {
			try {
				if ( method_exists( $activation, 'is_expired' ) && $activation->is_expired() ) {
					return self::STATE_EXPIRED;
				}
				if ( $activation->is_activated() ) {
					return self::STATE_ACTIVE;
				}
			} catch ( \Throwable $error ) {
				unset( $error );
			}
		}

		$unified = self::option_string( 'cmatic_license_activated' );
		if ( 'activated' === $unified ) {
			return self::STATE_ACTIVE;
		}
		if ( 'deactivated' === $unified ) {
			return self::STATE_EXPIRED;
		}

		$runtime_state = self::runtime_state();
		if ( in_array( $runtime_state, array( self::STATE_ACTIVE, self::STATE_EXPIRED, self::STATE_INVALID ), true ) ) {
			return $runtime_state;
		}

		return self::validated_state( get_option( 'chimpmatic_license_state', self::STATE_NONE ) );
	}

	/**
	 * Resolve the WC API Manager generation used by early Pro releases.
	 */
	private static function resolve_legacy(): string {
		$legacy = self::option_string( 'wc_am_client_chimpmatic_activated' );
		if ( 'activated' === $legacy ) {
			return self::STATE_ACTIVE;
		}

		$unified = self::option_string( 'cmatic_license_activated' );
		if ( 'activated' === $unified || ( '' === $legacy && self::option_int( 'wpcf7-mc-api-tool_unpolluted' ) > 0 ) ) {
			return self::STATE_ACTIVE;
		}

		return self::STATE_NONE;
	}

	/**
	 * Ask Pro's public runtime API before inspecting compatibility options.
	 */
	private static function runtime_state(): string {
		if ( function_exists( 'chimpmatic_get_license_status' ) ) {
			try {
				$status = chimpmatic_get_license_status();
				if ( is_array( $status ) ) {
					$value = self::string_value( $status['status'] ?? '' );
					if ( 'active' === $value ) {
						return self::STATE_ACTIVE;
					}
					if ( 'expired' === $value ) {
						return self::STATE_EXPIRED;
					}
					if ( 'unverified' === $value ) {
						return self::STATE_INVALID;
					}
				}
			} catch ( \Throwable $error ) {
				unset( $error );
			}
		}

		$activation = self::activation();
		if ( ! is_object( $activation ) || ! method_exists( $activation, 'is_activated' ) ) {
			return '';
		}

		try {
			if ( method_exists( $activation, 'is_expired' ) && $activation->is_expired() ) {
				return self::STATE_EXPIRED;
			}
			if ( ! $activation->is_activated() ) {
				return '';
			}
			if ( function_exists( 'chimpmatic_is_licensed' ) && chimpmatic_is_licensed() ) {
				return self::STATE_ACTIVE;
			}
			return self::STATE_INVALID;
		} catch ( \Throwable $error ) {
			unset( $error );
			return '';
		}
	}

	/**
	 * Return Pro's activation object when its client is available.
	 *
	 * @return object|null
	 */
	private static function activation() {
		if ( ! function_exists( 'chimpmatic_license' ) ) {
			return null;
		}

		try {
			$client = chimpmatic_license();
			return is_object( $client ) && method_exists( $client, 'get_activation' )
				? $client->get_activation()
				: null;
		} catch ( \Throwable $error ) {
			unset( $error );
			return null;
		}
	}

	/**
	 * Return the stored activation without exposing its license key.
	 */
	private static function activation_data(): array {
		$activation = get_option( 'chimpmatic_license_activation', array() );
		return is_array( $activation ) ? $activation : array();
	}

	private static function pro_version(): string {
		if ( defined( 'CMATIC_VERSION' ) ) {
			return (string) CMATIC_VERSION;
		}
		if ( defined( 'CHIMPMATIC_VERSION' ) ) {
			return (string) CHIMPMATIC_VERSION;
		}
		if ( defined( 'SPARTAN_CHM_VERSION' ) ) {
			return (string) SPARTAN_CHM_VERSION;
		}
		return '';
	}

	private static function validated_state( $state ): string {
		$state = self::string_value( $state );
		return in_array( $state, array( self::STATE_ACTIVE, self::STATE_EXPIRED, self::STATE_INVALID ), true )
			? $state
			: self::STATE_NONE;
	}

	private static function option_string( string $name ): string {
		return self::string_value( get_option( $name, '' ) );
	}

	private static function option_int( string $name ): int {
		$value = get_option( $name, 0 );
		return is_numeric( $value ) ? (int) $value : 0;
	}

	private static function string_value( $value ): string {
		return is_scalar( $value ) ? strtolower( (string) $value ) : '';
	}

	private static function timestamp( $value ): int {
		if ( is_numeric( $value ) ) {
			return max( 0, (int) $value );
		}
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return 0;
		}
		$timestamp = strtotime( $value );
		return false === $timestamp ? 0 : $timestamp;
	}
}
