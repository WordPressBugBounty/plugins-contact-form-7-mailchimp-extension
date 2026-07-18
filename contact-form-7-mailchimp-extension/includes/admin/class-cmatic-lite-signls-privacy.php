<?php
/**
 * Signls consent migration and suggested privacy-policy content.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

final class Cmatic_Lite_Signls_Privacy {

	public static function init(): void {
		add_action( 'admin_init', array( self::class, 'add_policy_content' ) );
	}

	public static function consent_status(): string {
		$data   = Cmatic_Options_Repository::get_all_options();
		$status = isset( $data['signls']['consent_status'] ) ? (string) $data['signls']['consent_status'] : '';
		if ( in_array( $status, array( 'unset', 'enabled', 'disabled' ), true ) ) {
			return $status;
		}

		$legacy = isset( $data['telemetry']['enabled'] ) ? $data['telemetry']['enabled'] : null;
		$status = false === $legacy || 0 === $legacy || '0' === $legacy ? 'disabled' : 'unset';
		if ( ! isset( $data['signls'] ) || ! is_array( $data['signls'] ) ) {
			$data['signls'] = array();
		}
		$data['signls']['consent_status']  = $status;
		$data['signls']['consent_version'] = 1;
		$data['signls']['migrated_at']     = time();
		Cmatic_Options_Repository::instance()->save( $data );
		return $status;
	}

	public static function add_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$content  = '<p>' . esc_html__( 'ChimpMatic Lite sends pseudonymous aggregate product signals to Signls only when an administrator explicitly enables “Help Us Improve.”', 'chimpmatic-lite' ) . '</p>';
		$content .= '<p>' . esc_html__( 'The data includes a stable pseudonymous plugin install ID, a device ID that rotates when the site origin changes, plugin/WordPress/PHP/Contact Form 7 versions, multisite state, aggregate configured and active form counts, provider authentication preference counts, destination and mapping counts, enabled feature counts, aggregate success/failure classes, and whether the ChimpMatic Pro add-on is installed, active, and licensed. It does not include contact data, credentials, license keys, form IDs, names, destination identifiers, raw errors, IP addresses, user agents, or the site URL.', 'chimpmatic-lite' ) . '</p>';
		$content .= '<p>' . esc_html__( 'Active installations send at most once daily and quiet installations at most once weekly. Delivery receipts are retained for 90 days; daily aggregate summaries are retained for product analysis; the stable install identity is retained to measure long-term product adoption. Disabling sharing stops future delivery and clears the local delivery credential without sending an opt-out event.', 'chimpmatic-lite' ) . '</p>';
		$content .= '<p><a href="https://chimpmatic.com/terms-and-conditions">' . esc_html__( 'Terms and Conditions', 'chimpmatic-lite' ) . '</a> · <a href="https://chimpmatic.com/privacy">' . esc_html__( 'Privacy Policy', 'chimpmatic-lite' ) . '</a></p>';
		wp_add_privacy_policy_content( 'ChimpMatic Lite', wp_kses_post( $content ) );
	}

	private function __construct() {}
}
