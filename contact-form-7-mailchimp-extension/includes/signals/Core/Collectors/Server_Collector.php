<?php
/**
 * Server data collector.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

namespace Cmatic\Metrics\Core\Collectors;

defined( 'ABSPATH' ) || exit;

class Server_Collector {
	public static function collect(): array {
		$server_load = self::get_load_average();

		list( $disk_free, $disk_total ) = self::get_disk_space();
		$disk_used          = $disk_total - $disk_free;
		$disk_usage_percent = $disk_total > 0 ? round( ( $disk_used / $disk_total ) * 100, 2 ) : 0;

		$hostname     = self::get_hostname();
		$architecture = self::get_architecture();

		return array(
			'load_average_1min'   => isset( $server_load[0] ) ? round( (float) $server_load[0], 2 ) : 0,
			'load_average_5min'   => isset( $server_load[1] ) ? round( (float) $server_load[1], 2 ) : 0,
			'load_average_15min'  => isset( $server_load[2] ) ? round( (float) $server_load[2], 2 ) : 0,
			'disk_usage_percent'  => $disk_usage_percent,
			'disk_total_gb'       => $disk_total ? round( $disk_total / 1024 / 1024 / 1024, 2 ) : 0,
			'server_ip'           => hash( 'sha256', isset( $_SERVER['SERVER_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_ADDR'] ) ) : '' ),
			'server_hostname'     => hash( 'sha256', $hostname ),
			'server_os'           => PHP_OS,
			'server_architecture' => $architecture,
		);
	}

	private static function get_load_average(): array {
		if ( ! function_exists( 'sys_getloadavg' ) ) {
			return array( 0, 0, 0 );
		}

		$load = @sys_getloadavg();
		if ( false !== $load && is_array( $load ) ) {
			return $load;
		}

		return array( 0, 0, 0 );
	}

	private static function get_disk_space(): array {
		$disk_free  = 0;
		$disk_total = 0;

		if ( function_exists( 'disk_free_space' ) ) {
			$free = @disk_free_space( ABSPATH );
			if ( false !== $free ) {
				$disk_free = $free;
			}
		}

		if ( function_exists( 'disk_total_space' ) ) {
			$total = @disk_total_space( ABSPATH );
			if ( false !== $total ) {
				$disk_total = $total;
			}
		}

		return array( $disk_free, $disk_total );
	}

	private static function get_hostname(): string {
		if ( ! function_exists( 'gethostname' ) ) {
			return '';
		}

		$name = @gethostname();
		return false !== $name ? $name : '';
	}

	private static function get_architecture(): string {
		if ( ! function_exists( 'php_uname' ) ) {
			return '';
		}

		$arch = @php_uname( 'm' );
		return false !== $arch ? $arch : '';
	}
}
