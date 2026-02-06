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

	public static function build( array $cf7_mch, array $posted_data ): array {
		$merge_vars = array();

		if ( empty( $cf7_mch['merge_fields'] ) || ! is_array( $cf7_mch['merge_fields'] ) ) {
			return $merge_vars;
		}

		$field_index = 3;
		$max_index   = CMATIC_LITE_FIELDS + 2;

		foreach ( $cf7_mch['merge_fields'] as $merge_field ) {
			$field_key = 'field' . $field_index;
			$merge_tag = $merge_field['tag'] ?? '';

			if ( ! empty( $cf7_mch[ $field_key ] ) && ! empty( $merge_tag ) ) {
				$value = Cmatic_Email_Extractor::replace_tags( $cf7_mch[ $field_key ], $posted_data );
				if ( ! empty( $value ) ) {
					$merge_vars[ $merge_tag ] = $value;
				}
			}

			++$field_index;
			if ( $field_index > $max_index ) {
				break;
			}
		}

		return self::filter_empty( $merge_vars );
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
