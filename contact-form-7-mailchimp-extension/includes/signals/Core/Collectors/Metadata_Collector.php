<?php
/**
 * Metadata collector.
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

class Metadata_Collector {
	public static function collect(): array {
		$first_installed = Storage::get_quest();
		$total_uptime    = $first_installed > 0 ? time() - $first_installed : 0;
		$failed_count    = (int) Cmatic_Options_Repository::get_option( 'telemetry.failed_count', 0 );

		return array(
			'schedule'             => Cmatic_Options_Repository::get_option( 'telemetry.schedule', 'frequent' ),
			'frequent_started_at'  => (int) Cmatic_Options_Repository::get_option( 'telemetry.frequent_started_at', 0 ),
			'is_reactivation'      => Storage::is_reactivation(),
			'disabled_count'       => (int) Cmatic_Options_Repository::get_option( 'telemetry.disabled_count', 0 ),
			'opt_in_date'          => (int) Cmatic_Options_Repository::get_option( 'telemetry.opt_in_date', 0 ),
			'last_heartbeat'       => (int) Cmatic_Options_Repository::get_option( 'telemetry.last_heartbeat', 0 ),
			'failed_heartbeats'    => $failed_count,
			'total_uptime_seconds' => $total_uptime,
			'telemetry_version'    => SPARTAN_MCE_VERSION,
		);
	}
}
