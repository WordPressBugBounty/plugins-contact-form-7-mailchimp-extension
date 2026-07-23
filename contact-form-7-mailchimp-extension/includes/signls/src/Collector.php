<?php
/**
 * Closed product snapshot collector.
 *
 * @package signls-sdk
 * @license GPL-3.0-or-later
 */

namespace Signls\Sdk\V1;

defined( 'ABSPATH' ) || exit;

final class Collector {

	private const FAILURE_CLASSES = array(
		'transport_dns',
		'transport_tls',
		'transport_connect',
		'transport_timeout',
		'http_4xx',
		'http_429',
		'http_5xx',
		'auth',
		'configuration',
		'validation',
		'remote_rejected',
		'unknown',
	);

	public static function collect( ProductAdapterInterface $adapter ): array {
		$contract = $adapter->contract();
		$source   = $adapter->snapshot();
		$allowed  = self::allowlists( $contract );

		$payload        = array(
			'versions'         => self::versions( isset( $source['versions'] ) ? $source['versions'] : array(), $adapter ),
			'is_multisite'     => Sanitizer::bool( isset( $source['is_multisite'] ) ? $source['is_multisite'] : false ),
			'configured_units' => Sanitizer::counter( isset( $source['configured_units'] ) ? $source['configured_units'] : 0 ),
			'active_units'     => Sanitizer::counter( isset( $source['active_units'] ) ? $source['active_units'] : 0 ),
			'integrations'     => self::integrations( isset( $source['integrations'] ) ? $source['integrations'] : array(), $allowed['integrations'] ),
			'features'         => self::features( isset( $source['features'] ) ? $source['features'] : array(), $allowed['features'] ),
			'operation_health' => self::operations( isset( $source['operation_health'] ) ? $source['operation_health'] : array(), $allowed ),
			'companions'       => self::companions( isset( $source['companions'] ) ? $source['companions'] : array(), $allowed['companions'] ),
		);
		$schema_version = isset( $contract['snapshot_schema_version'] ) ? (int) $contract['snapshot_schema_version'] : 1;
		if ( 2 === $schema_version ) {
			$observation_source      = isset( $source['observations'] ) && is_array( $source['observations'] ) ? $source['observations'] : array();
			$observation_schema      = isset( $contract['observation_schema'] ) && is_array( $contract['observation_schema'] ) ? $contract['observation_schema'] : array();
			$payload['observations'] = ObservationSanitizer::sanitize( $observation_source, $observation_schema );
		}

		$payload['active_units'] = min( $payload['active_units'], $payload['configured_units'] );
		return $payload;
	}

	private static function allowlists( array $contract ): array {
		$result = array();
		foreach ( array( 'integrations', 'features', 'operations', 'companions' ) as $key ) {
			$result[ $key ] = array();
			$values         = isset( $contract[ $key ] ) && is_array( $contract[ $key ] ) ? $contract[ $key ] : array();
			foreach ( $values as $value ) {
				$slug = Sanitizer::slug( $value );
				if ( '' !== $slug ) {
					$result[ $key ][ $slug ] = true;
				}
			}
		}
		return $result;
	}

	private static function versions( $source, ProductAdapterInterface $adapter ): array {
		$source = is_array( $source ) ? $source : array();
		return array(
			'product'   => Sanitizer::version( $adapter->product_version() ),
			'wordpress' => Sanitizer::version( isset( $source['wordpress'] ) ? $source['wordpress'] : get_bloginfo( 'version' ) ),
			'php'       => Sanitizer::version( PHP_VERSION ),
			'cf7'       => Sanitizer::version( isset( $source['cf7'] ) ? $source['cf7'] : 'unknown' ),
		);
	}

	private static function integrations( $rows, array $allowlist ): array {
		$result = array();
		$rows   = is_array( $rows ) ? $rows : array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$slug = Sanitizer::slug( isset( $row['slug'] ) ? $row['slug'] : '' );
			if ( '' === $slug || ! isset( $allowlist[ $slug ] ) || isset( $result[ $slug ] ) ) {
				continue;
			}
			$clean = array( 'slug' => $slug );
			foreach ( array( 'configured_units', 'credential_units', 'oauth_units', 'api_key_units', 'destination_units', 'mapping_count', 'attempts', 'successes', 'failures' ) as $key ) {
				$clean[ $key ] = Sanitizer::counter( isset( $row[ $key ] ) ? $row[ $key ] : 0 );
			}
			$result[ $slug ] = $clean;
		}
		return array_values( $result );
	}

	private static function features( $rows, array $allowlist ): array {
		$result = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$slug = Sanitizer::slug( isset( $row['slug'] ) ? $row['slug'] : '' );
			if ( '' === $slug || ! isset( $allowlist[ $slug ] ) || isset( $result[ $slug ] ) ) {
				continue;
			}
			$result[ $slug ] = array(
				'slug'             => $slug,
				'configured_units' => Sanitizer::counter( isset( $row['configured_units'] ) ? $row['configured_units'] : 0 ),
				'source'           => Sanitizer::slug( isset( $row['source'] ) ? $row['source'] : '', 48, 'unknown' ),
			);
		}
		return array_values( $result );
	}

	private static function operations( $rows, array $allowlists ): array {
		$result = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$integration = Sanitizer::slug( isset( $row['integration'] ) ? $row['integration'] : '' );
			$operation   = Sanitizer::slug( isset( $row['operation'] ) ? $row['operation'] : '' );
			$key         = $integration . '|' . $operation;
			if ( ! isset( $allowlists['integrations'][ $integration ], $allowlists['operations'][ $operation ] ) || isset( $result[ $key ] ) ) {
				continue;
			}
			$classes = array();
			$source  = isset( $row['failure_classes'] ) && is_array( $row['failure_classes'] ) ? $row['failure_classes'] : array();
			foreach ( self::FAILURE_CLASSES as $class ) {
				$classes[ $class ] = Sanitizer::counter( isset( $source[ $class ] ) ? $source[ $class ] : 0 );
			}
			$result[ $key ] = array(
				'integration'      => $integration,
				'operation'        => $operation,
				'last_success_age' => Sanitizer::age( isset( $row['last_success_age'] ) ? $row['last_success_age'] : null ),
				'last_failure_age' => Sanitizer::age( isset( $row['last_failure_age'] ) ? $row['last_failure_age'] : null ),
				'failure_classes'  => $classes,
			);
		}
		return array_values( $result );
	}

	private static function companions( $rows, array $allowlist ): array {
		$result = array();
		$states = array( 'active', 'expired', 'inactive', 'legacy_activated', 'not_present', 'unknown' );
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$slug  = Sanitizer::slug( isset( $row['slug'] ) ? $row['slug'] : '' );
			$state = Sanitizer::slug( isset( $row['license_state'] ) ? $row['license_state'] : '', 48, 'unknown' );
			if ( '' === $slug || ! isset( $allowlist[ $slug ] ) || isset( $result[ $slug ] ) ) {
				continue;
			}
			$result[ $slug ] = array(
				'slug'                   => $slug,
				'installed'              => Sanitizer::bool( isset( $row['installed'] ) ? $row['installed'] : false ),
				'active'                 => Sanitizer::bool( isset( $row['active'] ) ? $row['active'] : false ),
				'version'                => Sanitizer::version( isset( $row['version'] ) ? $row['version'] : 'unknown' ),
				'license_state'          => in_array( $state, $states, true ) ? $state : 'unknown',
				'source'                 => Sanitizer::slug( isset( $row['source'] ) ? $row['source'] : '', 48, 'unknown' ),
				'observation_started_at' => Sanitizer::counter( isset( $row['observation_started_at'] ) ? $row['observation_started_at'] : time() ),
			);
		}
		return array_values( $result );
	}
}
