<?php
/**
 * Plugin uninstall handler.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'mce_loyalty' );
delete_option( 'chimpmatic-update' );
delete_option( 'cmatic_log_on' );
delete_option( 'cmatic_do_activation_redirect' );
delete_option( 'cmatic_news_retry_count' );
delete_option( 'csyncr_last_weekly_run' );

delete_option( 'cmatic' );

wp_clear_scheduled_hook( 'cmatic_daily_cron' );
wp_clear_scheduled_hook( 'csyncr_weekly_telemetry' );
wp_clear_scheduled_hook( 'csyncr_metrics_heartbeat' );

$cmatic_signls_product_hash = substr( hash( 'sha256', 'contact-form-7-mailchimp-extension' ), 0, 12 );
wp_clear_scheduled_hook( 'signls_sdk_v1_' . $cmatic_signls_product_hash . '_routine' );
wp_clear_scheduled_hook( 'signls_sdk_v1_' . $cmatic_signls_product_hash . '_refresh' );
delete_option( 'signls_sdk_v1_' . $cmatic_signls_product_hash );
delete_metadata( 'user', 0, 'cmatic_signls_consent_notice_dismissed', '', true );

global $wpdb;
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}signls_signal_counters WHERE product_hash=%s", $cmatic_signls_product_hash ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Product-scoped shared SDK cleanup; the table belongs to all SDK consumers.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", 'cf7_mch_%' ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", 'cmatic_auth_%' ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", 'cmatic_provider_auth_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required uninstall cleanup.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_cmatic_provider_backoff_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required uninstall cleanup.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_timeout_cmatic_provider_backoff_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required uninstall cleanup.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_cmatic_oauth_secret_%' ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_timeout_cmatic_oauth_secret_%' ) );
