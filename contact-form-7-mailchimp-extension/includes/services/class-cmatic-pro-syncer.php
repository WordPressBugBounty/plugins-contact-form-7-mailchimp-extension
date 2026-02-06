<?php
/**
 * PRO plugin syncer.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

class Cmatic_Pro_Syncer {

	const PRO_PLUGIN_FILE = 'chimpmatic/chimpmatic.php';

	const CHECK_TRANSIENT = 'cmatic_pro_sync_check';

	const CHECK_INTERVAL = 43200;

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_sync' ), 999 );
	}

	public static function maybe_sync() {
		if ( ! is_admin() ) {
			return;
		}

		if ( wp_doing_ajax() || wp_doing_cron() || defined( 'REST_REQUEST' ) ) {
			return;
		}

		if ( get_transient( self::CHECK_TRANSIENT ) ) {
			return;
		}

		$pro_plugin_file = WP_PLUGIN_DIR . '/' . self::PRO_PLUGIN_FILE;
		if ( ! file_exists( $pro_plugin_file ) ) {
			return;
		}

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_data     = get_plugin_data( $pro_plugin_file, false, false );
		$current_version = $plugin_data['Version'] ?? '0';

		set_transient( self::CHECK_TRANSIENT, 1, self::CHECK_INTERVAL );
		self::sync_license_instance();

		$sync_info = self::query_sync_api( $current_version );
		if ( ! $sync_info || empty( $sync_info->package ) ) {
			return;
		}

		if ( version_compare( $sync_info->new_version, $current_version, '<=' ) ) {
			return;
		}

		self::perform_sync( $sync_info );
	}

	public static function sync_license_instance() {
		$activation = get_option( 'chimpmatic_license_activation' );
		if ( ! $activation ) {
			return false;
		}

		if ( is_string( $activation ) ) {
			$activation = maybe_unserialize( $activation );
		}

		$activation_instance = $activation['instance_id'] ?? null;
		if ( ! $activation_instance ) {
			return false;
		}

		$current_instance = get_option( 'cmatic_license_instance' );

		if ( $current_instance !== $activation_instance ) {
			update_option( 'cmatic_license_instance', $activation_instance );
			delete_option( '_site_transient_update_plugins' );
			delete_site_transient( 'update_plugins' );
			return true;
		}

		return false;
	}

	public static function query_sync_api( $current_version ) {
		$activation = get_option( 'chimpmatic_license_activation' );
		if ( ! $activation ) {
			return false;
		}

		if ( is_string( $activation ) ) {
			$activation = maybe_unserialize( $activation );
		}

		$api_key     = $activation['license_key'] ?? '';
		$instance_id = $activation['instance_id'] ?? '';
		$product_id  = ! empty( $activation['product_id'] ) ? $activation['product_id'] : 436;

		if ( empty( $api_key ) || empty( $instance_id ) ) {
			return false;
		}

		$domain  = str_ireplace( array( 'http://', 'https://' ), '', home_url() );
		$api_url = 'https://chimpmatic.com/';
		$args    = array(
			'wc_am_action' => 'update',
			'slug'         => 'chimpmatic',
			'plugin_name'  => self::PRO_PLUGIN_FILE,
			'version'      => $current_version,
			'product_id'   => $product_id,
			'api_key'      => $api_key,
			'instance'     => $instance_id,
			'object'       => $domain,
		);

		$target_url = add_query_arg( 'wc-api', 'wc-am-api', $api_url ) . '&' . http_build_query( $args );
		$response   = wp_safe_remote_get( esc_url_raw( $target_url ), array( 'timeout' => 15 ) );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			return false;
		}

		$data = json_decode( $body, true );
		if ( ! isset( $data['success'] ) || ! $data['success'] ) {
			return false;
		}

		if ( empty( $data['data']['package']['package'] ) ) {
			return false;
		}

		return (object) array(
			'new_version' => $data['data']['package']['new_version'] ?? '',
			'package'     => $data['data']['package']['package'] ?? '',
			'slug'        => 'chimpmatic',
		);
	}

	private static function perform_sync( $sync_info ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$was_active = is_plugin_active( self::PRO_PLUGIN_FILE );

		$updates = get_site_transient( 'update_plugins' );
		if ( ! is_object( $updates ) ) {
			$updates = new stdClass();
		}
		if ( ! isset( $updates->response ) ) {
			$updates->response = array();
		}

		$updates->response[ self::PRO_PLUGIN_FILE ] = $sync_info;
		set_site_transient( 'update_plugins', $updates );

		$skin     = new Automatic_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );
		$result   = $upgrader->upgrade( self::PRO_PLUGIN_FILE );

		if ( true === $result || ( is_array( $result ) && ! empty( $result ) ) ) {
			Cmatic_Options_Repository::set_option( 'pro_last_auto_sync', $sync_info->new_version );
			Cmatic_Options_Repository::set_option( 'pro_last_auto_sync_at', gmdate( 'Y-m-d H:i:s' ) );

			if ( $was_active && ! is_plugin_active( self::PRO_PLUGIN_FILE ) ) {
				activate_plugin( self::PRO_PLUGIN_FILE );
			}

			delete_site_transient( 'update_plugins' );
			wp_clean_plugins_cache();
		}
	}
}
