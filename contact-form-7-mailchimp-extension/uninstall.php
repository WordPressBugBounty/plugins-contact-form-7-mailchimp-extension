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
wp_clear_scheduled_hook( 'cmatic_weekly_telemetry' );
wp_clear_scheduled_hook( 'cmatic_metrics_heartbeat' );
wp_clear_scheduled_hook( 'csyncr_weekly_telemetry' );
wp_clear_scheduled_hook( 'csyncr_metrics_heartbeat' );

global $wpdb;
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", 'cf7_mch_%' ) );
