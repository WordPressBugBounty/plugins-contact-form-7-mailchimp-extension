<?php
/**
 * Merge variables builder.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

class Cmatic_Merge_Vars_Builder {

	public static function build( array $cf7_mch, array $posted_data, int $field_limit = CMATIC_LITE_FIELDS ): array {
		$merge_vars  = array();
		$field_limit = max( 1, min( 50, $field_limit ) );

		if ( empty( $cf7_mch['merge_fields'] ) || ! is_array( $cf7_mch['merge_fields'] ) ) {
			return $merge_vars;
		}

		$field_index = 3;
		$max_index   = $field_limit + 2;

		foreach ( $cf7_mch['merge_fields'] as $merge_field ) {
			$field_key  = 'field' . $field_index;
			$merge_tag  = $merge_field['tag'] ?? '';
			$field_type = is_array( $merge_field ) ? ( $merge_field['type'] ?? '' ) : '';

			if ( ! empty( $cf7_mch[ $field_key ] ) && ! empty( $merge_tag ) ) {
				$value = 'multiple-choice' === $field_type
					? self::sanitize_multiple_choice(
						Cmatic_Email_Extractor::replace_tag_values( $cf7_mch[ $field_key ], $posted_data )
					)
					: Cmatic_Email_Extractor::replace_tags( $cf7_mch[ $field_key ], $posted_data );
				if ( ! empty( $value ) ) {
					$merge_vars[ $merge_tag ] = self::sanitize_value( $value );
				}
			}

			++$field_index;
			if ( $field_index > $max_index ) {
				break;
			}
		}

		return self::filter_empty( $merge_vars );
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
}
