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
			'type' => 'cmatic',
			'path' => 'telemetry.enabled',
		),
	);

	/** @var array Field labels for user messages. */
	protected static $field_labels = array(
		'debug'       => 'Debug Logger',
		'backlink'    => 'Developer Backlink',
		'auto_update' => 'Auto Update',
		'telemetry'   => 'Usage Statistics',
	);

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
			'/settings/toggle',
			array(
				'methods'             => 'POST',
				'callback'            => array( static::class, 'toggle_setting' ),
				'permission_callback' => array( static::class, 'check_admin_permission' ),
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
				'callback'            => array( static::class, 'dismiss_notice' ),
				'permission_callback' => array( static::class, 'check_admin_permission' ),
				'args'                => array(
					'notice_id' => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => 'news',
						'sanitize_callback' => 'sanitize_text_field',
						'enum'              => array( 'news', 'upgrade' ),
					),
				),
			)
		);

	}

	public static function check_admin_permission( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				esc_html__( 'You do not have permission to access this endpoint.', 'chimpmatic-lite' ),
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

	public static function toggle_setting( $request ) {
		$field   = $request->get_param( 'field' );
		$enabled = $request->get_param( 'enabled' );

		if ( ! array_key_exists( $field, self::$allowed_settings ) ) {
			return new WP_Error(
				'invalid_field',
				__( 'Invalid settings field.', 'chimpmatic-lite' ),
				array( 'status' => 400 )
			);
		}

		$field_config = self::$allowed_settings[ $field ];

		if ( 'telemetry' === $field ) {
			self::handle_telemetry_toggle( $enabled );
		}

		Cmatic_Options_Repository::set_option( $field_config['path'], $enabled ? 1 : 0 );

		$label = self::$field_labels[ $field ] ?? ucfirst( str_replace( '_', ' ', $field ) );

		return rest_ensure_response(
			array(
				'success' => true,
				'field'   => $field,
				'enabled' => $enabled,
				'message' => $enabled
					? sprintf( __( '%s enabled.', 'chimpmatic-lite' ), $label )
					: sprintf( __( '%s disabled.', 'chimpmatic-lite' ), $label ),
			)
		);
	}

	protected static function handle_telemetry_toggle( $enabled ) {
		if ( class_exists( 'Cmatic\\Metrics\\Core\\Storage' ) && class_exists( 'Cmatic\\Metrics\\Core\\Tracker' ) ) {
			$storage_enabled = \Cmatic\Metrics\Core\Storage::is_enabled();
			if ( ! $enabled && $storage_enabled ) {
				\Cmatic\Metrics\Core\Tracker::on_opt_out();
			}
			if ( $enabled && ! $storage_enabled ) {
				\Cmatic\Metrics\Core\Tracker::on_re_enable();
			}
		}
	}

	public static function toggle_telemetry( $request ) {
		$enabled = $request->get_param( 'enabled' );

		self::handle_telemetry_toggle( $enabled );
		Cmatic_Options_Repository::set_option( 'telemetry.enabled', $enabled );

		return rest_ensure_response(
			array(
				'success' => true,
				'enabled' => $enabled,
				'message' => $enabled
					? esc_html__( 'Telemetry enabled. Thank you for helping improve the plugin!', 'chimpmatic-lite' )
					: esc_html__( 'Telemetry disabled.', 'chimpmatic-lite' ),
			)
		);
	}

	public static function dismiss_notice( $request ) {
		$notice_id = $request->get_param( 'notice_id' );

		switch ( $notice_id ) {
			case 'upgrade':
				Cmatic_Options_Repository::set_option( 'ui.upgrade_clicked', true );
				$message = __( 'Upgrade notice dismissed.', 'chimpmatic-lite' );
				break;

			case 'news':
			default:
				Cmatic_Options_Repository::set_option( 'ui.news', false );
				$message = __( 'Notice dismissed successfully.', 'chimpmatic-lite' );
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
