<?php
/**
 * Shared random WordPress-origin identity.
 *
 * @package signls-sdk
 * @license GPL-3.0-or-later
 */

namespace Signls\Sdk\V1;

defined( 'ABSPATH' ) || exit;

final class SiteIdentity {

	private const ID_OPTION = 'signls_sdk_site_id';

	private const ORIGIN_OPTION = 'signls_sdk_site_origin';

	public function site_id(): string {
		$current_origin          = $this->origin_fingerprint();
		$stored_origin_candidate = get_option( self::ORIGIN_OPTION, '' );
		$site_id_candidate       = get_option( self::ID_OPTION, '' );
		$stored_origin           = is_scalar( $stored_origin_candidate ) ? (string) $stored_origin_candidate : '';
		$site_id                 = is_scalar( $site_id_candidate ) ? (string) $site_id_candidate : '';

		if ( ! self::valid( $site_id ) || ( '' !== $stored_origin && ! hash_equals( $stored_origin, $current_origin ) ) ) {
			$site_id = bin2hex( random_bytes( 16 ) );
			self::replace_option( self::ID_OPTION, $site_id );
			self::replace_option( self::ORIGIN_OPTION, $current_origin );
		} elseif ( '' === $stored_origin ) {
			self::replace_option( self::ORIGIN_OPTION, $current_origin );
		}

		return $site_id;
	}

	private function origin_fingerprint(): string {
		$origin = function_exists( 'home_url' ) ? (string) home_url( '/' ) : 'http://local/';
		$parts  = wp_parse_url( $origin );
		$parts  = is_array( $parts ) ? $parts : array();
		$scheme = isset( $parts['scheme'] ) ? strtolower( (string) $parts['scheme'] ) : 'http';
		$host   = isset( $parts['host'] ) ? strtolower( rtrim( (string) $parts['host'], '.' ) ) : 'local';
		$port   = isset( $parts['port'] ) ? (int) $parts['port'] : ( 'https' === $scheme ? 443 : 80 );
		$path   = isset( $parts['path'] ) ? '/' . trim( (string) $parts['path'], '/' ) : '/';
		$path   = '/' === $path ? $path : $path . '/';
		$salt   = function_exists( 'wp_salt' ) ? (string) wp_salt( 'auth' ) : 'signls-test-salt';
		return hash_hmac( 'sha256', $scheme . '://' . $host . ':' . $port . $path, $salt );
	}

	private static function replace_option( string $name, string $value ): bool {
		if ( false === get_option( $name, false ) ) {
			return add_option( $name, $value, '', false );
		}
		return update_option( $name, $value, false );
	}

	private static function valid( string $value ): bool {
		return 1 === preg_match( '/^[a-f0-9]{32}$/', $value );
	}
}
