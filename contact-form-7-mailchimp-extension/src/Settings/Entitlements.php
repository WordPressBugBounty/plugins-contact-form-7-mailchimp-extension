<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    hello@chimpmatic.com
 * @copyright 2014-2026 ChimpMatic
 * @license   GPL-3.0+
 */

declare(strict_types=1);

namespace Cmatic\Lite\Settings;

use Cmatic\Lite\Submission\DestinationResolver;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing -- Typed signatures follow the existing Lite class convention.
final class Entitlements {
	const CONTRACT_VERSION = 2;
	const MAX_FIELDS       = 50;
	const DOI_KEYS         = array( 'subscription_mode', 'doi_template_id', 'doi_redirect_url', 'doi_verified' );

	public static function contract_version(): int {
		return self::CONTRACT_VERSION;
	}

	/**
	 * @return array<int,int>
	 */
	public static function supported_contract_versions(): array {
		return array( self::CONTRACT_VERSION );
	}

	/**
	 * @return array<string,string>
	 */
	public static function declared( string $provider ): array {
		return \Cmatic_Lite_Esp_Registry::catalog()->descriptor( sanitize_key( $provider ) )->entitlements();
	}

	/**
	 * @return array<int,string>
	 */
	public static function paid_features(): array {
		$paid = array();
		foreach ( \Cmatic_Lite_Esp_Registry::catalog()->slugs() as $slug ) {
			foreach ( self::declared( $slug ) as $filter_name ) {
				$paid[ $filter_name ] = true;
			}
		}
		return array_keys( $paid );
	}

	/**
	 * @return array<string,bool>
	 */
	public static function mailerlite_entitlements( int $form_id ): array {
		$result = array();
		foreach ( array( 'mailerlite_routing', 'mailerlite_status', 'mailerlite_resubscribe', 'mailerlite_consent_metadata' ) as $feature ) {
			$result[ $feature ] = self::feature_enabled( $feature, 'mailerlite', $form_id );
		}
		return $result;
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
		if ( 'double_optin' === $feature ) {
			return true;
		}
		$enabled = (bool) apply_filters(
			'cmatic_lite_esp_feature_enabled',
			false,
			$feature,
			sanitize_key( $provider ),
			max( 0, $form_id )
		);

		return self::pro_entitled() && in_array( $feature, self::paid_features(), true ) && $enabled;
	}

	public static function routing_feature( string $provider ): string {
		$declared = self::declared( $provider );
		return $declared['routing'] ?? 'destination_routing';
	}

	/**
	 * @param array<string,mixed> $section
	 * @return array{effective_settings: array<string,mixed>, degraded: bool, suppressed_features: array<int,string>, suppressed_rule_ids: array<int,string>, entitled: array<string,bool>, field_limit: int}
	 */
	public static function apply( string $slug, array $section, int $form_id ): array {
		$declared            = self::declared( $slug );
		$entitled            = array( 'double_optin' => true );
		$effective           = $section;
		$suppressed_features = array();
		$suppressed_rule_ids = array();
		foreach ( $declared as $feature => $filter_name ) {
			$entitled[ $feature ] = self::feature_enabled( $filter_name, $slug, $form_id );
		}
		if ( isset( $declared['routing'] ) && DestinationResolver::is_premium_configured( $section ) && empty( $entitled['routing'] ) ) {
			foreach ( (array) ( $section['routing_rules'] ?? array() ) as $rule ) {
				if ( is_array( $rule ) && isset( $rule['id'] ) && is_scalar( $rule['id'] ) && '' !== (string) $rule['id'] ) {
					$suppressed_rule_ids[] = sanitize_key( (string) $rule['id'] );
				}
			}
			$groups                     = DestinationResolver::base_groups( $section );
			$primary                    = FormSettings::raw_list( $section );
			$primary                    = '' !== $primary ? $primary : (string) ( $groups[0] ?? '' );
			$effective['list']          = $primary;
			$effective['base_groups']   = '' === $primary ? array() : array( $primary );
			$effective['routing_rules'] = array();
			$suppressed_features[]      = $declared['routing'];
		}
		if ( isset( $declared['status'] ) && empty( $entitled['status'] ) ) {
			$effective['status_mode'] = 'account';
		}
		$status_mode = MappingSchema::str( $section['status_mode'] ?? 'legacy_provider_managed' );
		if ( isset( $declared['status'] ) && 'legacy_provider_managed' !== $status_mode && empty( $entitled['status'] ) ) {
			$effective['status_mode'] = 'account';
			$suppressed_features[]    = $declared['status'];
		}
		if ( isset( $declared['resubscribe'] ) && ! empty( $section['resubscribe_force'] ) && ( empty( $entitled['resubscribe'] ) || empty( $entitled['status'] ) ) ) {
			unset( $effective['resubscribe_force'] );
			$suppressed_features[] = $declared['resubscribe'];
		}
		if ( isset( $declared['consent_metadata'] ) && ! empty( $section['consent_metadata_enabled'] ) && empty( $entitled['consent_metadata'] ) ) {
			unset( $effective['consent_metadata_enabled'] );
			$suppressed_features[] = $declared['consent_metadata'];
		}
		if ( isset( $declared['double_optin'] ) && empty( $entitled['double_optin'] ) ) {
			foreach ( self::DOI_KEYS as $premium_key ) {
				unset( $effective[ $premium_key ] );
			}
		}
		$suppressed_features = self::canonical( $suppressed_features );
		$suppressed_rule_ids = self::canonical( $suppressed_rule_ids );

		return array(
			'effective_settings'  => $effective,
			'degraded'            => ! empty( $suppressed_features ),
			'suppressed_features' => $suppressed_features,
			'suppressed_rule_ids' => $suppressed_rule_ids,
			'entitled'            => $entitled,
			'field_limit'         => self::field_limit( $slug, $form_id ),
		);
	}

	/**
	 * @param array<int,mixed> $values
	 * @return array<int,string>
	 */
	public static function canonical( array $values ): array {
		$values = array_values(
			array_unique(
				array_filter(
					array_map(
						static function ( $value ): string {
							return sanitize_key( MappingSchema::str( $value ) );
						},
						$values
					),
					static function ( string $value ): bool {
						return '' !== $value;
					}
				)
			)
		);
		sort( $values, SORT_STRING );
		return $values;
	}

	private static function pro_entitled(): bool {
		return class_exists( 'Cmatic_Pro_Esp_Bridge' )
			&& \Cmatic_Pro_Esp_Bridge::is_compatible()
			&& function_exists( 'cmatic_is_blessed' )
			&& cmatic_is_blessed();
	}

	private function __construct() {}
}
