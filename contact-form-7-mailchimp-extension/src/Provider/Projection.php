<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    hello@chimpmatic.com
 * @copyright 2014-2026 ChimpMatic
 * @license   GPL-3.0+
 */

declare(strict_types=1);

namespace Cmatic\Lite\Provider;

use Cmatic\Lite\Settings\MappingSchema;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing -- Typed signatures follow the existing Lite class convention.
final class Projection {
	/**
	 * @param array<mixed> $spec
	 * @param array<mixed> $body
	 * @return array<int,array<mixed>>
	 */
	public static function lists( array $spec, array $body ): array {
		$root   = MappingSchema::str( $spec['root'] ?? '' );
		$source = '' !== $root && isset( $body[ $root ] ) && is_array( $body[ $root ] ) ? $body[ $root ] : array();
		$lists  = array();
		foreach ( $source as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$id   = self::read( $item, MappingSchema::str( $spec['id'] ?? 'id' ) );
			$name = self::read( $item, MappingSchema::str( $spec['name'] ?? 'name' ) );
			if ( null === $id || null === $name ) {
				continue;
			}
			$entry = array(
				'id'   => ! empty( $spec['sanitize_id'] ) ? sanitize_text_field( MappingSchema::str( $id ) ) : MappingSchema::str( $id ),
				'name' => sanitize_text_field( MappingSchema::str( $name ) ),
			);
			$extra = isset( $spec['extra'] ) && is_array( $spec['extra'] ) ? $spec['extra'] : array();
			foreach ( $extra as $key => $path ) {
				$entry[ (string) $key ] = sanitize_key( MappingSchema::str( self::read( $item, MappingSchema::str( $path ) ) ) );
			}
			$count_path     = MappingSchema::str( $spec['member_count'] ?? '' );
			$entry['stats'] = array(
				'member_count'      => '' === $count_path ? 0 : self::int( self::read( $item, $count_path ) ),
				'merge_field_count' => self::int( $spec['merge_field_count'] ?? 0 ),
			);
			$lists[]        = $entry;
		}
		return $lists;
	}

	/**
	 * @param array<mixed> $spec
	 * @param array<mixed> $body
	 * @return array<int,array<mixed>>
	 */
	public static function fields( array $spec, array $body ): array {
		if ( isset( $spec['static'] ) && is_array( $spec['static'] ) ) {
			$static = array();
			foreach ( $spec['static'] as $field ) {
				if ( is_array( $field ) ) {
					$static[] = $field;
				}
			}
			return $static;
		}
		$fields = array(
			array(
				'tag'           => 'EMAIL',
				'name'          => MappingSchema::str( $spec['email_label'] ?? 'Email' ),
				'type'          => 'email',
				'display_order' => 0,
			),
		);
		if ( isset( $spec['name_label'] ) ) {
			$fields[] = array(
				'tag'           => 'NAME',
				'name'          => MappingSchema::str( $spec['name_label'] ),
				'type'          => 'text',
				'display_order' => 1,
			);
		}
		$root   = MappingSchema::str( $spec['root'] ?? '' );
		$source = '' !== $root && isset( $body[ $root ] ) && is_array( $body[ $root ] ) ? $body[ $root ] : array();
		$filter = isset( $spec['filter'] ) && is_array( $spec['filter'] ) ? $spec['filter'] : array();
		$skip   = isset( $spec['skip_tag_ci'] ) && is_array( $spec['skip_tag_ci'] ) ? $spec['skip_tag_ci'] : array();
		$falsy  = ! empty( $spec['skip_falsy_tag'] );
		$tags   = isset( $spec['tag'] ) && is_array( $spec['tag'] ) ? $spec['tag'] : array( 'tag' );
		$names  = isset( $spec['name'] ) && is_array( $spec['name'] ) ? $spec['name'] : array( 'name' );
		foreach ( $source as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$keep = true;
			foreach ( $filter as $key => $value ) {
				if ( ( $item[ (string) $key ] ?? '' ) !== $value ) {
					$keep = false;
				}
			}
			if ( ! $keep ) {
				continue;
			}
			$tag = self::first( $item, $tags );
			if ( null === $tag || '' === $tag || ( $falsy && '0' === $tag ) || in_array( strtolower( $tag ), $skip, true ) ) {
				continue;
			}
			$name = self::first( $item, $names );
			if ( null === $name ) {
				$name = $tag;
			}
			$type_key = MappingSchema::str( $spec['type'] ?? 'type' );
			$type     = array_key_exists( $type_key, $item ) && null !== $item[ $type_key ] ? MappingSchema::str( $item[ $type_key ] ) : MappingSchema::str( $spec['type_default'] ?? 'text' );
			if ( isset( $spec['type_keys'] ) && is_array( $spec['type_keys'] ) ) {
				$type = self::first( $item, $spec['type_keys'] ) ?? MappingSchema::str( $spec['type_default'] ?? 'text' );
			}
			if ( isset( $spec['format_keys'], $spec['formats'] ) && is_array( $spec['format_keys'] ) && is_array( $spec['formats'] ) ) {
				$format = self::first( $item, $spec['format_keys'] );
				$type   = null !== $format && in_array( $format, $spec['formats'], true ) ? $format : $type;
			}
			$type_map = isset( $spec['type_map'] ) && is_array( $spec['type_map'] ) ? $spec['type_map'] : array();
			$type     = isset( $type_map[ $type ] ) ? MappingSchema::str( $type_map[ $type ] ) : $type;
			$fields[] = array(
				'tag'           => sanitize_text_field( $tag ),
				'name'          => sanitize_text_field( $name ),
				'type'          => sanitize_key( $type ),
				'display_order' => count( $fields ),
			);
		}
		return $fields;
	}

	/**
	 * @param array<mixed> $item
	 * @param array<mixed> $candidates
	 */
	private static function first( array $item, array $candidates ): ?string {
		foreach ( $candidates as $candidate ) {
			$key = MappingSchema::str( $candidate );
			if ( array_key_exists( $key, $item ) && null !== $item[ $key ] ) {
				return MappingSchema::str( $item[ $key ] );
			}
		}
		return null;
	}

	/**
	 * @param array<mixed> $item
	 * @return mixed
	 */
	private static function read( array $item, string $path ) {
		$value = $item;
		foreach ( explode( '.', $path ) as $segment ) {
			if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
				return null;
			}
			$value = $value[ $segment ];
		}
		return $value;
	}

	/**
	 * @param mixed $value
	 */
	public static function int( $value ): int {
		if ( is_array( $value ) ) {
			return array() === $value ? 0 : 1;
		}
		return is_scalar( $value ) ? (int) $value : 0;
	}
}
