<?php
/**
 * Closed aggregate value sanitizer.
 *
 * @package signls-sdk
 * @license GPL-3.0-or-later
 */

namespace Signls\Sdk\V1;

defined( 'ABSPATH' ) || exit;

final class Sanitizer {

	public static function slug( $value, int $max = 48, string $fallback = '' ): string {
		$value = is_string( $value ) ? strtolower( $value ) : '';
		return 1 === preg_match( '/^[a-z0-9][a-z0-9_-]{0,' . ( max( 1, $max ) - 1 ) . '}$/', $value ) ? $value : $fallback;
	}

	public static function version( $value, int $maximum = 20 ): string {
		$maximum = max( 1, min( 64, $maximum ) );
		$value   = is_string( $value ) ? $value : '';
		return 1 === preg_match( '/^[A-Za-z0-9][A-Za-z0-9._+-]{0,' . ( $maximum - 1 ) . '}$/', $value ) ? $value : 'unknown';
	}

	public static function counter( $value ): int {
		if ( ! is_int( $value ) && ! ctype_digit( (string) $value ) ) {
			return 0;
		}
		$value = (int) $value;
		return max( 0, min( 4294967295, $value ) );
	}

	public static function age( $value ) {
		if ( null === $value ) {
			return null;
		}
		return self::counter( $value );
	}

	public static function bool( $value ): bool {
		return true === $value || 1 === $value || '1' === $value;
	}

	public static function int( $value, int $minimum, int $maximum ): int {
		if ( ! is_int( $value ) && ! ( is_string( $value ) && 1 === preg_match( '/^-?[0-9]+$/', $value ) ) ) {
			return $minimum;
		}
		return max( $minimum, min( $maximum, (int) $value ) );
	}

	public static function decimal( $value, float $minimum, float $maximum, int $scale = 4 ): float {
		if ( ! is_numeric( $value ) ) {
			return $minimum;
		}
		$value = (float) $value;
		if ( is_nan( $value ) || is_infinite( $value ) ) {
			return $minimum;
		}
		return round( max( $minimum, min( $maximum, $value ) ), max( 0, min( 8, $scale ) ) );
	}

	public static function enum( $value, array $allowed, string $fallback = '' ): string {
		$value = is_string( $value ) ? $value : '';
		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	public static function text( $value, int $maximum ): string {
		if ( ! is_string( $value ) || $maximum < 1 || 1 !== preg_match( '//u', $value ) || 1 === preg_match( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value ) ) {
			return '';
		}
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $maximum, 'UTF-8' );
		}
		if ( false !== preg_match_all( '/./us', $value, $characters ) ) {
			return implode( '', array_slice( $characters[0], 0, $maximum ) );
		}
		return '';
	}

	public static function url( $value, int $maximum = 500 ): string {
		$value = self::text( $value, $maximum );
		if ( '' === $value ) {
			return '';
		}
		$parts = wp_parse_url( $value );
		if ( ! is_array( $parts ) || ! isset( $parts['scheme'], $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return '';
		}
		$scheme = strtolower( (string) $parts['scheme'] );
		return in_array( $scheme, array( 'http', 'https' ), true ) ? $value : '';
	}

	public static function sha256( $value ): string {
		$value = is_string( $value ) ? $value : '';
		return 1 === preg_match( '/^[a-f0-9]{64}$/', $value ) ? $value : '';
	}
}
