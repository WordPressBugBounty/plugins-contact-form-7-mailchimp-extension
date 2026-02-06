<?php
/**
 * Lifecycle data collector.
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

class Lifecycle_Collector {
	public static function collect(): array {
		$activations   = Cmatic_Options_Repository::get_option( 'lifecycle.activations', array() );
		$deactivations = Cmatic_Options_Repository::get_option( 'lifecycle.deactivations', array() );
		$upgrades      = Cmatic_Options_Repository::get_option( 'lifecycle.upgrades', array() );

		$first_activated  = Storage::get_quest();
		$last_activated   = ! empty( $activations ) ? max( $activations ) : 0;
		$last_deactivated = ! empty( $deactivations ) ? max( $deactivations ) : 0;
		$last_upgrade     = ! empty( $upgrades ) ? max( $upgrades ) : 0;

		$days_since_first = 0;
		if ( $first_activated > 0 ) {
			$days_since_first = floor( ( time() - $first_activated ) / DAY_IN_SECONDS );
		}

		$avg_session_length = self::calculate_avg_session_length( $activations, $deactivations );

		$version_history         = Cmatic_Options_Repository::get_option( 'lifecycle.version_history', array() );
		$previous_version        = Cmatic_Options_Repository::get_option( 'lifecycle.previous_version', '' );
		$days_since_last_upgrade = $last_upgrade > 0 ? floor( ( time() - $last_upgrade ) / DAY_IN_SECONDS ) : 0;

		$active_session = empty( $deactivations ) || $last_activated > $last_deactivated;

		$data = array(
			'activation_count'            => count( $activations ),
			'deactivation_count'          => count( $deactivations ),
			'upgrade_count'               => count( $upgrades ),
			'first_activated'             => $first_activated,
			'last_activated'              => $last_activated,
			'last_deactivated'            => $last_deactivated,
			'last_upgrade'                => $last_upgrade,
			'days_since_first_activation' => (int) $days_since_first,
			'days_since_last_upgrade'     => (int) $days_since_last_upgrade,
			'avg_session_length_seconds'  => $avg_session_length,
			'total_sessions'              => count( $activations ),
			'previous_version'            => $previous_version,
			'version_history_count'       => count( $version_history ),
			'install_method'              => Cmatic_Options_Repository::get_option( 'lifecycle.install_method', 'unknown' ),
			'days_on_current_version'     => $last_upgrade > 0 ? floor( ( time() - $last_upgrade ) / DAY_IN_SECONDS ) : $days_since_first,
			'activation_timestamps'       => $activations,
			'deactivation_timestamps'     => $deactivations,
			'upgrade_timestamps'          => $upgrades,
		);

		$data = array_filter( $data, fn( $v ) => $v !== 0 && $v !== '' && $v !== 'unknown' );
		$data['active_session'] = $active_session;

		return $data;
	}

	private static function calculate_avg_session_length( array $activations, array $deactivations ): int {
		if ( count( $activations ) === 0 || count( $deactivations ) === 0 ) {
			return 0;
		}

		$total_session_time = 0;
		$session_count      = 0;

		foreach ( $activations as $index => $activation_time ) {
			if ( isset( $deactivations[ $index ] ) ) {
				$total_session_time += $deactivations[ $index ] - $activation_time;
				++$session_count;
			}
		}

		if ( $session_count > 0 ) {
			return (int) floor( $total_session_time / $session_count );
		}

		return 0;
	}
}
