<?php
/**
 * MailerLite typed field normalization.
 *
 * @package contact-form-7-mailchimp-extension
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

final class Cmatic_Mailerlite_Field_Normalizer {
	/**
	 * Adds explicit Boolean values for mapped Contact Form 7 acceptance tags.
	 *
	 * @param array $fields      Already-built MailerLite fields.
	 * @param array $settings    Effective provider settings.
	 * @param array $posted_data Raw Contact Form 7 submission data.
	 * @param int   $field_limit Effective mapping limit.
	 */
	public static function apply_boolean_mappings( array $fields, array $settings, array $posted_data, int $field_limit ): array {
		foreach ( array_slice( (array) ( $settings['merge_fields'] ?? array() ), 0, $field_limit ) as $offset => $definition ) {
			if ( ! is_array( $definition ) || 'boolean' !== sanitize_key( self::scalar_string( $definition['type'] ?? '' ) ) ) {
				continue;
			}
			$remote_tag = sanitize_text_field( self::scalar_string( $definition['tag'] ?? '' ) );
			$mapping    = self::scalar_string( $settings[ 'field' . ( $offset + 3 ) ] ?? '' );
			if ( '' === $remote_tag || 1 !== preg_match( '/^\[([A-Za-z0-9_-]+)\]$/', $mapping, $matches ) ) {
				continue;
			}
			$raw                   = $posted_data[ $matches[1] ] ?? null;
			$fields[ $remote_tag ] = is_array( $raw ) ? ! empty( $raw ) : null !== $raw && '' !== (string) $raw;
		}

		return $fields;
	}

	/**
	 * Returns normalized fields and any invalid field keys.
	 *
	 * @param array $fields      Mapped submission values.
	 * @param array $definitions MailerLite field definitions.
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
				$date  = DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
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
