<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

use Cmatic\Lite\Rest\Args;

final class Cmatic_Rest_Lists {

	/**
	 * @var string REST namespace.
	 */
	protected static $namespace = 'chimpmatic-lite/v1';

	/**
	 * @var bool Whether initialized.
	 */
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
			'/merge-fields',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'get_merge_fields' ),
				'permission_callback' => array( 'Cmatic_Rest_Permissions', 'form' ),
				'args'                => array(
					'form_id' => Args::form_id(),
					'list_id' => Args::of( 'string', 'sanitize_text_field' ),
				),
			)
		);
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

	private static function record_transition( string $operation, bool $success, string $failure_class, $value, string $fallback ): void {
		Cmatic_Lite_Signls_Outcome::record( 'mailchimp', $operation, $success, $failure_class, $value, $fallback );
	}

	private function __construct() {}
}
