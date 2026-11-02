<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    hello@chimpmatic.com
 * @copyright 2014-2026 ChimpMatic
 * @license   GPL-3.0+
 */

declare(strict_types=1);

namespace Cmatic\Lite\Provider;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing -- Typed signatures follow the existing Lite class convention.
final class Protocol {
	/** @param array<mixed> $spec */
	public static function validate( array $spec ): void {
		foreach ( $spec as $key => $value ) {
			if ( ! in_array( $key, array( 'lists', 'fields', 'subscribe', 'lookup', 'query_key', 'envelope' ), true ) || ( 'query_key' === $key ? ! is_string( $value ) || 1 !== preg_match( '/^[A-Za-z][A-Za-z0-9_]*$/', $value ) : ! is_array( $value ) ) ) {
				throw new \InvalidArgumentException( 'Invalid provider protocol section.' );
			}
		}
		self::validate_paths( $spec );
		foreach ( array( 'lists', 'fields' ) as $name ) {
			if ( ! isset( $spec[ $name ] ) ) {
				continue;
			}
			if ( ! is_array( $spec[ $name ] ) ) {
				throw new \InvalidArgumentException( 'Invalid collection specification.' );
			}
			self::validate_collection( $spec[ $name ] );
		}
		if ( isset( $spec['subscribe'] ) ) {
			$send = $spec['subscribe'];
			if ( ! is_array( $send ) || empty( $send['path'] ) || empty( $send['email'] ) || ! in_array( $send['format'] ?? '', array( 'map', 'repeat', 'arrays', 'pairs' ), true ) || ! in_array( $send['method'] ?? 'POST', array( 'POST', 'PUT', 'PATCH' ), true ) ) {
				throw new \InvalidArgumentException( 'Invalid subscription protocol.' );
			}
			self::keys( $send, array( 'path', 'email', 'list', 'name', 'fields', 'format', 'field_key', 'field_value', 'defaults', 'ack_email', 'codes', 'queued', 'duplicates', 'create_only', 'preserve_errors', 'status_path', 'status_map', 'method' ) );
			foreach ( array( 'email', 'list', 'name', 'fields', 'ack_email', 'status_path' ) as $path ) {
				if ( isset( $send[ $path ] ) && ( ! is_string( $send[ $path ] ) || 1 !== preg_match( '/^[A-Za-z_][A-Za-z0-9_.]*$/', $send[ $path ] ) ) ) {
					throw new \InvalidArgumentException( 'Invalid subscription data path.' );
				}
			}
			if ( empty( $send['fields'] ) || ( in_array( $send['format'], array( 'repeat', 'arrays' ), true ) && ( empty( $send['field_key'] ) || empty( $send['field_value'] ) ) ) ) {
				throw new \InvalidArgumentException( 'Custom field encoding requires its declared keys.' );
			}
			foreach ( array( 'queued', 'create_only', 'preserve_errors' ) as $flag ) {
				if ( isset( $send[ $flag ] ) && ! is_bool( $send[ $flag ] ) ) {
					throw new \InvalidArgumentException( 'Subscription flags must be booleans.' );
				}
			}
			foreach ( array( 'codes', 'duplicates', 'defaults', 'status_map' ) as $map ) {
				if ( isset( $send[ $map ] ) && ! is_array( $send[ $map ] ) ) {
					throw new \InvalidArgumentException( 'Subscription maps must be arrays.' );
				}
			}
			if ( ! empty( $send['create_only'] ) && empty( $spec['lookup'] ) ) {
				throw new \InvalidArgumentException( 'Create-only subscriptions require a lookup.' );
			}
		}
		foreach ( array(
			'lookup'   => array( 'path', 'root', 'email', 'absent_status', 'absent_path', 'absent_value', 'absent_error_prefix' ),
			'envelope' => array( 'code', 'success', 'error' ),
		) as $section => $keys ) {
			if ( isset( $spec[ $section ] ) && is_array( $spec[ $section ] ) ) {
				self::keys( $spec[ $section ], $keys );
			}
		}
	}

	/**
	 * @param array<mixed> $spec
	 * @param array<string> $keys
	 */
	private static function keys( array $spec, array $keys ): void {
		if ( array() !== array_diff( array_keys( $spec ), $keys ) ) {
			throw new \InvalidArgumentException( 'Unknown provider protocol key.' );
		}
	}

	/** @param array<mixed> $spec */
	private static function validate_collection( array $spec ): void {
		self::keys( $spec, array( 'path', 'root', 'id', 'name', 'limit', 'deduplicate', 'children', 'paging', 'total', 'current', 'next', 'cursor', 'cursor_parameter' ) );
		foreach ( array( 'root', 'id', 'name', 'total', 'current', 'next', 'cursor' ) as $key ) {
			if ( isset( $spec[ $key ] ) && ! is_string( $spec[ $key ] ) ) {
				throw new \InvalidArgumentException( 'Collection paths must be strings.' );
			}
		}
		if ( isset( $spec['limit'] ) && ( ! is_int( $spec['limit'] ) || $spec['limit'] < 1 || $spec['limit'] > 1000 ) ) {
			throw new \InvalidArgumentException( 'Invalid collection page limit.' );
		}
		if ( ! in_array( $spec['paging'] ?? 'none', array( 'none', 'headers', 'pages', 'cursor' ), true ) ) {
			throw new \InvalidArgumentException( 'Invalid collection pagination.' );
		}
		if ( isset( $spec['children'] ) ) {
			if ( ! is_array( $spec['children'] ) || isset( $spec['children']['children'] ) ) {
				throw new \InvalidArgumentException( 'Only one catalog nesting level is supported.' );
			}
			self::validate_collection( $spec['children'] );
		}
	}

	/** @param array<mixed> $spec */
	private static function validate_paths( array $spec ): void {
		foreach ( $spec as $key => $value ) {
			if ( is_array( $value ) ) {
				self::validate_paths( $value );
			} elseif ( 'path' === $key ) {
				if ( ! is_string( $value ) || 1 !== preg_match( '#^/(?!/)[^\s]*$#', $value ) || false !== strpos( $value, '://' ) || false !== strpos( $value, '..' ) || false !== strpos( $value, '#' ) ) {
					throw new \InvalidArgumentException( 'Protocol endpoints must be local absolute paths.' );
				}
				$rest = str_replace( array( '{list_id}', '{parent_id}', '{page}', '{email}', '{email_hash}' ), '', $value );
				if ( false !== strpos( $rest, '{' ) || false !== strpos( $rest, '}' ) ) {
					throw new \InvalidArgumentException( 'Unknown endpoint placeholder.' );
				}
			}
		}
	}

	/**
	 * @param array<mixed> $data
	 * @return mixed
	 */
	public static function read( array $data, string $path ) {
		$value = $data;
		foreach ( '' === $path ? array() : explode( '.', $path ) as $key ) {
			if ( ! is_array( $value ) || ! array_key_exists( $key, $value ) ) {
				return null;
			}
			$value = $value[ $key ];
		}
		return $value;
	}

	/** @param array<mixed> $data */
	public static function has( array $data, string $path ): bool {
		$keys = explode( '.', $path );
		$key  = array_pop( $keys );
		$node = self::read( $data, implode( '.', $keys ) );
		return is_array( $node ) && array_key_exists( $key, $node );
	}

	/**
	 * @param array<mixed> $data
	 * @param mixed $value
	 */
	public static function put( array &$data, string $path, $value ): void {
		$target = &$data;
		foreach ( explode( '.', $path ) as $key ) {
			if ( ! isset( $target[ $key ] ) || ! is_array( $target[ $key ] ) ) {
				$target[ $key ] = array();
			}
			$target = &$target[ $key ];
		}
		$target = $value;
	}

	/** @param mixed $value */
	public static function text( $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	}

	/** @param mixed $value */
	public static function page( $value ): ?int {
		return ( is_string( $value ) || is_int( $value ) ) && 1 === preg_match( '/^(0|[1-9][0-9]{0,5})$/', (string) $value ) ? (int) $value : null;
	}

	/**
	 * @param mixed $value
	 * @return mixed
	 */
	public static function redact( $value, string $secret ) {
		if ( '' === $secret ) {
			return $value;
		}
		if ( is_array( $value ) ) {
			$clean = array();
			foreach ( $value as $key => $item ) {
				$clean[ is_string( $key ) ? self::text( self::redact( $key, $secret ) ) : $key ] = self::redact( $item, $secret );
			}
			return $clean;
		}
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode -- Provider errors can echo form-encoded query credentials.
		return is_string( $value ) ? str_replace( array_unique( array( $secret, rawurlencode( $secret ), urlencode( $secret ) ) ), '[redacted]', $value ) : $value;
	}
}
