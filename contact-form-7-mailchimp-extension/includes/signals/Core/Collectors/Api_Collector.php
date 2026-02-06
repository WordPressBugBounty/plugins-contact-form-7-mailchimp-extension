<?php
/**
 * API data collector.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

namespace Cmatic\Metrics\Core\Collectors;

use Cmatic_Options_Repository;

defined( 'ABSPATH' ) || exit;

class Api_Collector {
	public static function collect(): array {
		$has_api_key     = false;
		$api_data_center = '';
		$api_key_length  = 0;
		$forms_with_api  = 0;
		$cf7_forms       = Forms_Collector::get_cf7_forms();

		$form_ids     = array_map( fn( $form ) => $form->id(), $cf7_forms );
		$form_options = Forms_Collector::batch_load_form_options( $form_ids );

		foreach ( $cf7_forms as $form ) {
			$form_id = $form->id();
			$cf7_mch = $form_options[ $form_id ]['settings'] ?? array();

			if ( ! empty( $cf7_mch['api'] ) ) {
				$has_api_key = true;
				++$forms_with_api;

				if ( empty( $api_data_center ) && preg_match( '/-([a-z0-9]+)$/i', $cf7_mch['api'], $matches ) ) {
					$api_data_center = $matches[1];
				}

				if ( 0 === $api_key_length ) {
					$api_key_length = strlen( $cf7_mch['api'] );
				}
			}
		}

		$total_sent        = (int) Cmatic_Options_Repository::get_option( 'stats.sent', 0 );
		$total_attempts    = (int) Cmatic_Options_Repository::get_option( 'api.total_attempts', $total_sent );
		$total_successes   = (int) Cmatic_Options_Repository::get_option( 'api.total_successes', $total_sent );
		$total_failures    = (int) Cmatic_Options_Repository::get_option( 'api.total_failures', 0 );
		$success_rate      = $total_attempts > 0 ? round( ( $total_successes / $total_attempts ) * 100, 2 ) : 0;
		$first_connected   = (int) Cmatic_Options_Repository::get_option( 'api.first_connected', 0 );
		$last_success      = (int) Cmatic_Options_Repository::get_option( 'api.last_success', 0 );
		$last_failure      = (int) Cmatic_Options_Repository::get_option( 'api.last_failure', 0 );
		$avg_response_time = (int) Cmatic_Options_Repository::get_option( 'api.avg_response_time', 0 );

		$error_summary        = self::get_error_summary();
		$consecutive_failures = (int) Cmatic_Options_Repository::get_option( 'api.consecutive_failures', 0 );
		$uptime_percentage    = $total_attempts > 0 ? round( ( $total_successes / $total_attempts ) * 100, 2 ) : 100;

		$days_since_last_success = $last_success > 0 ? floor( ( time() - $last_success ) / DAY_IN_SECONDS ) : 0;
		$days_since_last_failure = $last_failure > 0 ? floor( ( time() - $last_failure ) / DAY_IN_SECONDS ) : 0;

		$data = array(
			'is_connected'              => $has_api_key,
			'forms_with_api'            => $forms_with_api,
			'api_data_center'           => $api_data_center,
			'api_key_length'            => $api_key_length,
			'first_connected'           => $first_connected,
			'total_attempts'            => $total_attempts,
			'total_successes'           => $total_successes,
			'total_failures'            => $total_failures,
			'success_rate'              => $success_rate,
			'uptime_percentage'         => $uptime_percentage,
			'last_success'              => $last_success,
			'last_failure'              => $last_failure,
			'days_since_last_success'   => $days_since_last_success,
			'days_since_last_failure'   => $days_since_last_failure,
			'avg_response_time_ms'      => $avg_response_time,
			'error_codes'               => $error_summary,
			'api_health_score'          => min( 100, max( 0, $uptime_percentage - ( $consecutive_failures * 5 ) ) ),
			'setup_sync_attempted'      => Cmatic_Options_Repository::get_option( 'api.sync_attempted', false ),
			'setup_sync_attempts_count' => (int) Cmatic_Options_Repository::get_option( 'api.sync_attempts_count', 0 ),
			'setup_first_success'       => Cmatic_Options_Repository::get_option( 'api.setup_first_success', false ),
			'setup_first_failure'       => Cmatic_Options_Repository::get_option( 'api.setup_first_failure', false ),
			'setup_failure_count'       => (int) Cmatic_Options_Repository::get_option( 'api.setup_failure_count', 0 ),
			'setup_audience_selected'   => Cmatic_Options_Repository::get_option( 'api.audience_selected', false ),
		);

		return array_filter( $data, fn( $v ) => $v !== 0 && $v !== false && $v !== '' && $v !== array() );
	}

	private static function get_error_summary(): array {
		$error_codes   = Cmatic_Options_Repository::get_option( 'api.error_codes', array() );
		$error_summary = array();

		foreach ( $error_codes as $code => $count ) {
			if ( $count > 0 ) {
				$error_summary[ $code ] = (int) $count;
			}
		}

		return $error_summary;
	}
}
