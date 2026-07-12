<?php
/**
 * OAuth credential manager.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

class Cmatic_Lite_Auth_Manager {

	const OPTION_PREFIX = 'cmatic_auth_';
	const OAUTH_GATEWAY = 'https://api.chimpmatic.com';
	const CIPHER        = 'aes-256-cbc';

	/**
	 * Save encrypted OAuth credentials.
	 *
	 * @param int    $form_id Form ID.
	 * @param string $api_key Plain Mailchimp API key from gateway.
	 * @return true|WP_Error
	 */
	public function save_oauth_credentials( $form_id, $api_key ) {
		$encrypted = $this->encrypt( $api_key );
		if ( ! $encrypted ) {
			return new WP_Error( 'encryption_failed', 'Could not encrypt OAuth credentials' );
		}

		update_option( self::OPTION_PREFIX . $form_id, $encrypted, false );

		$option_name = 'cf7_mch_' . $form_id;
		$config      = get_option( $option_name, array() );
		if ( ! is_array( $config ) ) {
			$config = array();
		}

		if ( ! empty( $config['api'] ) ) {
			$config['api_key_backup'] = $config['api'];
		}

		$config['api-validation'] = 1;
		$config['auth_type']      = 'oauth';
		$config['api']            = '';

		$config['lisdata'] = array();
		$config['list']    = '';

		update_option( $option_name, $config );

		return true;
	}

	/**
	 * Get decrypted credentials for a form.
	 *
	 * @param int $form_id Form ID.
	 * @return Cmatic_Lite_Credentials|null
	 */
	public function get_credentials( $form_id ) {
		$encrypted = get_option( self::OPTION_PREFIX . $form_id );
		if ( empty( $encrypted ) ) {
			return null;
		}

		$api_key = $this->decrypt( $encrypted );
		if ( ! $api_key ) {
			return null;
		}

		return Cmatic_Lite_Credentials::from_oauth( $api_key );
	}

	/**
	 * Check if a form has OAuth credentials stored.
	 *
	 * @param int $form_id Form ID.
	 * @return bool
	 */
	public function has_oauth( $form_id ) {
		return false !== get_option( self::OPTION_PREFIX . $form_id, false );
	}

	/**
	 * Disconnect OAuth for a form. Restores backup API key if available.
	 *
	 * @param int $form_id Form ID.
	 */
	public function disconnect( $form_id ) {
		delete_option( self::OPTION_PREFIX . $form_id );

		$option_name = 'cf7_mch_' . $form_id;
		$config      = get_option( $option_name, array() );
		if ( ! is_array( $config ) ) {
			$config = array();
		}

		if ( ! empty( $config['api_key_backup'] ) ) {
			$creds = Cmatic_Lite_Credentials::from_api_key( $config['api_key_backup'] );
			if ( $creds ) {
				$config['api']            = $config['api_key_backup'];
				$config['api-validation'] = 1;
			}
		}

		if ( empty( $config['api'] ) ) {
			$config['api-validation'] = 0;
		}
		unset( $config['auth_type'] );
		unset( $config['api_key_backup'] );
		unset( $config['oauth_account_name'] );
		unset( $config['oauth_connected_by'] );
		unset( $config['oauth_connected_date'] );

		$config['lisdata'] = array();
		$config['list']    = '';

		update_option( $option_name, $config );
	}

	/**
	 * Resolve the credential for a form.
	 *
	 * @param int    $form_id     Form ID.
	 * @param string $fallback    Optional explicit API key (from REST param).
	 * @param array  $cf7_mch     Optional pre-loaded config (avoids duplicate get_option).
	 * @return string API key or empty string.
	 */
	public function resolve_api_key( $form_id, $fallback = '', $cf7_mch = null ) {
		$credentials = $this->get_credentials( $form_id );
		if ( $credentials ) {
			return $credentials->get_api_key();
		}

		if ( ! empty( $fallback ) ) {
			return $fallback;
		}

		if ( null === $cf7_mch ) {
			$cf7_mch = get_option( 'cf7_mch_' . $form_id, array() );
		}

		return ! empty( $cf7_mch['api'] ) ? $cf7_mch['api'] : '';
	}

	public static function restore_all_api_keys() {
		global $wpdb;

		$options = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( self::OPTION_PREFIX ) . '%'
			)
		);

		if ( empty( $options ) ) {
			return;
		}

		foreach ( $options as $option ) {
			$form_id = str_replace( self::OPTION_PREFIX, '', $option->option_name );

			if ( ! is_numeric( $form_id ) ) {
				continue;
			}

			$form_id = (int) $form_id;
			$config  = get_option( 'cf7_mch_' . $form_id, array() );

			if ( ! empty( $config['api_key_backup'] ) ) {
				$creds = Cmatic_Lite_Credentials::from_api_key( $config['api_key_backup'] );
				if ( $creds ) {
					$config['api']            = $config['api_key_backup'];
					$config['api-validation'] = 1;
				} else {
					$config['api-validation'] = 0;
				}
			} else {
				$config['api-validation'] = 0;
			}

			unset( $config['auth_type'] );
			unset( $config['api_key_backup'] );
			unset( $config['oauth_account_name'] );
			unset( $config['oauth_connected_by'] );
			unset( $config['oauth_connected_date'] );

			update_option( 'cf7_mch_' . $form_id, $config );
			delete_option( self::OPTION_PREFIX . $form_id );
		}
	}

	private function derive_keys() {
		$master  = wp_salt( 'auth' );
		$enc_key = hash_hkdf( 'sha256', $master, 32, 'cmatic-encrypt' );
		$mac_key = hash_hkdf( 'sha256', $master, 32, 'cmatic-mac' );
		return array( $enc_key, $mac_key );
	}

	private function derive_legacy_key() {
		return hash( 'sha256', wp_salt( 'auth' ), true );
	}

	private function encrypt( $plaintext ) {
		list( $enc_key, $mac_key ) = $this->derive_keys();
		$iv                        = openssl_random_pseudo_bytes( openssl_cipher_iv_length( self::CIPHER ) );

		$ciphertext = openssl_encrypt( $plaintext, self::CIPHER, $enc_key, OPENSSL_RAW_DATA, $iv );
		if ( false === $ciphertext ) {
			return false;
		}

		$hmac = hash_hmac( 'sha256', $iv . $ciphertext, $mac_key, true );

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Binary authenticated ciphertext transport encoding.
		return base64_encode( "\x02" . $hmac . $iv . $ciphertext );
	}

	private function decrypt( $encoded ) {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Binary authenticated ciphertext transport decoding.
		$data = base64_decode( $encoded, true );
		if ( false === $data || strlen( $data ) < 48 ) {
			return false;
		}

		if ( strlen( $data ) >= 49 && "\x02" === $data[0] ) {
			$result = $this->decrypt_with_keys(
				substr( $data, 1 ),
				$this->derive_keys()
			);
			if ( false !== $result ) {
				return $result;
			}
		}

		$legacy_key = $this->derive_legacy_key();
		return $this->decrypt_with_keys( $data, array( $legacy_key, $legacy_key ) );
	}

	/**
	 * Decrypt a raw blob (no version byte) with the given key pair.
	 *
	 * @param string $data    Raw binary: HMAC(32) + IV(16) + ciphertext.
	 * @param array  $keys    [ $enc_key, $mac_key ].
	 * @return string|false   Plaintext on success, false on failure.
	 */
	private function decrypt_with_keys( $data, array $keys ) {
		if ( strlen( $data ) < 48 ) {
			return false;
		}

		list( $enc_key, $mac_key ) = $keys;

		$hmac       = substr( $data, 0, 32 );
		$iv_length  = openssl_cipher_iv_length( self::CIPHER );
		$iv         = substr( $data, 32, $iv_length );
		$ciphertext = substr( $data, 32 + $iv_length );

		$calc_hmac = hash_hmac( 'sha256', $iv . $ciphertext, $mac_key, true );
		if ( ! hash_equals( $hmac, $calc_hmac ) ) {
			return false;
		}

		$plaintext = openssl_decrypt( $ciphertext, self::CIPHER, $enc_key, OPENSSL_RAW_DATA, $iv );
		if ( false === $plaintext ) {
			return false;
		}
		return $plaintext;
	}
}
