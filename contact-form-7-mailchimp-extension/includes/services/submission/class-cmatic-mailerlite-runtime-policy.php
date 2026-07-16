<?php
/**
 * MailerLite paid-feature runtime degradation policy.
 *
 * @package contact-form-7-mailchimp-extension
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

final class Cmatic_Mailerlite_Runtime_Policy {
	/**
	 * Applies current entitlements and returns suppressed feature identifiers.
	 *
	 * @param array $settings     Saved provider settings.
	 * @param array $entitlements Effective paid-feature grants.
	 */
	public static function apply( array $settings, array $entitlements ): array {
		$effective           = $settings;
		$suppressed_features = array();
		$suppressed_rule_ids = array();

		if ( Cmatic_Mailerlite_Routing_Resolver::is_premium_configured( $settings ) && empty( $entitlements['mailerlite_routing'] ) ) {
			foreach ( (array) ( $settings['routing_rules'] ?? array() ) as $rule ) {
				if ( is_array( $rule ) && isset( $rule['id'] ) && is_scalar( $rule['id'] ) && '' !== (string) $rule['id'] ) {
					$suppressed_rule_ids[] = sanitize_key( (string) $rule['id'] );
				}
			}
			$groups                     = Cmatic_Mailerlite_Routing_Resolver::base_groups( $settings );
			$primary                    = self::primary_group( $settings, $groups );
			$effective['list']          = $primary;
			$effective['base_groups']   = '' === $primary ? array() : array( $primary );
			$effective['routing_rules'] = array();
			$suppressed_features[]      = 'mailerlite_routing';
		}

		$status_mode = (string) ( $settings['status_mode'] ?? 'legacy_provider_managed' );
		if ( 'legacy_provider_managed' !== $status_mode && empty( $entitlements['mailerlite_status'] ) ) {
			unset( $effective['status_mode'] );
			$suppressed_features[] = 'mailerlite_status';
		}

		if ( ! empty( $settings['resubscribe_force'] ) && ( empty( $entitlements['mailerlite_resubscribe'] ) || empty( $entitlements['mailerlite_status'] ) ) ) {
			unset( $effective['resubscribe_force'] );
			$suppressed_features[] = 'mailerlite_resubscribe';
		}

		if ( ! empty( $settings['consent_metadata_enabled'] ) && empty( $entitlements['mailerlite_consent_metadata'] ) ) {
			unset( $effective['consent_metadata_enabled'] );
			$suppressed_features[] = 'mailerlite_consent_metadata';
		}

		$suppressed_features = self::canonical( $suppressed_features );
		$suppressed_rule_ids = self::canonical( $suppressed_rule_ids );

		return array(
			'effective_settings'  => $effective,
			'degraded'            => ! empty( $suppressed_features ),
			'suppressed_features' => $suppressed_features,
			'suppressed_rule_ids' => $suppressed_rule_ids,
		);
	}

	private static function primary_group( array $settings, array $groups ): string {
		$list = $settings['list'] ?? '';
		if ( is_array( $list ) ) {
			$list = reset( $list );
		}
		return '' !== (string) $list ? (string) $list : (string) ( $groups[0] ?? '' );
	}

	private static function canonical( array $values ): array {
		$values = array_values(
			array_unique(
				array_filter(
					array_map( 'sanitize_key', $values ),
					static function ( string $value ): bool {
						return '' !== $value;
					}
				)
			)
		);
		sort( $values, SORT_STRING );
		return $values;
	}

	private function __construct() {}
}
