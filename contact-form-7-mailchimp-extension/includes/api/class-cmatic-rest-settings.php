<?php
/**
 * REST API controller for global settings.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

final class Cmatic_Rest_Settings {

	/** @var string REST namespace. */
	protected static $namespace = 'chimpmatic-lite/v1';

	/** @var bool Whether initialized. */
	protected static $initialized = false;

	/** @var array Allowed GLOBAL settings configuration (toggles in Advanced Settings panel). */
	protected static $allowed_settings = array(
		'debug'       => array(
			'type' => 'cmatic',
			'path' => 'debug',
		),
		'backlink'    => array(
			'type' => 'cmatic',
			'path' => 'backlink',
		),
		'auto_update' => array(
			'type' => 'cmatic',
			'path' => 'auto_update',
		),
		'telemetry'   => array(
			'type' => 'signls',
			'path' => 'signls.consent_status',
		),
	);

	/** @var array Field labels for user messages. */
	protected static $field_labels = array(
		'debug'       => 'Debug Logger',
		'backlink'    => 'Developer Backlink',
		'auto_update' => 'Auto Update',
		'telemetry'   => 'Help Us Improve',
	);

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
			'/settings/toggle',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'toggle_setting' ),
				'permission_callback' => array( self::class, 'check_admin_permission' ),
				'args'                => array(
					'field'   => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'enabled' => array(
						'required'          => true,
						'type'              => 'boolean',
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
				),
			)
		);

		register_rest_route(
			self::$namespace,
			'/notices/dismiss',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'dismiss_notice' ),
				'permission_callback' => array( self::class, 'check_admin_permission' ),
				'args'                => array(
					'notice_id' => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => 'news',
						'sanitize_callback' => 'sanitize_text_field',
						'enum'              => array( 'news', 'upgrade', 'license_banner', 'signls_consent' ),
					),
					'state'     => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);
	}

	public static function check_admin_permission( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				esc_html__( 'You do not have permission to access this endpoint.', 'contact-form-7-mailchimp-extension' ),
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

	public static function toggle_setting( $request ) {
		$field   = $request->get_param( 'field' );
		$enabled = $request->get_param( 'enabled' );

		if ( ! array_key_exists( $field, self::$allowed_settings ) ) {
			return new WP_Error(
				'invalid_field',
				__( 'Invalid settings field.', 'contact-form-7-mailchimp-extension' ),
				array( 'status' => 400 )
			);
		}

		$field_config = self::$allowed_settings[ $field ];

		if ( 'telemetry' === $field ) {
			if ( ! self::handle_signls_toggle( (bool) $enabled ) ) {
				return new WP_Error( 'setting_not_saved', esc_html__( 'The sharing preference could not be saved.', 'contact-form-7-mailchimp-extension' ), array( 'status' => 500 ) );
			}
		} else {
			Cmatic_Options_Repository::set_option( $field_config['path'], $enabled ? 1 : 0 );
		}

		$label = self::$field_labels[ $field ] ?? ucfirst( str_replace( '_', ' ', $field ) );

		return rest_ensure_response(
			array(
				'success' => true,
				'field'   => $field,
				'enabled' => $enabled,
				'message' => $enabled
					// translators: %s: setting label.
					? sprintf( __( '%s enabled.', 'contact-form-7-mailchimp-extension' ), $label )
					// translators: %s: setting label.
					: sprintf( __( '%s disabled.', 'contact-form-7-mailchimp-extension' ), $label ),
			)
		);
	}

	private static function handle_signls_toggle( bool $enabled ): bool {
		$data = Cmatic_Options_Repository::get_all_options();
		if ( ! isset( $data['signls'] ) || ! is_array( $data['signls'] ) ) {
			$data['signls'] = array();
		}
		if ( ! isset( $data['telemetry'] ) || ! is_array( $data['telemetry'] ) ) {
			$data['telemetry'] = array();
		}
		$now                                       = time();
		$data['telemetry']['enabled']              = $enabled;
		$data['signls']['consent_status']          = $enabled ? 'enabled' : 'disabled';
		$data['signls']['consent_version']         = Cmatic_Lite_Signls_Privacy::CONSENT_VERSION;
		$data['signls']['consent_last_changed_at'] = $now;
		$data['signls']['consent_source']          = 'admin_settings_rest';
		if ( $enabled && empty( $data['signls']['consent_first_enabled_at'] ) ) {
			$data['signls']['consent_first_enabled_at'] = $now;
		}
		$saved = Cmatic_Options_Repository::instance()->save( $data );
		if ( ! $saved && get_option( 'cmatic', array() ) !== $data ) {
			return false;
		}
		if ( $enabled ) {
			Signls_Sdk_Bridge_1_1_7::enable( 'contact-form-7-mailchimp-extension', 'admin_settings_rest', Cmatic_Lite_Signls_Privacy::CONSENT_VERSION );
		} else {
			Signls_Sdk_Bridge_1_1_7::disable( 'contact-form-7-mailchimp-extension', 'admin_settings_rest', Cmatic_Lite_Signls_Privacy::CONSENT_VERSION );
		}
		return true;
	}

	protected static function handle_telemetry_toggle( $enabled ) {
		return self::handle_signls_toggle( (bool) $enabled );
	}

	public static function toggle_telemetry( $request ) {
		$enabled = $request->get_param( 'enabled' );

		$saved = self::handle_telemetry_toggle( $enabled );
		if ( ! $saved ) {
			return new WP_Error( 'setting_not_saved', esc_html__( 'The sharing preference could not be saved.', 'contact-form-7-mailchimp-extension' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'enabled' => $enabled,
				'message' => $enabled
					? esc_html__( 'Telemetry enabled. Thank you for helping improve the plugin!', 'contact-form-7-mailchimp-extension' )
					: esc_html__( 'Telemetry disabled.', 'contact-form-7-mailchimp-extension' ),
			)
		);
	}

	public static function dismiss_notice( $request ) {
		$notice_id = $request->get_param( 'notice_id' );

		switch ( $notice_id ) {
			case 'signls_consent':
				// Compatibility for an already-open pre-removal admin page. The retired notice
				// disappears on navigation; its stale dismissal request intentionally has no side effects.
				$message = __( 'Sharing notice retired.', 'contact-form-7-mailchimp-extension' );
				break;

			case 'license_banner':
				$result = class_exists( 'Cmatic_License_Banner' )
					? Cmatic_License_Banner::handle_dismiss( (string) $request->get_param( 'state' ) )
					: false;
				if ( false === $result ) {
					return new WP_Error( 'bad_state', esc_html__( 'Unknown banner state.', 'contact-form-7-mailchimp-extension' ), array( 'status' => 400 ) );
				}
				$message = __( 'License banner snoozed.', 'contact-form-7-mailchimp-extension' );
				break;

			case 'upgrade':
				Cmatic_Options_Repository::set_option( 'ui.upgrade_clicked', true );
				$message = __( 'Upgrade notice dismissed.', 'contact-form-7-mailchimp-extension' );
				break;

			case 'news':
			default:
				Cmatic_Options_Repository::set_option( 'ui.news', false );
				$message = __( 'Notice dismissed successfully.', 'contact-form-7-mailchimp-extension' );
				break;
		}

		return rest_ensure_response(
			array(
				'success'   => true,
				'message'   => esc_html( $message ),
				'dismissed' => $notice_id,
			)
		);
	}


	private function __construct() {}
}
