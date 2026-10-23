<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    hello@chimpmatic.com
 * @copyright 2014-2026 ChimpMatic
 * @license   GPL-3.0+
 */

declare(strict_types=1);

namespace Cmatic\Lite\Settings;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing -- Typed signatures follow the existing Lite class convention.
final class MappingSchema {
	const SLOT_BASE   = 3;
	const SLOT_MAX    = 52;
	const MIRROR_ROWS = 50;
	const EMAIL_TAG   = 'EMAIL';

	public static function active_slots( array $section, int $limit ): array {
		$email = array();
		$other = array();
		foreach ( self::arr( $section['merge_fields'] ?? array() ) as $offset => $field ) {
			$slot = self::slot( (int) $offset );
			if ( ! is_array( $field ) || '' === trim( self::str( $section[ $slot ] ?? '' ) ) ) {
				continue;
			}
			if ( 'EMAIL' === strtoupper( self::str( $field['tag'] ?? '' ) ) ) {
				$email[] = $slot;
			} else {
				$other[] = $slot;
			}
		}
		return array_merge( array_slice( $email, 0, 1 ), array_slice( $other, 0, max( 0, $limit - 1 ) ) );
	}


	public static function slot( int $offset ): string {
		return 'field' . ( $offset + self::SLOT_BASE );
	}

	/**
	 * @return array<int,string>
	 */
	public static function slots( int $limit ): array {
		$slots = array();
		for ( $index = self::SLOT_BASE; $index < $limit + self::SLOT_BASE; ++$index ) {
			$slots[] = 'field' . $index;
		}
		return $slots;
	}

	/**
	 * @param mixed $fields
	 * @return array<int,array<string,mixed>>
	 */
	public static function normalize_fields( $fields, int $limit, bool $numeric_only = true ): array {
		if ( ! is_array( $fields ) ) {
			return array();
		}
		$sanitized = array();
		foreach ( array_slice( $fields, 0, $limit ) as $offset => $field ) {
			if ( ! is_array( $field ) || empty( $field['tag'] ) ) {
				continue;
			}
			$tag   = sanitize_text_field( self::str( $field['tag'] ) );
			$order = self::num( $offset );
			if ( isset( $field['display_order'] ) ) {
				$order = $numeric_only ? self::num( $field['display_order'] ) : self::int( $field['display_order'] );
			}
			$sanitized[] = array(
				'tag'           => $tag,
				'name'          => sanitize_text_field( self::str( $field['name'] ?? $tag ) ),
				'type'          => sanitize_key( self::str( $field['type'] ?? 'text' ) ),
				'display_order' => $order,
			);
		}
		return $sanitized;
	}

	/**
	 * @return array<string,string>
	 */
	public static function empty_mappings( int $limit ): array {
		$mappings = array();
		foreach ( self::slots( $limit ) as $slot ) {
			$mappings[ $slot ] = '';
		}
		return $mappings;
	}

	/**
	 * @param array<string,mixed> $section
	 * @return array<string,string>
	 */
	public static function mappings( array $section, int $limit ): array {
		$mappings = array();
		foreach ( self::slots( PHP_INT_MAX === $limit ? count( self::arr( $section['merge_fields'] ?? array() ) ) : $limit ) as $slot ) {
			$mappings[ $slot ] = sanitize_text_field( self::str( $section[ $slot ] ?? '' ) );
		}
		return $mappings;
	}

	/**
	 * @param array<mixed>        $fields
	 * @param array<string,mixed> $section
	 * @return array<string,string>
	 */
	public static function previous_mappings( array $fields, array $section, bool $upper ): array {
		$by_tag = array();
		foreach ( $fields as $offset => $field ) {
			if ( ! is_array( $field ) || ! isset( $field['tag'] ) || ! is_scalar( $field['tag'] ) || '' === (string) $field['tag'] ) {
				continue;
			}
			$key            = $upper ? strtoupper( (string) $field['tag'] ) : (string) $field['tag'];
			$by_tag[ $key ] = sanitize_text_field( self::str( $section[ self::slot( (int) $offset ) ] ?? '' ) );
		}
		return $by_tag;
	}

	/**
	 * @param array<string,string> $by_tag
	 * @param array<mixed>         $new_fields
	 * @return array<string,string>
	 */
	public static function reconcile( array $by_tag, array $new_fields, int $limit, bool $upper ): array {
		$mappings = self::empty_mappings( PHP_INT_MAX === $limit ? count( $new_fields ) : $limit );
		foreach ( array_slice( $new_fields, 0, $limit ) as $offset => $field ) {
			if ( ! is_array( $field ) || ! isset( $field['tag'] ) || ! is_scalar( $field['tag'] ) || '' === (string) $field['tag'] ) {
				continue;
			}
			$key                                     = $upper ? strtoupper( (string) $field['tag'] ) : (string) $field['tag'];
			$mappings[ self::slot( (int) $offset ) ] = $by_tag[ $key ] ?? '';
		}
		return $mappings;
	}

	/**
	 * @param array<mixed> $fields
	 */
	public static function email_offset( array $fields, int $limit, bool $last = false ): ?int {
		$found = null;
		foreach ( array_slice( $fields, 0, $limit, true ) as $offset => $field ) {
			if ( is_array( $field ) && self::EMAIL_TAG === strtoupper( self::str( $field['tag'] ?? '' ) ) ) {
				$found = (int) $offset;
				if ( ! $last ) {
					break;
				}
			}
		}
		return $found;
	}

	/**
	 * @param array<string,mixed> $section
	 */
	public static function has_email_mapping( array $section, int $limit, bool $last = false ): bool {
		$fields = isset( $section['merge_fields'] ) && is_array( $section['merge_fields'] ) ? $section['merge_fields'] : array();
		$offset = self::email_offset( $fields, $limit, $last );
		return null !== $offset && '' !== trim( self::str( $section[ self::slot( $offset ) ] ?? '' ) );
	}

	/**
	 * @param array<string,mixed> $section
	 * @return array<int,string>
	 */
	public static function mapped_tags( array $section ): array {
		$keys = array();
		foreach ( (array) ( $section['merge_fields'] ?? array() ) as $offset => $field ) {
			if ( is_array( $field ) && isset( $field['tag'] ) && is_scalar( $field['tag'] ) && ! empty( $section[ self::slot( (int) $offset ) ] ) ) {
				$keys[] = (string) $field['tag'];
			}
		}
		return $keys;
	}

	/**
	 * @param array<string,mixed> $record
	 * @return array<int,array<string,mixed>>
	 */
	public static function legacy_fields( array $record ): array {
		$own = self::normalize_fields( $record['merge_fields'] ?? array(), self::SLOT_MAX );
		if ( array() !== $own ) {
			return $own;
		}
		if ( ! isset( $record['merge-vars'] ) || ! is_array( $record['merge-vars'] ) ) {
			return array();
		}
		$fields = array(
			array(
				'tag'           => self::EMAIL_TAG,
				'name'          => 'Email',
				'type'          => 'email',
				'display_order' => 0,
			),
		);
		foreach ( $record['merge-vars'] as $field ) {
			if ( is_array( $field ) && ! empty( $field['tag'] ) && self::EMAIL_TAG !== strtoupper( self::str( $field['tag'] ) ) ) {
				$fields[] = array(
					'tag'           => self::str( $field['tag'] ),
					'name'          => self::str( $field['name'] ?? '' ),
					'type'          => self::str( $field['type'] ?? 'text' ),
					'display_order' => count( $fields ),
				);
			}
		}
		return $fields;
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function cleared_mailchimp_account(): array {
		$state = array(
			'list'               => '',
			'merge_fields'       => array(),
			'locked_fields'      => array(),
			'total_merge_fields' => 0,
		);
		foreach ( range( self::SLOT_BASE, self::SLOT_MAX ) as $index ) {
			$state[ 'field' . $index ] = '';
		}
		return $state;
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function cleared_provider_account( int $limit ): array {
		return array_merge(
			array(
				'list'               => '',
				'base_groups'        => array(),
				'routing_rules'      => array(),
				'routing_schema'     => 1,
				'merge_fields'       => array(),
				'total_merge_fields' => 0,
			),
			self::empty_mappings( $limit )
		);
	}

	/**
	 * @param array<string,mixed> $settings
	 * @return array<string,mixed>
	 */
	public static function legacy_mirror( array $settings ): array {
		if ( empty( $settings['merge_fields'] ) || ! is_array( $settings['merge_fields'] ) ) {
			return $settings;
		}
		$row         = 1;
		$field_index = self::SLOT_BASE;
		foreach ( $settings['merge_fields'] as $merge_field ) {
			$tag       = is_array( $merge_field ) && isset( $merge_field['tag'] ) ? self::str( $merge_field['tag'] ) : '';
			$field_key = 'field' . $field_index;
			$mapped    = isset( $settings[ $field_key ] ) ? self::str( $settings[ $field_key ] ) : '';
			++$field_index;
			if ( '' === $tag || '' === $mapped ) {
				continue;
			}
			$field_name = preg_match( '/\[\s*([a-zA-Z_][0-9a-zA-Z:._-]*)\s*\]/', $mapped, $m ) ? $m[1] : trim( $mapped );
			if ( '' === $field_name ) {
				continue;
			}
			$settings[ "CustomKey{$row}" ]     = $tag;
			$settings[ "CustomValue{$row}" ]   = $field_name;
			$settings[ "CustomKeyType{$row}" ] = is_array( $merge_field ) && isset( $merge_field['type'] ) ? self::str( $merge_field['type'] ) : 'text';
			if ( self::EMAIL_TAG === $tag ) {
				$settings['email'] = $field_name;
			}
			++$row;
		}
		for ( $i = $row; $i <= self::MIRROR_ROWS; $i++ ) {
			if ( ! isset( $settings[ "CustomKey{$i}" ] ) && ! isset( $settings[ "CustomValue{$i}" ] ) ) {
				break;
			}
			unset( $settings[ "CustomKey{$i}" ], $settings[ "CustomValue{$i}" ], $settings[ "CustomKeyType{$i}" ] );
		}
		return $settings;
	}

	/**
	 * @param mixed $value
	 */
	public static function str( $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * @param mixed $value
	 */
	public static function num( $value ): int {
		return is_numeric( $value ) ? (int) $value : 0;
	}

	/**
	 * @param mixed $value
	 */
	public static function int( $value ): int {
		return is_scalar( $value ) ? (int) $value : 0;
	}

	public static function arr( $value ): array {
		return is_array( $value ) ? $value : array();
	}


	private function __construct() {}
}
