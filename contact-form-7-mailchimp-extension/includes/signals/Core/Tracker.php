<?php
/**
 * Metrics event tracker.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

namespace Cmatic\Metrics\Core;

use Cmatic_Options_Repository;

defined( 'ABSPATH' ) || exit;

class Tracker {

	public static function init() {
		add_action( 'cmatic_metrics_on_activation', array( __CLASS__, 'on_activation' ) );
		add_action( 'cmatic_metrics_on_deactivation', array( __CLASS__, 'on_deactivation' ) );
	}

	public static function on_activation() {
		Storage::init();
		Storage::record_activation();
		Storage::set_schedule( 'super_frequent' );

		if ( ! wp_next_scheduled( 'cmatic_metrics_heartbeat' ) ) {
			wp_schedule_event( time() + ( 2 * MINUTE_IN_SECONDS ), 'cmatic_2min', 'cmatic_metrics_heartbeat' );
		}

		self::send_event_heartbeat( 'activation' );
	}

	public static function on_deactivation() {
		Storage::record_deactivation();

		$timestamp = wp_next_scheduled( 'cmatic_metrics_heartbeat' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'cmatic_metrics_heartbeat' );
		}

		self::send_event_heartbeat( 'deactivation', true );
	}

	private static function send_event_heartbeat( $event, $force = false ) {
		if ( ! $force && ! Storage::is_enabled() ) {
			return;
		}

		$payload = Collector::collect( $event );
		Sync::send_async( $payload );
	}

	public static function on_opt_out() {
		Storage::increment_disabled_count();

		$payload = Collector::collect( 'opt_out' );
		Sync::send_async( $payload );

		$timestamp = wp_next_scheduled( 'cmatic_metrics_heartbeat' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'cmatic_metrics_heartbeat' );
		}
	}

	public static function on_re_enable() {
		if ( ! Cmatic_Options_Repository::get_option( 'telemetry.opt_in_date' ) ) {
			Cmatic_Options_Repository::set_option( 'telemetry.opt_in_date', time() );
		}

		Storage::set_schedule( 'super_frequent' );

		if ( ! wp_next_scheduled( 'cmatic_metrics_heartbeat' ) ) {
			wp_schedule_event( time() + ( 2 * MINUTE_IN_SECONDS ), 'cmatic_2min', 'cmatic_metrics_heartbeat' );
		}

		$payload = Collector::collect( 'reactivation' );
		Sync::send_async( $payload );
	}
}
