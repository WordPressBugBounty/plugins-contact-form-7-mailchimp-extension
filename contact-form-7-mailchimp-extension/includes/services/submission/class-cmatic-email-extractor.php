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
			// Not a posted field. Give CF7's special mail tags a chance to resolve it
			// ([_user_last_name], [_remote_ip], [_date], ...) so those work as users expect.
			// A real WPCF7_MailTag instance is required since CF7 5.2.2; passing null
			// made every registered handler fire doing_it_wrong on each submission.
			$mail_tag = class_exists( 'WPCF7_MailTag' )
				? new WPCF7_MailTag( sprintf( '[%s]', $matches[1] ), $matches[1], '' )
				: null;
			$special  = apply_filters( 'wpcf7_special_mail_tags', '', $matches[1], false, $mail_tag );
			if ( is_string( $special ) && '' !== $special ) {
				return $special;
			}
			// Still unresolved: never ship the literal token as subscriber data; it
			// corrupts Mailchimp records (real case: LNAME stored as "[_user_last_name]").
			return '';
		}
		return $subject;
	}
}
