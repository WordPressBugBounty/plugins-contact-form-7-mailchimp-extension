<?php
/**
 * ChimpMatic Lite multi-ESP component.
 *
 * @package contact-form-7-mailchimp-extension
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing -- Typed signatures follow the existing Lite class convention.
final class Cmatic_Lite_Esp_Credentials {
	private const PREFIX = 'cmatic_provider_auth_';
	private const CIPHER = 'aes-256-cbc';

	public static function save( int $form_id, string $provider, string $api_key ): bool {
		$iv_length = openssl_cipher_iv_length( self::CIPHER );
		if ( false === $iv_length || $iv_length < 1 ) {
			return false;
		}
		try {
			$iv = random_bytes( $iv_length );
		} catch ( Exception $exception ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- A missing CSPRNG must fail closed without exposing internals.
			return false;
		}
		list( $encryption_key, $mac_key ) = self::derive_keys();
		$ciphertext                       = openssl_encrypt( $api_key, self::CIPHER, $encryption_key, OPENSSL_RAW_DATA, $iv );
		if ( false === $ciphertext ) {
			return false;
		}
		$mac = hash_hmac( 'sha256', $iv . $ciphertext, $mac_key, true );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Binary authenticated-encryption envelope, not code obfuscation.
		$encoded = base64_encode( "\x01" . $mac . $iv . $ciphertext );
		return update_option( self::option_name( $form_id, $provider ), $encoded, false );
	}

	public static function get( int $form_id, string $provider ): string {
		$encoded = get_option( self::option_name( $form_id, $provider ), '' );
		if ( ! is_string( $encoded ) ) {
			return '';
		}
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decode the binary authenticated-encryption envelope.
		$data = base64_decode( $encoded, true );
		if ( false === $data || strlen( $data ) < 50 || "\x01" !== $data[0] ) {
			return '';
		}
		$data                             = substr( $data, 1 );
		list( $encryption_key, $mac_key ) = self::derive_keys();
		$iv_length                        = openssl_cipher_iv_length( self::CIPHER );
		if ( false === $iv_length ) {
			return '';
		}
		$mac        = substr( $data, 0, 32 );
		$iv         = substr( $data, 32, $iv_length );
		$ciphertext = substr( $data, 32 + $iv_length );
		if ( ! hash_equals( $mac, hash_hmac( 'sha256', $iv . $ciphertext, $mac_key, true ) ) ) {
			return '';
		}
		$plaintext = openssl_decrypt( $ciphertext, self::CIPHER, $encryption_key, OPENSSL_RAW_DATA, $iv );
		if ( false === $plaintext ) {
			return '';
		}
		return $plaintext;
	}

	public static function delete( int $form_id, string $provider ): bool {
		return delete_option( self::option_name( $form_id, $provider ) );
	}

	private static function option_name( int $form_id, string $provider ): string {
		return self::PREFIX . $form_id . '_' . sanitize_key( $provider );
	}

	private static function derive_keys(): array {
		$master = wp_salt( 'auth' );
		return array(
			hash_hkdf( 'sha256', $master, 32, 'cmatic-provider-encryption' ),
			hash_hkdf( 'sha256', $master, 32, 'cmatic-provider-mac' ),
		);
	}

	private function __construct() {}
}
