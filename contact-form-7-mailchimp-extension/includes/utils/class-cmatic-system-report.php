<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

final class Cmatic_System_Report {

	const HOSTS = array(
		'Mailchimp'  => 'https://login.mailchimp.com/',
		'Brevo'      => 'https://api.brevo.com/',
		'MailerLite' => 'https://connect.mailerlite.com/',
		'Klaviyo'    => 'https://a.klaviyo.com/',
	);

	public static function build(): string {
		global $wpdb;
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$lines   = array();
		$section = static function ( string $title ) use ( &$lines ): void {
			$lines[] = '';
			$lines[] = '== ' . $title;
		};
		$row = static function ( string $key, $value ) use ( &$lines ): void {
			if ( is_bool( $value ) ) {
				$value = $value ? 'yes' : 'no';
			}
			$lines[] = str_pad( $key . ':', 26 ) . ( is_scalar( $value ) ? (string) $value : '' );
		};

		$lines[] = 'ChimpMatic system report, ' . gmdate( 'Y-m-d H:i' ) . ' UTC';

		$section( 'ChimpMatic' );
		$row( 'Lite', SPARTAN_MCE_VERSION );
		$row( 'Pro', defined( 'CMATIC_VERSION' ) ? (string) constant( 'CMATIC_VERSION' ) . ' (active)' : 'not active' );
		$row( 'Connected forms', self::connected_forms() );
		$row( 'Debug log', Cmatic_Debug_Log::logging() ? 'on until ' . gmdate( 'Y-m-d H:i', Cmatic_Debug_Log::until() ) . ' UTC' : 'off' );
		$row( 'Site log', '' === Cmatic_Debug_Log::site_log_path() ? 'none' : basename( Cmatic_Debug_Log::site_log_path() ) );

		$section( 'Provider reachability' );
		foreach ( self::HOSTS as $name => $url ) {
			$start = microtime( true );
			$probe = wp_remote_head( $url, array( 'timeout' => 5, 'redirection' => 0 ) );
			$row( $name, is_wp_error( $probe ) ? 'error: ' . $probe->get_error_message() : 'HTTP ' . (int) wp_remote_retrieve_response_code( $probe ) . ' in ' . (int) round( ( microtime( true ) - $start ) * 1000 ) . ' ms' );
		}

		$section( 'Form builder' );
		$row( 'Contact Form 7', defined( 'WPCF7_VERSION' ) ? (string) constant( 'WPCF7_VERSION' ) : 'absent' );

		$section( 'WordPress' );
		$row( 'Site', home_url() );
		$row( 'WordPress', get_bloginfo( 'version' ) );
		$row( 'Multisite', is_multisite() );
		$row( 'Language', get_locale() );
		$row( 'Timezone', wp_timezone_string() );
		$row( 'WP_DEBUG', defined( 'WP_DEBUG' ) && WP_DEBUG );
		$row( 'WP_MEMORY_LIMIT', defined( 'WP_MEMORY_LIMIT' ) ? (string) constant( 'WP_MEMORY_LIMIT' ) : '' );
		$row( 'Cron', defined( 'DISABLE_WP_CRON' ) && constant( 'DISABLE_WP_CRON' ) ? 'DISABLE_WP_CRON set' : 'WP-Cron' );
		$theme = wp_get_theme();
		$row( 'Theme', $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ) );

		$section( 'Server' );
		$row( 'PHP', PHP_VERSION );
		$row( 'memory_limit', (string) ini_get( 'memory_limit' ) );
		$row( 'max_execution_time', (string) ini_get( 'max_execution_time' ) );
		$row( 'upload_max_filesize', (string) ini_get( 'upload_max_filesize' ) );
		$curl = function_exists( 'curl_version' ) ? curl_version() : false;
		$row( 'cURL', is_array( $curl ) && isset( $curl['version'] ) && is_string( $curl['version'] ) ? $curl['version'] : 'absent' );
		$row( 'OpenSSL', defined( 'OPENSSL_VERSION_TEXT' ) ? OPENSSL_VERSION_TEXT : 'absent' );
		$row( 'Database', $wpdb instanceof wpdb ? $wpdb->db_version() : '' );
		$software = isset( $_SERVER['SERVER_SOFTWARE'] ) ? wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) : '';
		$row( 'Web server', is_string( $software ) ? sanitize_text_field( $software ) : '' );

		$section( 'Active plugins' );
		$active  = (array) get_option( 'active_plugins', array() );
		$updates = get_site_transient( 'update_plugins' );
		foreach ( get_plugins() as $file => $data ) {
			if ( ! in_array( $file, $active, true ) ) {
				continue;
			}
			$pending = is_object( $updates ) && isset( $updates->response[ $file ]->new_version ) ? ' (update ' . (string) $updates->response[ $file ]->new_version . ' pending)' : '';
			$lines[] = (string) ( $data['Name'] ?? $file ) . ' ' . (string) ( $data['Version'] ?? '' ) . $pending;
		}

		$section( 'Last lines of the debug log' );
		foreach ( Cmatic_Debug_Log::tail( 20 ) as $line ) {
			$lines[] = $line;
		}
		$section( 'Last lines of the site log' );
		foreach ( Cmatic_Debug_Log::site_log_tail( true, 20 ) as $line ) {
			$lines[] = Cmatic_Debug_Log::redact( $line );
		}

		return implode( "\n", $lines ) . "\n";
	}

	private static function connected_forms(): int {
		global $wpdb;
		if ( ! ( $wpdb instanceof wpdb ) ) {
			return 0;
		}
		$names = $wpdb->get_col( $wpdb->prepare( 'SELECT option_name FROM %i WHERE option_name LIKE %s', $wpdb->options, 'cf7_mch_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- one read for a support report.
		$count = 0;
		foreach ( (array) $names as $name ) {
			$config = get_option( (string) $name );
			if ( is_array( $config ) && ! empty( $config['api-validation'] ) ) {
				++$count;
			}
		}

		return $count;
	}
}
