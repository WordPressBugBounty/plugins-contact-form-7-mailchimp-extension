<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

use Cmatic\Lite\Rest\Args;

final class Cmatic_Rest_Form {

	/**
	 * @var string Primary REST namespace.
	 */
	protected static $namespace = 'chimpmatic-lite/v1';

	/**
	 * @var string Secondary REST namespace for form settings.
	 */
	protected static $cmatic_namespace = 'cmatic';

	/**
	 * @var bool Whether initialized.
	 */
	protected static $initialized = false;

	/**
	 * @var array Field pattern configuration.
	 */
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
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
		self::$initialized = true;
	}

	public static function register_routes() {
		register_rest_route(
			self::$namespace,
			'/form/field',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'save_field' ),
				'permission_callback' => array( 'Cmatic_Rest_Permissions', 'form' ),
				'args'                => array(
					'form_id' => Args::form_id(),
					'field'   => Args::of( 'string', 'sanitize_text_field' ),
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
				'callback'            => array( self::class, 'save_setting' ),
				'permission_callback' => array( 'Cmatic_Rest_Permissions', 'form' ),
				'args'                => array(
					'form_id' => Args::positive_form_id(),
					'field'   => Args::of( 'string', 'sanitize_key' ),
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
				// translators: %s: field name.
				sprintf( __( 'Field "%s" is not allowed.', 'contact-form-7-mailchimp-extension' ), $field ),
				array( 'status' => 400 )
			);
		}

		if ( $matched_config['pro_only'] && ! Cmatic_Lite_Pro_Entitlement::active() ) {
			return new WP_Error(
				'pro_required',
				__( 'This field requires ChimpMatic Pro.', 'contact-form-7-mailchimp-extension' ),
				array( 'status' => 403 )
			);
		}

		if ( 'boolean' === $matched_config['type'] ) {
			$value = rest_sanitize_boolean( $value );
		} elseif ( 'string' === $matched_config['type'] ) {
			$value = trim( sanitize_text_field( $value ) );
		}

		$cf7_mch = \Cmatic\Lite\Settings\FormSettingsRepository::load( (int) $form_id )->record();

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

		if ( preg_match( '/^field[0-9]+$/', $field ) && '' !== $value && null !== $value ) {
			$mapped = 0;
			foreach ( $cf7_mch as $key => $mapping ) {
				if ( preg_match( '/^field[0-9]+$/', (string) $key ) && is_string( $mapping ) && '' !== trim( $mapping ) ) {
					++$mapped;
				}
			}
			if ( $mapped > \Cmatic\Lite\Settings\Entitlements::field_limit( 'mailchimp', (int) $form_id ) ) {
				return new WP_Error( 'mapping_limit', __( 'Choose email and up to three other fields.', 'contact-form-7-mailchimp-extension' ), array( 'status' => 400 ) );
			}
		}
		\Cmatic\Lite\Settings\FormSettingsRepository::store( (int) $form_id, $cf7_mch );

		return rest_ensure_response(
			array(
				'success' => true,
				'field'   => $field,
				'value'   => $value,
				'message' => __( 'Field saved successfully.', 'contact-form-7-mailchimp-extension' ),
			)
		);
	}

	public static function save_setting( $request ) {
		$form_id = $request->get_param( 'form_id' );
		$field   = $request->get_param( 'field' );
		$value   = $request->get_param( 'value' );

		$allowed_fields = array(
			'sync_tags'    => array(
				'label'    => __( 'Sync Tags', 'contact-form-7-mailchimp-extension' ),
				'pro_only' => false,
			),
			'double_optin' => array(
				'label'    => __( 'Double Opt-in', 'contact-form-7-mailchimp-extension' ),
				'pro_only' => false,
			),
		);

		$allowed_fields = apply_filters( 'cmatic_form_setting_fields', $allowed_fields, $form_id );

		if ( ! array_key_exists( $field, $allowed_fields ) ) {
			return new WP_Error(
				'invalid_field',
				sprintf(
					/* translators: %s: field name */
					__( 'Field "%s" is not a valid setting.', 'contact-form-7-mailchimp-extension' ),
					$field
				),
				array( 'status' => 400 )
			);
		}

		$field_config = $allowed_fields[ $field ];

		if ( ! empty( $field_config['pro_only'] ) && ! Cmatic_Lite_Pro_Entitlement::active() ) {
			return new WP_Error(
				'pro_required',
				sprintf(
					/* translators: %s: field label */
					__( '%s requires ChimpMatic Pro.', 'contact-form-7-mailchimp-extension' ),
					$field_config['label']
				),
				array( 'status' => 403 )
			);
		}

		$cf7_mch = \Cmatic\Lite\Settings\FormSettingsRepository::load( (int) $form_id )->record();

		$cf7_mch[ $field ] = $value ? 1 : 0;

		\Cmatic\Lite\Settings\FormSettingsRepository::store( (int) $form_id, $cf7_mch );

		return rest_ensure_response(
			array(
				'success' => true,
				'form_id' => $form_id,
				'field'   => $field,
				'value'   => (bool) $value,
				'message' => $value
					? sprintf(
						/* translators: %s: field label */
						__( '%s enabled.', 'contact-form-7-mailchimp-extension' ),
						$field_config['label']
					)
					: sprintf(
						/* translators: %s: field label */
						__( '%s disabled.', 'contact-form-7-mailchimp-extension' ),
						$field_config['label']
					),
			)
		);
	}

	private function __construct() {}
}
