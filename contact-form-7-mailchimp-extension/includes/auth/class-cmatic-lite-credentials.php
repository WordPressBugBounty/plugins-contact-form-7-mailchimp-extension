<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

class Cmatic_Lite_Credentials {

	const DC_PATTERN = '/^[a-z]{2}\d{1,3}$/';

	private $api_key;

	private function __construct( string $api_key ) {
		$this->api_key = $api_key;
	}

	public static function from_api_key( $api_key ) {
		return self::parse( $api_key );
	}

	public static function from_oauth( $api_key ) {
		return self::parse( $api_key );
	}

	private static function parse( $api_key ): ?self {
		if ( ! is_string( $api_key ) || '' === $api_key || 1 !== substr_count( $api_key, '-' ) ) {
			return null;
		}
		$parts = explode( '-', $api_key );
		if ( '' === $parts[0] || ! preg_match( self::DC_PATTERN, $parts[1] ) ) {
			return null;
		}
		return new self( $api_key );
	}

	public function get_api_key() {
		return $this->api_key;
	}
}
