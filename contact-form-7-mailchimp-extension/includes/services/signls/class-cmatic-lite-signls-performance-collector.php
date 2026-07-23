<?php
/**
 * ChimpMatic Lite performance Signls collector.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

final class Cmatic_Lite_Signls_Performance_Collector {

	public function collect(): array {
		$memory_limit       = (string) ini_get( 'memory_limit' );
		$memory_limit_bytes = $this->bytes( $memory_limit );
		$memory_peak        = memory_get_peak_usage( true );
		$cache              = $this->object_cache();
		$opcache            = $this->opcache();
		$request_started    = isset( $_SERVER['REQUEST_TIME_FLOAT'] ) && is_numeric( $_SERVER['REQUEST_TIME_FLOAT'] ) ? (float) $_SERVER['REQUEST_TIME_FLOAT'] : null;

		return array(
			'performance' => array(
				'memory_current'          => memory_get_usage( true ),
				'memory_peak'             => $memory_peak,
				'memory_limit'            => $memory_limit,
				'memory_limit_bytes'      => $memory_limit_bytes > 0 ? $memory_limit_bytes : null,
				'memory_usage_percent'    => $memory_limit_bytes > 0 ? round( ( $memory_peak / $memory_limit_bytes ) * 100, 2 ) : null,
				'memory_available'        => $memory_limit_bytes > 0 ? max( 0, $memory_limit_bytes - $memory_peak ) : null,
				'php_max_execution_time'  => (int) ini_get( 'max_execution_time' ),
				'page_load_time_ms'       => null !== $request_started ? round( ( microtime( true ) - $request_started ) * 1000, 2 ) : null,
				'plugin_load_time_ms'     => $this->option_decimal( 'performance.plugin_load_time' ),
				'db_queries_count'        => function_exists( 'get_num_queries' ) ? get_num_queries() : null,
				'db_query_time_seconds'   => function_exists( 'timer_stop' ) ? (float) timer_stop( 0, 3 ) : null,
				'db_size_mb'              => $this->database_size(),
				'api_avg_response_ms'     => $this->option_decimal( 'performance.api_avg_response' ),
				'api_slowest_response_ms' => $this->option_decimal( 'performance.api_slowest' ),
				'api_fastest_response_ms' => $this->option_decimal( 'performance.api_fastest' ),
				'object_cache_enabled'    => function_exists( 'wp_using_ext_object_cache' ) ? wp_using_ext_object_cache() : null,
				'object_cache_hits'       => $cache['hits'],
				'object_cache_misses'     => $cache['misses'],
				'object_cache_hit_rate'   => $this->hit_rate( $cache['hits'], $cache['misses'] ),
				'opcache_enabled'         => $opcache['enabled'],
				'opcache_hit_rate'        => $opcache['hit_rate'],
			),
		);
	}

	private function bytes( string $value ): int {
		$value = trim( $value );
		if ( '' === $value || '-1' === $value ) {
			return 0;
		}
		$unit   = strtolower( substr( $value, -1 ) );
		$number = (int) $value;
		if ( 'g' === $unit ) {
			return $number * 1073741824;
		}
		if ( 'm' === $unit ) {
			return $number * 1048576;
		}
		if ( 'k' === $unit ) {
			return $number * 1024;
		}
		return max( 0, $number );
	}

	private function database_size(): ?float {
		global $wpdb;

		if ( ! defined( 'DB_NAME' ) ) {
			return null;
		}
		$value = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT SUM(data_length + index_length) / 1024 / 1024 FROM information_schema.TABLES WHERE table_schema = %s',
				DB_NAME
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One aggregate capacity fact; no table content or query text leaves the site.
		return is_numeric( $value ) ? round( (float) $value, 2 ) : null;
	}

	private function object_cache(): array {
		global $wp_object_cache;

		if ( function_exists( 'wp_cache_get_stats' ) ) {
			$stats = wp_cache_get_stats();
			if ( is_array( $stats ) ) {
				return array(
					'hits'   => isset( $stats['hits'] ) && is_numeric( $stats['hits'] ) ? (int) $stats['hits'] : null,
					'misses' => isset( $stats['misses'] ) && is_numeric( $stats['misses'] ) ? (int) $stats['misses'] : null,
				);
			}
		}
		return array(
			'hits'   => is_object( $wp_object_cache ) && isset( $wp_object_cache->cache_hits ) ? (int) $wp_object_cache->cache_hits : null,
			'misses' => is_object( $wp_object_cache ) && isset( $wp_object_cache->cache_misses ) ? (int) $wp_object_cache->cache_misses : null,
		);
	}

	private function opcache(): array {
		if ( ! function_exists( 'opcache_get_status' ) ) {
			return array(
				'enabled'  => null,
				'hit_rate' => null,
			);
		}
		if ( '' !== trim( (string) ini_get( 'opcache.restrict_api' ) ) ) {
			return array(
				'enabled'  => null,
				'hit_rate' => null,
			);
		}
		$status = opcache_get_status( false );
		if ( false === $status ) {
			return array(
				'enabled'  => false,
				'hit_rate' => null,
			);
		}
		$stats = isset( $status['opcache_statistics'] ) && is_array( $status['opcache_statistics'] ) ? $status['opcache_statistics'] : array();
		$rate  = isset( $stats['opcache_hit_rate'] ) && is_numeric( $stats['opcache_hit_rate'] ) ? round( (float) $stats['opcache_hit_rate'], 2 ) : null;
		return array(
			'enabled'  => true,
			'hit_rate' => $rate,
		);
	}

	private function hit_rate( $hits, $misses ): ?float {
		if ( null === $hits || null === $misses || ( $hits + $misses ) <= 0 ) {
			return null;
		}
		return round( ( $hits / ( $hits + $misses ) ) * 100, 2 );
	}

	private function option_decimal( string $key ): ?float {
		$value = Cmatic_Options_Repository::get_option( $key, null );
		return is_numeric( $value ) ? round( (float) $value, 2 ) : null;
	}
}
