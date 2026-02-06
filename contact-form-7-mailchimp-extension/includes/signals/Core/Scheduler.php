<?php
/**
 * Metrics scheduler.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

namespace Cmatic\Metrics\Core;

use Cmatic_Options_Repository;

defined( 'ABSPATH' ) || exit;

class Scheduler {

	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_cron_intervals' ) );
		add_action( 'cmatic_metrics_heartbeat', array( __CLASS__, 'execute_heartbeat' ) );
		add_action( 'admin_init', array( __CLASS__, 'ensure_schedule' ) );
		add_action( 'cmatic_subscription_success', array( __CLASS__, 'execute_heartbeat' ) );
	}

	public static function add_cron_intervals( $schedules ) {
		$schedules['cmatic_2min'] = array(
			'interval' => 2 * MINUTE_IN_SECONDS,
			'display'  => 'Every 2 Minutes',
		);

		$schedules['cmatic_10min'] = array(
			'interval' => 10 * MINUTE_IN_SECONDS,
			'display'  => 'Every 10 Minutes',
		);

		$interval_hours             = Storage::get_heartbeat_interval();
		$schedules['cmatic_sparse'] = array(
			'interval' => $interval_hours * HOUR_IN_SECONDS,
			'display'  => 'Every ' . $interval_hours . ' Hours',
		);

		return $schedules;
	}

	public static function execute_heartbeat() {
		if ( ! Storage::is_enabled() ) {
			return;
		}
		$schedule   = Storage::get_schedule();
		$started_at = Storage::get_frequent_started_at();
		$elapsed    = $started_at > 0 ? time() - $started_at : 0;

		if ( 'super_frequent' === $schedule && $elapsed >= ( 15 * MINUTE_IN_SECONDS ) ) {
			self::transition_to_frequent();
		} elseif ( 'frequent' === $schedule && $elapsed >= ( 1 * HOUR_IN_SECONDS ) ) {
			self::transition_to_sparse();
		}

		self::sync_global_lisdata();
		$payload = Collector::collect( 'heartbeat' );
		Sync::send( $payload );
	}

	private static function transition_to_frequent() {
		Storage::set_schedule( 'frequent' );

		$timestamp = wp_next_scheduled( 'cmatic_metrics_heartbeat' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'cmatic_metrics_heartbeat' );
		}

		wp_schedule_event( time(), 'cmatic_10min', 'cmatic_metrics_heartbeat' );
	}

	private static function transition_to_sparse() {
		Storage::set_schedule( 'sparse' );

		$timestamp = wp_next_scheduled( 'cmatic_metrics_heartbeat' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'cmatic_metrics_heartbeat' );
		}

		$interval_hours = Storage::get_heartbeat_interval();
		wp_schedule_single_event(
			time() + ( $interval_hours * HOUR_IN_SECONDS ),
			'cmatic_metrics_heartbeat'
		);
	}

	public static function ensure_schedule() {
		if ( ! Storage::is_enabled() ) {
			return;
		}

		if ( wp_next_scheduled( 'cmatic_metrics_heartbeat' ) ) {
			return;
		}

		$schedule   = Storage::get_schedule();
		$started_at = Storage::get_frequent_started_at();
		$elapsed    = $started_at > 0 ? time() - $started_at : 0;

		if ( 'super_frequent' === $schedule ) {
			if ( $elapsed >= ( 15 * MINUTE_IN_SECONDS ) ) {
				self::transition_to_frequent();
			} else {
				wp_schedule_event( time(), 'cmatic_2min', 'cmatic_metrics_heartbeat' );
			}
		} elseif ( 'frequent' === $schedule ) {
			if ( Storage::is_frequent_elapsed() ) {
				self::transition_to_sparse();
			} else {
				wp_schedule_event( time(), 'cmatic_10min', 'cmatic_metrics_heartbeat' );
			}
		} else {
			$interval_hours = Storage::get_heartbeat_interval();
			$last_heartbeat = Storage::get_last_heartbeat();
			$next_heartbeat = $last_heartbeat + ( $interval_hours * HOUR_IN_SECONDS );

			if ( $next_heartbeat < time() ) {
				$next_heartbeat = time();
			}

			wp_schedule_single_event( $next_heartbeat, 'cmatic_metrics_heartbeat' );
		}
	}

	public static function schedule_next_sparse() {
		if ( 'sparse' !== Storage::get_schedule() ) {
			return;
		}

		$timestamp = wp_next_scheduled( 'cmatic_metrics_heartbeat' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'cmatic_metrics_heartbeat' );
		}

		$interval_hours = Storage::get_heartbeat_interval();
		wp_schedule_single_event(
			time() + ( $interval_hours * HOUR_IN_SECONDS ),
			'cmatic_metrics_heartbeat'
		);
	}

	public static function clear_schedule() {
		$timestamp = wp_next_scheduled( 'cmatic_metrics_heartbeat' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'cmatic_metrics_heartbeat' );
		}
	}

	private static function sync_global_lisdata() {
		global $wpdb;
		$form_options = $wpdb->get_results(
			"SELECT option_value FROM {$wpdb->options}
			 WHERE option_name LIKE 'cf7_mch_%'
			 AND option_value LIKE '%lisdata%'
			 LIMIT 50"
		);

		$best_lisdata    = null;
		$best_list_count = 0;

		if ( ! empty( $form_options ) ) {
			foreach ( $form_options as $row ) {
				$cf7_mch = maybe_unserialize( $row->option_value );
				if ( ! is_array( $cf7_mch ) || empty( $cf7_mch['lisdata']['lists'] ) ) {
					continue;
				}

				$list_count = count( $cf7_mch['lisdata']['lists'] );
				if ( $list_count > $best_list_count ) {
					$best_list_count = $list_count;
					$best_lisdata    = $cf7_mch['lisdata'];
				}
			}
		}

		// Always update - either with found data or empty array to clear stale data.
		Cmatic_Options_Repository::set_option( 'lisdata', $best_lisdata ?? array() );
		Cmatic_Options_Repository::set_option( 'lisdata_updated', time() );
	}
}
