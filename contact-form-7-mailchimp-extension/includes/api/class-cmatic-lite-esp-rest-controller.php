<?php
/**
 * ChimpMatic Lite provider REST transitions.
 *
 * @package contact-form-7-mailchimp-extension
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing -- Typed signatures follow the existing Lite provider convention.
final class Cmatic_Lite_Esp_Rest_Controller {
	private const REST_NAMESPACE = 'chimpmatic-lite/v1';

	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes(): void {
		foreach ( array( 'connect', 'fields', 'disconnect' ) as $action ) {
			register_rest_route(
				self::REST_NAMESPACE,
				'/providers/' . $action,
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, $action ),
					'permission_callback' => array( __CLASS__, 'permission' ),
					'args'                => array(
						'form_id'  => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
						'provider' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_key',
							'validate_callback' => array( __CLASS__, 'validate_provider' ),
						),
						'api_key'  => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'list_id'  => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				)
			);
		}
		register_rest_route(
			self::REST_NAMESPACE,
			'/providers/consent/validate',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'validate_consent' ),
				'permission_callback' => array( __CLASS__, 'permission' ),
				'args'                => array(
					'form_id'           => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'provider'          => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
					'list_id'           => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'subscription_mode' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
					'doi_template_id'   => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'doi_redirect_url'  => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'esc_url_raw',
					),
				),
			)
		);
	}

	public static function validate_provider( string $provider ): bool {
		return 'mailchimp' !== $provider && Cmatic_Lite_Esp_Registry::has( $provider );
	}

	public static function permission( $request ) {
		$form_id = (int) $request->get_param( 'form_id' );
        // phpcs:ignore WordPress.WP.Capabilities.Unknown -- Contact Form 7 registers this meta capability.
		if ( ! current_user_can( 'wpcf7_edit_contact_form', $form_id ) ) {
			return new WP_Error(
				'rest_forbidden',
				esc_html__( 'You cannot edit this form.', 'chimpmatic-lite' ),
				array( 'status' => 403 )
			);
		}
		if ( ! wp_verify_nonce( (string) $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) {
			return new WP_Error(
				'rest_cookie_invalid_nonce',
				esc_html__( 'Cookie nonce is invalid.', 'chimpmatic-lite' ),
				array( 'status' => 403 )
			);
		}
		return true;
	}

	public static function connect( $request ) {
		$form_id      = (int) $request->get_param( 'form_id' );
		$slug         = (string) $request->get_param( 'provider' );
		$field_limit  = Cmatic_Lite_Esp_Capabilities::field_limit( $slug, $form_id );
		$submitted    = trim( (string) $request->get_param( 'api_key' ) );
		$previous_key = Cmatic_Lite_Esp_Credentials::get( $form_id, $slug );
		$key          = '' !== $submitted ? $submitted : $previous_key;

		if ( '' === $key ) {
			return new WP_Error(
				'missing_provider_key',
				esc_html__( 'Enter a provider credential first.', 'chimpmatic-lite' ),
				array( 'status' => 400 )
			);
		}

		$provider   = Cmatic_Lite_Esp_Registry::get( $slug );
		$logging    = (bool) Cmatic_Options_Repository::get_option( 'debug', false );
		$validation = $provider->validate_key( $key, $logging );
		if ( 1 !== (int) ( $validation['api-validation'] ?? 0 ) ) {
			return new WP_Error(
				'invalid_provider_key',
				esc_html__( 'The provider rejected this credential.', 'chimpmatic-lite' ),
				array( 'status' => 400 )
			);
		}

		$lists = $provider->get_lists( $key, $logging );
		if ( ! empty( $lists['_cmatic_error'] ) ) {
			return new WP_Error(
				'provider_lists_failed',
				esc_html__( 'The credential is valid, but provider destinations could not be loaded.', 'chimpmatic-lite' ),
				array( 'status' => 502 )
			);
		}

		$list_items  = isset( $lists['lisdata']['lists'] ) && is_array( $lists['lisdata']['lists'] )
			? $lists['lisdata']['lists']
			: array();
		$key_changed = '' !== $submitted
			&& ( '' === $previous_key || ! hash_equals( $previous_key, $submitted ) );
		$settings    = self::provider_settings( $form_id, $slug );
		$selected    = self::selected_list( $settings );
		$changes     = array_merge( $validation, $lists );

		if (
			$key_changed
			|| (
				'' !== $selected
				&& ! self::list_exists( $list_items, $selected )
			)
		) {
			$changes = array_merge( $changes, self::empty_account_state( $field_limit ) );
		}

		$credential_replaced = false;
		if ( $key_changed ) {
			if ( ! Cmatic_Lite_Esp_Credentials::save( $form_id, $slug, $submitted ) ) {
				return new WP_Error(
					'credential_save_failed',
					esc_html__( 'Could not encrypt provider credentials.', 'chimpmatic-lite' ),
					array( 'status' => 500 )
				);
			}
			$credential_replaced = true;
		}

		if ( ! self::merge_provider_settings( $form_id, $slug, $changes ) ) {
			if ( $credential_replaced ) {
				if ( '' === $previous_key ) {
					Cmatic_Lite_Esp_Credentials::delete( $form_id, $slug );
				} else {
					Cmatic_Lite_Esp_Credentials::save( $form_id, $slug, $previous_key );
				}
			}
			return new WP_Error(
				'provider_settings_save_failed',
				esc_html__( 'The provider connected, but its settings could not be saved.', 'chimpmatic-lite' ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response(
			array(
				'success'            => true,
				'provider'           => $slug,
				'connected'          => true,
				'credential_present' => true,
				'key_changed'        => $key_changed,
				'lists'              => $list_items,
			)
		);
	}

	public static function fields( $request ) {
		$form_id     = (int) $request->get_param( 'form_id' );
		$slug        = (string) $request->get_param( 'provider' );
		$list_id     = trim( (string) $request->get_param( 'list_id' ) );
		$field_limit = Cmatic_Lite_Esp_Capabilities::field_limit( $slug, $form_id );

		if ( '' === $list_id ) {
			return rest_ensure_response(
				array(
					'success'            => true,
					'provider'           => $slug,
					'list_id'            => '',
					'merge_fields'       => array(),
					'total_merge_fields' => 0,
					'mappings'           => self::empty_mappings( $field_limit ),
				)
			);
		}

		if ( ! self::is_known_list( $form_id, $slug, $list_id ) ) {
			return new WP_Error(
				'invalid_provider_list',
				esc_html__( 'Select a destination returned by the connected provider.', 'chimpmatic-lite' ),
				array( 'status' => 400 )
			);
		}

		$key = Cmatic_Lite_Esp_Credentials::get( $form_id, $slug );
		if ( '' === $key ) {
			return new WP_Error(
				'missing_provider_key',
				esc_html__( 'Connect this provider first.', 'chimpmatic-lite' ),
				array( 'status' => 400 )
			);
		}

		$settings = self::provider_settings( $form_id, $slug );
		$result   = Cmatic_Lite_Esp_Registry::get( $slug )->get_merge_fields(
			$key,
			$list_id,
			(bool) Cmatic_Options_Repository::get_option( 'debug', false )
		);
		if ( ! empty( $result['_cmatic_error'] ) ) {
			return new WP_Error(
				'provider_fields_failed',
				esc_html__( 'Provider fields could not be loaded; existing mappings were preserved.', 'chimpmatic-lite' ),
				array( 'status' => 502 )
			);
		}

		$fields   = $result['merge_fields']['merge_fields'] ?? array();
		$fields   = is_array( $fields ) ? $fields : array();
		$capped   = array_slice( $fields, 0, $field_limit );
		$mappings = self::reconcile_mappings( $settings, $capped, $field_limit );
		return rest_ensure_response(
			array(
				'success'            => true,
				'provider'           => $slug,
				'list_id'            => $list_id,
				'merge_fields'       => $capped,
				'total_merge_fields' => count( $fields ),
				'mappings'           => $mappings,
			)
		);
	}

	public static function disconnect( $request ) {
		$form_id      = (int) $request->get_param( 'form_id' );
		$slug         = (string) $request->get_param( 'provider' );
		$previous_key = Cmatic_Lite_Esp_Credentials::get( $form_id, $slug );
		Cmatic_Lite_Esp_Credentials::delete( $form_id, $slug );

		if ( ! self::merge_provider_settings( $form_id, $slug, array( 'api-validation' => 0 ) ) ) {
			if ( '' !== $previous_key ) {
				Cmatic_Lite_Esp_Credentials::save( $form_id, $slug, $previous_key );
			}
			return new WP_Error(
				'provider_settings_save_failed',
				esc_html__( 'The provider could not be disconnected.', 'chimpmatic-lite' ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response(
			array(
				'success'            => true,
				'provider'           => $slug,
				'connected'          => false,
				'credential_present' => false,
			)
		);
	}

	public static function validate_consent( $request ) {
		$form_id     = (int) $request->get_param( 'form_id' );
		$slug        = (string) $request->get_param( 'provider' );
		$list_id     = trim( (string) $request->get_param( 'list_id' ) );
		$template_id = (int) $request->get_param( 'doi_template_id' );
		$redirect    = esc_url_raw( (string) $request->get_param( 'doi_redirect_url' ) );
		$mode        = (string) $request->get_param( 'subscription_mode' );
		if (
			'brevo' !== $slug
			|| 'double' !== $mode
			|| ! Cmatic_Lite_Esp_Capabilities::feature_enabled( 'advanced_consent', $slug, $form_id )
		) {
			return new WP_Error( 'provider_consent_forbidden', esc_html__( 'Advanced provider consent is unavailable.', 'chimpmatic-lite' ), array( 'status' => 403 ) );
		}
		if ( $template_id < 1 || 'https' !== wp_parse_url( $redirect, PHP_URL_SCHEME ) || ! self::is_known_list( $form_id, $slug, $list_id ) ) {
			return new WP_Error( 'invalid_provider_consent', esc_html__( 'Check the selected list and double opt-in settings.', 'chimpmatic-lite' ), array( 'status' => 400 ) );
		}

		$key = Cmatic_Lite_Esp_Credentials::get( $form_id, $slug );
		if ( '' === $key ) {
			return new WP_Error( 'missing_provider_key', esc_html__( 'Connect Brevo before verifying double opt-in.', 'chimpmatic-lite' ), array( 'status' => 400 ) );
		}
		$options                = array(
			'subscription_mode' => $mode,
			'doi_template_id'   => $template_id,
			'doi_redirect_url'  => $redirect,
		);
		$result                 = Cmatic_Lite_Esp_Registry::get( $slug )->validate_subscription_options( $key, $list_id, $options );
		$credential_fingerprint = self::credential_fingerprint( $key );
		unset( $key );
		if ( ! $result['success'] ) {
			return new WP_Error( 'provider_consent_invalid', esc_html__( 'Brevo did not accept this double opt-in template.', 'chimpmatic-lite' ), array( 'status' => 409 ) );
		}
		$payload                           = self::consent_token_payload( $form_id, $slug, $list_id, $template_id, $redirect );
		$payload['credential_fingerprint'] = $credential_fingerprint;
		return rest_ensure_response(
			array(
				'success'            => true,
				'provider'           => $slug,
				'verified'           => true,
				'template'           => array(
					'id'   => (int) ( $result['data']['template_id'] ?? $template_id ),
					'name' => sanitize_text_field( (string) ( $result['data']['template_name'] ?? '' ) ),
				),
				'verification_token' => self::sign_consent_payload( $payload ),
			)
		);
	}

	public static function verify_consent_token( string $token, array $expected ): bool {
		$parts = explode( '.', $token, 2 );
		if ( 2 !== count( $parts ) ) {
			return false;
		}
		$payload_json = self::base64url_decode( $parts[0] );
		$signature    = self::base64url_decode( $parts[1] );
		if ( false === $payload_json || false === $signature ) {
			return false;
		}
		$expected_signature = hash_hmac( 'sha256', $parts[0], wp_salt( 'nonce' ), true );
		if ( ! hash_equals( $expected_signature, $signature ) ) {
			return false;
		}
		$payload = json_decode( $payload_json, true );
		if ( ! is_array( $payload ) || (int) ( $payload['expires'] ?? 0 ) < time() ) {
			return false;
		}
		foreach ( $expected as $key => $value ) {
			if ( ! array_key_exists( $key, $payload ) || (string) $payload[ $key ] !== (string) $value ) {
				return false;
			}
		}
		return (int) ( $payload['user_id'] ?? 0 ) === get_current_user_id();
	}

	private static function consent_token_payload( int $form_id, string $slug, string $list_id, int $template_id, string $redirect ): array {
		return array(
			'form_id'       => $form_id,
			'provider'      => $slug,
			'list_id'       => $list_id,
			'template_id'   => $template_id,
			'redirect_hash' => hash( 'sha256', $redirect ),
			'user_id'       => get_current_user_id(),
			'expires'       => time() + ( 5 * MINUTE_IN_SECONDS ),
		);
	}

	public static function credential_fingerprint( string $credential ): string {
		return hash_hmac( 'sha256', $credential, wp_salt( 'auth' ) );
	}

	private static function sign_consent_payload( array $payload ): string {
		$json      = wp_json_encode( $payload );
		$encoded   = self::base64url_encode( false === $json ? '{}' : $json );
		$signature = hash_hmac( 'sha256', $encoded, wp_salt( 'nonce' ), true );
		return $encoded . '.' . self::base64url_encode( $signature );
	}

	private static function base64url_encode( string $value ): string {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- URL-safe signed token encoding.
		return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' );
	}

	private static function base64url_decode( string $value ) {
		$padding = strlen( $value ) % 4;
		if ( $padding ) {
			$value .= str_repeat( '=', 4 - $padding );
		}
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- URL-safe signed token decoding.
		return base64_decode( strtr( $value, '-_', '+/' ), true );
	}

	private static function empty_account_state( int $field_limit ): array {
		return array_merge(
			array(
				'list'               => '',
				'merge_fields'       => array(),
				'total_merge_fields' => 0,
			),
			self::empty_mappings( $field_limit )
		);
	}

	private static function empty_mappings( int $field_limit ): array {
		$mappings = array();
		foreach ( range( 3, $field_limit + 2 ) as $index ) {
			$mappings[ 'field' . $index ] = '';
		}
		return $mappings;
	}

	private static function reconcile_mappings( array $settings, array $new_fields, int $field_limit ): array {
		$by_tag     = array();
		$old_fields = isset( $settings['merge_fields'] ) && is_array( $settings['merge_fields'] )
			? $settings['merge_fields']
			: array();

		foreach ( array_slice( $old_fields, 0, $field_limit ) as $offset => $field ) {
			if ( ! is_array( $field ) || empty( $field['tag'] ) ) {
				continue;
			}
			$slot                             = 'field' . ( $offset + 3 );
			$by_tag[ (string) $field['tag'] ] = sanitize_text_field(
				(string) ( $settings[ $slot ] ?? '' )
			);
		}

		$mappings = self::empty_mappings( $field_limit );
		foreach ( array_slice( $new_fields, 0, $field_limit ) as $offset => $field ) {
			if ( ! is_array( $field ) || empty( $field['tag'] ) ) {
				continue;
			}
			$slot              = 'field' . ( $offset + 3 );
			$mappings[ $slot ] = $by_tag[ (string) $field['tag'] ] ?? '';
		}
		return $mappings;
	}

	private static function provider_settings( int $form_id, string $slug ): array {
		$config = get_option( 'cf7_mch_' . $form_id, array() );
		$config = is_array( $config ) ? $config : array();
		if (
			! isset( $config['providers'][ $slug ] )
			|| ! is_array( $config['providers'][ $slug ] )
		) {
			return array();
		}
		return $config['providers'][ $slug ];
	}

	private static function selected_list( array $settings ): string {
		$selected = $settings['list'] ?? '';
		if ( is_array( $selected ) ) {
			$first    = reset( $selected );
			$selected = false === $first ? '' : $first;
		}
		return sanitize_text_field( (string) $selected );
	}

	private static function list_exists( array $lists, string $list_id ): bool {
		foreach ( $lists as $list ) {
			if (
				is_array( $list )
				&& isset( $list['id'] )
				&& $list_id === (string) $list['id']
			) {
				return true;
			}
		}
		return false;
	}

	private static function merge_provider_settings( int $form_id, string $slug, array $changes ): bool {
		$option                       = 'cf7_mch_' . $form_id;
		$config                       = get_option( $option, array() );
		$config                       = is_array( $config ) ? $config : array();
		$config['providers']          = isset( $config['providers'] ) && is_array( $config['providers'] )
			? $config['providers']
			: array();
		$current                      = isset( $config['providers'][ $slug ] ) && is_array( $config['providers'][ $slug ] )
			? $config['providers'][ $slug ]
			: array();
		$config['providers'][ $slug ] = array_merge( $current, $changes );
		return update_option( $option, $config ) || get_option( $option, array() ) === $config;
	}

	private static function is_known_list( int $form_id, string $slug, string $list_id ): bool {
		$settings = self::provider_settings( $form_id, $slug );
		$lists    = isset( $settings['lisdata']['lists'] ) && is_array( $settings['lisdata']['lists'] )
			? $settings['lisdata']['lists']
			: array();
		return self::list_exists( $lists, $list_id );
	}

	private function __construct() {}
}
