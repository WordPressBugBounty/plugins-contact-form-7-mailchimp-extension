<?php
/**
 * Email extraction handler.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

class Cmatic_Email_Extractor {

	private const TAG_PATTERN = '/\[\s*([a-zA-Z_][0-9a-zA-Z:._-]*)\s*\]/';

	public static function extract( array $cf7_mch, array $posted_data ): string {
		if ( empty( $cf7_mch['merge_fields'] ) || ! is_array( $cf7_mch['merge_fields'] ) ) {
			return '';
		}

		foreach ( $cf7_mch['merge_fields'] as $idx => $merge_field ) {
			if ( ( $merge_field['tag'] ?? '' ) === 'EMAIL' ) {
				$field_key = 'field' . ( $idx + 3 );
				if ( ! empty( $cf7_mch[ $field_key ] ) ) {
					return self::replace_tags( $cf7_mch[ $field_key ], $posted_data );
				}
				break;
			}
		}

		return '';
	}

	public static function replace_tags( string $subject, array $posted_data ): string {
		if ( preg_match( self::TAG_PATTERN, $subject, $matches ) > 0 ) {
			if ( isset( $posted_data[ $matches[1] ] ) ) {
				$submitted = $posted_data[ $matches[1] ];
				return is_array( $submitted ) ? implode( ', ', $submitted ) : $submitted;
			}
			return $matches[0];
		}
		return $subject;
	}
}
