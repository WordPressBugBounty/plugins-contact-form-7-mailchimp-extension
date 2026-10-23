<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

wp_clear_scheduled_hook( 'cmatic_daily_cron' );
wp_clear_scheduled_hook( 'csyncr_weekly_telemetry' );
wp_clear_scheduled_hook( 'csyncr_metrics_heartbeat' );

$cmatic_signls_product_hash = substr( hash( 'sha256', 'contact-form-7-mailchimp-extension' ), 0, 12 );
wp_clear_scheduled_hook( 'signls_sdk_v1_' . $cmatic_signls_product_hash . '_routine' );
wp_clear_scheduled_hook( 'signls_sdk_v1_' . $cmatic_signls_product_hash . '_refresh' );

$cmatic_uninstall_settings = get_option( 'cmatic', array() );
if ( ! is_array( $cmatic_uninstall_settings ) || empty( $cmatic_uninstall_settings['purge_on_uninstall'] ) ) {
	return;
}

$cmatic_uploads = wp_upload_dir( null, false );
$cmatic_log_dir = ( is_array( $cmatic_uploads ) && ! empty( $cmatic_uploads['basedir'] ) ? rtrim( (string) $cmatic_uploads['basedir'], '/' ) : WP_CONTENT_DIR . '/uploads' ) . '/chimpmatic';
if ( is_dir( $cmatic_log_dir ) ) {
	foreach ( (array) glob( $cmatic_log_dir . '/*' ) as $cmatic_log_file ) {
		if ( is_string( $cmatic_log_file ) && is_file( $cmatic_log_file ) ) {
			wp_delete_file( $cmatic_log_file );
		}
	}
	foreach ( array( '.htaccess', 'index.php' ) as $cmatic_guard ) {
		if ( is_file( $cmatic_log_dir . '/' . $cmatic_guard ) ) {
			wp_delete_file( $cmatic_log_dir . '/' . $cmatic_guard );
		}
	}
	rmdir( $cmatic_log_dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- removing the plugin's own, now empty, log directory.
}

delete_option( 'mce_loyalty' );
delete_option( 'chimpmatic-update' );
delete_option( 'cmatic_log_on' );
delete_option( 'cmatic_do_activation_redirect' );
delete_option( 'cmatic_news_retry_count' );
delete_option( 'csyncr_last_weekly_run' );

delete_option( 'cmatic' );
delete_option( 'cmatic_sync_stats' );
delete_transient( 'cmatic_ab_forms' );

delete_option( 'signls_sdk_v1_' . $cmatic_signls_product_hash );
delete_metadata( 'user', 0, 'cmatic_signls_consent_notice_dismissed', '', true );

global $wpdb;
foreach ( array( 'signls_signal_reason_counters', 'signls_signal_daily_counters', 'signls_signal_counters' ) as $cmatic_signls_table_suffix ) {
	$cmatic_signls_table = $wpdb->prefix . $cmatic_signls_table_suffix;
	if ( $cmatic_signls_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $cmatic_signls_table ) ) ) ) {
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$cmatic_signls_table} WHERE product_hash=%s", $cmatic_signls_product_hash ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Closed SDK-owned table list and product-scoped prepared deletion.
	}
}
$cmatic_signls_other_products = (int) $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( 'signls_sdk_v1_' ) . '%'
	)
);
if ( 0 === $cmatic_signls_other_products ) {
	delete_option( 'signls_sdk_site_id' );
	delete_option( 'signls_sdk_site_origin' );
}
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", 'cf7_mch_%' ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", 'cmatic_auth_%' ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", 'cmatic_provider_auth_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required uninstall cleanup.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_cmatic_provider_backoff_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required uninstall cleanup.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_timeout_cmatic_provider_backoff_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required uninstall cleanup.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_cmatic_oauth_secret_%' ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_timeout_cmatic_oauth_secret_%' ) );
