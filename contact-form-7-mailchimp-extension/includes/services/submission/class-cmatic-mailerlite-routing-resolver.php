<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
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

	public static function normalize_rules( array $settings ): array {
		$rules = array();
		foreach ( (array) ( $settings['routing_rules'] ?? array() ) as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}
			$rules[] = array(
				'id'       => sanitize_text_field( self::scalar_string( $rule['id'] ?? '' ) ),
				'field'    => sanitize_key( self::scalar_string( $rule['field'] ?? '' ) ),
				'value'    => sanitize_text_field( self::scalar_string( $rule['value'] ?? '' ) ),
				'group_id' => sanitize_text_field( self::scalar_string( $rule['group_id'] ?? '' ) ),
			);
		}
		return $rules;
	}

	/**
	 * @param array $posted    Posted panel fields (primary_group, base_groups or additional_groups, routing_rules).
	 * @param array $settings  Provider settings holding lisdata.lists.
	 * @param array $form_tags Normalized form tags with routing choices.
	 */
	public static function sanitize( string $primary, array $posted, array $settings, array $form_tags ): ?array {
		if ( '' === $primary || ! self::list_exists( $settings, $primary ) ) {
			return null;
		}
		$additional = array();
		if ( isset( $posted['base_groups'] ) && is_array( $posted['base_groups'] ) ) {
			$additional = $posted['base_groups'];
		} elseif ( isset( $posted['additional_groups'] ) && is_array( $posted['additional_groups'] ) ) {
			$additional = $posted['additional_groups'];
		}
		$groups = array( $primary );
		foreach ( $additional as $group_id ) {
			$group_id = sanitize_text_field( self::scalar_string( $group_id ) );
			if ( '' !== $group_id && $primary !== $group_id && self::list_exists( $settings, $group_id ) ) {
				$groups[] = $group_id;
			}
		}
		$groups = array_slice( array_values( array_unique( $groups ) ), 0, 20 );

		$choice_index = array();
		foreach ( $form_tags as $tag ) {
			if ( ! is_array( $tag ) || empty( $tag['routing_eligible'] ) || ! isset( $tag['name'] ) || ! is_scalar( $tag['name'] ) ) {
				continue;
			}
			$choices = array();
			foreach ( isset( $tag['choices'] ) && is_array( $tag['choices'] ) ? $tag['choices'] : array() as $choice ) {
				if ( is_array( $choice ) && isset( $choice['value'] ) && is_scalar( $choice['value'] ) ) {
					$choices[] = (string) $choice['value'];
				}
			}
			$choice_index[ (string) $tag['name'] ] = $choices;
		}

		$rules     = array();
		$seen      = array();
		$seen_rule = array();
		foreach ( array_slice( (array) ( $posted['routing_rules'] ?? array() ), 0, 50 ) as $rule ) {
			if ( ! is_array( $rule ) ) {
				return null;
			}
			$id       = sanitize_text_field( self::scalar_string( $rule['id'] ?? '' ) );
			$field    = sanitize_key( self::scalar_string( $rule['field'] ?? '' ) );
			$value    = sanitize_text_field( self::scalar_string( $rule['value'] ?? '' ) );
			$group_id = sanitize_text_field( self::scalar_string( $rule['group_id'] ?? '' ) );
			if ( 1 !== preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5a-f0-9][a-f0-9]{3}-[89ab0-9][a-f0-9]{3}-[a-f0-9]{12}$/i', $id ) || isset( $seen[ $id ] ) ) {
				return null;
			}
			if ( ! isset( $choice_index[ $field ] ) || ! in_array( $value, $choice_index[ $field ], true ) || ! self::list_exists( $settings, $group_id ) ) {
				return null;
			}
			$rule_key = hash( 'sha256', $field . "\0" . $value . "\0" . $group_id );
			if ( isset( $seen_rule[ $rule_key ] ) ) {
				return null;
			}
			$seen[ $id ]            = true;
			$seen_rule[ $rule_key ] = true;
			$rules[]                = compact( 'id', 'field', 'value', 'group_id' );
		}

		return array(
			'base_groups'   => $groups,
			'routing_rules' => $rules,
		);
	}

	private static function list_exists( array $settings, string $list_id ): bool {
		$lists = isset( $settings['lisdata']['lists'] ) && is_array( $settings['lisdata']['lists'] ) ? $settings['lisdata']['lists'] : array();
		foreach ( $lists as $list ) {
			if ( is_array( $list ) && isset( $list['id'] ) && $list_id === self::scalar_string( $list['id'] ) ) {
				return true;
			}
		}
		return false;
	}

	private function __construct() {}
}
