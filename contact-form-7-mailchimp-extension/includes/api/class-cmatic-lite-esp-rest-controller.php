<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

use Cmatic\Lite\Rest\Args;
use Cmatic\Lite\Settings\FormSettings;
use Cmatic\Lite\Settings\FormSettingsRepository;
use Cmatic\Lite\Settings\MappingSchema;

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
					'permission_callback' => array( 'Cmatic_Rest_Permissions', 'fields' === $action ? 'form' : 'admin' ),
					'args'                => array(
						'form_id'  => 'disconnect' === $action ? Args::positive_form_id() : Args::form_id(),
						'provider' => Args::of( 'string', 'sanitize_key' ) + array( 'validate_callback' => array( __CLASS__, 'validate_provider' ) ),
						'api_key'  => Args::of( 'string', 'sanitize_text_field', false ),
						'list_id'  => Args::of( 'string', 'sanitize_text_field', false ),
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
				'permission_callback' => array( 'Cmatic_Rest_Permissions', 'editor' ),
				'args'                => array(
					'form_id'           => Args::form_id(),
					'provider'          => Args::of( 'string', 'sanitize_key' ),
					'list_id'           => Args::of( 'string', 'sanitize_text_field' ),
					'subscription_mode' => Args::of( 'string', 'sanitize_key' ),
					'doi_template_id'   => Args::of( 'integer', 'absint' ),
					'doi_redirect_url'  => Args::of( 'string', 'esc_url_raw' ),
				),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/providers/reset',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'reset' ),
				'permission_callback' => array( 'Cmatic_Rest_Permissions', 'editor' ),
				'args'                => array(
					'form_id' => Args::form_id(),
				),
			)
		);
		$tools = array(
			'/providers/fields/create' => 'create_field',
			'/providers/lookup'        => 'lookup',
		);
		foreach ( $tools as $route => $callback ) {
			register_rest_route(
				self::REST_NAMESPACE,
				$route,
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, $callback ),
					'permission_callback' => array( 'Cmatic_Rest_Permissions', 'create_field' === $callback ? 'admin' : 'form' ),
					'args'                => array(
						'form_id'  => Args::form_id(),
						'provider' => Args::of( 'string', 'sanitize_key' ),
						'name'     => Args::of( 'string', 'sanitize_text_field', false ),
						'type'     => Args::of( 'string', 'sanitize_key', false ),
						'email'    => Args::of( 'string', 'sanitize_email', false ),
					),
				)
			);
		}
	}

	public static function validate_provider( string $provider ): bool {
		return Cmatic_Lite_Esp_Registry::has( $provider );
	}

	public static function connect( $request ) {
		$form_id = (int) $request->get_param( 'form_id' );
		$slug    = (string) $request->get_param( 'provider' );
		if ( 'mailchimp' === $slug ) {
			$answer = Cmatic_Mailchimp_Panel_Store::connect( $form_id, trim( (string) $request->get_param( 'api_key' ) ) );
			self::record_transition( $slug, 'connect', ! is_wp_error( $answer ), is_wp_error( $answer ) ? 'auth' : 'unknown', is_wp_error( $answer ) ? $answer->get_error_message() : '', 'unknown' );

			return is_wp_error( $answer ) ? $answer : rest_ensure_response( $answer );
		}
		$field_limit  = \Cmatic\Lite\Settings\Entitlements::field_limit( $slug, $form_id );
		$submitted    = trim( (string) $request->get_param( 'api_key' ) );
		$previous_key = Cmatic_Lite_Esp_Credentials::get( $form_id, $slug );
		$key          = '' !== $submitted ? $submitted : $previous_key;

		if ( '' === $key ) {
			self::record_transition( $slug, 'connect', false, 'configuration', 'Provider credential is not configured.', 'configuration' );
			return new WP_Error(
				'missing_provider_key',
				esc_html__( 'Enter a provider credential first.', 'contact-form-7-mailchimp-extension' ),
				array( 'status' => 400 )
			);
		}

		$provider   = Cmatic_Lite_Esp_Registry::get( $slug );
		$logging    = (bool) Cmatic_Options_Repository::get_option( 'debug', false );
		$validation = $provider->validate_key( $key, $logging );
		if ( 1 !== (int) ( $validation['api-validation'] ?? 0 ) ) {
			self::record_transition( $slug, 'connect', false, 'auth', $validation, 'revoked_credential' );
			$detail = isset( $validation['_cmatic_error'] ) && is_string( $validation['_cmatic_error'] ) ? sanitize_text_field( $validation['_cmatic_error'] ) : '';
			return new WP_Error(
				'' !== $detail ? 'provider_validation_failed' : 'invalid_provider_key',
				'' !== $detail ? $detail : esc_html__( 'The provider rejected this credential.', 'contact-form-7-mailchimp-extension' ),
				array( 'status' => 400 )
			);
		}

		$lists = $provider->get_lists( $key, $logging );
		if ( ! empty( $lists['_cmatic_error'] ) ) {
			self::record_transition( $slug, 'connect', false, 'remote_rejected', $lists['_cmatic_error'], 'remote_rejected' );
			return new WP_Error(
				'provider_lists_failed',
				esc_html__( 'The credential is valid, but provider destinations could not be loaded.', 'contact-form-7-mailchimp-extension' ),
				array( 'status' => 502 )
			);
		}

		$list_items  = isset( $lists['lisdata']['lists'] ) && is_array( $lists['lisdata']['lists'] )
			? $lists['lisdata']['lists']
			: array();
		$key_changed = '' !== $submitted
			&& ( '' === $previous_key || ! hash_equals( $previous_key, $submitted ) );
		$settings    = FormSettingsRepository::load( $form_id )->section( $slug );
		$selected    = FormSettings::selected_list( $settings );
		$changes     = array_merge( $validation, array_diff_key( $lists, array_flip( array( 'merge_fields', 'total_merge_fields' ) ) ) );

		if (
			$key_changed
			|| (
				'' !== $selected
				&& ! FormSettings::list_exists( array( 'lisdata' => array( 'lists' => $list_items ) ), $selected )
			)
		) {
			$changes = array_merge( $changes, MappingSchema::cleared_provider_account( $field_limit ) );
		}

		$credential_replaced = false;
		if ( $key_changed ) {
			if ( ! Cmatic_Lite_Esp_Credentials::save( $form_id, $slug, $submitted ) ) {
				self::record_transition( $slug, 'connect', false, 'configuration', 'Credential persistence failed.', 'configuration' );
				return new WP_Error(
					'credential_save_failed',
					esc_html__( 'Could not encrypt provider credentials.', 'contact-form-7-mailchimp-extension' ),
					array( 'status' => 500 )
				);
			}
			$credential_replaced = true;
		}

		if ( ! FormSettingsRepository::merge_section( $form_id, $slug, $changes ) ) {
			if ( $credential_replaced ) {
				if ( '' === $previous_key ) {
					Cmatic_Lite_Esp_Credentials::delete( $form_id, $slug );
				} else {
					Cmatic_Lite_Esp_Credentials::save( $form_id, $slug, $previous_key );
				}
			}
			self::record_transition( $slug, 'connect', false, 'configuration', 'Provider settings persistence failed.', 'configuration' );
			return new WP_Error(
				'provider_settings_save_failed',
				esc_html__( 'The provider connected, but its settings could not be saved.', 'contact-form-7-mailchimp-extension' ),
				array( 'status' => 500 )
			);
		}
		if ( $key_changed && 'mailerlite' === $slug ) {
			\Cmatic\Lite\Submission\Degradation::clear( $form_id );
		}
		if ( ! Cmatic_Options_Repository::get_option( 'api.first_connected' ) ) {
			Cmatic_Options_Repository::set_option( 'api.first_connected', time() );
		}
		self::record_transition( $slug, 'connect', true, 'unknown', '', 'unknown' );

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
		$field_limit = PHP_INT_MAX;
		if ( 'mailchimp' === $slug ) {
			$answer = Cmatic_Mailchimp_Panel_Store::fields( $form_id, $list_id, $field_limit );
			self::record_transition( $slug, 'refresh_schema', ! is_wp_error( $answer ), is_wp_error( $answer ) ? 'remote_rejected' : 'unknown', is_wp_error( $answer ) ? $answer->get_error_message() : '', 'unknown' );

			return is_wp_error( $answer ) ? $answer : rest_ensure_response( $answer );
		}

		if ( '' === $list_id ) {
			self::record_transition( $slug, 'refresh_schema', true, 'unknown', '', 'unknown' );
			return rest_ensure_response(
				array(
					'success'            => true,
					'provider'           => $slug,
					'list_id'            => '',
					'merge_fields'       => array(),
					'total_merge_fields' => 0,
					'mappings'           => array(),
				)
			);
		}

		if ( ! self::is_known_list( $form_id, $slug, $list_id ) ) {
			self::record_transition( $slug, 'refresh_schema', false, 'validation', 'Destination is missing or no longer known.', 'deleted_destination' );
			return new WP_Error(
				'invalid_provider_list',
				esc_html__( 'Select a destination returned by the connected provider.', 'contact-form-7-mailchimp-extension' ),
				array( 'status' => 400 )
			);
		}

		$key = Cmatic_Lite_Esp_Credentials::get( $form_id, $slug );
		if ( '' === $key ) {
			self::record_transition( $slug, 'refresh_schema', false, 'configuration', 'Provider credential is not configured.', 'configuration' );
			return new WP_Error(
				'missing_provider_key',
				esc_html__( 'Connect this provider first.', 'contact-form-7-mailchimp-extension' ),
				array( 'status' => 400 )
			);
		}

		$settings = FormSettingsRepository::load( $form_id )->section( $slug );
		$result   = Cmatic_Lite_Esp_Registry::get( $slug )->get_merge_fields(
			$key,
			$list_id,
			(bool) Cmatic_Options_Repository::get_option( 'debug', false )
		);
		if ( ! empty( $result['_cmatic_error'] ) ) {
			self::record_transition( $slug, 'refresh_schema', false, 'remote_rejected', $result['_cmatic_error'], 'remote_rejected' );
			return new WP_Error(
				'provider_fields_failed',
				esc_html__( 'Provider fields could not be loaded; existing mappings were preserved.', 'contact-form-7-mailchimp-extension' ),
				array( 'status' => 502 )
			);
		}

		$fields   = $result['merge_fields']['merge_fields'] ?? array();
		$fields   = is_array( $fields ) ? $fields : array();
		$capped   = array_slice( $fields, 0, $field_limit );
		$mappings = MappingSchema::reconcile( MappingSchema::previous_mappings( array_slice( isset( $settings['merge_fields'] ) && is_array( $settings['merge_fields'] ) ? $settings['merge_fields'] : array(), 0, $field_limit ), $settings, false ), $capped, $field_limit, false );
		if ( ! Cmatic_Options_Repository::get_option( 'api.audience_selected' ) ) {
			Cmatic_Options_Repository::set_option( 'api.audience_selected', time() );
		}
		self::record_transition( $slug, 'refresh_schema', true, 'unknown', '', 'unknown' );
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

	public static function reset( $request ) {
		$form_id = (int) $request->get_param( 'form_id' );
		if ( $form_id <= 0 ) {
			return new WP_Error( 'invalid_form', esc_html__( 'Unknown form.', 'contact-form-7-mailchimp-extension' ), array( 'status' => 400 ) );
		}
		FormSettingsRepository::reset_choices( $form_id );
		\Cmatic\Lite\Submission\Stats::forget( $form_id );
		self::record_transition( 'all', 'reset', true, 'unknown', '', 'unknown' );

		return rest_ensure_response(
			array(
				'success' => true,
				'form_id' => $form_id,
			)
		);
	}
	public static function disconnect( $request ) {
		$form_id = (int) $request->get_param( 'form_id' );
		if ( $form_id < 1 || 'wpcf7_contact_form' !== get_post_type( $form_id ) ) {
			return new WP_Error( 'invalid_form', esc_html__( 'This contact form is unavailable.', 'contact-form-7-mailchimp-extension' ), array( 'status' => 400 ) );
		}
		$slug = (string) $request->get_param( 'provider' );
		if ( 'mailchimp' === $slug ) {
			self::record_transition( $slug, 'disconnect', true, 'unknown', '', 'unknown' );

			return rest_ensure_response( Cmatic_Mailchimp_Panel_Store::disconnect( $form_id ) );
		}
		$previous_key = Cmatic_Lite_Esp_Credentials::get( $form_id, $slug );
		Cmatic_Lite_Esp_Credentials::delete( $form_id, $slug );

		if ( ! FormSettingsRepository::merge_section( $form_id, $slug, array( 'api-validation' => 0 ) ) ) {
			if ( '' !== $previous_key ) {
				Cmatic_Lite_Esp_Credentials::save( $form_id, $slug, $previous_key );
			}
			self::record_transition( $slug, 'disconnect', false, 'configuration', 'Provider settings persistence failed.', 'configuration' );
			return new WP_Error(
				'provider_settings_save_failed',
				esc_html__( 'The provider could not be disconnected.', 'contact-form-7-mailchimp-extension' ),
				array( 'status' => 500 )
			);
		}
		self::record_transition( $slug, 'disconnect', true, 'unknown', '', 'unknown' );

		return rest_ensure_response(
			array(
				'success'            => true,
				'provider'           => $slug,
				'connected'          => false,
				'credential_present' => false,
			)
		);
	}

	public static function create_field( $request ) {
		$form_id  = (int) $request->get_param( 'form_id' );
		$slug     = (string) $request->get_param( 'provider' );
		$name     = sanitize_text_field( (string) $request->get_param( 'name' ) );
		$type     = sanitize_key( (string) $request->get_param( 'type' ) );
		$provider = Cmatic_Lite_Esp_Registry::get( $slug );
		if ( 'mailerlite' !== $slug || ! $provider instanceof Cmatic_Lite_Esp_Field_Creator_Interface || ! \Cmatic\Lite\Settings\Entitlements::feature_enabled( 'mailerlite_create_field', $slug, $form_id ) ) {
			return new WP_Error( 'provider_field_create_forbidden', esc_html__( 'Field creation is unavailable.', 'contact-form-7-mailchimp-extension' ), array( 'status' => 403 ) );
		}
		if ( '' === $name || strlen( $name ) > 255 || ! in_array( $type, array( 'text', 'number', 'date' ), true ) ) {
			return new WP_Error( 'invalid_provider_field', esc_html__( 'Enter a valid field name and type.', 'contact-form-7-mailchimp-extension' ), array( 'status' => 400 ) );
		}
		$key = Cmatic_Lite_Esp_Credentials::get( $form_id, $slug );
		if ( '' === $key ) {
			return new WP_Error( 'missing_provider_key', esc_html__( 'Connect MailerLite first.', 'contact-form-7-mailchimp-extension' ), array( 'status' => 400 ) );
		}
		$lock = 'cmatic_ml_field_' . substr( hash_hmac( 'sha256', get_current_user_id() . '|' . $form_id . '|' . strtolower( $name ) . '|' . $type, wp_salt( 'nonce' ) ), 0, 40 );
		if ( ! self::acquire_lock( $lock, 60 ) ) {
			return new WP_Error( 'provider_field_create_locked', esc_html__( 'An identical field creation is already running.', 'contact-form-7-mailchimp-extension' ), array( 'status' => 409 ) );
		}
		try {
			$result = $provider->create_field(
				$key,
				array(
					'name' => $name,
					'type' => $type,
				),
				(bool) Cmatic_Options_Repository::get_option( 'debug', false )
			);
		} finally {
			delete_option( $lock );
		}
		if ( empty( $result['success'] ) ) {
			return new WP_Error( 'provider_field_create_failed', esc_html__( 'Creation was not confirmed. Refresh fields before trying again.', 'contact-form-7-mailchimp-extension' ), array( 'status' => 502 ) );
		}
		$data       = isset( $result['body']['data'] ) && is_array( $result['body']['data'] ) ? $result['body']['data'] : (array) ( $result['body'] ?? array() );
		$field_id   = $data['id'] ?? '';
		$field_name = $data['name'] ?? $name;
		$field_key  = $data['key'] ?? $field_name;
		$field_type = $data['type'] ?? $type;
		$field_id   = is_scalar( $field_id ) ? (string) $field_id : '';
		$field_name = is_scalar( $field_name ) ? (string) $field_name : $name;
		$field_key  = is_scalar( $field_key ) ? (string) $field_key : $field_name;
		$field_type = is_scalar( $field_type ) ? (string) $field_type : $type;
		return rest_ensure_response(
			array(
				'success' => true,
				'field'   => array(
					'id'   => sanitize_text_field( $field_id ),
					'key'  => sanitize_text_field( $field_key ),
					'name' => sanitize_text_field( $field_name ),
					'type' => sanitize_key( $field_type ),
				),
			)
		);
	}

	public static function lookup( $request ) {
		$form_id  = (int) $request->get_param( 'form_id' );
		$slug     = (string) $request->get_param( 'provider' );
		$email    = sanitize_email( (string) $request->get_param( 'email' ) );
		$provider = Cmatic_Lite_Esp_Registry::get( $slug );
		if ( ! current_user_can( 'wpcf7_edit_contact_form', $form_id ) || 'mailerlite' !== $slug || ! $provider instanceof Cmatic_Lite_Esp_Lookup_Interface ) {
			return new WP_Error( 'provider_lookup_forbidden', esc_html__( 'Subscriber lookup is unavailable.', 'contact-form-7-mailchimp-extension' ), array( 'status' => 403 ) );
		}
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'invalid_lookup_email', esc_html__( 'Enter a valid email address.', 'contact-form-7-mailchimp-extension' ), array( 'status' => 400 ) );
		}
		if ( ! self::consume_lookup_limit( $form_id, $email ) ) {
			return new WP_Error( 'provider_lookup_limited', esc_html__( 'Too many lookups. Try again later.', 'contact-form-7-mailchimp-extension' ), array( 'status' => 429 ) );
		}
		$key = Cmatic_Lite_Esp_Credentials::get( $form_id, $slug );
		if ( '' === $key ) {
			return new WP_Error( 'missing_provider_key', esc_html__( 'Connect MailerLite first.', 'contact-form-7-mailchimp-extension' ), array( 'status' => 400 ) );
		}
		$result = $provider->lookup( $key, $email );
		if ( empty( $result['success'] ) ) {
			return new WP_Error( 'provider_lookup_failed', esc_html__( 'MailerLite lookup failed.', 'contact-form-7-mailchimp-extension' ), array( 'status' => 502 ) );
		}
		$data     = isset( $result['data'] ) && is_array( $result['data'] ) ? $result['data'] : array();
		$settings = FormSettingsRepository::load( $form_id )->section( $slug );
		$allowed  = MappingSchema::mapped_tags( $settings );
		$fields   = array_intersect_key( is_array( $data['fields'] ?? null ) ? $data['fields'] : array(), array_flip( $allowed ) );
		$groups   = array();
		foreach ( (array) ( $data['groups'] ?? array() ) as $group ) {
			if ( is_array( $group ) ) {
				$groups[] = array(
					'id'   => sanitize_text_field( self::scalar_string( $group['id'] ?? '' ) ),
					'name' => sanitize_text_field( self::scalar_string( $group['name'] ?? '' ) ),
				);
			}
		}
		$sanitized_fields = array();
		foreach ( $fields as $field_key => $field_value ) {
			if ( is_scalar( $field_value ) ) {
				$sanitized_fields[ $field_key ] = sanitize_text_field( (string) $field_value );
			}
		}
		$response = rest_ensure_response(
			array(
				'found'  => ! empty( $result['found'] ),
				'status' => sanitize_key( self::scalar_string( $data['status'] ?? '' ) ),
				'groups' => $groups,
				'fields' => $sanitized_fields,
			)
		);
		$response->header( 'Cache-Control', 'no-store, private' );
		return $response;
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
			|| ! \Cmatic\Lite\Settings\Entitlements::feature_enabled( 'double_optin', $slug, $form_id )
		) {
			return new WP_Error( 'provider_consent_forbidden', esc_html__( 'Advanced provider consent is unavailable.', 'contact-form-7-mailchimp-extension' ), array( 'status' => 403 ) );
		}
		if ( $template_id < 1 || 'https' !== wp_parse_url( $redirect, PHP_URL_SCHEME ) || ! self::is_known_list( $form_id, $slug, $list_id ) ) {
			return new WP_Error( 'invalid_provider_consent', esc_html__( 'Check the selected list and double opt-in settings.', 'contact-form-7-mailchimp-extension' ), array( 'status' => 400 ) );
		}

		$key = Cmatic_Lite_Esp_Credentials::get( $form_id, $slug );
		if ( '' === $key ) {
			return new WP_Error( 'missing_provider_key', esc_html__( 'Connect Brevo before verifying double opt-in.', 'contact-form-7-mailchimp-extension' ), array( 'status' => 400 ) );
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
			return new WP_Error( 'provider_consent_invalid', esc_html__( 'Brevo did not accept this double opt-in template.', 'contact-form-7-mailchimp-extension' ), array( 'status' => 409 ) );
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
			if ( ! array_key_exists( $key, $payload ) || self::scalar_string( $payload[ $key ] ) !== self::scalar_string( $value ) ) {
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


	private static function acquire_lock( string $name, int $ttl ): bool {
		$expires = time() + $ttl;
		if ( add_option( $name, $expires, '', false ) ) {
			return true;
		}
		$stored  = get_option( $name, 0 );
		$current = is_numeric( $stored ) ? (int) $stored : 0;
		if ( $current >= time() ) {
			return false;
		}
		delete_option( $name );
		return add_option( $name, $expires, '', false );
	}

	private static function consume_lookup_limit( int $form_id, string $email ): bool {
		$user_id     = get_current_user_id();
		$base        = 'cmatic_ml_lookup_' . $user_id . '_' . $form_id;
		$target      = $base . '_' . substr( hash_hmac( 'sha256', strtolower( $email ), wp_salt( 'nonce' ) ), 0, 24 );
		$total_value = get_transient( $base );
		$same_value  = get_transient( $target );
		$total       = is_numeric( $total_value ) ? (int) $total_value : 0;
		$same        = is_numeric( $same_value ) ? (int) $same_value : 0;
		if ( $total >= 10 || $same >= 3 ) {
			return false;
		}
		set_transient( $base, $total + 1, 10 * MINUTE_IN_SECONDS );
		set_transient( $target, $same + 1, 10 * MINUTE_IN_SECONDS );
		return true;
	}







	private static function scalar_string( $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	}


	private static function is_known_list( int $form_id, string $slug, string $list_id ): bool {
		return FormSettings::list_exists( FormSettingsRepository::load( $form_id )->section( $slug ), $list_id );
	}

	private static function record_transition( string $provider, string $operation, bool $success, string $failure_class, $value, string $fallback ): void {
		Cmatic_Lite_Signls_Outcome::record( $provider, $operation, $success, $failure_class, $value, $fallback );
	}

	private function __construct() {}
}
