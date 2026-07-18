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

	public static function version( $value ): string {
		$value = is_string( $value ) ? $value : '';
		return 1 === preg_match( '/^[A-Za-z0-9][A-Za-z0-9._+-]{0,19}$/', $value ) ? $value : 'unknown';
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
}
