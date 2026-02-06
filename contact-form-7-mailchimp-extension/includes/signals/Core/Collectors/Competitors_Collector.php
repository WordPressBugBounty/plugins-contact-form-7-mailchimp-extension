<?php
/**
 * Competitors data collector.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

namespace Cmatic\Metrics\Core\Collectors;

defined( 'ABSPATH' ) || exit;

class Competitors_Collector {
	private const COMPETITORS = array(
		'mc4wp'              => array(
			'slug' => 'mailchimp-for-wp/mailchimp-for-wp.php',
			'name' => 'MC4WP: Mailchimp for WordPress',
		),
		'mc4wp_premium'      => array(
			'slug' => 'mc4wp-premium/mc4wp-premium.php',
			'name' => 'MC4WP Premium',
		),
		'mailchimp_woo'      => array(
			'slug' => 'mailchimp-for-woocommerce/mailchimp-woocommerce.php',
			'name' => 'Mailchimp for WooCommerce',
		),
		'crm_perks'          => array(
			'slug' => 'cf7-mailchimp/cf7-mailchimp.php',
			'name' => 'CRM Perks CF7 Mailchimp',
		),
		'easy_forms'         => array(
			'slug' => 'jetwp-easy-mailchimp/jetwp-easy-mailchimp.php',
			'name' => 'Easy Forms for Mailchimp',
		),
		'jetrail'            => array(
			'slug' => 'jetrail-cf7-mailchimp/jetrail-cf7-mailchimp.php',
			'name' => 'Jetrail CF7 Mailchimp',
		),
		'cf7_mailchimp_ext'  => array(
			'slug' => 'contact-form-7-mailchimp-extension-jetrail/cf7-mailchimp-ext.php',
			'name' => 'CF7 Mailchimp Extension Jetrail',
		),
		'newsletter'         => array(
			'slug' => 'newsletter/plugin.php',
			'name' => 'Newsletter',
		),
		'mailpoet'           => array(
			'slug' => 'mailpoet/mailpoet.php',
			'name' => 'MailPoet',
		),
		'fluent_forms'       => array(
			'slug' => 'fluentform/fluentform.php',
			'name' => 'Fluent Forms',
		),
		'wpforms'            => array(
			'slug' => 'wpforms-lite/wpforms.php',
			'name' => 'WPForms',
		),
		'gravity_forms'      => array(
			'slug' => 'gravityforms/gravityforms.php',
			'name' => 'Gravity Forms',
		),
		'ninja_forms'        => array(
			'slug' => 'ninja-forms/ninja-forms.php',
			'name' => 'Ninja Forms',
		),
		'formidable'         => array(
			'slug' => 'formidable/formidable.php',
			'name' => 'Formidable Forms',
		),
		'hubspot'            => array(
			'slug' => 'leadin/leadin.php',
			'name' => 'HubSpot',
		),
		'elementor_pro'      => array(
			'slug' => 'elementor-pro/elementor-pro.php',
			'name' => 'Elementor Pro',
		),
	);

	public static function collect(): array {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins    = get_plugins();
		$competitors    = self::check_competitors( $all_plugins );
		$summary        = self::build_summary( $competitors );
		$individual     = self::build_individual_status( $competitors );

		return array_merge( $summary, $individual );
	}

	private static function check_competitors( array $all_plugins ): array {
		$competitors = array();

		foreach ( self::COMPETITORS as $key => $competitor ) {
			$competitors[ $key ] = array(
				'slug'      => $competitor['slug'],
				'name'      => $competitor['name'],
				'installed' => isset( $all_plugins[ $competitor['slug'] ] ),
				'active'    => is_plugin_active( $competitor['slug'] ),
			);
		}

		return $competitors;
	}

	private static function build_summary( array $competitors ): array {
		$installed_count = 0;
		$active_count    = 0;
		$installed_list  = array();
		$active_list     = array();

		foreach ( $competitors as $key => $competitor ) {
			if ( $competitor['installed'] ) {
				++$installed_count;
				$installed_list[] = $key;
			}
			if ( $competitor['active'] ) {
				++$active_count;
				$active_list[] = $key;
			}
		}

		$risk_level = 'none';
		if ( $active_count > 0 ) {
			$risk_level = 'high';
		} elseif ( $installed_count > 0 ) {
			$risk_level = 'medium';
		}

		return array(
			'has_competitors'       => $installed_count > 0,
			'competitors_installed' => $installed_count,
			'competitors_active'    => $active_count,
			'churn_risk'            => $risk_level,
			'installed_list'        => $installed_list,
			'active_list'           => $active_list,
		);
	}

	private static function build_individual_status( array $competitors ): array {
		$status = array();

		foreach ( $competitors as $key => $competitor ) {
			$status[ $key . '_installed' ] = $competitor['installed'];
			$status[ $key . '_active' ]    = $competitor['active'];
		}

		return $status;
	}
}
