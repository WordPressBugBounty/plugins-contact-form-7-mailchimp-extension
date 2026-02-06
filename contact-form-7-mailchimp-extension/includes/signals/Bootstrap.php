<?php
/**
 * Metrics bootstrap class.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

namespace Cmatic\Metrics;

use Cmatic\Metrics\Core\Storage;
use Cmatic\Metrics\Core\Tracker;
use Cmatic\Metrics\Core\Scheduler;
use Cmatic\Metrics\Core\Sync;
use Cmatic\Metrics\Core\Collector;
use Cmatic_Options_Repository;

defined( 'ABSPATH' ) || exit;

class Bootstrap {

	private static $instance = null;
	private $config          = array();

	public static function init( $config = array() ) {
		if ( null === self::$instance ) {
			self::$instance = new self( $config );
		}
		return self::$instance;
	}

	private function __construct( $config ) {
		try {
			$this->config = wp_parse_args(
				$config,
				array(
					'plugin_basename' => '',
					'endpoint_url'    => '',
				)
			);

			Sync::set_endpoint( $this->config['endpoint_url'] );
			$this->init_components();
		} catch ( \Exception $e ) {
			return;
		}
	}

	private function init_components() {
		try {
			Storage::init();
			Tracker::init();
			Scheduler::init();

			add_action( 'admin_init', array( $this, 'admin_init_failsafe' ), 999 );
			add_action( 'cmatic_weekly_telemetry', array( $this, 'execute_weekly_telemetry' ) );
			$this->ensure_weekly_schedule();
		} catch ( \Exception $e ) {
			return;
		}
	}

	public function admin_init_failsafe() {
		try {
			$transient_key = 'cmatic_admin_checked';
			if ( get_transient( $transient_key ) ) {
				return;
			}
			set_transient( $transient_key, 1, HOUR_IN_SECONDS );

			if ( ! class_exists( 'Cmatic_Options_Repository' ) ) {
				return;
			}
			Storage::init();

			if ( ! Storage::is_enabled() ) {
				return;
			}

			global $pagenow;
			if ( isset( $pagenow ) && in_array( $pagenow, array( 'plugins.php', 'plugin-install.php', 'plugin-editor.php' ), true ) ) {
				return;
			}

			$last_heartbeat = Storage::get_last_heartbeat();
			$two_weeks      = 2 * WEEK_IN_SECONDS;

			if ( 0 === $last_heartbeat || ( time() - $last_heartbeat ) > $two_weeks ) {
				$payload = Collector::collect( 'heartbeat' );
				Sync::send_async( $payload );
			}
		} catch ( \Exception $e ) {
			return;
		}
	}

	private function ensure_weekly_schedule() {
		try {
			add_filter( 'cron_schedules', array( $this, 'add_weekly_schedule' ) );

			if ( ! wp_next_scheduled( 'cmatic_weekly_telemetry' ) ) {
				wp_schedule_event( time() + WEEK_IN_SECONDS, 'cmatic_weekly', 'cmatic_weekly_telemetry' );
			}
		} catch ( \Exception $e ) {
			return;
		}
	}

	public function add_weekly_schedule( $schedules ) {
		if ( ! isset( $schedules['cmatic_weekly'] ) ) {
			$schedules['cmatic_weekly'] = array(
				'interval' => WEEK_IN_SECONDS,
				'display'  => 'Once Weekly',
			);
		}
		return $schedules;
	}

	public function execute_weekly_telemetry() {
		try {
			if ( ! Storage::is_enabled() ) {
				return;
			}

			$last_run     = Cmatic_Options_Repository::get_option( 'telemetry.last_run' ) ?: 0;
			$current_time = time();

			if ( $current_time - $last_run < ( 6 * DAY_IN_SECONDS ) ) {
				return;
			}

			Cmatic_Options_Repository::set_option( 'telemetry.last_run', $current_time );

			$payload = Collector::collect( 'heartbeat' );
			Sync::send( $payload );
		} catch ( \Exception $e ) {
			return;
		}
	}

	public static function get_instance() {
		return self::$instance;
	}
}
