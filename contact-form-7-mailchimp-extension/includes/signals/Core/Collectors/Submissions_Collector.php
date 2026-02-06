<?php
/**
 * Submissions data collector.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

namespace Cmatic\Metrics\Core\Collectors;

use Cmatic\Metrics\Core\Storage;
use Cmatic_Options_Repository;

defined( 'ABSPATH' ) || exit;

class Submissions_Collector {
	public static function collect(): array {
		$total_sent       = (int) Cmatic_Options_Repository::get_option( 'stats.sent', 0 );
		$total_failed     = (int) Cmatic_Options_Repository::get_option( 'submissions.failed', 0 );
		$first_submission = (int) Cmatic_Options_Repository::get_option( 'submissions.first', 0 );
		$last_submission  = (int) Cmatic_Options_Repository::get_option( 'submissions.last', 0 );
		$last_success     = (int) Cmatic_Options_Repository::get_option( 'submissions.last_success', 0 );
		$last_failure     = (int) Cmatic_Options_Repository::get_option( 'submissions.last_failure', 0 );

		$first_activated = Storage::get_quest();

		$days_active = 1;
		if ( $first_activated > 0 ) {
			$days_active = max( 1, floor( ( time() - $first_activated ) / DAY_IN_SECONDS ) );
		}

		$total_submissions = $total_sent + $total_failed;
		$avg_per_day       = $days_active > 0 ? round( $total_submissions / $days_active, 2 ) : 0;
		$success_rate      = $total_submissions > 0 ? round( ( $total_sent / $total_submissions ) * 100, 2 ) : 100;

		$days_since_first = $first_submission > 0 ? floor( ( time() - $first_submission ) / DAY_IN_SECONDS ) : 0;
		$days_since_last  = $last_submission > 0 ? floor( ( time() - $last_submission ) / DAY_IN_SECONDS ) : 0;
		$hours_since_last = $last_submission > 0 ? floor( ( time() - $last_submission ) / HOUR_IN_SECONDS ) : 0;

		list( $busiest_hour, $max_submissions ) = self::get_busiest_hour();
		list( $busiest_day, $max_day_submissions ) = self::get_busiest_day();

		$this_month = (int) Cmatic_Options_Repository::get_option( 'submissions.this_month', 0 );
		$last_month = (int) Cmatic_Options_Repository::get_option( 'submissions.last_month', 0 );
		$peak_month = (int) Cmatic_Options_Repository::get_option( 'submissions.peak_month', 0 );

		$consecutive_successes = (int) Cmatic_Options_Repository::get_option( 'submissions.consecutive_successes', 0 );
		$consecutive_failures  = (int) Cmatic_Options_Repository::get_option( 'submissions.consecutive_failures', 0 );

		$data = array(
			'total_sent'                   => $total_sent,
			'total_failed'                 => $total_failed,
			'total_submissions'            => $total_submissions,
			'successful_submissions_count' => $total_sent,
			'failed_count'                 => $total_failed,
			'success_rate'                 => $success_rate,
			'first_submission'             => $first_submission,
			'last_submission'              => $last_submission,
			'last_success'                 => $last_success,
			'last_failure'                 => $last_failure,
			'days_since_first'             => $days_since_first,
			'days_since_last'              => $days_since_last,
			'hours_since_last'             => $hours_since_last,
			'avg_per_day'                  => $avg_per_day,
			'avg_per_week'                 => round( $avg_per_day * 7, 2 ),
			'avg_per_month'                => round( $avg_per_day * 30, 2 ),
			'busiest_hour'                 => $busiest_hour,
			'busiest_day'                  => $busiest_day,
			'submissions_busiest_hour'     => $max_submissions,
			'submissions_busiest_day'      => $max_day_submissions,
			'this_month'                   => $this_month,
			'last_month'                   => $last_month,
			'peak_month'                   => $peak_month,
			'month_over_month_change'      => $last_month > 0 ? round( ( ( $this_month - $last_month ) / $last_month ) * 100, 2 ) : 0,
			'consecutive_successes'        => $consecutive_successes,
			'consecutive_failures'         => $consecutive_failures,
			'longest_success_streak'       => (int) Cmatic_Options_Repository::get_option( 'submissions.longest_success_streak', 0 ),
			'active_forms_count'           => (int) Cmatic_Options_Repository::get_option( 'submissions.active_forms', 0 ),
			'forms_with_submissions'       => count( Cmatic_Options_Repository::get_option( 'submissions.forms_used', array() ) ),
		);

		return array_filter( $data, fn( $v ) => $v !== 0 && $v !== 0.0 );
	}

	private static function get_busiest_hour(): array {
		$hourly_distribution = Cmatic_Options_Repository::get_option( 'submissions.hourly', array() );
		$busiest_hour        = 0;
		$max_submissions     = 0;

		foreach ( $hourly_distribution as $hour => $count ) {
			if ( $count > $max_submissions ) {
				$max_submissions = $count;
				$busiest_hour    = (int) $hour;
			}
		}

		return array( $busiest_hour, $max_submissions );
	}

	private static function get_busiest_day(): array {
		$daily_distribution  = Cmatic_Options_Repository::get_option( 'submissions.daily', array() );
		$busiest_day         = 0;
		$max_day_submissions = 0;

		foreach ( $daily_distribution as $day => $count ) {
			if ( $count > $max_day_submissions ) {
				$max_day_submissions = $count;
				$busiest_day         = (int) $day;
			}
		}

		return array( $busiest_day, $max_day_submissions );
	}
}
