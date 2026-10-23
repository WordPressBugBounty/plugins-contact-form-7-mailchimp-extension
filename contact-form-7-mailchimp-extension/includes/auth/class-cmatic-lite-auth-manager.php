<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

class Cmatic_Lite_Auth_Manager {

	const OPTION_PREFIX = 'cmatic_auth_';
	const OAUTH_GATEWAY = 'https://api.chimpmatic.com';

	/**
	 * @param int    $form_id Form ID.
	 * @param string $api_key Plain Mailchimp API key from gateway.
	 * @return true|WP_Error
	 */
	public function save_oauth_credentials( $form_id, $api_key ) {
		$encrypted = is_string( $api_key ) ? $this->vault()->seal( $api_key, \Cmatic\Lite\Provider\CredentialVault::OAUTH ) : '';
		if ( '' === $encrypted ) {
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
	 * @param int $form_id Form ID.
	 * @return Cmatic_Lite_Credentials|null
	 */
	public function get_credentials( $form_id ) {
		$encrypted = get_option( self::OPTION_PREFIX . $form_id );
		if ( empty( $encrypted ) || ! is_string( $encrypted ) ) {
			return null;
		}

		$api_key = $this->vault()->open( $encrypted, \Cmatic\Lite\Provider\CredentialVault::OAUTH );
		if ( '' === $api_key ) {
			return null;
		}

		return Cmatic_Lite_Credentials::from_oauth( $api_key );
	}

	/**
	 * @param int $form_id Form ID.
	 * @return bool
	 */
	public function has_oauth( $form_id ) {
		return false !== get_option( self::OPTION_PREFIX . $form_id, false );
	}

	/**
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
	 * @param int    $form_id     Form ID.
	 * @param string $fallback    Optional explicit API key (from REST param).
	 * @param array  $cf7_mch     Optional pre-loaded config (avoids duplicate get_option).
	 * @return string API key or empty string.
	 */
	public function resolve_api_key( $form_id, $fallback = '', $cf7_mch = null ) {
		if ( ! empty( $fallback ) ) {
			return $fallback;
		}

		if ( null === $cf7_mch ) {
			$cf7_mch = get_option( 'cf7_mch_' . $form_id, array() );
		}

		if ( ! empty( $cf7_mch['api'] ) ) {
			return $cf7_mch['api'];
		}

		$credentials = $this->get_credentials( $form_id );
		if ( $credentials ) {
			return $credentials->get_api_key();
		}

		return '';
	}

	public static function restore_all_api_keys() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
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

	/**
	 * @return \Cmatic\Lite\Provider\CredentialVault
	 */
	private function vault() {
		return Cmatic_Lite_Esp_Credentials::vault();
	}
}
