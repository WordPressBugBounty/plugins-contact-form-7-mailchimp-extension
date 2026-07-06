<?php
/**
 * Credential value object.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

class Cmatic_Lite_Credentials {

	private $api_key;
	private $datacenter;
	private $source;

	/** Mailchimp datacenter format: 2 lowercase letters + 1-3 digits (e.g., us7, us21). */
	const DC_PATTERN = '/^[a-z]{2}\d{1,3}$/';

	private function __construct( $api_key, $datacenter, $source ) {
		$this->api_key    = $api_key;
		$this->datacenter = $datacenter;
		$this->source     = $source;
	}

	public static function from_api_key( $api_key ) {
		if ( empty( $api_key ) || substr_count( $api_key, '-' ) !== 1 ) {
			return null;
		}
		$parts = explode( '-', $api_key );
		if ( empty( $parts[0] ) || empty( $parts[1] ) ) {
			return null;
		}
		if ( ! preg_match( self::DC_PATTERN, $parts[1] ) ) {
			return null;
		}
		return new self( $api_key, $parts[1], 'api_key' );
	}

	public static function from_oauth( $api_key ) {
		if ( empty( $api_key ) || substr_count( $api_key, '-' ) !== 1 ) {
			return null;
		}
		$parts = explode( '-', $api_key );
		if ( empty( $parts[0] ) || empty( $parts[1] ) ) {
			return null;
		}
		if ( ! preg_match( self::DC_PATTERN, $parts[1] ) ) {
			return null;
		}
		return new self( $api_key, $parts[1], 'oauth' );
	}

	public function get_api_key() {
		return $this->api_key;
	}

	public function get_datacenter() {
		return $this->datacenter;
	}

	public function get_source() {
		return $this->source;
	}

	public function is_oauth() {
		return 'oauth' === $this->source;
	}
}
