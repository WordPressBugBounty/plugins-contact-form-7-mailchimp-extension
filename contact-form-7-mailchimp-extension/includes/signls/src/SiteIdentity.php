<?php
/**
 * Shared random WordPress-origin identity.
 *
 * The origin fingerprint is derived from the stored `home` option, read
 * unfiltered from the options table, so request-scoped values (WPML or
 * Polylang language prefixes and domains, dynamic WP_HOME, proxies that flip
 * the scheme, scanners hitting the server by IP) can never rotate the site.
 * A fingerprint-version marker lets an upgrade re-key the stored value
 * without rotating, and a 24-hour cap bounds rotations on a real change.
 *
 * @package signls-sdk
 * @license GPL-3.0-or-later
 */

namespace Signls\Sdk\V1;

defined( 'ABSPATH' ) || exit;

final class SiteIdentity {

	private const ID_OPTION = 'signls_sdk_site_id';

	private const ORIGIN_OPTION = 'signls_sdk_site_origin';

	private const ORIGIN_VERSION_OPTION = 'signls_sdk_site_origin_version';

	private const ROTATED_AT_OPTION = 'signls_sdk_site_rotated_at';

	/** Fingerprint formula version. 1 = home_url('/') (SDK <= 1.1.12), 2 = stored home option (SDK >= 1.1.13). */
	public const ORIGIN_VERSION = 2;

	public function site_id(): string {
		$current_origin          = $this->origin_fingerprint();
		$stored_origin_candidate = get_option( self::ORIGIN_OPTION, '' );
		$site_id_candidate       = get_option( self::ID_OPTION, '' );
		$version_candidate       = get_option( self::ORIGIN_VERSION_OPTION, 0 );
		$stored_origin           = is_scalar( $stored_origin_candidate ) ? (string) $stored_origin_candidate : '';
		$site_id                 = is_scalar( $site_id_candidate ) ? (string) $site_id_candidate : '';
		$version                 = is_scalar( $version_candidate ) ? (int) $version_candidate : 0;

		if ( ! self::valid( $site_id ) ) {
			$site_id = bin2hex( random_bytes( 16 ) );
			self::replace_option( self::ID_OPTION, $site_id );
			self::replace_option( self::ORIGIN_OPTION, $current_origin );
			self::replace_option( self::ORIGIN_VERSION_OPTION, (string) self::ORIGIN_VERSION );
			return $site_id;
		}

		if ( '' === $stored_origin || $version < self::ORIGIN_VERSION ) {
			// First run on this formula, or an upgrade from an older formula: re-key, never rotate.
			self::replace_option( self::ORIGIN_OPTION, $current_origin );
			self::replace_option( self::ORIGIN_VERSION_OPTION, (string) self::ORIGIN_VERSION );
			return $site_id;
		}

		if ( ! hash_equals( $stored_origin, $current_origin ) ) {
			$rotated_at_candidate = get_option( self::ROTATED_AT_OPTION, 0 );
			$rotated_at           = is_scalar( $rotated_at_candidate ) ? (int) $rotated_at_candidate : 0;
			if ( time() - $rotated_at < DAY_IN_SECONDS ) {
				self::notify( 'signls_sdk_v1_site_rotation_suppressed', 'origin_changed', $stored_origin, $current_origin );
				return $site_id;
			}
			$site_id = bin2hex( random_bytes( 16 ) );
			self::replace_option( self::ID_OPTION, $site_id );
			self::replace_option( self::ORIGIN_OPTION, $current_origin );
			self::replace_option( self::ORIGIN_VERSION_OPTION, (string) self::ORIGIN_VERSION );
			self::replace_option( self::ROTATED_AT_OPTION, (string) time() );
			self::notify( 'signls_sdk_v1_site_rotated', 'origin_changed', $stored_origin, $current_origin );
		}

		return $site_id;
	}

	/**
	 * Version-2 fingerprint: HMAC of the normalized stored home option.
	 */
	public function origin_fingerprint(): string {
		$salt = function_exists( 'wp_salt' ) ? (string) wp_salt( 'auth' ) : 'signls-test-salt';
		return hash_hmac( 'sha256', 'v2|' . self::normalized_origin( self::stored_home() ), $salt );
	}

	/**
	 * Version-1 fingerprint (SDK 1.1.11 and 1.1.12): HMAC of scheme://host:port/path of home_url('/').
	 * Kept only so an upgrade can tell a continued install from a pre-upgrade clone.
	 */
	public function legacy_origin_fingerprint(): string {
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

	/**
	 * The `home` option as stored in this blog's options table, bypassing WP_HOME and option filters.
	 * Falls back to get_option() and then home_url() only when the table cannot be read.
	 */
	public static function stored_home(): string {
		global $wpdb;
		$raw = null;
		if ( is_object( $wpdb ) && isset( $wpdb->options ) && method_exists( $wpdb, 'get_var' ) && method_exists( $wpdb, 'prepare' ) ) {
			try {
				$raw = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Unfiltered read of the stored origin is the point.
					$wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", 'home' ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Core table name.
				);
			} catch ( \Throwable $error ) {
				$raw = null;
			}
		}
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			$fallback = function_exists( 'get_option' ) ? get_option( 'home', '' ) : '';
			$raw      = is_string( $fallback ) ? $fallback : '';
		}
		if ( '' === trim( $raw ) ) {
			$raw = function_exists( 'home_url' ) ? (string) home_url( '/' ) : 'http://local/';
		}
		return trim( $raw );
	}

	/**
	 * host[:port][/path] in lowercase, without scheme, without the default ports, without edge slashes.
	 * An http to https move, a trailing slash or a language prefix on the request must not change it.
	 */
	public static function normalized_origin( string $url ): string {
		$parts = wp_parse_url( trim( $url ) );
		$parts = is_array( $parts ) ? $parts : array();
		if ( ! isset( $parts['host'] ) && isset( $parts['path'] ) && false === strpos( (string) $parts['path'], '/' ) ) {
			$parts = wp_parse_url( 'http://' . trim( $url ) );
			$parts = is_array( $parts ) ? $parts : array();
		}
		$host = isset( $parts['host'] ) ? strtolower( rtrim( (string) $parts['host'], '.' ) ) : 'local';
		$port = isset( $parts['port'] ) ? (int) $parts['port'] : 0;
		$path = isset( $parts['path'] ) ? trim( (string) $parts['path'], '/' ) : '';
		return $host . ( $port > 0 && ! in_array( $port, array( 80, 443 ), true ) ? ':' . $port : '' ) . ( '' !== $path ? '/' . $path : '' );
	}

	private static function notify( string $hook, string $reason, string $old_fingerprint, string $new_fingerprint ): void {
		if ( function_exists( 'do_action' ) ) {
			do_action( $hook, $reason, substr( $old_fingerprint, 0, 12 ), substr( $new_fingerprint, 0, 12 ) );
		}
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
