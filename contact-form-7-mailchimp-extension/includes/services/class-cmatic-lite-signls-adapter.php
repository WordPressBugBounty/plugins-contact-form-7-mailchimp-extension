<?php
/**
 * ChimpMatic Lite aggregate Signls adapter.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

final class Cmatic_Lite_Signls_Adapter implements \Signls\Sdk\V1\ProductAdapterInterface {

	private const PRODUCT = 'contact-form-7-mailchimp-extension';

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

	public function product_slug(): string {
		return self::PRODUCT;
	}

	public function product_version(): string {
		return defined( 'SPARTAN_MCE_VERSION' ) ? (string) SPARTAN_MCE_VERSION : 'unknown';
	}

	public function signal_sharing_enabled(): bool {
		return 'enabled' === Cmatic_Options_Repository::get_option( 'signls.consent_status', 'unset' );
	}

	public function install_id(): string {
		$install = new Cmatic_Install_Data( Cmatic_Options_Repository::instance() );
		return (string) $install->get_install_id();
	}

	public function contract(): array {
		return array(
			'integrations' => self::INTEGRATIONS,
			'features'     => self::FEATURES,
			'operations'   => array( 'subscribe', 'connect', 'disconnect', 'refresh_schema' ),
			'companions'   => array( 'chimpmatic' ),
		);
	}

	public function snapshot(): array {
		$forms        = $this->form_settings();
		$integrations = $this->empty_integrations();
		$features     = array_fill_keys( self::FEATURES, 0 );
		$configured   = 0;
		$active       = 0;

		foreach ( $forms as $form_id => $config ) {
			$provider = Cmatic_Lite_Esp_Registry::get_selected( $config );
			$settings = $this->provider_settings( $config, $provider );
			if ( ! $this->has_configuration( $settings, $provider ) ) {
				continue;
			}
			++$configured;
			++$integrations[ $provider ]['configured_units'];
			$auth = $this->authentication( $form_id, $provider, $config );
			if ( 'none' !== $auth ) {
				++$integrations[ $provider ]['credential_units'];
				if ( 'oauth' === $auth ) {
					++$integrations[ $provider ]['oauth_units'];
				} elseif ( 'api_key' === $auth ) {
					++$integrations[ $provider ]['api_key_units'];
				}
			}
			$destination = $this->has_destination( $settings );
			if ( $destination ) {
				++$integrations[ $provider ]['destination_units'];
			}
			$mappings                                    = $this->mapping_count( $settings );
			$integrations[ $provider ]['mapping_count'] += $mappings;
			if ( 'none' !== $auth && $destination ) {
				++$active;
			}
			$this->count_features( $features, $settings, $provider, $mappings );
		}

		$this->merge_outcomes( $integrations, $operations );
		$features['debug_logging'] = Cmatic_Options_Repository::get_option( 'debug', false ) ? 1 : 0;
		$features['auto_updates']  = Cmatic_Options_Repository::get_option( 'auto_update', true ) ? 1 : 0;

		return array(
			'versions'         => array(
				'wordpress' => get_bloginfo( 'version' ),
				'cf7'       => defined( 'WPCF7_VERSION' ) ? (string) WPCF7_VERSION : 'unknown',
			),
			'is_multisite'     => is_multisite(),
			'configured_units' => $configured,
			'active_units'     => $active,
			'integrations'     => array_values( $integrations ),
			'features'         => $this->feature_rows( $features ),
			'operation_health' => $operations,
			'companions'       => array( $this->pro_companion() ),
		);
	}

	private function form_settings(): array {
		global $wpdb;
		$like   = $wpdb->esc_like( 'cf7_mch_' ) . '%';
		$rows   = $wpdb->get_results(
			$wpdb->prepare( "SELECT option_name,option_value FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_id ASC", $like ),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One bounded ordered aggregate source read avoids per-form configuration queries.
		$result = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			if ( ! isset( $row['option_name'] ) || 1 !== preg_match( '/^cf7_mch_([0-9]+)$/', (string) $row['option_name'], $matches ) ) {
				continue;
			}
			$value = maybe_unserialize( $row['option_value'] );
			if ( is_array( $value ) ) {
				$result[ (int) $matches[1] ] = $value;
			}
		}
		return $result;
	}

	private function empty_integrations(): array {
		$result = array();
		foreach ( self::INTEGRATIONS as $slug ) {
			$result[ $slug ] = array(
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
		return $result;
	}

	private function provider_settings( array $config, string $provider ): array {
		if ( 'mailchimp' === $provider ) {
			return $config;
		}
		return isset( $config['providers'][ $provider ] ) && is_array( $config['providers'][ $provider ] ) ? $config['providers'][ $provider ] : array();
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
			$manager     = new Cmatic_Lite_Auth_Manager();
			$credentials = $manager->get_credentials( $form_id );
			$present     = null !== $credentials;
			unset( $credentials );
			return $present ? 'oauth' : 'none';
		}
		return Cmatic_Lite_Esp_Credentials::has( $form_id, $provider ) ? 'api_key' : 'none';
	}

	private function has_destination( array $settings ): bool {
		$list = isset( $settings['list'] ) ? $settings['list'] : '';
		return is_array( $list ) ? ! empty( array_filter( $list ) ) : '' !== trim( (string) $list );
	}

	private function mapping_count( array $settings ): int {
		$count = 0;
		foreach ( $settings as $key => $value ) {
			if ( 1 === preg_match( '/^field[0-9]+$/', (string) $key ) && '' !== trim( is_scalar( $value ) ? (string) $value : '' ) ) {
				++$count;
			}
		}
		if ( 0 === $count && isset( $settings['merge_fields'] ) && is_array( $settings['merge_fields'] ) ) {
			$count = count( $settings['merge_fields'] );
		}
		return $count;
	}

	private function count_features( array &$features, array $settings, string $provider, int $mappings ): void {
		$checks = array(
			'advanced_fields'     => $mappings > 4,
			'tags_management'     => ! empty( $settings['tags'] ) || ! empty( $settings['tag'] ),
			'groups_segments'     => ! empty( $settings['groups'] ) || ! empty( $settings['interests'] ),
			'gdpr_fields'         => ! empty( $settings['gdpr'] ) || ! empty( $settings['marketing_permissions'] ),
			'conditional_logic'   => ! empty( $settings['conditions'] ) || ! empty( $settings['conditional'] ),
			'double_optin'        => ! empty( $settings['doubleoptin'] ) || 'double' === ( isset( $settings['subscription_mode'] ) ? $settings['subscription_mode'] : '' ),
			'visitor_geolocation' => ! empty( $settings['visitor_geolocation'] ) || ! empty( $settings['geolocation'] ),
			'unsubscribe'         => ! empty( $settings['addunsubscr'] ),
			'multi_group_routing' => 'mailerlite' === $provider && ( ! empty( $settings['base_groups'] ) || ! empty( $settings['routing_rules'] ) ),
			'status_control'      => ! empty( $settings['status_mode'] ) || ! empty( $settings['status'] ),
			'consent_metadata'    => ! empty( $settings['consent_field'] ) || ! empty( $settings['consent_metadata'] ),
		);
		foreach ( $checks as $slug => $enabled ) {
			if ( $enabled ) {
				++$features[ $slug ];
			}
		}
	}

	private function merge_outcomes( array &$integrations, ?array &$operations ): void {
		$operations = array();
		$rows       = \Signls\Sdk\V1\CounterStore::read_product( self::PRODUCT );
		$grouped    = array();
		foreach ( $rows as $row ) {
			$integration = isset( $row['integration_slug'] ) ? (string) $row['integration_slug'] : '';
			$operation   = isset( $row['operation_slug'] ) ? (string) $row['operation_slug'] : '';
			$counter     = isset( $row['counter_slug'] ) ? (string) $row['counter_slug'] : '';
			$value       = isset( $row['counter_value'] ) ? max( 0, (int) $row['counter_value'] ) : 0;
			if ( ! isset( $integrations[ $integration ] ) || '' === $operation ) {
				continue;
			}
			$grouped[ $integration ][ $operation ][ $counter ] = $value;
		}
		foreach ( $grouped as $integration => $by_operation ) {
			foreach ( $by_operation as $operation => $counters ) {
				$integrations[ $integration ]['attempts']  += isset( $counters['attempts'] ) ? $counters['attempts'] : 0;
				$integrations[ $integration ]['successes'] += isset( $counters['successes'] ) ? $counters['successes'] : 0;
				$integrations[ $integration ]['failures']  += isset( $counters['failures'] ) ? $counters['failures'] : 0;
				$failures                                   = array();
				foreach ( array( 'transport_dns', 'transport_tls', 'transport_connect', 'transport_timeout', 'http_4xx', 'http_429', 'http_5xx', 'auth', 'configuration', 'validation', 'remote_rejected', 'unknown' ) as $class ) {
					$failures[ $class ] = isset( $counters[ 'failure_' . $class ] ) ? $counters[ 'failure_' . $class ] : 0;
				}
				$operations[] = array(
					'integration'      => $integration,
					'operation'        => $operation,
					'last_success_age' => null,
					'last_failure_age' => null,
					'failure_classes'  => $failures,
				);
			}
		}
	}

	private function feature_rows( array $features ): array {
		$result = array();
		foreach ( self::FEATURES as $slug ) {
			$result[] = array(
				'slug'             => $slug,
				'configured_units' => $features[ $slug ],
				'source'           => 'lite_config',
			);
		}
		return $result;
	}

	private function pro_companion(): array {
		$status    = new Cmatic_Pro_Status( Cmatic_Options_Repository::instance() );
		$installed = (bool) $status->is_installed();
		$active    = $installed && (bool) $status->is_activated();
		$version   = $this->pro_version( $installed );
		$state     = ! $installed ? 'not_present' : ( $active ? 'unknown' : 'inactive' );
		if ( $active && class_exists( 'Cmatic_License_Validator' ) && method_exists( 'Cmatic_License_Validator', 'get_license_state' ) ) {
			$reported = (string) Cmatic_License_Validator::get_license_state();
			$state    = in_array( $reported, array( 'active', 'expired' ), true ) ? $reported : 'inactive';
		} elseif ( $active && $status->is_licensed() ) {
			$state = 'legacy_activated';
		}
		return array(
			'slug'                   => 'chimpmatic',
			'installed'              => $installed,
			'active'                 => $active,
			'version'                => $version,
			'license_state'          => $state,
			'source'                 => 'lite_observed',
			'observation_started_at' => (int) ( new Cmatic_Install_Data( Cmatic_Options_Repository::instance() ) )->get_quest(),
		);
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
}
