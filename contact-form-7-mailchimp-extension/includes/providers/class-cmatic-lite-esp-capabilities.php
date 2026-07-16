<?php
/**
 * Versioned provider capability contract for compatible add-ons.
 *
 * @package contact-form-7-mailchimp-extension
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing -- Typed signatures follow the existing Lite provider convention.
final class Cmatic_Lite_Esp_Capabilities {
	private const CONTRACT_VERSION = 2;
	private const MAX_FIELDS       = 50;

	public static function contract_version(): int {
		return self::CONTRACT_VERSION;
	}

	public static function supported_contract_versions(): array {
		return array( self::CONTRACT_VERSION );
	}

	public static function field_limit( string $provider = '', int $form_id = 0 ): int {
		$limit = (int) apply_filters(
			'cmatic_lite_esp_field_limit',
			CMATIC_LITE_FIELDS,
			sanitize_key( $provider ),
			max( 0, $form_id )
		);

		if ( ! self::pro_entitled() ) {
			return CMATIC_LITE_FIELDS;
		}

		return max( 1, min( self::MAX_FIELDS, $limit ) );
	}

	public static function feature_enabled( string $feature, string $provider = '', int $form_id = 0 ): bool {
		$feature = sanitize_key( $feature );
		$enabled = (bool) apply_filters(
			'cmatic_lite_esp_feature_enabled',
			false,
			$feature,
			sanitize_key( $provider ),
			max( 0, $form_id )
		);

		$paid = array(
			'advanced_consent',
			'mailerlite_routing',
			'mailerlite_status',
			'mailerlite_consent_metadata',
			'mailerlite_create_field',
			'mailerlite_resubscribe',
		);

		return self::pro_entitled() && in_array( $feature, $paid, true ) && $enabled;
	}

	private static function pro_entitled(): bool {
		return class_exists( 'Cmatic_Pro_Esp_Bridge' )
			&& Cmatic_Pro_Esp_Bridge::is_compatible()
			&& function_exists( 'cmatic_is_blessed' )
			&& cmatic_is_blessed();
	}

	private function __construct() {}
}
