<?php
/**
 * Deterministic MailerLite group routing.
 *
 * @package contact-form-7-mailchimp-extension
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

final class Cmatic_Mailerlite_Routing_Resolver {
	public static function is_premium_configured( array $settings ): bool {
		return count( self::base_groups( $settings ) ) > 1 || ! empty( $settings['routing_rules'] );
	}

	public static function base_groups( array $settings ): array {
		if ( isset( $settings['base_groups'] ) && is_array( $settings['base_groups'] ) ) {
			return self::unique_strings( $settings['base_groups'] );
		}

		$list = $settings['list'] ?? '';
		if ( is_array( $list ) ) {
			$list = reset( $list );
		}

		return ! is_scalar( $list ) || '' === (string) $list ? array() : array( (string) $list );
	}

	/**
	 * Resolves exact submitted values against current static choices.
	 *
	 * @param array $settings    Effective provider settings.
	 * @param array $posted_data Submitted Contact Form 7 data.
	 * @param array $form_tags   Current normalized form tags.
	 */
	public static function resolve( array $settings, array $posted_data, array $form_tags ): array {
		$groups  = self::base_groups( $settings );
		$choices = self::choice_index( $form_tags );
		$matched = array();
		$rules   = isset( $settings['routing_rules'] ) && is_array( $settings['routing_rules'] ) ? $settings['routing_rules'] : array();

		if ( empty( $groups ) ) {
			return self::failure( 'routing_missing_base_group' );
		}

		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				return self::failure( 'routing_stale_rule' );
			}
			$field = self::scalar_string( $rule['field'] ?? '' );
			$value = self::scalar_string( $rule['value'] ?? '' );
			if ( ! isset( $choices[ $field ] ) || ! in_array( $value, $choices[ $field ], true ) ) {
				return self::failure( 'routing_stale_rule' );
			}

			$submitted = isset( $posted_data[ $field ] ) ? (array) $posted_data[ $field ] : array();
			$submitted = self::unique_strings( $submitted );
			if ( in_array( $value, $submitted, true ) ) {
				$group_id = self::scalar_string( $rule['group_id'] ?? '' );
				if ( '' === $group_id ) {
					return self::failure( 'routing_stale_rule' );
				}
				$groups[]  = $group_id;
				$matched[] = self::scalar_string( $rule['id'] ?? '' );
			}
		}

		return array(
			'success'       => true,
			'reason'        => '',
			'groups'        => self::unique_strings( $groups ),
			'matched_rules' => self::unique_strings( $matched ),
		);
	}

	private static function choice_index( array $form_tags ): array {
		$index = array();
		foreach ( $form_tags as $tag ) {
			if ( ! is_array( $tag ) || empty( $tag['routing_eligible'] ) || empty( $tag['name'] ) ) {
				continue;
			}
			$values = array();
			foreach ( (array) ( $tag['choices'] ?? array() ) as $choice ) {
				if ( is_array( $choice ) && isset( $choice['value'] ) && is_scalar( $choice['value'] ) ) {
					$values[] = (string) $choice['value'];
				}
			}
			if ( is_scalar( $tag['name'] ) ) {
				$index[ (string) $tag['name'] ] = self::unique_strings( $values );
			}
		}
		return $index;
	}

	private static function unique_strings( array $values ): array {
		$result = array();
		foreach ( $values as $value ) {
			if ( is_scalar( $value ) && '' !== (string) $value ) {
				$result[] = (string) $value;
			}
		}
		return array_values( array_unique( $result ) );
	}

	private static function scalar_string( $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	}

	private static function failure( string $reason ): array {
		return array(
			'success'       => false,
			'reason'        => $reason,
			'groups'        => array(),
			'matched_rules' => array(),
		);
	}

	private function __construct() {}
}
