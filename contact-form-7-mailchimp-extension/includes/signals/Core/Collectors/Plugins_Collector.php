<?php
/**
 * Plugins data collector.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

namespace Cmatic\Metrics\Core\Collectors;

defined( 'ABSPATH' ) || exit;

class Plugins_Collector {
	public static function collect(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins    = get_plugins();
		$active_plugins = get_option( 'active_plugins', array() );
		$mu_plugins     = get_mu_plugins();

		$plugin_list    = self::build_plugin_list( $all_plugins, $active_plugins, $mu_plugins );
		$plugin_stats   = self::get_plugin_stats( $all_plugins );
		$known_plugins  = self::get_known_plugins();

		$data = array(
			'total_plugins'     => count( $all_plugins ),
			'active_plugins'    => count( $active_plugins ),
			'inactive_plugins'  => count( $all_plugins ) - count( $active_plugins ),
			'mu_plugins'        => count( $mu_plugins ),
			'premium_plugins'   => $plugin_stats['premium'],
			'cf7_addons'        => $plugin_stats['cf7'],
			'mailchimp_plugins' => $plugin_stats['mailchimp'],
			'security_plugins'  => $plugin_stats['security'],
			'cache_plugins'     => $plugin_stats['cache'],
			'seo_plugins'       => $plugin_stats['seo'],
			'has_woocommerce'   => $known_plugins['woocommerce'],
			'has_elementor'     => $known_plugins['elementor'],
			'has_jetpack'       => $known_plugins['jetpack'],
			'has_wordfence'     => $known_plugins['wordfence'],
			'has_yoast_seo'     => $known_plugins['yoast_seo'],
			'plugin_list'       => $plugin_list,
		);

		return array_filter( $data, fn( $v ) => $v !== 0 && $v !== false && $v !== '' && $v !== array() );
	}

	private static function build_plugin_list( array $all_plugins, array $active_plugins, array $mu_plugins ): array {
		$plugin_list    = array();
		$network_active = is_multisite() ? get_site_option( 'active_sitewide_plugins', array() ) : array();

		foreach ( $all_plugins as $plugin_path => $plugin_data ) {
			$is_active  = in_array( $plugin_path, $active_plugins, true );
			$is_network = isset( $network_active[ $plugin_path ] );

			$status = 'inactive';
			if ( $is_network ) {
				$status = 'network-active';
			} elseif ( $is_active ) {
				$status = 'active';
			}

			$plugin_list[] = array(
				'name'    => $plugin_data['Name'],
				'version' => $plugin_data['Version'],
				'author'  => wp_strip_all_tags( $plugin_data['Author'] ),
				'status'  => $status,
			);
		}

		foreach ( $mu_plugins as $mu_plugin_path => $mu_plugin_data ) {
			$plugin_list[] = array(
				'name'    => $mu_plugin_data['Name'],
				'version' => $mu_plugin_data['Version'],
				'author'  => wp_strip_all_tags( $mu_plugin_data['Author'] ),
				'status'  => 'mu-plugin',
			);
		}

		return $plugin_list;
	}

	private static function get_plugin_stats( array $all_plugins ): array {
		$stats = array(
			'premium'   => 0,
			'cf7'       => 0,
			'mailchimp' => 0,
			'security'  => 0,
			'cache'     => 0,
			'seo'       => 0,
		);

		foreach ( $all_plugins as $plugin_path => $plugin_data ) {
			$name = strtolower( $plugin_data['Name'] );

			if ( strpos( $name, 'pro' ) !== false || strpos( $name, 'premium' ) !== false ) {
				++$stats['premium'];
			}
			if ( strpos( $name, 'contact form 7' ) !== false ) {
				++$stats['cf7'];
			}
			if ( strpos( $name, 'mailchimp' ) !== false ) {
				++$stats['mailchimp'];
			}
			if ( strpos( $name, 'security' ) !== false || strpos( $name, 'wordfence' ) !== false || strpos( $name, 'sucuri' ) !== false ) {
				++$stats['security'];
			}
			if ( strpos( $name, 'cache' ) !== false || strpos( $name, 'wp rocket' ) !== false || strpos( $name, 'w3 total cache' ) !== false ) {
				++$stats['cache'];
			}
			if ( strpos( $name, 'seo' ) !== false || strpos( $name, 'yoast' ) !== false ) {
				++$stats['seo'];
			}
		}

		return $stats;
	}

	private static function get_known_plugins(): array {
		return array(
			'woocommerce' => is_plugin_active( 'woocommerce/woocommerce.php' ),
			'elementor'   => is_plugin_active( 'elementor/elementor.php' ),
			'jetpack'     => is_plugin_active( 'jetpack/jetpack.php' ),
			'wordfence'   => is_plugin_active( 'wordfence/wordfence.php' ),
			'yoast_seo'   => is_plugin_active( 'wordpress-seo/wp-seo.php' ),
		);
	}
}
