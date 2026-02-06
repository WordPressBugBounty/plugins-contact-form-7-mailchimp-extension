<?php
/**
 * Data container element renderer.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

class Cmatic_Data_Container {
	const ELEMENT_ID = 'cmatic_data';

	public static function render( int $form_id, string $apivalid = '0', array $extra = array() ): void {
		$attrs_html = self::build_data_attrs( $form_id, $apivalid, $extra );

		printf(
			'<div id="%s"%s style="display:none;"></div>',
			esc_attr( self::ELEMENT_ID ),
			$attrs_html
		);
	}

	public static function render_open( int $form_id, string $apivalid = '0', array $extra = array() ): void {
		$attrs_html = self::build_data_attrs( $form_id, $apivalid, $extra );

		printf(
			'<div id="%s" class="cmatic-inner"%s>',
			esc_attr( self::ELEMENT_ID ),
			$attrs_html
		);
	}

	public static function render_close(): void {
		echo '</div><!-- #cmatic_data.cmatic-inner -->';
	}

	private static function build_data_attrs( int $form_id, string $apivalid = '0', array $extra = array() ): string {
		$data_attrs = array(
			'form-id'   => $form_id,
			'api-valid' => $apivalid,
		);

		foreach ( $extra as $key => $value ) {
			$key = str_replace( '_', '-', sanitize_key( $key ) );

			if ( is_array( $value ) || is_object( $value ) ) {
				$data_attrs[ $key ] = wp_json_encode( $value );
			} else {
				$data_attrs[ $key ] = esc_attr( $value );
			}
		}

		$attrs_html = '';
		foreach ( $data_attrs as $key => $value ) {
			$attrs_html .= sprintf( ' data-%s="%s"', esc_attr( $key ), esc_attr( $value ) );
		}

		return $attrs_html;
	}

	public static function get_element_id(): string {
		return self::ELEMENT_ID;
	}
}
