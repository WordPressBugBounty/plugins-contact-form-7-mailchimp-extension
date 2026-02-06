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
		add_action( 'rest_api_init', array( static::class, 'register_routes' ) );
		self::$initialized = true;
	}

	public static function register_routes() {
		register_rest_route(
			self::$namespace,
			'/lists',
			array(
				'methods'             => 'POST',
				'callback'            => array( static::class, 'get_lists' ),
				'permission_callback' => array( static::class, 'check_form_permission' ),
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
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $param ) {
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
				'callback'            => array( static::class, 'get_merge_fields' ),
				'permission_callback' => array( static::class, 'check_form_permission' ),
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
				'callback'            => array( static::class, 'get_api_key' ),
				'permission_callback' => array( static::class, 'check_form_permission' ),
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
				esc_html__( 'You do not have permission to access the API key.', 'chimpmatic-lite' ),
				array( 'status' => 403 )
			);
		}

		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'rest_cookie_invalid_nonce',
				esc_html__( 'Cookie nonce is invalid.', 'chimpmatic-lite' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	public static function get_lists( $request ) {
		$form_id = $request->get_param( 'form_id' );
		$api_key = $request->get_param( 'api_key' );

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
				'name' => 'Subscriber Email',
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

			$settings_to_save = array_merge(
				$cf7_mch,
				$validation_result,
				$lists_result,
				array(
					'api'          => $api_key,
					'merge_fields' => $merge_fields,
				)
			);
			update_option( $option_name, $settings_to_save );

			// Record first successful connection after form settings are saved.
			if ( 1 === (int) $api_valid && ! Cmatic_Options_Repository::get_option( 'api.first_connected' ) ) {
				Cmatic_Options_Repository::set_option( 'api.first_connected', time() );
			}

			if ( ! empty( $lists_result['lisdata'] ) ) {
				Cmatic_Options_Repository::set_option( 'lisdata', $lists_result['lisdata'] );
				Cmatic_Options_Repository::set_option( 'lisdata_updated', time() );
			}

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
			$logger = new Cmatic_File_Logger( 'REST-API-Error', true );
			$logger->log( 'ERROR', 'REST API list loading failed.', $e->getMessage() );

			return new WP_Error(
				'api_request_failed',
				esc_html__( 'Failed to load Mailchimp lists. Check debug log for details.', 'chimpmatic-lite' ),
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
			return new WP_Error(
				'missing_api_key',
				esc_html__( 'API key not found. Please connect to Mailchimp first.', 'chimpmatic-lite' ),
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
				'name' => 'Subscriber Email',
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

			$cf7_mch['merge_fields'] = $merge_fields;
			$cf7_mch['list']         = $list_id;
			update_option( $option_name, $cf7_mch );

			if ( ! Cmatic_Options_Repository::get_option( 'api.audience_selected' ) ) {
				Cmatic_Options_Repository::set_option( 'api.audience_selected', time() );
			}

			if ( class_exists( 'Cmatic\\Metrics\\Core\\Sync' ) && class_exists( 'Cmatic\\Metrics\\Core\\Collector' ) ) {
				$payload = \Cmatic\Metrics\Core\Collector::collect( 'list_selected' );
				\Cmatic\Metrics\Core\Sync::send_async( $payload );
			}

			return rest_ensure_response(
				array(
					'success'      => true,
					'merge_fields' => $merge_fields,
				)
			);

		} catch ( Exception $e ) {
			return new WP_Error(
				'api_request_failed',
				esc_html__( 'Failed to load merge fields. Check debug log for details.', 'chimpmatic-lite' ),
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

		$api_key = isset( $cf7_mch['api'] ) ? $cf7_mch['api'] : '';

		return rest_ensure_response(
			array(
				'success' => true,
				'api_key' => $api_key,
			)
		);
	}

	private function __construct() {}
}
