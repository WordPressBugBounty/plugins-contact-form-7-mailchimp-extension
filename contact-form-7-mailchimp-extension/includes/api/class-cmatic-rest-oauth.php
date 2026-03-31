<?php
/**
 * OAuth REST API controller.
 *
 * @package contact-form-7-mailchimp-extension
 */

defined( 'ABSPATH' ) || exit;

final class Cmatic_Rest_OAuth {

	protected static $namespace   = 'chimpmatic-lite/v1';
	protected static $initialized = false;

	public static function init() {
		if ( self::$initialized ) {
			return;
		}
		add_action( 'rest_api_init', array( static::class, 'register_routes' ) );
		self::$initialized = true;
	}

	public static function register_routes() {
		register_rest_route(
			self::$namespace,
			'/oauth/start',
			array(
				'methods'             => 'POST',
				'callback'            => array( static::class, 'oauth_start' ),
				'permission_callback' => array( static::class, 'check_admin_permissions' ),
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
				'callback'            => array( static::class, 'oauth_check_status' ),
				'permission_callback' => array( static::class, 'check_admin_permissions' ),
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
				'callback'            => array( static::class, 'oauth_finish' ),
				'permission_callback' => array( static::class, 'check_admin_permissions' ),
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
				'callback'            => array( static::class, 'disconnect_oauth' ),
				'permission_callback' => array( static::class, 'check_admin_permissions' ),
				'args'                => array(
					'form_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
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

		$response = wp_safe_remote_post(
			Cmatic_Lite_Auth_Manager::OAUTH_GATEWAY . '/api/start',
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'domain' => site_url(),
						'secret' => $secret,
					)
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$code = wp_remote_retrieve_response_code( $response );

		if ( 201 !== $code || empty( $body['token'] ) ) {
			return new WP_Error( 'oauth_start_failed', 'Could not start OAuth session', array( 'status' => 502 ) );
		}

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

		// SSRF guard: only allow requests to our gateway domain.
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

		// Whitelist response fields to prevent gateway data leakage.
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
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code || empty( $body['access_token'] ) || empty( $body['data_center'] ) ) {
			return new WP_Error( 'oauth_finish_failed', 'Could not retrieve credentials', array( 'status' => 502 ) );
		}

		// Validate datacenter format (e.g., us7, us21) to prevent host injection.
		if ( ! preg_match( '/^[a-z]{2}\d{1,3}$/', $body['data_center'] ) ) {
			return new WP_Error( 'invalid_datacenter', 'Invalid datacenter format from gateway', array( 'status' => 502 ) );
		}

		$api_key = $body['access_token'] . '-' . $body['data_center'];

		$creds = Cmatic_Lite_Credentials::from_oauth( $api_key );
		if ( ! $creds ) {
			return new WP_Error( 'invalid_key', 'Invalid API key format from gateway', array( 'status' => 502 ) );
		}

		$auth_manager = Cmatic_Lite_Container::get( 'auth.manager' );
		$result       = $auth_manager->save_oauth_credentials( $form_id, $api_key );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Fetch Mailchimp account name for display in the connected state.
		$dc           = $creds->get_datacenter();
		$account_name = '';
		$mc_response  = wp_remote_get(
			'https://' . $dc . '.api.mailchimp.com/3.0/',
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( 'chimpmatic:' . $api_key ),
				),
				'timeout' => 10,
			)
		);

		if ( ! is_wp_error( $mc_response ) ) {
			$mc_body = json_decode( wp_remote_retrieve_body( $mc_response ), true );
			if ( ! empty( $mc_body['account_name'] ) ) {
				$account_name = sanitize_text_field( $mc_body['account_name'] );
			}
		}

		$option_name = 'cf7_mch_' . $form_id;
		$config      = get_option( $option_name, array() );
		if ( is_array( $config ) ) {
			if ( $account_name ) {
				$config['oauth_account_name'] = $account_name;
			}
			$current_user = wp_get_current_user();
			$config['oauth_connected_by']   = sanitize_text_field( $current_user->display_name );
			$config['oauth_connected_date'] = current_time( 'Y-m-d' );
			update_option( $option_name, $config );
		}

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

	private function __construct() {}
}
