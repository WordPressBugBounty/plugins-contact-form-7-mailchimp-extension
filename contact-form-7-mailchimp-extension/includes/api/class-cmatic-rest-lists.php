<?php
/**
 * REST API controller for Mailchimp lists and merge fields.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

final class Cmatic_Rest_Lists {

	/** @var string REST namespace. */
	protected static $namespace = 'chimpmatic-lite/v1';

	/** @var bool Whether initialized. */
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
			'/lists',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'get_lists' ),
				'permission_callback' => array( self::class, 'check_form_permission' ),
				'args'                => array(
					'form_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && $param > 0;
						},
					),
					'api_key' => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $param ) {
							if ( empty( $param ) ) {
								return true;
							}
							return preg_match( '/^[a-f0-9]{32}-[a-z]{2,3}\d+$/', $param );
						},
					),
				),
			)
		);

		register_rest_route(
			self::$namespace,
			'/merge-fields',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'get_merge_fields' ),
				'permission_callback' => array( self::class, 'check_form_permission' ),
				'args'                => array(
					'form_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'list_id' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::$namespace,
			'/api-key/(?P<form_id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'get_api_key' ),
				'permission_callback' => array( self::class, 'check_form_permission' ),
				'args'                => array(
					'form_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && $param > 0;
						},
					),
				),
			)
		);
	}

	public static function check_form_permission( $request ) {
		$form_id = $request->get_param( 'form_id' );

		if ( ! current_user_can( 'wpcf7_edit_contact_form', $form_id ) ) {
			return new WP_Error(
				'rest_forbidden',
				esc_html__( 'You do not have permission to access the API key.', 'contact-form-7-mailchimp-extension' ),
				array( 'status' => 403 )
			);
		}

		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'rest_cookie_invalid_nonce',
				esc_html__( 'Cookie nonce is invalid.', 'contact-form-7-mailchimp-extension' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	public static function get_lists( $request ) {
		$form_id = $request->get_param( 'form_id' );
		$api_key = $request->get_param( 'api_key' );

		$key_from_oauth = false;

		if ( empty( $api_key ) ) {
			$auth_manager = Cmatic_Lite_Container::get( 'auth.manager' );
			if ( $auth_manager ) {
				$credentials = $auth_manager->get_credentials( $form_id );
				if ( $credentials ) {
					$api_key        = $credentials->get_api_key();
					$key_from_oauth = true;
				} else {
					$api_key = $auth_manager->resolve_api_key( $form_id );
				}
			}
		}

		if ( empty( $api_key ) ) {
			self::record_transition( 'connect', false, 'configuration', 'API key is not configured.', 'configuration' );
			return new WP_Error(
				'missing_api_key',
				esc_html__( 'API key not found. Please connect to Mailchimp first.', 'contact-form-7-mailchimp-extension' ),
				array( 'status' => 400 )
			);
		}

		if ( ! Cmatic_Options_Repository::get_option( 'api.sync_attempted' ) ) {
			Cmatic_Options_Repository::set_option( 'api.sync_attempted', time() );
		}
		$current_count = (int) Cmatic_Options_Repository::get_option( 'api.sync_attempts_count', 0 );
		Cmatic_Options_Repository::set_option( 'api.sync_attempts_count', $current_count + 1 );

		$option_name = 'cf7_mch_' . $form_id;
		$cf7_mch     = get_option( $option_name, array() );

		if ( ! is_array( $cf7_mch ) ) {
			$cf7_mch = array();
		}

		$logfile_enabled = (bool) get_option( CMATIC_LOG_OPTION, false );

		try {
			$validation_result = Cmatic_Lite_Api_Service::validate_key( $api_key, $logfile_enabled );
			$api_valid         = $validation_result['api-validation'] ?? 0;

			$lists_result      = ( 1 === (int) $api_valid ) ? Cmatic_Lite_Api_Service::get_lists( $api_key, $logfile_enabled ) : array( 'lisdata' => array() );
			$lists_data        = $lists_result['lisdata'] ?? array();
			$merge_fields_data = $lists_result['merge_fields'] ?? array();

			$lists = array();
			if ( $api_valid === 1 && isset( $lists_data['lists'] ) && is_array( $lists_data['lists'] ) ) {
				foreach ( $lists_data['lists'] as $list ) {
					if ( is_array( $list ) && isset( $list['id'], $list['name'] ) ) {
						$member_count = isset( $list['stats']['member_count'] ) ? intval( $list['stats']['member_count'] ) : 0;
						$field_count  = isset( $list['stats']['merge_field_count'] ) ? intval( $list['stats']['merge_field_count'] ) : 0;

						$lists[] = array(
							'id'           => $list['id'],
							'name'         => $list['name'],
							'member_count' => $member_count,
							'field_count'  => $field_count,
						);
					}
				}
			}

			$excluded_types = array( 'address', 'birthday', 'imageurl', 'zip' );
			$merge_fields   = array();

			$merge_fields[] = array(
				'tag'  => 'EMAIL',
				'name' => 'Contact email address',
				'type' => 'email',
			);

			if ( isset( $merge_fields_data['merge_fields'] ) && is_array( $merge_fields_data['merge_fields'] ) ) {
				$fields_to_process = $merge_fields_data['merge_fields'];

				usort(
					$fields_to_process,
					function ( $a, $b ) {
						return ( $a['display_order'] ?? 0 ) - ( $b['display_order'] ?? 0 );
					}
				);

				$count = 1;
				foreach ( $fields_to_process as $field ) {
					$field_type = strtolower( $field['type'] ?? '' );
					$field_tag  = $field['tag'] ?? '';

					if ( $field_tag === 'EMAIL' ) {
						continue;
					}

					if ( in_array( $field_type, $excluded_types, true ) ) {
						continue;
					}

					if ( $count >= CMATIC_LITE_FIELDS ) {
						break;
					}

					$merge_fields[] = array(
						'tag'  => $field_tag,
						'name' => $field['name'] ?? '',
						'type' => $field_type,
					);
					++$count;
				}
			}

			$extra = array(
				'api'          => $key_from_oauth ? '' : $api_key,
				'merge_fields' => $merge_fields,
			);

			if ( ! empty( $validation_result['account_name'] ) ) {
				$extra['oauth_account_name'] = $validation_result['account_name'];
			}

			$settings_to_save = array_merge( $cf7_mch, $validation_result, $lists_result, $extra );
			update_option( $option_name, $settings_to_save );

			if ( 1 === (int) $api_valid && ! Cmatic_Options_Repository::get_option( 'api.first_connected' ) ) {
				Cmatic_Options_Repository::set_option( 'api.first_connected', time() );
			}

			if ( ! empty( $lists_result['lisdata'] ) ) {
				Cmatic_Options_Repository::set_option( 'lisdata', $lists_result['lisdata'] );
				Cmatic_Options_Repository::set_option( 'lisdata_updated', time() );
			}
			self::record_transition(
				'connect',
				1 === (int) $api_valid,
				1 === (int) $api_valid ? 'unknown' : 'auth',
				1 === (int) $api_valid ? '' : $validation_result,
				1 === (int) $api_valid ? 'unknown' : 'revoked_credential'
			);

			return rest_ensure_response(
				array(
					'success'      => true,
					'api_valid'    => $api_valid === 1,
					'lists'        => $lists,
					'total'        => count( $lists ),
					'merge_fields' => $merge_fields,
				)
			);

		} catch ( Exception $e ) {
			self::record_transition( 'connect', false, 'remote_rejected', $e, 'remote_rejected' );
			$logger = new Cmatic_File_Logger( 'REST-API-Error', true );
			$logger->log( 'ERROR', 'REST API list loading failed.', $e->getMessage() );

			return new WP_Error(
				'api_request_failed',
				esc_html__( 'Failed to load Mailchimp audiences. Check debug log for details.', 'contact-form-7-mailchimp-extension' ),
				array( 'status' => 500 )
			);
		}
	}

	public static function get_merge_fields( $request ) {
		$form_id = $request->get_param( 'form_id' );
		$list_id = $request->get_param( 'list_id' );

		$option_name     = 'cf7_mch_' . $form_id;
		$cf7_mch         = get_option( $option_name, array() );
		$api_key         = $cf7_mch['api'] ?? '';
		$logfile_enabled = (bool) get_option( CMATIC_LOG_OPTION, false );

		if ( empty( $api_key ) ) {
			$auth_manager = Cmatic_Lite_Container::get( 'auth.manager' );
			if ( $auth_manager ) {
				$api_key = $auth_manager->resolve_api_key( $form_id, '', $cf7_mch );
			}
		}

		if ( empty( $api_key ) ) {
			self::record_transition( 'refresh_schema', false, 'configuration', 'API key is not configured.', 'configuration' );
			return new WP_Error(
				'missing_api_key',
				esc_html__( 'API key not found. Please connect to Mailchimp first.', 'contact-form-7-mailchimp-extension' ),
				array( 'status' => 400 )
			);
		}

		try {
			$merge_fields_result = Cmatic_Lite_Api_Service::get_merge_fields( $api_key, $list_id, $logfile_enabled );
			$merge_fields_data   = $merge_fields_result['merge_fields'] ?? array();

			$excluded_types = array( 'address', 'birthday', 'imageurl', 'zip' );
			$merge_fields   = array();

			$merge_fields[] = array(
				'tag'  => 'EMAIL',
				'name' => 'Contact email address',
				'type' => 'email',
			);

			$raw_field_count = 0;

			if ( isset( $merge_fields_data['merge_fields'] ) && is_array( $merge_fields_data['merge_fields'] ) ) {
				$fields_to_process = $merge_fields_data['merge_fields'];
				$raw_field_count   = count( $fields_to_process ) + 1;

				usort(
					$fields_to_process,
					function ( $a, $b ) {
						return ( $a['display_order'] ?? 0 ) - ( $b['display_order'] ?? 0 );
					}
				);

				$count = 1;
				foreach ( $fields_to_process as $field ) {
					$field_type = strtolower( $field['type'] ?? '' );
					$field_tag  = $field['tag'] ?? '';

					if ( $field_tag === 'EMAIL' ) {
						continue;
					}

					if ( in_array( $field_type, $excluded_types, true ) ) {
						continue;
					}

					if ( $count >= CMATIC_LITE_FIELDS ) {
						break;
					}

					$merge_fields[] = array(
						'tag'  => $field_tag,
						'name' => $field['name'] ?? '',
						'type' => $field_type,
					);
					++$count;
				}
			}

			$cf7_mch['merge_fields']       = $merge_fields;
			$cf7_mch['list']               = $list_id;
			$cf7_mch['total_merge_fields'] = $raw_field_count;
			update_option( $option_name, $cf7_mch );

			if ( ! Cmatic_Options_Repository::get_option( 'api.audience_selected' ) ) {
				Cmatic_Options_Repository::set_option( 'api.audience_selected', time() );
			}

			self::record_transition( 'refresh_schema', true, 'unknown', '', 'unknown' );

			$audience_name = '';
			if ( isset( $cf7_mch['lisdata']['lists'] ) && is_array( $cf7_mch['lisdata']['lists'] ) ) {
				foreach ( $cf7_mch['lisdata']['lists'] as $cmatic_list ) {
					if ( isset( $cmatic_list['id'], $cmatic_list['name'] ) && $cmatic_list['id'] === $list_id ) {
						$audience_name = (string) $cmatic_list['name'];
						break;
					}
				}
			}

			return rest_ensure_response(
				array(
					'success'            => true,
					'merge_fields'       => $merge_fields,
					'audience_name'      => $audience_name,
					'total_merge_fields' => (int) $raw_field_count,
					'lite_limit'         => CMATIC_LITE_FIELDS,
				)
			);

		} catch ( Exception $e ) {
			self::record_transition( 'refresh_schema', false, 'remote_rejected', $e, 'remote_rejected' );
			return new WP_Error(
				'api_request_failed',
				esc_html__( 'Failed to load merge fields. Check debug log for details.', 'contact-form-7-mailchimp-extension' ),
				array( 'status' => 500 )
			);
		}
	}

	public static function get_api_key( $request ) {
		$form_id     = $request->get_param( 'form_id' );
		$option_name = 'cf7_mch_' . $form_id;
		$cf7_mch     = get_option( $option_name, array() );

		if ( ! is_array( $cf7_mch ) ) {
			$cf7_mch = array();
		}

		$auth_type          = isset( $cf7_mch['auth_type'] ) ? sanitize_key( (string) $cf7_mch['auth_type'] ) : '';
		$auth_manager       = Cmatic_Lite_Container::get( 'auth.manager' );
		$credential_present = 'oauth' === $auth_type
			? ( $auth_manager && (bool) $auth_manager->get_credentials( $form_id ) )
			: ! empty( $cf7_mch['api'] );

		return rest_ensure_response(
			array(
				'success'            => true,
				'api_key'            => '',
				'auth_type'          => $auth_type,
				'credential_present' => $credential_present,
			)
		);
	}

	private static function record_transition( string $operation, bool $success, string $failure_class, $value, string $fallback ): void {
		try {
			$reason        = class_exists( 'Cmatic_Lite_Signls_Failure_Reason' )
				? Cmatic_Lite_Signls_Failure_Reason::from_value( $value, $fallback )
				: array(
					'code'   => $fallback,
					'sample' => '',
				);
			$failure_class = $success ? 'unknown' : self::failure_class_for_reason( $reason['code'], $failure_class );
			$recorded      = \Signls\Sdk\V1\CounterStore::record_outcome(
				'contact-form-7-mailchimp-extension',
				'mailchimp',
				$operation,
				$success,
				$failure_class,
				$success ? '' : $reason['code'],
				$success ? '' : $reason['sample']
			);
			if ( $recorded ) {
				Signls_Sdk_Bridge_1_1_7::relevant_change( 'contact-form-7-mailchimp-extension' );
			}
		} catch ( Throwable $error ) {
			// Signals must never change a REST result.
			return;
		}
	}

	private static function failure_class_for_reason( string $reason_code, string $fallback ): string {
		$map = array(
			'dns'                => 'transport_dns',
			'tls'                => 'transport_tls',
			'timeout'            => 'transport_timeout',
			'rate_limit'         => 'http_429',
			'http_4xx'           => 'http_4xx',
			'http_5xx'           => 'http_5xx',
			'revoked_credential' => 'auth',
			'permission'         => 'auth',
			'configuration'      => 'configuration',
			'validation'         => 'validation',
		);
		return isset( $map[ $reason_code ] ) ? $map[ $reason_code ] : $fallback;
	}

	private function __construct() {}
}
