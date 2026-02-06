<?php
/**
 * REST API controller for reset operations.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

final class Cmatic_Rest_Reset {

	/** @var string REST namespace. */
	protected static $namespace = 'chimpmatic-lite/v1';

	/** @var bool Whether initialized. */
	protected static $initialized = false;

	/** @var array License options to delete during nuclear reset. */
	protected static $license_options = array(
		'chimpmatic_license_activation',
		'chimpmatic_license_status',
		'chimpmatic_license_state',
		'chimpmatic_license_last_check',
		'chimpmatic_license_error_state',
		'cmatic_license_activated',
		'cmatic_license_api_key',
		'cmatic_product_id',
		'wc_am_client_chimpmatic',
		'wc_am_product_id_chimpmatic',
		'wc_am_client_chimpmatic_activated',
		'wc_am_client_chimpmatic_instance',
		'wc_am_client_chimpmatic_deactivate_checkbox',
		'chimpmatic_product_id',
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
			'/settings/reset',
			array(
				'methods'             => 'POST',
				'callback'            => array( static::class, 'reset_settings' ),
				'permission_callback' => array( static::class, 'check_admin_permission' ),
				'args'                => array(
					'type'    => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => 'form',
						'enum'              => array( 'form', 'nuclear' ),
						'sanitize_callback' => 'sanitize_text_field',
					),
					'form_id' => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
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

	public static function reset_settings( $request ) {
		$type = $request->get_param( 'type' );

		if ( 'nuclear' === $type ) {
			return self::nuclear_reset( $request );
		}

		$form_id = $request->get_param( 'form_id' );
		if ( ! $form_id ) {
			return new WP_Error(
				'missing_form_id',
				__( 'Form ID is required for form reset.', 'chimpmatic-lite' ),
				array( 'status' => 400 )
			);
		}

		$option_name = 'cf7_mch_' . $form_id;
		delete_option( $option_name );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Form settings cleared successfully.', 'chimpmatic-lite' ),
			)
		);
	}

	public static function nuclear_reset( $request ) {
		global $wpdb;

		$current_user   = wp_get_current_user();
		$username       = $current_user->user_login ?? 'unknown';
		$deleted_counts = array();

		$options_deleted = 0;
		foreach ( self::$license_options as $option ) {
			if ( delete_option( $option ) ) {
				++$options_deleted;
			}
		}
		$deleted_counts['options'] = $options_deleted;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$transients_deleted           = $wpdb->query(
			"DELETE FROM {$wpdb->options}
            WHERE option_name LIKE '_transient_%chimpmatic%'
               OR option_name LIKE '_transient_timeout_%chimpmatic%'
               OR option_name LIKE '_site_transient_%chimpmatic%'
               OR option_name LIKE '_site_transient_timeout_%chimpmatic%'
               OR option_name LIKE 'site_transient_%chimpmatic%'
               OR option_name LIKE '_transient_%cmatic%'
               OR option_name LIKE '_transient_timeout_%cmatic%'
               OR option_name LIKE '_site_transient_%cmatic%'
               OR option_name LIKE '_site_transient_timeout_%cmatic%'
               OR option_name LIKE 'site_transient_%cmatic%'"
		);
		$deleted_counts['transients'] = (int) $transients_deleted;
		$cache_flushed = false;
		if ( function_exists( 'wp_cache_flush' ) ) {
			$cache_flushed = wp_cache_flush();
		}
		$deleted_counts['cache_flushed'] = $cache_flushed;
		delete_site_transient( 'update_plugins' );
		update_option( 'chimpmatic_license_status', 'deactivated' );

		return rest_ensure_response(
			array(
				'success'        => true,
				'message'        => 'License data completely wiped (nuclear reset)',
				'deleted_counts' => $deleted_counts,
				'performed_by'   => $username,
				'timestamp'      => current_time( 'mysql' ),
				'actions_taken'  => array(
					'Deleted ' . $options_deleted . ' license options',
					'Deleted ' . $transients_deleted . ' cached transients',
					'Flushed object cache: ' . ( $cache_flushed ? 'yes' : 'no' ),
					'Cleared plugin update cache',
					'Set license status to deactivated',
				),
			)
		);
	}

	private function __construct() {}
}
