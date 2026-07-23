<?php
/**
 * ChimpMatic Lite product Signls collector.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

final class Cmatic_Lite_Signls_Product_Collector {

	private const PRODUCT = 'contact-form-7-mailchimp-extension';

	private const INTEGRATIONS = array( 'mailchimp', 'brevo', 'mailerlite', 'klaviyo' );

	private const NARROW_FEATURES = array(
		'advanced_fields',
		'tags_management',
		'groups_segments',
		'gdpr_fields',
		'conditional_logic',
		'double_optin',
		'visitor_geolocation',
		'unsubscribe',
		'multi_group_routing',
		'status_control',
		'consent_metadata',
		'debug_logging',
		'auto_updates',
	);

	private $forms_collector;
	private $lifetime_rows;
	private $daily_rows;
	private $reason_rows;

	public function __construct( $forms_collector = null ) {
		$this->forms_collector = $forms_collector;
	}

	public function collect(): array {
		$configurations = $this->configurations();
		$analysis       = $this->analyse_forms( $configurations );
		$install        = new Cmatic_Install_Data( Cmatic_Options_Repository::instance() );
		$quest          = (int) $install->get_quest();
		$pro            = $this->pro_state();
		$lifecycle      = $this->lifecycle( $quest );
		$submissions    = $this->submissions( $quest, $configurations, $analysis['active'] );
		$rolling        = array(
			'seven'  => $submissions['_subs_7d'],
			'thirty' => $submissions['_subs_30d'],
			'median' => $submissions['_subs_median'],
		);
		unset( $submissions['_subs_7d'], $submissions['_subs_30d'], $submissions['_subs_median'] );
		$setup  = $this->setup( $analysis );
		$update = $this->update_state();
		$reason = $this->top_failure_reason();

		return array(
			'narrow'           => $this->narrow( $analysis, $pro ),
			'install'          => array(
				'plugin_slug' => self::PRODUCT,
				'quest'       => $quest,
				'pro'         => $pro,
			),
			'metadata'         => $this->metadata( $quest ),
			'lifecycle'        => $lifecycle,
			'api'              => $this->api( $analysis ),
			'submissions'      => $submissions,
			'features'         => $this->features( $configurations ),
			'legacy_lifecycle' => array(
				'event'         => 'heartbeat',
				'install_id'    => (string) $install->get_install_id(),
				'version'       => defined( 'SPARTAN_MCE_VERSION' ) ? (string) SPARTAN_MCE_VERSION : 'unknown',
				'site_url'      => site_url(),
				'timestamp'     => time(),
				'wp_version'    => get_bloginfo( 'version' ),
				'php'           => PHP_VERSION,
				'mysql_version' => $this->mysql_version(),
				'software'      => array( 'server' => $this->server_software() ),
			),
			'opportunities'    => array(
				'setup_stage'      => $setup['stage'],
				'setup_percent'    => $setup['percent'],
				'failure_reason'   => $reason,
				'update_available' => $update['available'],
				'versions_behind'  => $update['versions_behind'],
				'subs_7d'          => $rolling['seven'],
				'subs_30d'         => $rolling['thirty'],
				'subs_median'      => $rolling['median'],
				'install_source'   => $this->install_source( isset( $lifecycle['install_method'] ) ? $lifecycle['install_method'] : 'unknown' ),
			),
		);
	}

	private function configurations(): array {
		if ( is_object( $this->forms_collector ) && is_callable( array( $this->forms_collector, 'configurations' ) ) ) {
			$value = $this->forms_collector->configurations();
			return is_array( $value ) ? $value : array();
		}
		return array();
	}

	private function analyse_forms( array $configurations ): array {
		$integrations = array();
		foreach ( self::INTEGRATIONS as $slug ) {
			$integrations[ $slug ] = array(
				'slug'              => $slug,
				'configured_units'  => 0,
				'credential_units'  => 0,
				'oauth_units'       => 0,
				'api_key_units'     => 0,
				'destination_units' => 0,
				'mapping_count'     => 0,
				'attempts'          => 0,
				'successes'         => 0,
				'failures'          => 0,
			);
		}
		$feature_counts = array_fill_keys( self::NARROW_FEATURES, 0 );
		$result         = array(
			'integrations'      => &$integrations,
			'features'          => &$feature_counts,
			'configured'        => 0,
			'active'            => 0,
			'credential_units'  => 0,
			'destination_units' => 0,
			'mapping_count'     => 0,
			'manual_key_length' => 0,
			'data_center'       => '',
		);

		foreach ( $configurations as $form_id => $row ) {
			$config   = isset( $row['settings'] ) && is_array( $row['settings'] ) ? $row['settings'] : array();
			$provider = Cmatic_Lite_Esp_Registry::get_selected( $config );
			$settings = $this->provider_settings( $config, $provider );
			if ( ! $this->has_configuration( $settings, $provider ) ) {
				continue;
			}
			++$result['configured'];
			++$integrations[ $provider ]['configured_units'];
			$auth = $this->authentication( (int) $form_id, $provider, $config );
			if ( 'none' !== $auth ) {
				++$result['credential_units'];
				++$integrations[ $provider ]['credential_units'];
				if ( 'oauth' === $auth ) {
					$integrations[ $provider ]['oauth_units'] = isset( $integrations[ $provider ]['oauth_units'] ) ? $integrations[ $provider ]['oauth_units'] + 1 : 1;
				} else {
					$integrations[ $provider ]['api_key_units'] = isset( $integrations[ $provider ]['api_key_units'] ) ? $integrations[ $provider ]['api_key_units'] + 1 : 1;
				}
			}
			$destination = $this->has_destination( $settings );
			if ( $destination ) {
				++$result['destination_units'];
				++$integrations[ $provider ]['destination_units'];
			}
			$mappings                                    = $this->mapping_count( $settings );
			$result['mapping_count']                    += $mappings;
			$integrations[ $provider ]['mapping_count'] += $mappings;
			if ( 'none' !== $auth && $destination ) {
				++$result['active'];
			}
			if ( 'mailchimp' === $provider && ! empty( $config['api'] ) && is_scalar( $config['api'] ) ) {
				$key = (string) $config['api'];
				if ( 0 === $result['manual_key_length'] ) {
					$result['manual_key_length'] = strlen( $key );
				}
				if ( '' === $result['data_center'] && 1 === preg_match( '/-([a-z]{2}\d{1,3})$/i', $key, $match ) ) {
					$result['data_center'] = strtolower( $match[1] );
				}
				unset( $key );
			}
			$this->count_narrow_features( $feature_counts, $settings, $provider, $mappings );
		}
		unset( $result['integrations'], $result['features'] );
		$result['integrations'] = $integrations;
		$result['features']     = $feature_counts;
		$operations             = array();
		$this->merge_outcomes( $result['integrations'], $operations );
		$result['operations']                = $operations;
		$result['features']['debug_logging'] = Cmatic_Options_Repository::get_option( 'debug', false ) ? 1 : 0;
		$result['features']['auto_updates']  = Cmatic_Options_Repository::get_option( 'auto_update', true ) ? 1 : 0;
		return $result;
	}

	private function narrow( array $analysis, array $pro ): array {
		$features = array();
		foreach ( self::NARROW_FEATURES as $slug ) {
			$features[] = array(
				'slug'             => $slug,
				'configured_units' => isset( $analysis['features'][ $slug ] ) ? $analysis['features'][ $slug ] : 0,
				'source'           => 'lite_config',
			);
		}
		return array(
			'versions'         => array(
				'wordpress' => get_bloginfo( 'version' ),
				'cf7'       => defined( 'WPCF7_VERSION' ) ? (string) WPCF7_VERSION : 'unknown',
			),
			'is_multisite'     => is_multisite(),
			'configured_units' => $analysis['configured'],
			'active_units'     => $analysis['active'],
			'integrations'     => array_values( $analysis['integrations'] ),
			'features'         => $features,
			'operation_health' => $analysis['operations'],
			'companions'       => array( $this->pro_companion( $pro ) ),
		);
	}

	private function pro_state(): array {
		$status    = new Cmatic_Pro_Status( Cmatic_Options_Repository::instance() );
		$installed = (bool) $status->is_installed();
		$active    = $installed && (bool) $status->is_activated();
		$version   = $this->pro_version( $installed );
		$licensed  = $active && (bool) $status->is_licensed();
		$expires   = Cmatic_Options_Repository::get_option( 'install.pro.license_expires', null );
		if ( null === $expires ) {
			$activation = get_option( 'chimpmatic_license_activation', array() );
			$expires    = is_array( $activation ) && isset( $activation['expires_at'] ) ? $activation['expires_at'] : null;
		}
		if ( null !== $expires && ! is_numeric( $expires ) ) {
			$expires = strtotime( (string) $expires );
		}
		return array(
			'installed'       => $installed,
			'activated'       => $active,
			'version'         => $version,
			'licensed'        => $licensed,
			'license_expires' => is_numeric( $expires ) ? max( 0, (int) $expires ) : null,
		);
	}

	private function pro_companion( array $pro ): array {
		$state           = ! $pro['installed'] ? 'not_present' : ( $pro['activated'] ? 'unknown' : 'inactive' );
		$validator_class = 'Cmatic_License_Validator';
		$validator       = array( $validator_class, 'get_license_state' );
		if ( $pro['activated'] && class_exists( $validator_class ) && is_callable( $validator ) ) {
			$reported_value = call_user_func( $validator );
			$reported       = is_scalar( $reported_value ) ? (string) $reported_value : '';
			$state          = in_array( $reported, array( 'active', 'expired' ), true ) ? $reported : 'inactive';
		} elseif ( $pro['activated'] && $pro['licensed'] ) {
			$state = 'legacy_activated';
		}
		return array(
			'slug'                   => 'chimpmatic',
			'installed'              => $pro['installed'],
			'active'                 => $pro['activated'],
			'version'                => $pro['version'],
			'license_state'          => $state,
			'source'                 => 'lite_observed',
			'observation_started_at' => (int) ( new Cmatic_Install_Data( Cmatic_Options_Repository::instance() ) )->get_quest(),
		);
	}

	private function metadata( int $quest ): array {
		$state = $this->sdk_state();
		$mode  = isset( $state['cadence_mode'] ) ? (string) $state['cadence_mode'] : (string) Cmatic_Options_Repository::get_option( 'telemetry.schedule', 'daily' );
		return array(
			'schedule'             => $mode,
			'frequent_started_at'  => isset( $state['consent_first_enabled_at'] ) ? (int) $state['consent_first_enabled_at'] : (int) Cmatic_Options_Repository::get_option( 'telemetry.frequent_started_at', 0 ),
			'is_reactivation'      => (bool) Cmatic_Options_Repository::get_option( 'telemetry.is_reactivation', false ) || $this->timestamps( 'activations' )['reported_total'] > 1,
			'disabled_count'       => (int) Cmatic_Options_Repository::get_option( 'telemetry.disabled_count', 0 ),
			'opt_in_date'          => isset( $state['consent_first_enabled_at'] ) ? (int) $state['consent_first_enabled_at'] : (int) Cmatic_Options_Repository::get_option( 'telemetry.opt_in_date', 0 ),
			'last_heartbeat'       => isset( $state['last_acknowledged_at'] ) ? (int) $state['last_acknowledged_at'] : (int) Cmatic_Options_Repository::get_option( 'telemetry.last_heartbeat', 0 ),
			'failed_heartbeats'    => isset( $state['retry_attempt'] ) ? (int) $state['retry_attempt'] : (int) Cmatic_Options_Repository::get_option( 'telemetry.failed_count', 0 ),
			'total_uptime_seconds' => $quest > 0 ? max( 0, time() - $quest ) : 0,
			'signal_version'       => defined( 'SPARTAN_MCE_VERSION' ) ? (string) SPARTAN_MCE_VERSION : 'unknown',
		);
	}

	private function lifecycle( int $quest ): array {
		$activations   = $this->timestamps( 'activations' );
		$deactivations = $this->timestamps( 'deactivations' );
		$upgrades      = $this->timestamps( 'upgrades' );
		$last_active   = ! empty( $activations['items'] ) ? max( $activations['items'] ) : 0;
		$last_inactive = ! empty( $deactivations['items'] ) ? max( $deactivations['items'] ) : 0;
		$last_upgrade  = ! empty( $upgrades['items'] ) ? max( $upgrades['items'] ) : 0;
		$history       = Cmatic_Options_Repository::get_option( 'lifecycle.version_history', array() );
		return array(
			'activation_count'            => $activations['reported_total'],
			'deactivation_count'          => $deactivations['reported_total'],
			'upgrade_count'               => $upgrades['reported_total'],
			'first_activated'             => $quest,
			'last_activated'              => $last_active,
			'last_deactivated'            => $last_inactive,
			'last_upgrade'                => $last_upgrade,
			'days_since_first_activation' => $this->age_days( $quest ),
			'days_since_last_upgrade'     => $this->age_days( $last_upgrade ),
			'avg_session_length_seconds'  => $this->average_session( $activations['items'], $deactivations['items'] ),
			'total_sessions'              => $activations['reported_total'],
			'previous_version'            => (string) Cmatic_Options_Repository::get_option( 'lifecycle.previous_version', '' ),
			'version_history_count'       => is_array( $history ) ? count( $history ) : 0,
			'install_method'              => (string) Cmatic_Options_Repository::get_option( 'lifecycle.install_method', 'unknown' ),
			'days_on_current_version'     => $this->age_days( $last_upgrade > 0 ? $last_upgrade : $quest ),
			'activation_timestamps'       => $activations,
			'deactivation_timestamps'     => $deactivations,
			'upgrade_timestamps'          => $upgrades,
			'active_session'              => 0 === $last_inactive || $last_active > $last_inactive,
		);
	}

	private function api( array $analysis ): array {
		$data_center   = isset( $analysis['data_center'] ) && is_string( $analysis['data_center'] ) ? strtolower( $analysis['data_center'] ) : '';
		$data_center   = 1 === preg_match( '/^[a-z]{2}\d{1,3}$/D', $data_center ) ? $data_center : 'unknown';
		$counters      = $this->outcome_totals( 'subscribe' );
		$old_sent      = (int) Cmatic_Options_Repository::get_option( 'stats.sent', 0 );
		$attempts      = max( (int) Cmatic_Options_Repository::get_option( 'api.total_attempts', $old_sent ), $counters['attempts'] );
		$successes     = max( (int) Cmatic_Options_Repository::get_option( 'api.total_successes', $old_sent ), $counters['successes'] );
		$failures      = max( (int) Cmatic_Options_Repository::get_option( 'api.total_failures', 0 ), $counters['failures'] );
		$last_success  = max( (int) Cmatic_Options_Repository::get_option( 'api.last_success', 0 ), $counters['last_success_at'] );
		$last_failure  = max( (int) Cmatic_Options_Repository::get_option( 'api.last_failure', 0 ), $counters['last_failure_at'] );
		$rate          = $attempts > 0 ? round( ( $successes / $attempts ) * 100, 2 ) : null;
		$errors        = Cmatic_Options_Repository::get_option( 'api.error_codes', array() );
		$error_summary = array();
		foreach ( is_array( $errors ) ? $errors : array() as $code => $count ) {
			$code  = sanitize_key( (string) $code );
			$count = self::nonnegative_int( $count );
			if ( '' !== $code && $count > 0 ) {
				$error_summary[ $code ] = $count;
			}
		}
		ksort( $error_summary, SORT_STRING );
		$consecutive = (int) Cmatic_Options_Repository::get_option( 'api.consecutive_failures', 0 );
		return array(
			'is_connected'              => $analysis['credential_units'] > 0,
			'forms_with_api'            => $analysis['credential_units'],
			'api_data_center'           => $data_center,
			'api_key_length'            => $analysis['manual_key_length'],
			'first_connected'           => (int) Cmatic_Options_Repository::get_option( 'api.first_connected', 0 ),
			'total_attempts'            => $attempts,
			'total_successes'           => $successes,
			'total_failures'            => $failures,
			'success_rate'              => $rate,
			'uptime_percentage'         => $rate,
			'last_success'              => $last_success,
			'last_failure'              => $last_failure,
			'days_since_last_success'   => $this->age_days( $last_success ),
			'days_since_last_failure'   => $this->age_days( $last_failure ),
			'avg_response_time_ms'      => (int) Cmatic_Options_Repository::get_option( 'api.avg_response_time', 0 ),
			'error_codes'               => array_slice( $error_summary, 0, 64, true ),
			'api_health_score'          => null === $rate ? null : max( 0, min( 100, $rate - ( $consecutive * 5 ) ) ),
			'setup_sync_attempted'      => (bool) Cmatic_Options_Repository::get_option( 'api.sync_attempted', false ),
			'setup_sync_attempts_count' => (int) Cmatic_Options_Repository::get_option( 'api.sync_attempts_count', 0 ),
			'setup_first_success'       => (bool) Cmatic_Options_Repository::get_option( 'api.setup_first_success', false ),
			'setup_first_failure'       => (bool) Cmatic_Options_Repository::get_option( 'api.setup_first_failure', false ),
			'setup_failure_count'       => (int) Cmatic_Options_Repository::get_option( 'api.setup_failure_count', 0 ),
			'setup_audience_selected'   => (bool) Cmatic_Options_Repository::get_option( 'api.audience_selected', false ),
		);
	}

	private function submissions( int $quest, array $configurations, int $active_forms ): array {
		$counters     = $this->outcome_totals( 'subscribe' );
		$old_sent     = (int) Cmatic_Options_Repository::get_option( 'stats.sent', 0 );
		$sent         = max( $old_sent, $counters['successes'] );
		$failed       = max( (int) Cmatic_Options_Repository::get_option( 'submissions.failed', 0 ), $counters['failures'] );
		$total        = max( $sent + $failed, $counters['attempts'] );
		$first        = (int) Cmatic_Options_Repository::get_option( 'submissions.first', $counters['first_recorded_at'] );
		$last         = (int) Cmatic_Options_Repository::get_option( 'submissions.last', max( $counters['last_success_at'], $counters['last_failure_at'] ) );
		$last_success = max( (int) Cmatic_Options_Repository::get_option( 'submissions.last_success', 0 ), $counters['last_success_at'] );
		$last_failure = max( (int) Cmatic_Options_Repository::get_option( 'submissions.last_failure', 0 ), $counters['last_failure_at'] );
		$days         = max( 1, $this->age_days( $quest ) );
		$hourly       = Cmatic_Options_Repository::get_option( 'submissions.hourly', array() );
		$daily        = Cmatic_Options_Repository::get_option( 'submissions.daily', array() );
		$busiest_hour = $this->busiest( is_array( $hourly ) ? $hourly : array(), 0, 23 );
		$busiest_day  = $this->busiest( is_array( $daily ) ? $daily : array(), 0, 6 );
		$this_month   = (int) Cmatic_Options_Repository::get_option( 'submissions.this_month', 0 );
		$last_month   = (int) Cmatic_Options_Repository::get_option( 'submissions.last_month', 0 );
		$rolling      = $this->rolling_successes();
		$forms_used   = 0;
		foreach ( $configurations as $row ) {
			if ( isset( $row['submissions'] ) && (int) $row['submissions'] > 0 ) {
				++$forms_used;
			}
		}
		$rate = $total > 0 ? round( ( $sent / $total ) * 100, 2 ) : null;
		$data = array(
			'total_sent'                   => $sent,
			'total_failed'                 => $failed,
			'total_submissions'            => $total,
			'successful_submissions_count' => $sent,
			'failed_count'                 => $failed,
			'success_rate'                 => $rate,
			'first_submission'             => $first,
			'last_submission'              => $last,
			'last_success'                 => $last_success,
			'last_failure'                 => $last_failure,
			'days_since_first'             => $this->age_days( $first ),
			'days_since_last'              => $this->age_days( $last ),
			'hours_since_last'             => $this->age_hours( $last ),
			'avg_per_day'                  => round( $total / $days, 2 ),
			'avg_per_week'                 => round( ( $total / $days ) * 7, 2 ),
			'avg_per_month'                => round( ( $total / $days ) * 30, 2 ),
			'busiest_hour'                 => $busiest_hour['key'],
			'busiest_day'                  => $busiest_day['key'],
			'submissions_busiest_hour'     => $busiest_hour['count'],
			'submissions_busiest_day'      => $busiest_day['count'],
			'this_month'                   => $this_month,
			'last_month'                   => $last_month,
			'peak_month'                   => (int) Cmatic_Options_Repository::get_option( 'submissions.peak_month', 0 ),
			'month_over_month_change'      => $last_month > 0 ? round( ( ( $this_month - $last_month ) / $last_month ) * 100, 2 ) : null,
			'consecutive_successes'        => (int) Cmatic_Options_Repository::get_option( 'submissions.consecutive_successes', 0 ),
			'consecutive_failures'         => (int) Cmatic_Options_Repository::get_option( 'submissions.consecutive_failures', 0 ),
			'longest_success_streak'       => (int) Cmatic_Options_Repository::get_option( 'submissions.longest_success_streak', 0 ),
			'active_forms_count'           => max( 0, $active_forms ),
			'forms_with_submissions'       => $forms_used,
			'_subs_7d'                     => $rolling['seven'],
			'_subs_30d'                    => $rolling['thirty'],
			'_subs_median'                 => $rolling['median'],
		);
		return $data;
	}

	private function features( array $configurations ): array {
		$counts = array_fill_keys( array( 'double_optin', 'required_consent', 'debug_logger', 'custom_merge_fields', 'interest_groups', 'groups_total_mapped', 'tags_enabled', 'tags_total_selected', 'arbitrary_tags', 'conditional_logic' ), 0 );
		foreach ( $configurations as $row ) {
			$config                       = isset( $row['settings'] ) && is_array( $row['settings'] ) ? $row['settings'] : array();
			$provider                     = Cmatic_Lite_Esp_Registry::get_selected( $config );
			$settings                     = $this->provider_settings( $config, $provider );
			$counts['double_optin']      += ! empty( $settings['confsubs'] ) || 'double' === ( isset( $settings['subscription_mode'] ) ? $settings['subscription_mode'] : '' ) ? 1 : 0;
			$counts['required_consent']  += ! empty( $settings['accept'] ) || ! empty( $settings['consent_required'] ) || 'required' === ( isset( $settings['consent_gate'] ) ? $settings['consent_gate'] : '' ) ? 1 : 0;
			$counts['debug_logger']      += ! empty( $settings['logfileEnabled'] ) ? 1 : 0;
			$counts['conditional_logic'] += ! empty( $settings['conditional_logic'] ) || ! empty( $settings['conditions'] ) ? 1 : 0;
			$tags                         = isset( $settings['labeltags'] ) && is_array( $settings['labeltags'] ) ? array_filter( $settings['labeltags'] ) : array();
			if ( ! empty( $tags ) || ! empty( $settings['tags'] ) ) {
				++$counts['tags_enabled'];
				$counts['tags_total_selected'] += count( $tags ) + ( is_array( isset( $settings['tags'] ) ? $settings['tags'] : null ) ? count( $settings['tags'] ) : 0 );
			}
			$counts['arbitrary_tags'] += ! empty( $settings['labeltags_cm-tag'] ) || ! empty( $settings['tag'] ) ? 1 : 0;
			$custom                    = 0;
			$defaults                  = array( 'EMAIL', 'FNAME', 'LNAME', 'ADDRESS', 'PHONE' );
			$merge_fields              = isset( $settings['merge_fields'] ) && is_array( $settings['merge_fields'] ) ? $settings['merge_fields'] : array();
			if ( empty( $merge_fields ) && isset( $settings['merge-vars'] ) && is_array( $settings['merge-vars'] ) ) {
				$merge_fields = $settings['merge-vars'];
			}
			foreach ( $merge_fields as $field ) {
				if ( is_array( $field ) && isset( $field['tag'] ) && is_scalar( $field['tag'] ) && ! in_array( strtoupper( (string) $field['tag'] ), $defaults, true ) ) {
					++$custom;
				}
			}
			$counts['custom_merge_fields'] += $custom;
			$groups                         = 0;
			for ( $index = 1; $index <= 20; $index++ ) {
				if ( ! empty( $settings[ 'ggCustomKey' . $index ] ) && ! empty( trim( (string) ( isset( $settings[ 'ggCustomValue' . $index ] ) ? $settings[ 'ggCustomValue' . $index ] : '' ) ) ) ) {
					++$groups;
				}
			}
			if ( ! empty( $settings['base_groups'] ) && is_array( $settings['base_groups'] ) ) {
				$groups += count( $settings['base_groups'] );
			}
			if ( $groups > 0 ) {
				++$counts['interest_groups'];
				$counts['groups_total_mapped'] += $groups;
			}
		}
		$flags   = array(
			'double_optin'        => $counts['double_optin'] > 0,
			'required_consent'    => $counts['required_consent'] > 0,
			'debug_logger'        => $counts['debug_logger'] > 0,
			'custom_merge_fields' => $counts['custom_merge_fields'] > 0,
			'interest_groups'     => $counts['interest_groups'] > 0,
			'tags_enabled'        => $counts['tags_enabled'] > 0,
			'arbitrary_tags'      => $counts['arbitrary_tags'] > 0,
			'conditional_logic'   => $counts['conditional_logic'] > 0,
		);
		$enabled = count( array_filter( $flags ) );
		return array(
			'double_optin_count'        => $counts['double_optin'],
			'required_consent_count'    => $counts['required_consent'],
			'debug_logger_count'        => $counts['debug_logger'],
			'custom_merge_fields_count' => $counts['custom_merge_fields'],
			'interest_groups_count'     => $counts['interest_groups'],
			'groups_total_mapped'       => $counts['groups_total_mapped'],
			'tags_enabled_count'        => $counts['tags_enabled'],
			'tags_total_selected'       => $counts['tags_total_selected'],
			'arbitrary_tags_count'      => $counts['arbitrary_tags'],
			'conditional_logic_count'   => $counts['conditional_logic'],
			'double_optin'              => $flags['double_optin'],
			'required_consent'          => $flags['required_consent'],
			'debug_logger'              => $flags['debug_logger'],
			'custom_merge_fields'       => $flags['custom_merge_fields'],
			'interest_groups'           => $flags['interest_groups'],
			'tags_enabled'              => $flags['tags_enabled'],
			'arbitrary_tags'            => $flags['arbitrary_tags'],
			'conditional_logic'         => $flags['conditional_logic'],
			'auto_update'               => (bool) Cmatic_Options_Repository::get_option( 'auto_update', true ),
			'signal_sharing_enabled'    => 'enabled' === Cmatic_Options_Repository::get_option( 'signls.consent_status', 'unset' ),
			'debug'                     => (bool) Cmatic_Options_Repository::get_option( 'debug', false ),
			'backlink'                  => (bool) Cmatic_Options_Repository::get_option( 'backlink', false ),
			'total_features_enabled'    => $enabled,
			'features_usage_percentage' => round( ( $enabled / count( $flags ) ) * 100, 2 ),
			'webhook_enabled'           => (bool) Cmatic_Options_Repository::get_option( 'features.webhook_enabled', false ),
			'custom_api_endpoint'       => (bool) Cmatic_Options_Repository::get_option( 'features.custom_api_endpoint', false ),
			'email_notifications'       => (bool) Cmatic_Options_Repository::get_option( 'features.email_notifications', false ),
			'test_modal_used'           => (bool) Cmatic_Options_Repository::get_option( 'features.test_modal_used', false ),
			'contact_lookup_used'       => (bool) Cmatic_Options_Repository::get_option( 'features.contact_lookup_used', false ),
		);
	}

	private function setup( array $analysis ): array {
		$totals  = $this->outcome_totals( 'subscribe' );
		$stage   = 'installed';
		$percent = 0;
		if ( $analysis['credential_units'] > 0 ) {
			$stage   = 'connected';
			$percent = 20;
		}
		if ( $analysis['destination_units'] > 0 ) {
			$stage   = 'audience_selected';
			$percent = 40;
		}
		if ( $analysis['mapping_count'] > 0 ) {
			$stage   = 'fields_mapped';
			$percent = 60;
		}
		if ( $totals['attempts'] > 0 ) {
			$stage   = 'first_send';
			$percent = 80;
		}
		if ( $totals['successes'] > 0 ) {
			$stage   = 'first_success';
			$percent = 100;
		}
		return compact( 'stage', 'percent' );
	}

	private function merge_outcomes( array &$integrations, array &$operations ): void {
		$operations = array();
		$grouped    = array();
		foreach ( $this->lifetime_rows() as $row ) {
			$integration = isset( $row['integration_slug'] ) ? (string) $row['integration_slug'] : '';
			$operation   = isset( $row['operation_slug'] ) ? (string) $row['operation_slug'] : '';
			$counter     = isset( $row['counter_slug'] ) ? (string) $row['counter_slug'] : '';
			if ( ! isset( $integrations[ $integration ] ) || '' === $operation || '' === $counter ) {
				continue;
			}
			$grouped[ $integration ][ $operation ]['counters'][ $counter ] = max( 0, (int) ( isset( $row['counter_value'] ) ? $row['counter_value'] : 0 ) );
			foreach ( array( 'first_recorded_at', 'last_success_at', 'last_failure_at' ) as $time_key ) {
				$grouped[ $integration ][ $operation ][ $time_key ] = max( isset( $grouped[ $integration ][ $operation ][ $time_key ] ) ? $grouped[ $integration ][ $operation ][ $time_key ] : 0, (int) ( isset( $row[ $time_key ] ) ? $row[ $time_key ] : 0 ) );
			}
		}
		foreach ( $grouped as $integration => $by_operation ) {
			foreach ( $by_operation as $operation => $data ) {
				$counters                                   = $data['counters'];
				$integrations[ $integration ]['attempts']  += isset( $counters['attempts'] ) ? $counters['attempts'] : 0;
				$integrations[ $integration ]['successes'] += isset( $counters['successes'] ) ? $counters['successes'] : 0;
				$integrations[ $integration ]['failures']  += isset( $counters['failures'] ) ? $counters['failures'] : 0;
				$classes                                    = array();
				foreach ( array( 'transport_dns', 'transport_tls', 'transport_connect', 'transport_timeout', 'http_4xx', 'http_429', 'http_5xx', 'auth', 'configuration', 'validation', 'remote_rejected', 'unknown' ) as $class ) {
					$classes[ $class ] = isset( $counters[ 'failure_' . $class ] ) ? $counters[ 'failure_' . $class ] : 0;
				}
				$operations[] = array(
					'integration'      => $integration,
					'operation'        => $operation,
					'last_success_age' => $this->age_seconds_nullable( isset( $data['last_success_at'] ) ? $data['last_success_at'] : 0 ),
					'last_failure_age' => $this->age_seconds_nullable( isset( $data['last_failure_at'] ) ? $data['last_failure_at'] : 0 ),
					'failure_classes'  => $classes,
				);
			}
		}
	}

	private function outcome_totals( string $operation ): array {
		$result = array(
			'attempts'          => 0,
			'successes'         => 0,
			'failures'          => 0,
			'first_recorded_at' => 0,
			'last_success_at'   => 0,
			'last_failure_at'   => 0,
		);
		foreach ( $this->lifetime_rows() as $row ) {
			if ( $operation !== ( isset( $row['operation_slug'] ) ? (string) $row['operation_slug'] : '' ) ) {
				continue;
			}
			$counter = isset( $row['counter_slug'] ) ? (string) $row['counter_slug'] : '';
			if ( isset( $result[ $counter ] ) && in_array( $counter, array( 'attempts', 'successes', 'failures' ), true ) ) {
				$result[ $counter ] += max( 0, (int) $row['counter_value'] );
			}
			$first = (int) ( isset( $row['first_recorded_at'] ) ? $row['first_recorded_at'] : 0 );
			if ( $first > 0 ) {
				$result['first_recorded_at'] = 0 === $result['first_recorded_at'] ? $first : min( $result['first_recorded_at'], $first );
			}
			$result['last_success_at'] = max( $result['last_success_at'], (int) ( isset( $row['last_success_at'] ) ? $row['last_success_at'] : 0 ) );
			$result['last_failure_at'] = max( $result['last_failure_at'], (int) ( isset( $row['last_failure_at'] ) ? $row['last_failure_at'] : 0 ) );
		}
		return $result;
	}

	private function rolling_successes(): array {
		$by_date = array();
		foreach ( $this->daily_rows() as $row ) {
			if ( 'subscribe' !== ( isset( $row['operation_slug'] ) ? (string) $row['operation_slug'] : '' ) ) {
				continue;
			}
			$date = isset( $row['signal_date'] ) ? (string) $row['signal_date'] : '';
			if ( 1 === preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $date ) ) {
				$by_date[ $date ] = isset( $by_date[ $date ] ) ? $by_date[ $date ] + max( 0, (int) $row['successes'] ) : max( 0, (int) $row['successes'] );
			}
		}
		$values = array();
		$seven  = 0;
		for ( $offset = 29; $offset >= 0; --$offset ) {
			$date     = gmdate( 'Y-m-d', time() - $offset * DAY_IN_SECONDS );
			$value    = isset( $by_date[ $date ] ) ? $by_date[ $date ] : 0;
			$values[] = $value;
			if ( $offset <= 6 ) {
				$seven += $value;
			}
		}
		$sorted = $values;
		sort( $sorted, SORT_NUMERIC );
		$median = ( $sorted[14] + $sorted[15] ) / 2;
		return array(
			'seven'  => $seven,
			'thirty' => array_sum( $values ),
			'median' => round( $median, 2 ),
		);
	}

	private function lifetime_rows(): array {
		if ( null === $this->lifetime_rows ) {
			$this->lifetime_rows = \Signls\Sdk\V1\CounterStore::read_product( self::PRODUCT );
		}
		return is_array( $this->lifetime_rows ) ? $this->lifetime_rows : array();
	}

	private function daily_rows(): array {
		if ( null === $this->daily_rows ) {
			$this->daily_rows = \Signls\Sdk\V1\CounterStore::read_daily( self::PRODUCT, 30 );
		}
		return is_array( $this->daily_rows ) ? $this->daily_rows : array();
	}

	private function top_failure_reason(): array {
		if ( null === $this->reason_rows ) {
			$this->reason_rows = \Signls\Sdk\V1\CounterStore::read_reasons( self::PRODUCT );
		}
		$row = is_array( $this->reason_rows ) && isset( $this->reason_rows[0] ) && is_array( $this->reason_rows[0] ) ? $this->reason_rows[0] : array();
		return array(
			'code'   => isset( $row['reason_code'] ) && is_scalar( $row['reason_code'] ) ? (string) $row['reason_code'] : 'unknown',
			'sample' => isset( $row['sample'] ) && is_scalar( $row['sample'] ) ? (string) $row['sample'] : '',
		);
	}

	private function timestamps( string $key ): array {
		$source = Cmatic_Options_Repository::get_option( 'lifecycle.' . $key, array() );
		$items  = is_array( $source ) && isset( $source['items'] ) && is_array( $source['items'] ) ? $source['items'] : ( is_array( $source ) ? $source : array() );
		$items  = array_values(
			array_filter(
				array_map(
					static function ( $value ): int {
						return is_scalar( $value ) ? (int) $value : 0;
					},
					$items
				),
				static function ( int $value ): bool {
					return $value > 0;
				}
			)
		);
		sort( $items, SORT_NUMERIC );
		$reported = is_array( $source ) && isset( $source['reported_total'] ) && is_scalar( $source['reported_total'] ) ? max( count( $items ), (int) $source['reported_total'] ) : count( $items );
		$items    = array_slice( $items, -256 );
		return array(
			'items'          => $items,
			'reported_total' => $reported,
			'truncated'      => $reported > count( $items ),
		);
	}

	private function update_state(): array {
		$updates = get_site_transient( 'update_plugins' );
		$file    = 'contact-form-7-mailchimp-extension/chimpmatic-lite.php';
		$item    = is_object( $updates ) && isset( $updates->response[ $file ] ) ? $updates->response[ $file ] : null;
		return array(
			'available'       => is_object( $item ),
			'versions_behind' => is_object( $item ) ? 1 : 0,
		);
	}

	private function install_source( string $source ): string {
		$source = strtolower( sanitize_key( $source ) );
		if ( in_array( $source, array( 'wordpress', 'wordpress_org', 'wp_org', 'wporg', 'wp_org_search' ), true ) ) {
			return 'wp_org_search';
		}
		if ( in_array( $source, array( 'upload', 'manual', 'manual_upload' ), true ) ) {
			return 'manual_upload';
		}
		return in_array( $source, array( 'referral', 'migration', 'network' ), true ) ? $source : 'unknown';
	}

	private function sdk_state(): array {
		if ( ! class_exists( '\Signls\Sdk\V1\StateStore' ) ) {
			return array();
		}
		return ( new \Signls\Sdk\V1\StateStore( self::PRODUCT ) )->all();
	}

	private function provider_settings( array $config, string $provider ): array {
		return 'mailchimp' === $provider ? $config : ( isset( $config['providers'][ $provider ] ) && is_array( $config['providers'][ $provider ] ) ? $config['providers'][ $provider ] : array() );
	}

	private function has_configuration( array $settings, string $provider ): bool {
		foreach ( array( 'api-validation', 'list', 'merge_fields', 'field3', 'subscription_mode', 'doubleoptin' ) as $key ) {
			if ( ! empty( $settings[ $key ] ) ) {
				return true;
			}
		}
		return 'mailchimp' !== $provider && ! empty( $settings );
	}

	private function authentication( int $form_id, string $provider, array $config ): string {
		if ( 'mailchimp' === $provider ) {
			if ( ! empty( $config['api'] ) ) {
				return 'api_key';
			}
			return ( new Cmatic_Lite_Auth_Manager() )->has_oauth( $form_id ) ? 'oauth' : 'none';
		}
		return Cmatic_Lite_Esp_Credentials::has( $form_id, $provider ) ? 'api_key' : 'none';
	}

	private function has_destination( array $settings ): bool {
		$list = isset( $settings['list'] ) ? $settings['list'] : '';
		return is_array( $list ) ? ! empty( array_filter( $list ) ) : '' !== trim( is_scalar( $list ) ? (string) $list : '' );
	}

	private function mapping_count( array $settings ): int {
		$count = 0;
		foreach ( $settings as $key => $value ) {
			if ( 1 === preg_match( '/^field[0-9]+$/', (string) $key ) && is_scalar( $value ) && '' !== trim( (string) $value ) ) {
				++$count;
			}
		}
		if ( 0 === $count ) {
			for ( $index = 1; $index <= 50; $index++ ) {
				$count += ! empty( $settings[ 'CustomKey' . $index ] ) && ! empty( $settings[ 'CustomValue' . $index ] ) ? 1 : 0;
			}
		}
		return $count;
	}

	private function count_narrow_features( array &$features, array $settings, string $provider, int $mappings ): void {
		$checks = array(
			'advanced_fields'     => $mappings > 4,
			'tags_management'     => ! empty( $settings['tags'] ) || ! empty( $settings['tag'] ) || ! empty( $settings['labeltags'] ),
			'groups_segments'     => ! empty( $settings['groups'] ) || ! empty( $settings['interests'] ) || ! empty( $settings['base_groups'] ),
			'gdpr_fields'         => ! empty( $settings['gdpr'] ) || ! empty( $settings['marketing_permissions'] ),
			'conditional_logic'   => ! empty( $settings['conditions'] ) || ! empty( $settings['conditional_logic'] ),
			'double_optin'        => ! empty( $settings['confsubs'] ) || 'double' === ( isset( $settings['subscription_mode'] ) ? $settings['subscription_mode'] : '' ),
			'visitor_geolocation' => ! empty( $settings['visitor_geolocation'] ) || ! empty( $settings['geolocation'] ),
			'unsubscribe'         => ! empty( $settings['addunsubscr'] ),
			'multi_group_routing' => 'mailerlite' === $provider && ( ! empty( $settings['base_groups'] ) || ! empty( $settings['routing_rules'] ) ),
			'status_control'      => ! empty( $settings['status_mode'] ) || ! empty( $settings['status'] ),
			'consent_metadata'    => ! empty( $settings['consent_field'] ) || ! empty( $settings['consent_metadata_enabled'] ),
		);
		foreach ( $checks as $slug => $enabled ) {
			$features[ $slug ] += $enabled ? 1 : 0;
		}
	}

	private function pro_version( bool $installed ): string {
		if ( defined( 'CMATIC_VERSION' ) ) {
			return (string) CMATIC_VERSION;
		}
		if ( ! $installed ) {
			return 'unknown';
		}
		$data = get_file_data( WP_PLUGIN_DIR . '/chimpmatic/chimpmatic.php', array( 'version' => 'Version' ) );
		return ! empty( $data['version'] ) ? (string) $data['version'] : 'unknown';
	}

	private function age_days( int $timestamp ): int {
		return $timestamp > 0 ? (int) floor( max( 0, time() - $timestamp ) / DAY_IN_SECONDS ) : 0;
	}

	private function age_hours( int $timestamp ): int {
		return $timestamp > 0 ? (int) floor( max( 0, time() - $timestamp ) / HOUR_IN_SECONDS ) : 0;
	}

	private function age_seconds_nullable( int $timestamp ) {
		return $timestamp > 0 ? max( 0, time() - $timestamp ) : null;
	}

	private function average_session( array $activations, array $deactivations ): int {
		$total = 0;
		$count = 0;
		foreach ( $activations as $index => $activated ) {
			if ( isset( $deactivations[ $index ] ) && $deactivations[ $index ] >= $activated ) {
				$total += $deactivations[ $index ] - $activated;
				++$count;
			}
		}
		return $count > 0 ? (int) floor( $total / $count ) : 0;
	}

	private function busiest( array $distribution, int $minimum, int $maximum ): array {
		$result = array(
			'key'   => $minimum,
			'count' => 0,
		);
		foreach ( $distribution as $key => $count ) {
			$key   = (int) $key;
			$count = max( 0, (int) $count );
			if ( $key >= $minimum && $key <= $maximum && $count > $result['count'] ) {
				$result = array(
					'key'   => $key,
					'count' => $count,
				);
			}
		}
		return $result;
	}

	private function mysql_version(): string {
		global $wpdb;
		if ( ! is_object( $wpdb ) || ! is_callable( array( $wpdb, 'db_version' ) ) ) {
			return '';
		}
		return (string) $wpdb->db_version();
	}

	private static function nonnegative_int( $value ): int {
		return is_scalar( $value ) && is_numeric( $value ) ? max( 0, (int) $value ) : 0;
	}

	private function server_software(): string {
		return isset( $_SERVER['SERVER_SOFTWARE'] ) && is_scalar( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['SERVER_SOFTWARE'] ) ) : '';
	}
}
