<?php
/**
 * Declarative rich-observation sanitizer.
 *
 * @package signls-sdk
 * @license GPL-3.0-or-later
 */

namespace Signls\Sdk\V1;

defined( 'ABSPATH' ) || exit;

final class ObservationSanitizer {

	private const TYPES = array(
		'object',
		'list',
		'map',
		'bool',
		'uint',
		'int',
		'decimal',
		'enum',
		'slug',
		'version',
		'text',
		'url',
		'sha256',
	);

	public static function sanitize( array $source, array $schema ): array {
		if ( 'object' !== self::type( $schema ) ) {
			return array();
		}
		$value = self::node( $source, $schema );
		return is_array( $value ) ? $value : array();
	}

	private static function node( $value, array $schema ) {
		$type     = self::type( $schema );
		$nullable = ! empty( $schema['nullable'] );
		if ( null === $value && $nullable ) {
			return null;
		}

		switch ( $type ) {
			case 'object':
				return self::object( $value, $schema );
			case 'list':
				return self::list_value( $value, $schema );
			case 'map':
				return self::map_value( $value, $schema );
			case 'bool':
				return Sanitizer::bool( $value );
			case 'uint':
				return Sanitizer::int( $value, 0, self::integer_bound( $schema, 'max', 4294967295 ) );
			case 'int':
				return Sanitizer::int(
					$value,
					self::integer_bound( $schema, 'min', -2147483648 ),
					self::integer_bound( $schema, 'max', 2147483647 )
				);
			case 'decimal':
				return Sanitizer::decimal(
					$value,
					self::float_bound( $schema, 'min', 0.0 ),
					self::float_bound( $schema, 'max', 4294967295.0 ),
					self::integer_bound( $schema, 'scale', 4 )
				);
			case 'enum':
				$allowed  = self::allowed( $schema );
				$fallback = isset( $schema['fallback'] ) && is_string( $schema['fallback'] ) ? $schema['fallback'] : '';
				return Sanitizer::enum( $value, $allowed, $fallback );
			case 'slug':
				return Sanitizer::slug( $value, self::length( $schema, 48 ), self::fallback( $schema ) );
			case 'version':
				return Sanitizer::version( $value, self::length( $schema, 20 ) );
			case 'text':
				return Sanitizer::text( $value, self::length( $schema, 191 ) );
			case 'url':
				return Sanitizer::url( $value, self::length( $schema, 500 ) );
			case 'sha256':
				return Sanitizer::sha256( $value );
			default:
				return $nullable ? null : '';
		}
	}

	private static function object( $value, array $schema ): array {
		$source     = is_array( $value ) ? $value : array();
		$properties = isset( $schema['properties'] ) && is_array( $schema['properties'] ) ? $schema['properties'] : array();
		$result     = array();
		foreach ( $properties as $key => $property ) {
			if ( ! is_string( $key ) || ! is_array( $property ) || ! array_key_exists( $key, $source ) ) {
				continue;
			}
			$result[ $key ] = self::node( $source[ $key ], $property );
		}
		return $result;
	}

	private static function list_value( $value, array $schema ) {
		$accounted = ! empty( $schema['with_accounting'] );
		$source    = is_array( $value ) ? $value : array();
		$rows      = $accounted && isset( $source['items'] ) && is_array( $source['items'] ) ? $source['items'] : $source;
		$item      = isset( $schema['items'] ) && is_array( $schema['items'] ) ? $schema['items'] : array();
		$maximum   = max( 1, min( 1000, self::integer_bound( $schema, 'max_items', 1 ) ) );
		$clean     = array();
		foreach ( array_slice( $rows, 0, $maximum ) as $row ) {
			$clean[] = self::node( $row, $item );
		}

		$sort_key = isset( $schema['sort_key'] ) && is_string( $schema['sort_key'] ) ? $schema['sort_key'] : '';
		if ( '' !== $sort_key ) {
			usort(
				$clean,
				static function ( $left, $right ) use ( $sort_key ) {
					$left_candidate  = is_array( $left ) && isset( $left[ $sort_key ] ) ? $left[ $sort_key ] : '';
					$right_candidate = is_array( $right ) && isset( $right[ $sort_key ] ) ? $right[ $sort_key ] : '';
					$left_value      = is_scalar( $left_candidate ) ? (string) $left_candidate : '';
					$right_value     = is_scalar( $right_candidate ) ? (string) $right_candidate : '';
					return strcmp( $left_value, $right_value );
				}
			);
		}

		if ( ! $accounted ) {
			return $clean;
		}
		$reported = isset( $source['reported_total'] ) ? Sanitizer::counter( $source['reported_total'] ) : count( $rows );
		$reported = max( $reported, count( $rows ) );
		return array(
			'items'          => $clean,
			'reported_total' => $reported,
			'truncated'      => ! empty( $source['truncated'] ) || $reported > count( $clean ),
		);
	}

	private static function map_value( $value, array $schema ): array {
		$source     = is_array( $value ) ? $value : array();
		$value_rule = isset( $schema['values'] ) && is_array( $schema['values'] ) ? $schema['values'] : array();
		$maximum    = max( 1, min( 1000, self::integer_bound( $schema, 'max_items', 1 ) ) );
		$key_length = max( 1, min( 100, self::integer_bound( $schema, 'max_key_length', 48 ) ) );
		$result     = array();
		ksort( $source, SORT_STRING );
		foreach ( $source as $key => $item ) {
			$key = Sanitizer::slug( $key, $key_length );
			if ( '' === $key || isset( $result[ $key ] ) ) {
				continue;
			}
			$result[ $key ] = self::node( $item, $value_rule );
			if ( count( $result ) >= $maximum ) {
				break;
			}
		}
		return $result;
	}

	private static function type( array $schema ): string {
		$type = isset( $schema['type'] ) && is_string( $schema['type'] ) ? $schema['type'] : '';
		return in_array( $type, self::TYPES, true ) ? $type : '';
	}

	private static function allowed( array $schema ): array {
		$values = isset( $schema['allowed'] ) && is_array( $schema['allowed'] ) ? $schema['allowed'] : array();
		return array_values(
			array_filter(
				$values,
				static function ( $value ) {
					return is_string( $value );
				}
			)
		);
	}

	private static function fallback( array $schema ): string {
		return isset( $schema['fallback'] ) && is_string( $schema['fallback'] ) ? $schema['fallback'] : '';
	}

	private static function length( array $schema, int $fallback ): int {
		return max( 1, min( 4096, self::integer_bound( $schema, 'max_length', $fallback ) ) );
	}

	private static function integer_bound( array $schema, string $key, int $fallback ): int {
		return isset( $schema[ $key ] ) && is_int( $schema[ $key ] ) ? $schema[ $key ] : $fallback;
	}

	private static function float_bound( array $schema, string $key, float $fallback ): float {
		return isset( $schema[ $key ] ) && is_numeric( $schema[ $key ] ) ? (float) $schema[ $key ] : $fallback;
	}
}
