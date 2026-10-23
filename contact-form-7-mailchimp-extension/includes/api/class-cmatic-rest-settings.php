<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

use Cmatic\Lite\Rest\Args;

final class Cmatic_Rest_Settings {

	/**
	 * @var string REST namespace.
	 */
	protected static $namespace = 'chimpmatic-lite/v1';

	/**
	 * @var bool Whether initialized.
	 */
	protected static $initialized = false;

	/**
	 * @var array Allowed GLOBAL settings configuration (toggles in Advanced Settings panel).
	 */
	protected static $allowed_settings = array(
		'debug'              => array(
			'type' => 'cmatic',
			'path' => 'debug',
		),
		'backlink'           => array(
			'type' => 'cmatic',
			'path' => 'backlink',
		),
		'auto_update'        => array(
			'type' => 'cmatic',
			'path' => 'auto_update',
		),
		'telemetry'          => array(
			'type' => 'signls',
			'path' => 'signls.consent_status',
		),
		'purge_on_uninstall' => array(
			'type' => 'cmatic',
			'path' => 'purge_on_uninstall',
		),
		'sender_context'     => array(
			'type' => 'cmatic',
			'path' => 'sender_context',
		),
	);

	/**
	 * @var array Field labels for user messages.
	 */
	protected static $field_labels = array(
		'debug'              => 'Debug Logger',
		'backlink'           => 'Developer Backlink',
		'auto_update'        => 'Auto Update',
		'telemetry'          => 'Help Us Improve',
		'purge_on_uninstall' => 'Remove data on uninstall',
		'sender_context'     => 'Sender context in emails',
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
				'permission_callback' => array( 'Cmatic_Rest_Permissions', 'admin' ),
				'args'                => array(
					'field'   => Args::of( 'string', 'sanitize_text_field' ),
					'enabled' => Args::of( 'boolean', 'rest_sanitize_boolean' ),
				),
			)
		);

		register_rest_route(
			self::$namespace,
			'/notices/dismiss',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'dismiss_notice' ),
				'permission_callback' => array( 'Cmatic_Rest_Permissions', 'admin' ),
				'args'                => array(
					'notice_id' => Args::of( 'string', 'sanitize_text_field', false ) + array(
						'default' => 'news',
						'enum'    => array( 'news', 'upgrade', 'license_banner', 'signls_consent' ),
					),
					'state'     => Args::of( 'string', 'sanitize_key', false ) + array( 'default' => '' ),
				),
			)
		);

		register_rest_route(
			'chimpmatic-lite/v1',
			'/system-report',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'system_report' ),
				'permission_callback' => array( 'Cmatic_Rest_Permissions', 'admin' ),
			)
		);
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
			if ( 'debug' === $field ) {
				if ( $enabled ) {
					Cmatic_Debug_Log::switched_on();
				} else {
					Cmatic_Debug_Log::switched_off();
				}
			}
		}

		$label = self::$field_labels[ $field ] ?? ucfirst( str_replace( '_', ' ', $field ) );

		return rest_ensure_response(
			array(
				'success'     => true,
				'field'       => $field,
				'enabled'     => $enabled,
				'debug_until' => 'debug' === $field ? Cmatic_Debug_Log::until() : 0,
				'message'     => $enabled
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

	public static function dismiss_notice( $request ) {
		$notice_id = $request->get_param( 'notice_id' );

		switch ( $notice_id ) {
			case 'signls_consent':
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


	public static function system_report() {
		return rest_ensure_response(
			array(
				'success' => true,
				'report'  => Cmatic_System_Report::build(),
			)
		);
	}

	private function __construct() {}
}
