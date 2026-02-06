<?php
/**
 * Performance data collector.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

namespace Cmatic\Metrics\Core\Collectors;

use Cmatic_Options_Repository;

defined( 'ABSPATH' ) || exit;

class Performance_Collector {
	public static function collect(): array {
		$memory_current       = memory_get_usage( true );
		$memory_peak          = memory_get_peak_usage( true );
		$memory_limit         = ini_get( 'memory_limit' );
		$memory_limit_bytes   = self::convert_to_bytes( $memory_limit );
		$memory_usage_percent = $memory_limit_bytes > 0 ? round( ( $memory_peak / $memory_limit_bytes ) * 100, 2 ) : 0;

		$db_queries = get_num_queries();
		$db_time    = timer_stop( 0, 3 );

		$page_load_time = isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ? ( microtime( true ) - floatval( $_SERVER['REQUEST_TIME_FLOAT'] ) ) * 1000 : 0;

		list( $object_cache_hits, $object_cache_misses ) = self::get_object_cache_stats();

		$plugin_load_time = (float) Cmatic_Options_Repository::get_option( 'performance.plugin_load_time', 0 );
		$api_avg_response = (float) Cmatic_Options_Repository::get_option( 'performance.api_avg_response', 0 );

		$data = array(
			'memory_current'          => $memory_current,
			'memory_peak'             => $memory_peak,
			'memory_limit'            => $memory_limit,
			'memory_limit_bytes'      => $memory_limit_bytes,
			'memory_usage_percent'    => $memory_usage_percent,
			'memory_available'        => max( 0, $memory_limit_bytes - $memory_peak ),
			'php_max_execution_time'  => (int) ini_get( 'max_execution_time' ),
			'page_load_time_ms'       => round( $page_load_time, 2 ),
			'plugin_load_time_ms'     => round( $plugin_load_time, 2 ),
			'db_queries_count'        => $db_queries,
			'db_query_time_seconds'   => (float) $db_time,
			'db_size_mb'              => self::get_database_size(),
			'api_avg_response_ms'     => round( $api_avg_response, 2 ),
			'api_slowest_response_ms' => (int) Cmatic_Options_Repository::get_option( 'performance.api_slowest', 0 ),
			'api_fastest_response_ms' => (int) Cmatic_Options_Repository::get_option( 'performance.api_fastest', 0 ),
			'object_cache_enabled'    => wp_using_ext_object_cache(),
			'object_cache_hits'       => $object_cache_hits,
			'object_cache_misses'     => $object_cache_misses,
			'object_cache_hit_rate'   => ( $object_cache_hits + $object_cache_misses ) > 0 ? round( ( $object_cache_hits / ( $object_cache_hits + $object_cache_misses ) ) * 100, 2 ) : 0,
			'opcache_enabled'         => self::is_opcache_enabled(),
			'opcache_hit_rate'        => self::get_opcache_hit_rate(),
		);

		return array_filter( $data, fn( $v ) => $v !== 0 && $v !== 0.0 && $v !== false && $v !== null && $v !== '' );
	}

	private static function is_opcache_enabled(): bool {
		if ( ! function_exists( 'opcache_get_status' ) ) {
			return false;
		}
		$status = @opcache_get_status();
		return false !== $status && is_array( $status );
	}

	public static function convert_to_bytes( $value ): int {
		$value = trim( $value );
		if ( empty( $value ) ) {
			return 0;
		}
		$last  = strtolower( $value[ strlen( $value ) - 1 ] );
		$value = (int) $value;

		switch ( $last ) {
			case 'g':
				$value *= 1024;
				// Fall through.
			case 'm':
				$value *= 1024;
				// Fall through.
			case 'k':
				$value *= 1024;
		}

		return $value;
	}

	private static function get_database_size(): float {
		global $wpdb;

		$size = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT SUM(data_length + index_length) / 1024 / 1024
				FROM information_schema.TABLES
				WHERE table_schema = %s',
				DB_NAME
			)
		);

		return round( (float) $size, 2 );
	}

	private static function get_opcache_hit_rate(): float {
		if ( ! function_exists( 'opcache_get_status' ) ) {
			return 0;
		}

		$status = @opcache_get_status();
		if ( false === $status || ! is_array( $status ) || ! isset( $status['opcache_statistics'] ) ) {
			return 0;
		}

		$stats  = $status['opcache_statistics'];
		$hits   = isset( $stats['hits'] ) ? (int) $stats['hits'] : 0;
		$misses = isset( $stats['misses'] ) ? (int) $stats['misses'] : 0;

		if ( ( $hits + $misses ) === 0 ) {
			return 0;
		}

		return round( ( $hits / ( $hits + $misses ) ) * 100, 2 );
	}

	private static function get_object_cache_stats(): array {
		$object_cache_hits   = 0;
		$object_cache_misses = 0;

		if ( function_exists( 'wp_cache_get_stats' ) ) {
			$cache_stats         = wp_cache_get_stats();
			$object_cache_hits   = isset( $cache_stats['hits'] ) ? $cache_stats['hits'] : 0;
			$object_cache_misses = isset( $cache_stats['misses'] ) ? $cache_stats['misses'] : 0;
		}

		return array( $object_cache_hits, $object_cache_misses );
	}
}
