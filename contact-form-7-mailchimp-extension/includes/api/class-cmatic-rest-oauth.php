<?php
/**
 * OAuth REST API controller.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

final class Cmatic_Rest_OAuth {

	protected static $namespace   = 'chimpmatic-lite/v1';
	protected static $initialized = false;

	public static function init() {
		if ( self::$initialized ) {
			return;
		}
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
		self::$initialized = true;
	}

	public static function register_routes() {
		register_rest_route(
			self::$namespace,
			'/oauth/start',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'oauth_start' ),
				'permission_callback' => array( self::class, 'check_admin_permissions' ),
				'args'                => array(
					'form_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::$namespace,
			'/oauth/status',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'oauth_check_status' ),
				'permission_callback' => array( self::class, 'check_admin_permissions' ),
				'args'                => array(
					'url' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'esc_url_raw',
					),
				),
			)
		);

		register_rest_route(
			self::$namespace,
			'/oauth/finish',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'oauth_finish' ),
				'permission_callback' => array( self::class, 'check_admin_permissions' ),
				'args'                => array(
					'token'   => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'form_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::$namespace,
			'/oauth/disconnect',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'disconnect_oauth' ),
				'permission_callback' => array( self::class, 'check_admin_permissions' ),
				'args'                => array(
					'form_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::$namespace,
			'/oauth/event',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'oauth_event' ),
				'permission_callback' => array( self::class, 'check_admin_permissions' ),
				'args'                => array(
					'event'  => array(
						'required'          => true,
						'type'              => 'string',
						'enum'              => array( 'popup_blocked', 'popup_opened', 'timeout', 'finish_failed' ),
						'sanitize_callback' => 'sanitize_text_field',
					),
					'token'  => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'reason' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	public static function check_admin_permissions() {
		return current_user_can( 'manage_options' );
	}

	public static function oauth_start( $request ) {
		$form_id = $request->get_param( 'form_id' );
		if ( ! $form_id ) {
			return new WP_Error( 'missing_form_id', 'Form ID is required', array( 'status' => 400 ) );
		}

		$secret = wp_generate_password( 32, false );
		set_transient( 'cmatic_oauth_secret_' . $form_id, $secret, HOUR_IN_SECONDS );
		$start_body = array(
			'domain' => site_url(),
			'secret' => $secret,
		);
		$context    = Cmatic_Lite_Service_Context::payload( 'oauth_admin_connect' );
		if ( ! empty( $context ) ) {
			$start_body['context'] = $context;
		}

		$response = wp_safe_remote_post(
			Cmatic_Lite_Auth_Manager::OAUTH_GATEWAY . '/api/start',
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $start_body ),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			self::logger()->log(
				'error',
				'oauth start unreachable',
				array(
					'form_id' => $form_id,
					'reason'  => $response->get_error_code(),
				)
			);
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$code = wp_remote_retrieve_response_code( $response );

		if ( 201 !== $code || empty( $body['token'] ) ) {
			self::logger()->log(
				'error',
				'oauth start failed',
				array(
					'form_id' => $form_id,
					'code'    => $code,
				)
			);
			return new WP_Error( 'oauth_start_failed', 'Could not start OAuth session', array( 'status' => 502 ) );
		}

		self::logger()->log(
			'info',
			'oauth start ok',
			array(
				'form_id' => $form_id,
				'token'   => substr( $body['token'], 0, 8 ),
			)
		);
		return rest_ensure_response(
			array(
				'token'    => $body['token'],
				'auth_url' => Cmatic_Lite_Auth_Manager::OAUTH_GATEWAY . '/auth/start/' . $body['token'],
			)
		);
	}

	public static function oauth_check_status( $request ) {
		$url = $request->get_param( 'url' );
		if ( empty( $url ) ) {
			return new WP_Error( 'missing_url', 'Status URL is required', array( 'status' => 400 ) );
		}

		if ( 0 !== strpos( $url, Cmatic_Lite_Auth_Manager::OAUTH_GATEWAY . '/' ) ) {
			return new WP_Error( 'invalid_url', 'URL must point to the OAuth gateway', array( 'status' => 400 ) );
		}

		$response = wp_safe_remote_post(
			$url,
			array(
				'headers' => array(),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		$safe_response = array(
			'status' => isset( $body['status'] ) ? sanitize_text_field( $body['status'] ) : 'unknown',
		);

		return rest_ensure_response( $safe_response );
	}

	public static function oauth_finish( $request ) {
		$token   = $request->get_param( 'token' );
		$form_id = $request->get_param( 'form_id' );

		if ( ! $token || ! $form_id ) {
			return new WP_Error( 'missing_params', 'Token and form_id are required', array( 'status' => 400 ) );
		}

		$secret = get_transient( 'cmatic_oauth_secret_' . $form_id );
		if ( empty( $secret ) ) {
			self::logger()->log(
				'error',
				'oauth finish expired',
				array(
					'form_id' => $form_id,
					'token'   => substr( $token, 0, 8 ),
				)
			);
			self::relay( 'wp_expired_secret', $token, 'transient_expired' );
			return new WP_Error( 'oauth_expired', 'OAuth session expired', array( 'status' => 410 ) );
		}

		$response = wp_safe_remote_post(
			Cmatic_Lite_Auth_Manager::OAUTH_GATEWAY . '/api/finish',
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'domain' => site_url(),
						'secret' => $secret,
						'token'  => $token,
					)
				),
				'timeout' => 15,
			)
		);

		delete_transient( 'cmatic_oauth_secret_' . $form_id );

		if ( is_wp_error( $response ) ) {
			self::logger()->log(
				'error',
				'oauth finish unreachable',
				array(
					'form_id' => $form_id,
					'token'   => substr( $token, 0, 8 ),
					'reason'  => $response->get_error_code(),
				)
			);
			self::relay( 'wp_gateway_error', $token, $response->get_error_code() );
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code || empty( $body['access_token'] ) || empty( $body['data_center'] ) ) {
			self::logger()->log(
				'error',
				'oauth finish failed',
				array(
					'form_id' => $form_id,
					'code'    => $code,
					'token'   => substr( $token, 0, 8 ),
				)
			);
			self::relay( 'wp_gateway_error', $token, 'http_' . $code );
			return new WP_Error( 'oauth_finish_failed', 'Could not retrieve credentials', array( 'status' => 502 ) );
		}

		if ( ! preg_match( '/^[a-z]{2}\d{1,3}$/', $body['data_center'] ) ) {
			self::logger()->log(
				'error',
				'oauth finish bad dc',
				array(
					'form_id' => $form_id,
					'token'   => substr( $token, 0, 8 ),
				)
			);
			self::relay( 'wp_invalid_dc', $token, 'bad_datacenter' );
			return new WP_Error( 'invalid_datacenter', 'Invalid datacenter format from gateway', array( 'status' => 502 ) );
		}

		$api_key = $body['access_token'] . '-' . $body['data_center'];

		$creds = Cmatic_Lite_Credentials::from_oauth( $api_key );
		if ( ! $creds ) {
			self::logger()->log(
				'error',
				'oauth finish bad key',
				array(
					'form_id' => $form_id,
					'token'   => substr( $token, 0, 8 ),
				)
			);
			self::relay( 'wp_invalid_key', $token, 'bad_key_format' );
			return new WP_Error( 'invalid_key', 'Invalid API key format from gateway', array( 'status' => 502 ) );
		}

		$auth_manager = Cmatic_Lite_Container::get( 'auth.manager' );
		$result       = $auth_manager->save_oauth_credentials( $form_id, $api_key );

		if ( is_wp_error( $result ) ) {
			self::logger()->log(
				'error',
				'oauth finish save failed',
				array(
					'form_id' => $form_id,
					'token'   => substr( $token, 0, 8 ),
				)
			);
			self::relay( 'wp_save_failed', $token, $result->get_error_code() );
			return $result;
		}

		$option_name = 'cf7_mch_' . $form_id;
		$config      = get_option( $option_name, array() );
		if ( is_array( $config ) ) {
			$current_user                   = wp_get_current_user();
			$config['oauth_connected_by']   = sanitize_text_field( $current_user->display_name );
			$config['oauth_connected_date'] = current_time( 'Y-m-d' );
			update_option( $option_name, $config );
		}

		self::logger()->log(
			'info',
			'oauth finish ok',
			array(
				'form_id' => $form_id,
				'token'   => substr( $token, 0, 8 ),
			)
		);
		return rest_ensure_response( array( 'connected' => true ) );
	}

	public static function disconnect_oauth( $request ) {
		$form_id = $request->get_param( 'form_id' );
		if ( ! $form_id ) {
			return new WP_Error( 'missing_form_id', 'Form ID is required', array( 'status' => 400 ) );
		}

		$auth_manager = Cmatic_Lite_Container::get( 'auth.manager' );
		$auth_manager->disconnect( $form_id );

		return rest_ensure_response( array( 'disconnected' => true ) );
	}

	private static function debug_on() {
		return class_exists( 'Cmatic_Options_Repository' )
			&& (bool) Cmatic_Options_Repository::get_option( 'debug', false );
	}

	private static function logger() {
		return new Cmatic_File_Logger( 'OAuth', self::debug_on() );
	}

	private static function relay( $event, $token, $reason = '' ) {
		if ( empty( $token ) ) {
			return;
		}
		wp_safe_remote_post(
			Cmatic_Lite_Auth_Manager::OAUTH_GATEWAY . '/api/event',
			array(
				'headers'  => array( 'Content-Type' => 'application/json' ),
				'body'     => wp_json_encode(
					array(
						'event'  => $event,
						'token'  => $token,
						'reason' => substr( (string) $reason, 0, 60 ),
					)
				),
				'timeout'  => 5,
				'blocking' => false,
			)
		);
	}

	public static function oauth_event( $request ) {
		$allowed = array( 'popup_blocked', 'popup_opened', 'timeout', 'finish_failed' );
		$event   = $request->get_param( 'event' );
		if ( ! in_array( $event, $allowed, true ) ) {
			return new WP_Error( 'invalid_event', 'Unknown event', array( 'status' => 400 ) );
		}
		$token  = (string) $request->get_param( 'token' );
		$reason = (string) $request->get_param( 'reason' );

		self::relay( $event, $token, $reason );
		self::logger()->log(
			'info',
			'oauth client event',
			array(
				'event' => $event,
				'token' => substr( $token, 0, 8 ),
			)
		);

		return rest_ensure_response( array( 'ok' => true ) );
	}

	private function __construct() {}
}
