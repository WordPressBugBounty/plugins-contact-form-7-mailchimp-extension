<?php
/**
 * REST API controller for per-form field and setting operations.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

final class Cmatic_Rest_Form {

	/** @var string Primary REST namespace. */
	protected static $namespace = 'chimpmatic-lite/v1';

	/** @var string Secondary REST namespace for form settings. */
	protected static $cmatic_namespace = 'cmatic';

	/** @var bool Whether initialized. */
	protected static $initialized = false;

	/** @var array Field pattern configuration. */
	protected static $field_patterns = array(
		'labeltags\\.(.+)'      => array(
			'type'     => 'boolean',
			'pro_only' => false,
			'nested'   => 'labeltags',
		),
		'field(\\d+)'           => array(
			'type'     => 'string',
			'pro_only' => false,
			'direct'   => true,
		),
		'customKey(\\d+)'       => array(
			'type'     => 'string',
			'pro_only' => false,
			'direct'   => true,
		),
		'customValue(\\d+)'     => array(
			'type'     => 'string',
			'pro_only' => false,
			'direct'   => true,
		),
		'GDPRCheck(\\d+)'       => array(
			'type'     => 'boolean',
			'pro_only' => true,
			'direct'   => true,
		),
		'GDPRCustomValue(\\d+)' => array(
			'type'     => 'string',
			'pro_only' => true,
			'direct'   => true,
		),
		'ggCheck(\\d+)'         => array(
			'type'     => 'boolean',
			'pro_only' => true,
			'direct'   => true,
		),
		'ggCustomValue(\\d+)'   => array(
			'type'     => 'string',
			'pro_only' => true,
			'direct'   => true,
		),
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
			'/tags/toggle',
			array(
				'methods'             => 'POST',
				'callback'            => array( static::class, 'toggle_tag' ),
				'permission_callback' => array( static::class, 'check_admin_permission' ),
				'args'                => array(
					'form_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'tag'     => array(
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
			'/form/field',
			array(
				'methods'             => 'POST',
				'callback'            => array( static::class, 'save_field' ),
				'permission_callback' => array( static::class, 'check_form_permission' ),
				'args'                => array(
					'form_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'field'   => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'value'   => array(
						'required' => false,
						'default'  => null,
					),
				),
			)
		);

		register_rest_route(
			self::$cmatic_namespace,
			'/form/setting',
			array(
				'methods'             => 'POST',
				'callback'            => array( static::class, 'save_setting' ),
				'permission_callback' => array( static::class, 'check_admin_permission' ),
				'args'                => array(
					'form_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && $param > 0;
						},
					),
					'field'   => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
					'value'   => array(
						'required'          => true,
						'type'              => 'boolean',
						'sanitize_callback' => function ( $value ) {
							return Cmatic_Utils::validate_bool( $value );
						},
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

	public static function toggle_tag( $request ) {
		$form_id = $request->get_param( 'form_id' );
		$tag     = $request->get_param( 'tag' );
		$enabled = $request->get_param( 'enabled' );

		if ( ! $form_id || ! $tag ) {
			return new WP_Error(
				'missing_params',
				__( 'Missing required parameters.', 'chimpmatic-lite' ),
				array( 'status' => 400 )
			);
		}

		$option_name = 'cf7_mch_' . $form_id;
		$cf7_mch     = get_option( $option_name, array() );

		if ( ! isset( $cf7_mch['labeltags'] ) || ! is_array( $cf7_mch['labeltags'] ) ) {
			$cf7_mch['labeltags'] = array();
		}

		if ( $enabled ) {
			$cf7_mch['labeltags'][ $tag ] = '1';
		} else {
			unset( $cf7_mch['labeltags'][ $tag ] );
		}

		update_option( $option_name, $cf7_mch );

		return rest_ensure_response(
			array(
				'success' => true,
				'tag'     => $tag,
				'enabled' => $enabled,
				'message' => $enabled
					? sprintf( __( 'Tag [%s] enabled.', 'chimpmatic-lite' ), $tag )
					: sprintf( __( 'Tag [%s] disabled.', 'chimpmatic-lite' ), $tag ),
			)
		);
	}

	public static function save_field( $request ) {
		$form_id = $request->get_param( 'form_id' );
		$field   = $request->get_param( 'field' );
		$value   = $request->get_param( 'value' );

		$matched_config = null;
		$matched_key    = null;

		foreach ( self::$field_patterns as $pattern => $config ) {
			if ( preg_match( '/^' . $pattern . '$/', $field, $matches ) ) {
				$matched_config = $config;
				$matched_key    = isset( $matches[1] ) ? $matches[1] : null;
				break;
			}
		}

		if ( null === $matched_config ) {
			return new WP_Error(
				'invalid_field',
				sprintf( __( 'Field "%s" is not allowed.', 'chimpmatic-lite' ), $field ),
				array( 'status' => 400 )
			);
		}

		if ( $matched_config['pro_only'] && ! defined( 'CMATIC_VERSION' ) ) {
			return new WP_Error(
				'pro_required',
				__( 'This field requires ChimpMatic PRO.', 'chimpmatic-lite' ),
				array( 'status' => 403 )
			);
		}

		if ( 'boolean' === $matched_config['type'] ) {
			$value = rest_sanitize_boolean( $value );
		} elseif ( 'string' === $matched_config['type'] ) {
			$value = trim( sanitize_text_field( $value ) );
		}

		$option_name = 'cf7_mch_' . $form_id;
		$cf7_mch     = get_option( $option_name, array() );

		if ( isset( $matched_config['nested'] ) ) {
			$nested_key = $matched_config['nested'];
			if ( ! isset( $cf7_mch[ $nested_key ] ) ) {
				$cf7_mch[ $nested_key ] = array();
			}

			if ( $value ) {
				$cf7_mch[ $nested_key ][ $matched_key ] = true;
			} else {
				unset( $cf7_mch[ $nested_key ][ $matched_key ] );
			}
		} elseif ( null === $value || '' === $value ) {
			unset( $cf7_mch[ $field ] );
		} else {
			$cf7_mch[ $field ] = $value;
		}

		update_option( $option_name, $cf7_mch );

		return rest_ensure_response(
			array(
				'success' => true,
				'field'   => $field,
				'value'   => $value,
				'message' => __( 'Field saved successfully.', 'chimpmatic-lite' ),
			)
		);
	}

	public static function save_setting( $request ) {
		$form_id = $request->get_param( 'form_id' );
		$field   = $request->get_param( 'field' );
		$value   = $request->get_param( 'value' );

		$allowed_fields = array(
			'sync_tags'    => array(
				'label'    => __( 'Sync Tags', 'chimpmatic-lite' ),
				'pro_only' => false,
			),
			'double_optin' => array(
				'label'    => __( 'Double Opt-in', 'chimpmatic-lite' ),
				'pro_only' => false,
			),
		);

		/**
		 * Filter the allowed per-form setting fields.
		 *
		 * Pro can extend this to add GDPR, Groups/Interests, etc.
		 *
		 * @since 0.9.69
		 *
		 * @param array $allowed_fields Associative array of field_name => config.
		 * @param int   $form_id        The CF7 form ID.
		 */
		$allowed_fields = apply_filters( 'cmatic_form_setting_fields', $allowed_fields, $form_id );

		if ( ! array_key_exists( $field, $allowed_fields ) ) {
			return new WP_Error(
				'invalid_field',
				sprintf(
					/* translators: %s: field name */
					__( 'Field "%s" is not a valid setting.', 'chimpmatic-lite' ),
					$field
				),
				array( 'status' => 400 )
			);
		}

		$field_config = $allowed_fields[ $field ];

		if ( ! empty( $field_config['pro_only'] ) && ! defined( 'CMATIC_VERSION' ) ) {
			return new WP_Error(
				'pro_required',
				sprintf(
					/* translators: %s: field label */
					__( '%s requires ChimpMatic Pro.', 'chimpmatic-lite' ),
					$field_config['label']
				),
				array( 'status' => 403 )
			);
		}

		$option_name = 'cf7_mch_' . $form_id;
		$cf7_mch     = get_option( $option_name, array() );

		if ( ! is_array( $cf7_mch ) ) {
			$cf7_mch = array();
		}

		$cf7_mch[ $field ] = $value ? 1 : 0;

		update_option( $option_name, $cf7_mch );

		return rest_ensure_response(
			array(
				'success' => true,
				'form_id' => $form_id,
				'field'   => $field,
				'value'   => (bool) $value,
				'message' => $value
					? sprintf(
						/* translators: %s: field label */
						__( '%s enabled.', 'chimpmatic-lite' ),
						$field_config['label']
					)
					: sprintf(
						/* translators: %s: field label */
						__( '%s disabled.', 'chimpmatic-lite' ),
						$field_config['label']
					),
			)
		);
	}

	private function __construct() {}
}
