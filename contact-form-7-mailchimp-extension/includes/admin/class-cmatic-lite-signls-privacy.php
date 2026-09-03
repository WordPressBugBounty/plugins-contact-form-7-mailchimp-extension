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
	public const CONSENT_VERSION = 2;

	private const PRODUCT_SLUG = 'contact-form-7-mailchimp-extension';

	private const DEFAULT_SOURCE = 'network_install_default';

	public static function init(): void {
		add_action( 'admin_init', array( self::class, 'add_policy_content' ) );
	}

	public static function consent_status(): string {
		$data   = Cmatic_Options_Repository::get_all_options();
		$status = isset( $data['signls']['consent_status'] ) ? (string) $data['signls']['consent_status'] : '';
		if ( in_array( $status, array( 'enabled', 'disabled' ), true ) ) {
			if ( (int) ( $data['signls']['consent_version'] ?? 0 ) < self::CONSENT_VERSION ) {
				$data['signls']['consent_version'] = self::CONSENT_VERSION;
				Cmatic_Options_Repository::instance()->save( $data );
			}
			return $status;
		}

		$legacy = isset( $data['telemetry']['enabled'] ) ? $data['telemetry']['enabled'] : null;
		$status = 'unset' === $status ? 'enabled' : ( false === $legacy || 0 === $legacy || '0' === $legacy ? 'disabled' : 'enabled' );
		$source = 'disabled' === $status ? 'legacy_disabled' : self::DEFAULT_SOURCE;
		$now    = time();
		if ( ! isset( $data['signls'] ) || ! is_array( $data['signls'] ) ) {
			$data['signls'] = array();
		}
		$data['signls']['consent_status']          = $status;
		$data['signls']['consent_version']         = self::CONSENT_VERSION;
		$data['signls']['consent_last_changed_at'] = $now;
		$data['signls']['consent_source']          = $source;
		$data['signls']['migrated_at']             = $now;
		if ( 'enabled' === $status ) {
			$data['signls']['consent_first_enabled_at'] = $now;
		}
		Cmatic_Options_Repository::instance()->save( $data );
		return $status;
	}

	public static function sync_sdk_consent(): void {
		if ( 'enabled' !== self::consent_status() || ! class_exists( 'Signls_Sdk_Bridge_1_1_7', false ) ) {
			return;
		}

		$state          = Signls_Sdk_Bridge_1_1_7::state( self::PRODUCT_SLUG );
		$consent_status = $state['consent_status'] ?? 'unset';
		if ( ! is_string( $consent_status ) || 'unset' !== $consent_status ) {
			return;
		}

		Signls_Sdk_Bridge_1_1_7::enable(
			self::PRODUCT_SLUG,
			self::DEFAULT_SOURCE,
			self::CONSENT_VERSION
		);
	}

	public static function add_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$content  = '<p>' . esc_html__( 'While “Help Us Improve” is enabled, ChimpMatic Lite sends a bounded, signed product snapshot to Signls at https://signls.dev/wp-json/chimpmatic/v1/telemetry so we can measure setup completion, compatibility, feature use, reliability, upgrades and long-term adoption. This build preserves its established initial setting; an administrator can turn sharing off or on repeatedly at any time.', 'contact-form-7-mailchimp-extension' ) . '</p>';
		$content .= '<p>' . esc_html__( 'The snapshot includes random pseudonymous site, installation and rotating device identities; the current site URL and one snapshot user agent; WordPress, PHP, database, server, theme, Contact Form 7 and ChimpMatic versions and configuration facts; a maximum of 500 plugin inventory rows with names, versions, authors and active state; 16 fixed competitor indicators; aggregate provider/authentication, destination, mapping, feature, lifecycle, submission, performance and scrubbed failure facts; and bounded Contact Form 7 configuration labels (up to 100 forms, 50 form-detail rows and 30 field/mapping rows per detailed form). Totals and truncation flags report omitted rows.', 'contact-form-7-mailchimp-extension' ) . '</p>';
		$content .= '<p>' . esc_html__( 'The snapshot excludes contact details, submitted form values, credentials, API or license keys, OAuth secrets, cookies, authorization headers, raw provider responses, raw administrator email, commerce records, raw IP addresses, arbitrary settings, request bodies and file contents. Server address and hostname are exported only as SHA-256 hashes; failure samples are scrubbed and limited to 50 characters.', 'contact-form-7-mailchimp-extension' ) . '</p>';
		$content .= '<p>' . esc_html__( 'After a relevant local event is recorded, ChimpMatic queues one coalesced delivery attempt at the end of that WordPress request. Periodic heartbeats remain daily for active installations and weekly for quiet installations; failed attempts use scheduled retry. Delivery receipts are retained for 90 days, current typed facts are replaced by newer observations, and daily aggregates plus pseudonymous adoption/lifecycle identity are retained for long-term product analysis. Turning sharing off immediately clears scheduled delivery, pending payloads and the local delivery credential without sending an opt-out event; turning it on later initializes delivery under the same disclosed contract.', 'contact-form-7-mailchimp-extension' ) . '</p>';
		$content .= '<p><a href="https://github.com/signls-dev/signls/blob/main/reference/wordpress-signls-sdk-v2.md">' . esc_html__( 'Complete public field matrix, bounds and retention', 'contact-form-7-mailchimp-extension' ) . '</a> · <a href="https://chimpmatic.com/terms-and-conditions">' . esc_html__( 'Terms and Conditions', 'contact-form-7-mailchimp-extension' ) . '</a> · <a href="https://chimpmatic.com/privacy">' . esc_html__( 'Privacy Policy', 'contact-form-7-mailchimp-extension' ) . '</a></p>';
		wp_add_privacy_policy_content( 'ChimpMatic Lite', wp_kses_post( $content ) );
	}

	private function __construct() {}
}
