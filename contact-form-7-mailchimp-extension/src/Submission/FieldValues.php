<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    hello@chimpmatic.com
 * @copyright 2014-2026 ChimpMatic
 * @license   GPL-3.0+
 */

namespace Cmatic\Lite\Submission;

use Cmatic\Lite\Settings\MappingSchema;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing -- Typed signatures follow the existing Lite class convention.
final class FieldValues {

	private const TAG_PATTERN = '/\[\s*([a-zA-Z_][0-9a-zA-Z:._-]*)\s*\]/';

	public static function extract( array $cf7_mch, array $posted_data ): string {
		if ( empty( $cf7_mch['merge_fields'] ) || ! is_array( $cf7_mch['merge_fields'] ) ) {
			return '';
		}

		foreach ( $cf7_mch['merge_fields'] as $idx => $merge_field ) {
			if ( is_array( $merge_field ) && ( $merge_field['tag'] ?? '' ) === 'EMAIL' ) {
				$field_key = 'field' . ( $idx + 3 );
				if ( ! empty( $cf7_mch[ $field_key ] ) ) {
					return self::replace_tags( $cf7_mch[ $field_key ], $posted_data );
				}
				break;
			}
		}

		return '';
	}

	/**
	 * @param array<string,mixed> $section
	 * @param array<string,mixed> $posted_data
	 */
	public static function email( array $section, array $posted_data ): string {
		if ( ! empty( $section['merge_fields'] ) && is_array( $section['merge_fields'] ) ) {
			return self::extract( $section, $posted_data );
		}
		$slot = $section['field3'] ?? '';
		return is_string( $slot ) && '' !== $slot ? self::replace_tags( $slot, $posted_data ) : '';
	}

	/**
	 * @param array<array-key,mixed> $posted_data Submitted Contact Form 7 data.
	 */
	public static function posted_key( string $name, array $posted_data ): ?string {
		if ( '' === $name ) {
			return null;
		}
		if ( isset( $posted_data[ $name ] ) ) {
			return $name;
		}
		$found = array();
		foreach ( $posted_data as $key => $value ) {
			if ( is_string( $key ) && null !== $value && 0 === strcasecmp( $key, $name ) ) {
				$found[] = $key;
			}
		}
		return 1 === count( $found ) ? $found[0] : null;
	}

	public static function replace_tags( string $subject, array $posted_data ): string {
		$value = self::replace_tag_values( $subject, $posted_data );
		return is_array( $value ) ? implode( ', ', $value ) : (string) $value;
	}

	/**
	 * @return string|array<int,string>
	 */
	public static function replace_tag_values( string $subject, array $posted_data ) {
		if ( preg_match( self::TAG_PATTERN, $subject, $matches ) > 0 ) {
			$key = self::posted_key( $matches[1], $posted_data );
			if ( null !== $key ) {
				$submitted = $posted_data[ $key ];
				if ( is_array( $submitted ) ) {
					return array_values(
						array_map(
							'strval',
							array_filter( $submitted, 'is_scalar' )
						)
					);
				}
				return is_scalar( $submitted ) ? (string) $submitted : '';
			}
			$mail_tag = class_exists( 'WPCF7_MailTag' )
				? new \WPCF7_MailTag( sprintf( '[%s]', $matches[1] ), $matches[1], '' )
				: null;
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Contact Form 7 core hook.
			$special = apply_filters( 'wpcf7_special_mail_tags', '', $matches[1], false, $mail_tag );
			if ( is_string( $special ) && '' !== $special ) {
				return $special;
			}
			return '';
		}
		return $subject;
	}

	public static function build( array $cf7_mch, array $posted_data, int $field_limit = CMATIC_LITE_FIELDS, bool $zero_values = false ): array {
		$merge_vars  = array();
		$field_limit = max( 1, $field_limit );

		if ( empty( $cf7_mch['merge_fields'] ) || ! is_array( $cf7_mch['merge_fields'] ) ) {
			return $merge_vars;
		}

		$field_index  = 3;
		$active_slots = \Cmatic\Lite\Settings\MappingSchema::active_slots( $cf7_mch, $field_limit );

		foreach ( $cf7_mch['merge_fields'] as $merge_field ) {
			$field_key  = 'field' . $field_index;
			$merge_tag  = is_array( $merge_field ) && isset( $merge_field['tag'] ) && is_scalar( $merge_field['tag'] ) ? (string) $merge_field['tag'] : '';
			$field_type = is_array( $merge_field ) ? ( $merge_field['type'] ?? '' ) : '';

			if ( in_array( $field_key, $active_slots, true ) && ( ! empty( $cf7_mch[ $field_key ] ) || ( $zero_values && '0' === ( $cf7_mch[ $field_key ] ?? null ) ) ) && ! empty( $merge_tag ) ) {
				$value = 'multiple-choice' === $field_type
					? self::sanitize_multiple_choice(
						self::replace_tag_values( self::scalar_string( $cf7_mch[ $field_key ] ), $posted_data )
					)
					: self::replace_tags( self::scalar_string( $cf7_mch[ $field_key ] ), $posted_data );
				if ( ! empty( $value ) || ( $zero_values && '0' === $value ) ) {
					$merge_vars[ $merge_tag ] = self::sanitize_value( $value );
				}
			}

			++$field_index;
		}

		if ( $field_limit < PHP_INT_MAX ) {
			$email      = array_intersect_key( $merge_vars, array( 'EMAIL' => true ) );
			$merge_vars = $email + array_slice( array_diff_key( $merge_vars, $email ), 0, max( 0, $field_limit - 1 ), true );
		}
		return self::filter_empty( $merge_vars );
	}

	/**
	 * @param array $fields      Already-built provider fields.
	 * @param array $settings    Effective provider settings.
	 * @param array $posted_data Raw Contact Form 7 submission data.
	 * @param int   $field_limit Effective mapping limit.
	 */
	public static function apply_boolean_mappings( array $fields, array $settings, array $posted_data, int $field_limit ): array {
		foreach ( (array) ( $settings['merge_fields'] ?? array() ) as $offset => $definition ) {
			if ( ! in_array( \Cmatic\Lite\Settings\MappingSchema::slot( (int) $offset ), \Cmatic\Lite\Settings\MappingSchema::active_slots( $settings, $field_limit ), true ) ) {
				continue;
			}
			if ( ! is_array( $definition ) || 'boolean' !== sanitize_key( self::scalar_string( $definition['type'] ?? '' ) ) ) {
				continue;
			}
			$remote_tag = sanitize_text_field( self::scalar_string( $definition['tag'] ?? '' ) );
			$mapping    = self::scalar_string( $settings[ 'field' . ( $offset + 3 ) ] ?? '' );
			if ( '' === $remote_tag || 1 !== preg_match( '/^\[([A-Za-z0-9_-]+)\]$/', $mapping, $matches ) ) {
				continue;
			}
			$key                   = self::posted_key( $matches[1], $posted_data );
			$raw                   = null !== $key ? $posted_data[ $key ] : null;
			$fields[ $remote_tag ] = is_array( $raw ) ? ! empty( $raw ) : null !== $raw && '' !== \Cmatic\Lite\Settings\MappingSchema::str( $raw );
		}

		return $fields;
	}

	/**
	 * @param array $fields      Mapped submission values.
	 * @param array $definitions Provider field definitions.
	 */
	public static function normalize( array $fields, array $definitions ): array {
		$types  = array();
		$result = array();
		$errors = array();
		foreach ( $definitions as $definition ) {
			if ( is_array( $definition ) && isset( $definition['tag'] ) && is_scalar( $definition['tag'] ) ) {
				$types[ (string) $definition['tag'] ] = sanitize_key( self::scalar_string( $definition['type'] ?? 'text' ) );
			}
		}

		foreach ( $fields as $key => $raw ) {
			$type = $types[ (string) $key ] ?? 'text';
			if ( 'boolean' === $type ) {
				$result[ (string) $key ] = is_bool( $raw ) ? $raw : '' !== trim( is_array( $raw ) ? implode( '', self::scalar_strings( $raw ) ) : self::scalar_string( $raw ) );
				continue;
			}
			$value = is_array( $raw ) ? implode( ', ', self::scalar_strings( $raw ) ) : self::scalar_string( $raw );
			$valid = true;
			if ( 'date' === $type ) {
				$date  = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
				$valid = 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) && false !== $date && $date->format( 'Y-m-d' ) === $value;
			} elseif ( 'number' === $type ) {
				$valid = 1 === preg_match( '/^-?(?:\d+|\d*\.\d+)$/', $value );
			}
			if ( ! $valid ) {
				$errors[] = (string) $key;
				continue;
			}
			$result[ (string) $key ] = sanitize_text_field( $value );
		}

		return array(
			'success' => empty( $errors ),
			'fields'  => $result,
			'errors'  => $errors,
		);
	}

	public static function integer_fields( array $fields, array $definitions ): ?array {
		foreach ( $definitions as $definition ) {
			if ( ! is_array( $definition ) || 'number' !== ( $definition['type'] ?? '' ) || ! isset( $definition['tag'] ) || ! is_string( $definition['tag'] ) ) {
				continue;
			}
			$tag = $definition['tag'];
			if ( ! array_key_exists( $tag, $fields ) ) {
				continue;
			}
			$value = $fields[ $tag ];
			if ( ! is_string( $value ) && ! is_int( $value ) ) {
				return null;
			}
			$integer = filter_var( $value, FILTER_VALIDATE_INT );
			if ( false === $integer ) {
				return null;
			}
			$fields[ $tag ] = $integer;
		}
		return $fields;
	}

	private static function sanitize_value( $value ) {
		if ( is_array( $value ) ) {
			return array_map( array( __CLASS__, 'sanitize_value' ), $value );
		}
		return sanitize_text_field( (string) $value );
	}

	private static function sanitize_multiple_choice( $value ): array {
		$values = is_array( $value ) ? $value : array( $value );
		$result = array();

		foreach ( $values as $item ) {
			if ( ! is_scalar( $item ) ) {
				continue;
			}
			$item = sanitize_text_field( (string) $item );
			if ( '' !== $item && ! in_array( $item, $result, true ) ) {
				$result[] = $item;
			}
		}

		return $result;
	}

	private static function filter_empty( array $merge_vars ): array {
		return array_filter(
			$merge_vars,
			function ( $value ) {
				return ! empty( $value ) || 0 === $value || '0' === $value;
			}
		);
	}

	private static function scalar_string( $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	}

	private static function scalar_strings( array $values ): array {
		$result = array();
		foreach ( $values as $value ) {
			if ( is_scalar( $value ) ) {
				$result[] = (string) $value;
			}
		}
		return $result;
	}

	private function __construct() {}
}
