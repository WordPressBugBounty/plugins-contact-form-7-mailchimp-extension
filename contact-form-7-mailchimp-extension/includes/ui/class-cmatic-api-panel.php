<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

class Cmatic_Api_Panel {

	const CMATIC_FB_C = '.com';

	public static function mask_key( string $key ): string {
		if ( empty( $key ) || strlen( $key ) < 12 ) {
			return $key;
		}
		$prefix = substr( $key, 0, 8 );
		$suffix = substr( $key, -4 );
		return $prefix . str_repeat( "\u{2022}", 20 ) . $suffix;
	}

	public static function render( array $cf7_mch, string $apivalid = '0', int $form_id = 0 ): void {
		unset( $cf7_mch, $apivalid, $form_id );
	}
}
