<?php
/**
 * Metrics storage handler.
 *
 * Delegates install_id, quest, and lifecycle tracking to Cmatic_Install_Data
 * and Cmatic_Activator/Cmatic_Deactivator classes (single source of truth).
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

namespace Cmatic\Metrics\Core;

use Cmatic_Options_Repository;

defined( 'ABSPATH' ) || exit;

class Storage {

	public static function init() {
		if ( null !== Cmatic_Options_Repository::get_option( 'telemetry.enabled' ) ) {
			return;
		}
		$defaults = array(
			'enabled'             => true,
			'opt_in_date'         => time(),
			'disabled_count'      => 0,
			'heartbeat_interval'  => 48,
			'schedule'            => 'frequent',
			'frequent_started_at' => time(),
			'last_heartbeat'      => 0,
			'heartbeat_count'     => 0,
			'failed_count'        => 0,
			'last_payload_hash'   => '',
			'last_error'          => '',
		);

		foreach ( $defaults as $key => $value ) {
			Cmatic_Options_Repository::set_option( "telemetry.{$key}", $value );
		}
	}

	public static function is_enabled() {
		return (bool) Cmatic_Options_Repository::get_option( 'telemetry.enabled', true );
	}

	public static function get_schedule() {
		return Cmatic_Options_Repository::get_option( 'telemetry.schedule', 'frequent' );
	}

	public static function set_schedule( $schedule ) {
		Cmatic_Options_Repository::set_option( 'telemetry.schedule', $schedule );

		if ( 'frequent' === $schedule ) {
			Cmatic_Options_Repository::set_option( 'telemetry.frequent_started_at', time() );
		}
	}

	public static function get_heartbeat_interval() {
		return (int) Cmatic_Options_Repository::get_option( 'telemetry.heartbeat_interval', 48 );
	}

	public static function get_last_heartbeat() {
		return (int) Cmatic_Options_Repository::get_option( 'telemetry.last_heartbeat', 0 );
	}

	public static function update_last_heartbeat( $timestamp = null ) {
		if ( null === $timestamp ) {
			$timestamp = time();
		}
		Cmatic_Options_Repository::set_option( 'telemetry.last_heartbeat', $timestamp );
	}

	public static function increment_heartbeat_count() {
		$count = (int) Cmatic_Options_Repository::get_option( 'telemetry.heartbeat_count', 0 );
		Cmatic_Options_Repository::set_option( 'telemetry.heartbeat_count', $count + 1 );
	}

	public static function increment_failed_count() {
		$count = (int) Cmatic_Options_Repository::get_option( 'telemetry.failed_count', 0 );
		Cmatic_Options_Repository::set_option( 'telemetry.failed_count', $count + 1 );
	}

	public static function increment_disabled_count() {
		$count = (int) Cmatic_Options_Repository::get_option( 'telemetry.disabled_count', 0 );
		Cmatic_Options_Repository::set_option( 'telemetry.disabled_count', $count + 1 );
	}

	public static function get_frequent_started_at() {
		return (int) Cmatic_Options_Repository::get_option( 'telemetry.frequent_started_at', 0 );
	}

	public static function is_frequent_elapsed() {
		$started_at = self::get_frequent_started_at();
		if ( 0 === $started_at ) {
			return false;
		}
		$elapsed = time() - $started_at;
		return $elapsed >= ( 1 * HOUR_IN_SECONDS );
	}

	public static function record_activation() {
	}

	public static function record_deactivation() {
	}

	public static function is_reactivation() {
		return (bool) Cmatic_Options_Repository::get_option( 'lifecycle.is_reactivation', false );
	}

	public static function get_activation_count() {
		$activations = Cmatic_Options_Repository::get_option( 'lifecycle.activations', array() );
		return is_array( $activations ) ? count( $activations ) : 0;
	}

	public static function get_deactivation_count() {
		$deactivations = Cmatic_Options_Repository::get_option( 'lifecycle.deactivations', array() );
		return is_array( $deactivations ) ? count( $deactivations ) : 0;
	}

	public static function get_install_id(): string {
		$options      = new \Cmatic_Options_Repository();
		$install_data = new \Cmatic_Install_Data( $options );
		return $install_data->get_install_id();
	}

	public static function get_quest(): int {
		$options      = new \Cmatic_Options_Repository();
		$install_data = new \Cmatic_Install_Data( $options );
		return $install_data->get_quest();
	}

	public static function save_error( $error ): void {
		Cmatic_Options_Repository::set_option( 'telemetry.last_error', $error );
	}

	public static function save_payload_hash( $hash ) {
		Cmatic_Options_Repository::set_option( 'telemetry.last_payload_hash', $hash );
	}
}
