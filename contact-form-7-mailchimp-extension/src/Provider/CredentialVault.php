<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    hello@chimpmatic.com
 * @copyright 2014-2026 ChimpMatic
 * @license   GPL-3.0+
 */

declare(strict_types=1);

namespace Cmatic\Lite\Provider;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing -- Typed signatures follow the existing Lite class convention.
final class CredentialVault {
	public const PROVIDER = "\x01";
	public const OAUTH    = "\x02";

	public const PROVIDER_PREFIX = 'cmatic_provider_auth_';
	public const OAUTH_PREFIX    = 'cmatic_auth_';

	private const CIPHER        = 'aes-256-cbc';
	private const MAC_SIZE      = 32;
	private const PROVIDER_INFO = array( 'cmatic-provider-encryption', 'cmatic-provider-mac' );
	private const OAUTH_INFO    = array( 'cmatic-encrypt', 'cmatic-mac' );

	/** @var callable(): string */
	private $secret;

	public function __construct( ?callable $secret = null ) {
		$this->secret = null !== $secret ? $secret : static function (): string {
			return wp_salt( 'auth' );
		};
	}

	public static function provider_option( int $form_id, string $provider ): string {
		return self::PROVIDER_PREFIX . $form_id . '_' . sanitize_key( $provider );
	}

	public static function oauth_option( int $form_id ): string {
		return self::OAUTH_PREFIX . $form_id;
	}

	public function store( int $form_id, string $provider, string $api_key ): bool {
		$encoded = $this->seal( $api_key, self::PROVIDER );
		if ( '' === $encoded ) {
			return false;
		}
		return update_option( self::provider_option( $form_id, $provider ), $encoded, false );
	}

	public function read( int $form_id, string $provider ): string {
		$encoded = get_option( self::provider_option( $form_id, $provider ), '' );
		return is_string( $encoded ) ? $this->open( $encoded, self::PROVIDER ) : '';
	}

	public function forget( int $form_id, string $provider ): bool {
		return delete_option( self::provider_option( $form_id, $provider ) );
	}

	public function seal( string $plaintext, string $envelope ): string {
		$keys      = $this->keys( $envelope );
		$iv_length = openssl_cipher_iv_length( self::CIPHER );
		if ( null === $keys || ! is_int( $iv_length ) || $iv_length < 1 ) {
			return '';
		}
		try {
			$iv = random_bytes( $iv_length );
		} catch ( \Exception $exception ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- A missing CSPRNG must fail closed without exposing internals.
			return '';
		}
		$ciphertext = openssl_encrypt( $plaintext, self::CIPHER, $keys[0], OPENSSL_RAW_DATA, $iv );
		if ( ! is_string( $ciphertext ) ) {
			return '';
		}
		$mac = hash_hmac( 'sha256', $iv . $ciphertext, $keys[1], true );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Binary authenticated-encryption envelope, not code obfuscation.
		return base64_encode( $envelope . $mac . $iv . $ciphertext );
	}

	public function open( string $encoded, string $envelope ): string {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decode the binary authenticated-encryption envelope.
		$data = base64_decode( $encoded, true );
		if ( ! is_string( $data ) ) {
			return '';
		}
		if ( self::PROVIDER === $envelope ) {
			if ( strlen( $data ) < 50 || self::PROVIDER !== $data[0] ) {
				return '';
			}
			return $this->unseal( substr( $data, 1 ), $this->keys( self::PROVIDER ) );
		}
		if ( self::OAUTH !== $envelope || strlen( $data ) < 48 ) {
			return '';
		}
		if ( strlen( $data ) >= 49 && self::OAUTH === $data[0] ) {
			$plaintext = $this->unseal( substr( $data, 1 ), $this->keys( self::OAUTH ) );
			if ( '' !== $plaintext ) {
				return $plaintext;
			}
		}
		return $this->unseal( $data, $this->legacy_keys() );
	}

	/**
	 * @param array{string,string}|null $keys
	 */
	private function unseal( string $data, ?array $keys ): string {
		$iv_length = openssl_cipher_iv_length( self::CIPHER );
		if ( null === $keys || ! is_int( $iv_length ) || $iv_length < 1 || strlen( $data ) < self::MAC_SIZE + $iv_length ) {
			return '';
		}
		$mac        = substr( $data, 0, self::MAC_SIZE );
		$iv         = substr( $data, self::MAC_SIZE, $iv_length );
		$ciphertext = substr( $data, self::MAC_SIZE + $iv_length );
		if ( ! hash_equals( $mac, hash_hmac( 'sha256', $iv . $ciphertext, $keys[1], true ) ) ) {
			return '';
		}
		$plaintext = openssl_decrypt( $ciphertext, self::CIPHER, $keys[0], OPENSSL_RAW_DATA, $iv );
		return is_string( $plaintext ) ? $plaintext : '';
	}

	/**
	 * @return array{string,string}|null
	 */
	private function keys( string $envelope ): ?array {
		if ( self::PROVIDER === $envelope ) {
			$info = self::PROVIDER_INFO;
		} elseif ( self::OAUTH === $envelope ) {
			$info = self::OAUTH_INFO;
		} else {
			return null;
		}
		$secret = $this->secret();
		return array(
			hash_hkdf( 'sha256', $secret, 32, $info[0] ),
			hash_hkdf( 'sha256', $secret, 32, $info[1] ),
		);
	}

	/**
	 * @return array{string,string}
	 */
	private function legacy_keys(): array {
		$key = hash( 'sha256', $this->secret(), true );
		return array( $key, $key );
	}

	private function secret(): string {
		return ( $this->secret )();
	}
}
