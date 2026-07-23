<?php
/**
 * ChimpMatic Lite rich Signls contract.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

final class Cmatic_Lite_Signls_Contract {

	private const UINT_MAX = 4294967295;

	private const INTEGRATIONS = array( 'mailchimp', 'brevo', 'mailerlite', 'klaviyo' );

	private const FEATURES = array(
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

	public static function get(): array {
		return array(
			'snapshot_schema_version' => 2,
			'observation_profile'     => 'chimpmatic_connector_v1',
			'integrations'            => self::INTEGRATIONS,
			'features'                => self::FEATURES,
			'operations'              => array( 'subscribe', 'connect', 'disconnect', 'refresh_schema' ),
			'companions'              => array( 'chimpmatic' ),
			'observation_schema'      => self::object(
				array(
					'install'           => self::install(),
					'metadata'          => self::metadata(),
					'lifecycle'         => self::lifecycle(),
					'environment'       => self::environment(),
					'api'               => self::api(),
					'submissions'       => self::submissions(),
					'features'          => self::rich_features(),
					'forms'             => self::forms(),
					'performance'       => self::performance(),
					'plugins'           => self::plugins(),
					'competitors'       => self::competitors(),
					'server'            => self::server(),
					'wordpress'         => self::wordpress(),
					'legacy_lifecycle'  => self::legacy_lifecycle(),
					'opportunities'     => self::opportunities(),
					'collection_status' => self::enum( array( 'complete', 'partial', 'failed' ), 'failed' ),
					'missing_sections'  => self::list_of(
						self::enum(
							array( 'install', 'metadata', 'lifecycle', 'environment', 'api', 'submissions', 'features', 'forms', 'performance', 'plugins', 'competitors', 'server', 'wordpress', 'legacy_lifecycle' )
						),
						14
					),
				)
			),
		);
	}

	private static function install(): array {
		return self::object(
			array(
				'plugin_slug' => self::slug( 100 ),
				'quest'       => self::uint(),
				'pro'         => self::object(
					array(
						'installed'       => self::bool(),
						'activated'       => self::bool(),
						'version'         => self::version( true ),
						'licensed'        => self::bool(),
						'license_expires' => self::uint( self::UINT_MAX, true ),
					)
				),
			)
		);
	}

	private static function metadata(): array {
		return self::object(
			array(
				'schedule'             => self::enum( array( 'daily', 'frequent', 'sparse', 'weekly', 'disabled' ), 'daily' ),
				'frequent_started_at'  => self::uint(),
				'is_reactivation'      => self::bool(),
				'disabled_count'       => self::uint(),
				'opt_in_date'          => self::uint(),
				'last_heartbeat'       => self::uint(),
				'failed_heartbeats'    => self::uint(),
				'total_uptime_seconds' => self::uint(),
				'signal_version'       => self::version(),
			)
		);
	}

	private static function lifecycle(): array {
		$properties = array();
		foreach ( array( 'activation_count', 'deactivation_count', 'upgrade_count', 'first_activated', 'last_activated', 'last_deactivated', 'last_upgrade', 'days_since_first_activation', 'days_since_last_upgrade', 'avg_session_length_seconds', 'total_sessions', 'version_history_count', 'days_on_current_version' ) as $field ) {
			$properties[ $field ] = self::uint();
		}
		$properties['previous_version']        = self::version( true );
		$properties['install_method']          = self::enum( array( 'wp_org_search', 'referral', 'migration', 'manual_upload', 'network', 'unknown' ), 'unknown' );
		$properties['activation_timestamps']   = self::accounted_list( self::uint(), 256 );
		$properties['deactivation_timestamps'] = self::accounted_list( self::uint(), 256 );
		$properties['upgrade_timestamps']      = self::accounted_list( self::uint(), 256 );
		$properties['active_session']          = self::bool();
		return self::object( $properties );
	}

	private static function environment(): array {
		$properties = array();
		foreach ( array( 'php_version', 'php_curl_version', 'wp_version', 'mysql_version', 'mysql_client_version', 'theme_version', 'cf7_version', 'plugin_version' ) as $field ) {
			$properties[ $field ] = self::version( true );
		}
		foreach ( array( 'php_sapi', 'php_os', 'php_architecture', 'php_memory_limit', 'php_post_max_size', 'php_upload_max_filesize', 'php_default_timezone', 'php_log_errors', 'php_critical_extensions', 'php_openssl_version', 'wp_memory_limit', 'wp_max_memory_limit', 'wp_auto_update_core', 'db_charset', 'db_collate', 'server_software', 'server_protocol', 'locale', 'timezone', 'site_language', 'site_charset', 'permalink_structure', 'theme', 'theme_author', 'parent_theme' ) as $field ) {
			$properties[ $field ] = self::text();
		}
		foreach ( array( 'php_max_execution_time', 'php_max_input_time', 'php_max_input_vars', 'php_extensions_count', 'wp_db_version', 'db_prefix_length', 'server_port', 'active_plugins_count', 'total_plugins_count', 'must_use_plugins_count', 'network_count' ) as $field ) {
			$properties[ $field ] = self::uint();
		}
		foreach ( array( 'wp_debug', 'wp_debug_log', 'wp_debug_display', 'script_debug', 'wp_cache', 'wp_cron_disabled', 'https', 'is_child_theme', 'theme_supports_html5', 'theme_supports_post_thumbnails', 'is_multisite', 'is_subdomain_install', 'is_main_site', 'cf7_installed' ) as $field ) {
			$properties[ $field ] = self::bool();
		}
		foreach ( array( 'http_host_sha256', 'home_url_sha256', 'site_url_sha256', 'admin_email_sha256' ) as $field ) {
			$properties[ $field ] = self::sha256();
		}
		$properties['user_agent'] = self::text( 512 );
		return self::object( $properties );
	}

	private static function api(): array {
		$properties = array(
			'is_connected'            => self::bool(),
			'api_data_center'         => self::slug( 10 ),
			'success_rate'            => self::decimal( 0, 100, true ),
			'uptime_percentage'       => self::decimal( 0, 100, true ),
			'error_codes'             => self::map_of( self::uint(), 64, 100 ),
			'api_health_score'        => self::decimal( 0, 100, true ),
			'setup_sync_attempted'    => self::bool(),
			'setup_first_success'     => self::bool(),
			'setup_first_failure'     => self::bool(),
			'setup_audience_selected' => self::bool(),
		);
		foreach ( array( 'forms_with_api', 'api_key_length', 'first_connected', 'total_attempts', 'total_successes', 'total_failures', 'last_success', 'last_failure', 'days_since_last_success', 'days_since_last_failure', 'avg_response_time_ms', 'setup_sync_attempts_count', 'setup_failure_count' ) as $field ) {
			$properties[ $field ] = self::uint();
		}
		return self::object( $properties );
	}

	private static function submissions(): array {
		$properties = array();
		foreach ( array( 'total_sent', 'total_failed', 'total_submissions', 'successful_submissions_count', 'failed_count', 'first_submission', 'last_submission', 'last_success', 'last_failure', 'days_since_first', 'days_since_last', 'hours_since_last', 'submissions_busiest_hour', 'submissions_busiest_day', 'this_month', 'last_month', 'peak_month', 'consecutive_successes', 'consecutive_failures', 'longest_success_streak', 'active_forms_count', 'forms_with_submissions' ) as $field ) {
			$properties[ $field ] = self::uint();
		}
		foreach ( array( 'success_rate', 'avg_per_day', 'avg_per_week', 'avg_per_month' ) as $field ) {
			$properties[ $field ] = self::decimal( 0, self::UINT_MAX );
		}
		$properties['success_rate']['nullable'] = true;
		$properties['busiest_hour']             = self::int( 0, 23 );
		$properties['busiest_day']              = self::int( 0, 6 );
		$properties['month_over_month_change']  = self::decimal( -100, self::UINT_MAX, true );
		return self::object( $properties );
	}

	private static function rich_features(): array {
		$properties = array();
		foreach ( array( 'double_optin_count', 'required_consent_count', 'debug_logger_count', 'custom_merge_fields_count', 'interest_groups_count', 'groups_total_mapped', 'tags_enabled_count', 'tags_total_selected', 'arbitrary_tags_count', 'conditional_logic_count', 'total_features_enabled' ) as $field ) {
			$properties[ $field ] = self::uint();
		}
		foreach ( array( 'double_optin', 'required_consent', 'debug_logger', 'custom_merge_fields', 'interest_groups', 'tags_enabled', 'arbitrary_tags', 'conditional_logic', 'auto_update', 'signal_sharing_enabled', 'debug', 'backlink', 'webhook_enabled', 'custom_api_endpoint', 'email_notifications', 'test_modal_used', 'contact_lookup_used' ) as $field ) {
			$properties[ $field ] = self::bool();
		}
		$properties['features_usage_percentage'] = self::decimal( 0, 100 );
		return self::object( $properties );
	}

	private static function forms(): array {
		$audience      = self::object(
			array(
				'audience_id_sha256'    => self::sha256(),
				'member_count'          => self::uint(),
				'merge_field_count'     => self::uint(),
				'double_optin'          => self::bool(),
				'marketing_permissions' => self::bool(),
				'campaign_count'        => self::uint(),
				'is_paired'             => self::bool(),
			)
		);
		$field         = self::object(
			array(
				'name' => self::text(),
				'type' => self::text(),
			)
		);
		$mapping       = self::object(
			array(
				'cf7_field' => self::text(),
				'mc_tag'    => self::text(),
				'mc_type'   => self::text(),
			)
		);
		$form_features = array();
		foreach ( array( 'double_optin', 'required_consent', 'debug_logger', 'tags_enabled', 'interest_groups', 'custom_merge_fields', 'conditional_logic' ) as $name ) {
			$form_features[ $name ] = self::bool();
		}
		$form       = self::object(
			array(
				'form_id_sha256'            => self::sha256(),
				'field_count'               => self::uint(),
				'fields'                    => self::accounted_list( $field, 30 ),
				'paired_audience_id_sha256' => self::sha256( true ),
				'mappings'                  => self::accounted_list( $mapping, 30 ),
				'unmapped_cf7_fields'       => self::uint(),
				'unmapped_mc_fields'        => self::uint(),
				'features'                  => self::object( $form_features ),
			)
		);
		$properties = array();
		foreach ( array( 'total_forms', 'processed_forms', 'active_forms', 'forms_with_api', 'forms_with_lists', 'inactive_forms', 'total_audiences', 'total_contacts', 'max_lists_per_form', 'total_fields_all_forms', 'min_fields_per_form', 'max_fields_per_form', 'oldest_form_created', 'newest_form_created', 'days_since_oldest_form', 'days_since_newest_form', 'forms_with_submissions', 'forms_never_submitted', 'forms_with_double_opt', 'forms_with_consent', 'total_submissions_all_forms' ) as $name ) {
			$properties[ $name ] = self::uint();
		}
		foreach ( array( 'avg_lists_per_form', 'avg_fields_per_form', 'form_utilization_rate' ) as $name ) {
			$properties[ $name ] = self::decimal( 0, self::UINT_MAX, true );
		}
		$properties['audiences']              = self::accounted_list( $audience, 100, 'audience_id_sha256' );
		$properties['forms_detail']           = self::accounted_list( $form, 50, 'form_id_sha256' );
		$properties['forms_truncated']        = self::bool();
		$properties['forms_detail_truncated'] = self::bool();
		$properties['field_types_aggregate']  = self::map_of( self::uint(), 100, 100 );
		$properties['mapping_stats']          = self::object(
			array(
				'total_cf7_fields' => self::uint(),
				'total_mc_fields'  => self::uint(),
				'mapped_fields'    => self::uint(),
				'mapping_rate'     => self::decimal( 0, 100, true ),
			)
		);
		return self::object( $properties );
	}

	private static function performance(): array {
		$properties = array();
		foreach ( array( 'memory_current', 'memory_peak', 'memory_limit_bytes', 'memory_available', 'php_max_execution_time', 'db_queries_count', 'object_cache_hits', 'object_cache_misses' ) as $field ) {
			$properties[ $field ] = self::uint( self::UINT_MAX, true );
		}
		$properties['memory_limit'] = self::text( 32, true );
		foreach ( array( 'memory_usage_percent', 'page_load_time_ms', 'plugin_load_time_ms', 'db_query_time_seconds', 'db_size_mb', 'api_avg_response_ms', 'api_slowest_response_ms', 'api_fastest_response_ms', 'object_cache_hit_rate', 'opcache_hit_rate' ) as $field ) {
			$properties[ $field ] = self::decimal( 0, self::UINT_MAX, true );
		}
		$properties['object_cache_enabled'] = self::bool( true );
		$properties['opcache_enabled']      = self::bool( true );
		return self::object( $properties );
	}

	private static function plugins(): array {
		$row        = self::object(
			array(
				'slug'    => self::slug( 100 ),
				'name'    => self::text(),
				'version' => self::version( true ),
				'author'  => self::text(),
				'status'  => self::enum( array( 'inactive', 'active', 'network-active', 'mu-plugin' ), 'inactive' ),
			)
		);
		$properties = array();
		foreach ( array( 'total_plugins', 'active_plugins', 'inactive_plugins', 'mu_plugins', 'premium_plugins', 'cf7_addons', 'mailchimp_plugins', 'security_plugins', 'cache_plugins', 'seo_plugins' ) as $field ) {
			$properties[ $field ] = self::uint();
		}
		foreach ( array( 'has_woocommerce', 'has_elementor', 'has_jetpack', 'has_wordfence', 'has_yoast_seo' ) as $field ) {
			$properties[ $field ] = self::bool();
		}
		$properties['plugin_list'] = self::accounted_list( $row, 500, 'slug' );
		return self::object( $properties );
	}

	private static function competitors(): array {
		$keys       = array( 'mc4wp', 'mc4wp_premium', 'mailchimp_woo', 'crm_perks', 'easy_forms', 'jetrail', 'cf7_mailchimp_ext', 'newsletter', 'mailpoet', 'fluent_forms', 'wpforms', 'gravity_forms', 'ninja_forms', 'formidable', 'hubspot', 'elementor_pro' );
		$properties = array(
			'has_competitors'       => self::bool(),
			'competitors_installed' => self::uint( 16 ),
			'competitors_active'    => self::uint( 16 ),
			'churn_risk'            => self::enum( array( 'none', 'medium', 'high' ), 'none' ),
			'installed_list'        => self::list_of( self::enum( $keys ), 16 ),
			'active_list'           => self::list_of( self::enum( $keys ), 16 ),
		);
		foreach ( $keys as $key ) {
			$properties[ $key . '_installed' ] = self::bool();
			$properties[ $key . '_active' ]    = self::bool();
		}
		return self::object( $properties );
	}

	private static function server(): array {
		$properties = array();
		foreach ( array( 'load_average_1min', 'load_average_5min', 'load_average_15min', 'disk_usage_percent', 'disk_total_gb' ) as $field ) {
			$properties[ $field ] = self::decimal( 0, self::UINT_MAX, true );
		}
		$properties['server_ip_sha256']       = self::sha256();
		$properties['server_hostname_sha256'] = self::sha256();
		$properties['server_os']              = self::text();
		$properties['server_architecture']    = self::text();
		return self::object( $properties );
	}

	private static function wordpress(): array {
		$properties = array();
		foreach ( array( 'posts_published', 'posts_draft', 'pages_published', 'media_items', 'comments_pending', 'comments_spam', 'users_total', 'users_administrators', 'users_editors', 'users_authors', 'users_subscribers', 'categories_count', 'tags_count', 'revisions_count' ) as $field ) {
			$properties[ $field ] = self::uint();
		}
		$properties['auto_updates_enabled'] = self::bool();
		return self::object( $properties );
	}

	private static function legacy_lifecycle(): array {
		return self::object(
			array(
				'event'         => self::enum( array( 'activated', 'deactivated', 'upgraded', 'heartbeat' ), 'heartbeat' ),
				'install_id'    => self::text( 32 ),
				'version'       => self::version(),
				'site_url'      => self::url(),
				'timestamp'     => self::uint(),
				'wp_version'    => self::version(),
				'php'           => self::version(),
				'mysql_version' => self::version( true ),
				'software'      => self::object( array( 'server' => self::text() ) ),
			)
		);
	}

	private static function opportunities(): array {
		return self::object(
			array(
				'environment_type'   => self::enum( array( 'production', 'staging', 'development', 'local', 'unknown' ), 'unknown' ),
				'setup_stage'        => self::enum( array( 'installed', 'connected', 'audience_selected', 'fields_mapped', 'first_send', 'first_success' ), 'installed' ),
				'setup_percent'      => array(
					'type'    => 'uint',
					'max'     => 100,
					'allowed' => array( 0, 20, 40, 60, 80, 100 ),
				),
				'connector_conflict' => self::bool(),
				'failure_reason'     => self::object(
					array(
						'code'   => self::enum( array( 'revoked_credential', 'deleted_destination', 'merge_mismatch', 'required_field', 'invalid_value', 'rate_limit', 'plan_limit', 'permission', 'duplicate', 'timeout', 'dns', 'tls', 'http_4xx', 'http_5xx', 'configuration', 'validation', 'remote_rejected', 'unknown' ), 'unknown' ),
						'sample' => self::text( 50 ),
					)
				),
				'update_available'   => self::bool(),
				'versions_behind'    => self::uint(),
				'subs_7d'            => self::uint(),
				'subs_30d'           => self::uint(),
				'subs_median'        => self::decimal( 0, self::UINT_MAX ),
				'rest_api_ok'        => self::bool( true ),
				'install_source'     => self::enum( array( 'wp_org_search', 'referral', 'migration', 'manual_upload', 'network', 'unknown' ), 'unknown' ),
				'host_class'         => self::enum( array( 'wpcom', 'wpcom_vip', 'pressable', 'wp_engine', 'other', 'unknown' ), 'unknown' ),
				'cache_dropins'      => self::accounted_list( self::slug( 100 ), 8 ),
			)
		);
	}

	private static function object( array $properties ): array {
		return array(
			'type'       => 'object',
			'properties' => $properties,
		);
	}

	private static function bool( bool $nullable = false ): array {
		return self::scalar( 'bool', $nullable );
	}

	private static function uint( int $maximum = self::UINT_MAX, bool $nullable = false ): array {
		return self::scalar( 'uint', $nullable, array( 'max' => $maximum ) );
	}

	private static function int( int $minimum, int $maximum, bool $nullable = false ): array {
		return self::scalar(
			'int',
			$nullable,
			array(
				'min' => $minimum,
				'max' => $maximum,
			)
		);
	}

	private static function decimal( $minimum, $maximum, bool $nullable = false ): array {
		return self::scalar(
			'decimal',
			$nullable,
			array(
				'min'   => $minimum,
				'max'   => $maximum,
				'scale' => 4,
			)
		);
	}

	private static function enum( array $allowed, string $fallback = '' ): array {
		return array(
			'type'     => 'enum',
			'allowed'  => $allowed,
			'fallback' => $fallback,
		);
	}

	private static function slug( int $maximum = 48 ): array {
		return array(
			'type'       => 'slug',
			'max_length' => $maximum,
		);
	}

	private static function version( bool $nullable = false ): array {
		return self::scalar( 'version', $nullable, array( 'max_length' => 64 ) );
	}

	private static function text( int $maximum = 191, bool $nullable = false ): array {
		return self::scalar( 'text', $nullable, array( 'max_length' => $maximum ) );
	}

	private static function url(): array {
		return array(
			'type'       => 'url',
			'max_length' => 500,
		);
	}

	private static function sha256( bool $nullable = false ): array {
		return self::scalar( 'sha256', $nullable );
	}

	private static function list_of( array $items, int $maximum, string $sort_key = '' ): array {
		$schema = array(
			'type'      => 'list',
			'items'     => $items,
			'max_items' => $maximum,
		);
		if ( '' !== $sort_key ) {
			$schema['sort_key'] = $sort_key;
		}
		return $schema;
	}

	private static function accounted_list( array $items, int $maximum, string $sort_key = '' ): array {
		$schema                    = self::list_of( $items, $maximum, $sort_key );
		$schema['with_accounting'] = true;
		return $schema;
	}

	private static function map_of( array $values, int $maximum, int $key_length ): array {
		return array(
			'type'           => 'map',
			'values'         => $values,
			'max_items'      => $maximum,
			'max_key_length' => $key_length,
		);
	}

	private static function scalar( string $type, bool $nullable, array $extra = array() ): array {
		$schema = array_merge( array( 'type' => $type ), $extra );
		if ( $nullable ) {
			$schema['nullable'] = true;
		}
		return $schema;
	}
}
