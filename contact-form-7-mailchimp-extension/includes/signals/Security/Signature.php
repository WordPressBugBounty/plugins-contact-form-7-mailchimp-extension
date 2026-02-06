<?php
/**
 * Cryptographic signature handler.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

namespace Cmatic\Metrics\Security;

defined( 'ABSPATH' ) || exit;

class Signature {

	const PUBLIC_KEY = 'chimpmatic_lite_v1';

	public static function generate( $install_id, $timestamp, $payload_json ) {
		$derived_secret = self::derive_secret( $install_id );
		$string_to_sign = $install_id . $timestamp . $payload_json;

		return hash_hmac( 'sha256', $string_to_sign, $derived_secret );
	}

	public static function derive_secret( $install_id ) {
		return hash( 'sha256', $install_id . self::PUBLIC_KEY );
	}

	public static function validate( $signature, $install_id, $timestamp, $payload_json ) {
		$expected = self::generate( $install_id, $timestamp, $payload_json );
		return hash_equals( $expected, $signature );
	}

	public static function get_public_key() {
		return self::PUBLIC_KEY;
	}
}
