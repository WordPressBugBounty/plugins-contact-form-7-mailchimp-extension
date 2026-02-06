<?php
/**
 * Cron job handler.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

class Cmatic_Cron {

	private const DAILY_HOOK = 'cmatic_daily_cron';

	private const DAILY_TIME = '03:00:00';

	public static function init( string $plugin_file ): void {
		add_action( 'init', array( __CLASS__, 'schedule' ) );
		add_action( self::DAILY_HOOK, array( __CLASS__, 'run_daily_job' ) );

		register_deactivation_hook( $plugin_file, array( __CLASS__, 'unschedule' ) );
	}

	public static function schedule(): void {
		if ( wp_next_scheduled( self::DAILY_HOOK ) ) {
			return;
		}

		wp_schedule_event( strtotime( self::DAILY_TIME ), 'daily', self::DAILY_HOOK );
	}

	public static function unschedule(): void {
		// Idempotent: wp_clear_scheduled_hook is safe if hook doesn't exist.
		wp_clear_scheduled_hook( self::DAILY_HOOK );
	}

	public static function run_daily_job(): void {
		self::disable_all_logging();
	}

	private static function disable_all_logging(): int {
		global $wpdb;

		$option_names = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				'cf7_mch_%'
			)
		);

		$updated = 0;

		foreach ( $option_names as $option_name ) {
			$config = get_option( $option_name );

			if ( is_array( $config ) && isset( $config['logfileEnabled'] ) ) {
				unset( $config['logfileEnabled'] );
				update_option( $option_name, $config );
				++$updated;
			}
		}

		return $updated;
	}

	public static function is_scheduled(): bool {
		return (bool) wp_next_scheduled( self::DAILY_HOOK );
	}

	public static function get_next_run() {
		return wp_next_scheduled( self::DAILY_HOOK );
	}
}
