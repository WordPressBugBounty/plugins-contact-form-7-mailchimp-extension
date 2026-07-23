<?php
/**
 * ChimpMatic Lite inventory Signls collector.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

final class Cmatic_Lite_Signls_Inventory_Collector {

	private const MAX_PLUGINS = 500;

	private const COMPETITORS = array(
		'mc4wp'             => 'mailchimp-for-wp/mailchimp-for-wp.php',
		'mc4wp_premium'     => 'mc4wp-premium/mc4wp-premium.php',
		'mailchimp_woo'     => 'mailchimp-for-woocommerce/mailchimp-woocommerce.php',
		'crm_perks'         => 'cf7-mailchimp/cf7-mailchimp.php',
		'easy_forms'        => 'jetwp-easy-mailchimp/jetwp-easy-mailchimp.php',
		'jetrail'           => 'jetrail-cf7-mailchimp/jetrail-cf7-mailchimp.php',
		'cf7_mailchimp_ext' => 'contact-form-7-mailchimp-extension-jetrail/cf7-mailchimp-ext.php',
		'newsletter'        => 'newsletter/plugin.php',
		'mailpoet'          => 'mailpoet/mailpoet.php',
		'fluent_forms'      => 'fluentform/fluentform.php',
		'wpforms'           => 'wpforms-lite/wpforms.php',
		'gravity_forms'     => 'gravityforms/gravityforms.php',
		'ninja_forms'       => 'ninja-forms/ninja-forms.php',
		'formidable'        => 'formidable/formidable.php',
		'hubspot'           => 'leadin/leadin.php',
		'elementor_pro'     => 'elementor-pro/elementor-pro.php',
	);

	public function collect(): array {
		$this->load_plugin_api();
		$plugins        = get_plugins();
		$mu_plugins     = get_mu_plugins();
		$active_plugins = get_option( 'active_plugins', array() );
		$active         = array();
		foreach ( is_array( $active_plugins ) ? $active_plugins : array() as $plugin_file ) {
			if ( is_scalar( $plugin_file ) ) {
				$active[ plugin_basename( (string) $plugin_file ) ] = true;
			}
		}
		$network_active = is_multisite() ? (array) get_site_option( 'active_sitewide_plugins', array() ) : array();
		$inventory      = $this->inventory_rows( $plugins, $mu_plugins, $active, $network_active );
		$competitors    = $this->competitors( $plugins, $active, $network_active );

		return array(
			'plugins'       => $this->plugin_summary( $plugins, $mu_plugins, $inventory ),
			'competitors'   => $competitors,
			'opportunities' => array( 'connector_conflict' => $competitors['competitors_active'] > 0 ),
		);
	}

	public function theme_facts(): array {
		$theme  = wp_get_theme();
		$parent = $theme->parent();
		return array(
			'theme'          => (string) $theme->get( 'Name' ),
			'theme_version'  => (string) $theme->get( 'Version' ),
			'theme_author'   => wp_strip_all_tags( (string) $theme->get( 'Author' ) ),
			'parent_theme'   => $parent ? (string) $parent->get( 'Name' ) : '',
			'is_child_theme' => (bool) $parent,
		);
	}

	private function inventory_rows( array $plugins, array $mu_plugins, array $active, array $network_active ): array {
		ksort( $plugins, SORT_STRING );
		ksort( $mu_plugins, SORT_STRING );
		$rows = array();
		foreach ( $plugins as $basename => $data ) {
			$basename = plugin_basename( (string) $basename );
			$status   = isset( $network_active[ $basename ] ) ? 'network-active' : ( isset( $active[ $basename ] ) ? 'active' : 'inactive' );
			$rows[]   = $this->plugin_row( $basename, $data, $status );
		}
		foreach ( $mu_plugins as $basename => $data ) {
			$rows[] = $this->plugin_row( plugin_basename( (string) $basename ), $data, 'mu-plugin' );
		}
		return $rows;
	}

	private function plugin_row( string $basename, array $data, string $status ): array {
		$directory = dirname( $basename );
		$slug      = \Signls\Sdk\V1\Sanitizer::slug( sanitize_key( '.' === $directory ? basename( $basename, '.php' ) : $directory ), 100 );
		$slug      = '' !== $slug ? $slug : 'unknown-' . substr( hash( 'sha256', $basename ), 0, 12 );
		$author    = isset( $data['AuthorName'] ) && '' !== $data['AuthorName'] ? $data['AuthorName'] : ( isset( $data['Author'] ) ? $data['Author'] : '' );
		return array(
			'slug'    => $slug,
			'name'    => isset( $data['Name'] ) ? wp_strip_all_tags( (string) $data['Name'] ) : '',
			'version' => isset( $data['Version'] ) ? (string) $data['Version'] : '',
			'author'  => wp_strip_all_tags( (string) $author ),
			'status'  => $status,
		);
	}

	private function plugin_summary( array $plugins, array $mu_plugins, array $inventory ): array {
		$stats        = array_fill_keys( array( 'premium_plugins', 'cf7_addons', 'mailchimp_plugins', 'security_plugins', 'cache_plugins', 'seo_plugins' ), 0 );
		$active_count = 0;
		foreach ( $inventory as $row ) {
			if ( in_array( $row['status'], array( 'active', 'network-active' ), true ) ) {
				++$active_count;
			}
			if ( 'mu-plugin' === $row['status'] ) {
				continue;
			}
			$name                        = strtolower( $row['name'] );
			$stats['premium_plugins']   += false !== strpos( $name, 'pro' ) || false !== strpos( $name, 'premium' ) ? 1 : 0;
			$stats['cf7_addons']        += false !== strpos( $name, 'contact form 7' ) ? 1 : 0;
			$stats['mailchimp_plugins'] += false !== strpos( $name, 'mailchimp' ) ? 1 : 0;
			$stats['security_plugins']  += false !== strpos( $name, 'security' ) || false !== strpos( $name, 'wordfence' ) || false !== strpos( $name, 'sucuri' ) ? 1 : 0;
			$stats['cache_plugins']     += false !== strpos( $name, 'cache' ) || false !== strpos( $name, 'wp rocket' ) || false !== strpos( $name, 'w3 total cache' ) ? 1 : 0;
			$stats['seo_plugins']       += false !== strpos( $name, 'seo' ) || false !== strpos( $name, 'yoast' ) ? 1 : 0;
		}
		$total_rows = count( $inventory );
		return array_merge(
			array(
				'total_plugins'    => count( $plugins ),
				'active_plugins'   => $active_count,
				'inactive_plugins' => max( 0, count( $plugins ) - $active_count ),
				'mu_plugins'       => count( $mu_plugins ),
				'has_woocommerce'  => $this->has_active_plugin( $inventory, 'woocommerce' ),
				'has_elementor'    => $this->has_active_plugin( $inventory, 'elementor' ),
				'has_jetpack'      => $this->has_active_plugin( $inventory, 'jetpack' ),
				'has_wordfence'    => $this->has_active_plugin( $inventory, 'wordfence' ),
				'has_yoast_seo'    => $this->has_active_plugin( $inventory, 'wordpress-seo' ),
				'plugin_list'      => array(
					'items'          => array_slice( $inventory, 0, self::MAX_PLUGINS ),
					'reported_total' => $total_rows,
					'truncated'      => $total_rows > self::MAX_PLUGINS,
				),
			),
			$stats
		);
	}

	private function has_active_plugin( array $inventory, string $slug ): bool {
		foreach ( $inventory as $row ) {
			if ( $slug === $row['slug'] && in_array( $row['status'], array( 'active', 'network-active' ), true ) ) {
				return true;
			}
		}
		return false;
	}

	private function competitors( array $plugins, array $active, array $network_active ): array {
		$result = array(
			'has_competitors'       => false,
			'competitors_installed' => 0,
			'competitors_active'    => 0,
			'churn_risk'            => 'none',
			'installed_list'        => array(),
			'active_list'           => array(),
		);
		foreach ( self::COMPETITORS as $key => $basename ) {
			$installed                     = isset( $plugins[ $basename ] );
			$is_active                     = isset( $active[ $basename ] ) || isset( $network_active[ $basename ] );
			$result[ $key . '_installed' ] = $installed;
			$result[ $key . '_active' ]    = $is_active;
			if ( $installed ) {
				++$result['competitors_installed'];
				$result['installed_list'][] = $key;
			}
			if ( $is_active ) {
				++$result['competitors_active'];
				$result['active_list'][] = $key;
			}
		}
		$result['has_competitors'] = $result['competitors_installed'] > 0;
		$result['churn_risk']      = $result['competitors_active'] > 0 ? 'high' : ( $result['competitors_installed'] > 0 ? 'medium' : 'none' );
		return $result;
	}

	private function load_plugin_api(): void {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
	}
}
