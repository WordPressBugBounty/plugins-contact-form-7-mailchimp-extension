<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    hello@chimpmatic.com
 * @copyright 2014-2026 ChimpMatic
 * @license   GPL-3.0+
 */

declare(strict_types=1);

namespace Cmatic\Lite\Settings;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing -- Typed signatures follow the existing Lite class convention.
final class SettingsValidator {
	const STATUS_MODES = array( 'legacy_provider_managed', 'account', 'active', 'unconfirmed' );

	/**
	 * @param array<string,mixed> $section
	 * @param array<string,mixed> $posted
	 * @param mixed               $contact_form
	 * @return array<string,mixed>|null
	 */
	public static function provider_save( string $slug, array $section, array $posted, int $field_limit, $contact_form, int $form_id ): ?array {
		$mapped = 0;
		foreach ( MappingSchema::arr( $posted['merge_fields'] ?? array() ) as $offset => $field ) {
			if ( is_array( $field ) && '' !== trim( MappingSchema::str( $posted[ MappingSchema::slot( (int) $offset ) ] ?? '' ) ) ) {
				++$mapped;
			}
		}
		if ( $mapped > $field_limit ) {
			return null;
		}
		$field_limit = count( MappingSchema::arr( $posted['merge_fields'] ?? array() ) );
		if ( 'mailchimp' === $slug ) {
			return self::mailchimp_save( $section, $posted, $field_limit, $contact_form, $form_id );
		}
		return self::provider_section_save( $slug, $section, $posted, $field_limit, $contact_form, $form_id );
	}

	/**
	 * @param array<string,mixed> $current
	 * @param array<string,mixed> $posted
	 * @param mixed               $contact_form
	 * @return array<string,mixed>|null
	 */
	private static function mailchimp_save( array $current, array $posted, int $limit, $contact_form, int $form_id ): ?array {
		$list = sanitize_text_field( MappingSchema::str( $posted['primary_group'] ?? '' ) );
		if ( '' === $list ) {
			$list = sanitize_text_field( MappingSchema::str( $posted['list'] ?? '' ) );
		}
		$merge_fields = MappingSchema::normalize_fields( $posted['merge_fields'] ?? array(), $limit );
		if ( '' === $list || array() === $merge_fields || ! FormSettings::list_exists( $current, $list ) ) {
			return null;
		}
		$entitled = Entitlements::feature_enabled( 'destination_routing', 'mailchimp', $form_id );
		if ( ! $entitled && \Cmatic\Lite\Submission\DestinationResolver::is_premium_configured( $current ) ) {
			$list = FormSettings::selected_list( $current );
		} elseif ( ! $entitled ) {
			$current['routing_schema'] = 1;
			$current['base_groups']    = array( $list );
			$current['routing_rules']  = array();
		} else {
			$form_tags = is_object( $contact_form ) ? FormTags::get_tags_with_types( $contact_form ) : array();
			$routing   = \Cmatic\Lite\Submission\DestinationResolver::sanitize( $list, $posted, $current, $form_tags );
			if ( null === $routing ) {
				return null;
			}
			$current['routing_schema'] = 1;
			$current['base_groups']    = $routing['base_groups'];
			$current['routing_rules']  = $routing['routing_rules'];
		}
		$current['list']               = $list;
		$current['merge_fields']       = $merge_fields;
		$current['total_merge_fields'] = max( count( $merge_fields ), absint( MappingSchema::num( $posted['total_merge_fields'] ?? 0 ) ) );
		foreach ( MappingSchema::slots( $limit ) as $key ) {
			$current[ $key ] = sanitize_text_field( MappingSchema::str( $posted[ $key ] ?? '' ) );
		}
		if ( ! MappingSchema::has_email_mapping( $current, $limit, true ) ) {
			return null;
		}
		$gate  = 'required' === sanitize_key( MappingSchema::str( $posted['consent_gate'] ?? 'none' ) );
		$field = sanitize_text_field( MappingSchema::str( $posted['consent_field'] ?? '' ) );
		if ( $gate && ! \Cmatic_Mailchimp_Panel_Store::is_consent_field( $field, $contact_form ) ) {
			return null;
		}
		$current['consent_gate']  = $gate ? 'required' : 'none';
		$current['consent_field'] = $gate ? $field : '';
		$current['accept']        = $gate ? $field : ' ';
		$current['confsubs']      = 'pending' === sanitize_key( MappingSchema::str( $posted['double_optin'] ?? '' ) ) ? '1' : '0';

		return $current;
	}

	/**
	 * @param array<string,mixed> $settings
	 * @param array<string,mixed> $posted
	 * @param mixed               $contact_form
	 * @return array<string,mixed>|null
	 */
	private static function provider_section_save( string $slug, array $settings, array $posted, int $field_limit, $contact_form, int $form_id ): ?array {
		$form_tags         = is_object( $contact_form ) ? FormTags::get_tags_with_types( $contact_form ) : array();
		$routing_feature   = Entitlements::routing_feature( $slug );
		$routing_supported = ! empty( \Cmatic_Lite_Esp_Manifest::get( $slug )['features']['multi_group_routing'] );
		$list              = sanitize_text_field( MappingSchema::str( $posted['primary_group'] ?? '' ) );
		if ( '' === $list ) {
			$list = sanitize_text_field( MappingSchema::str( $posted['list'] ?? '' ) );
		}
		if ( $routing_supported && ! Entitlements::feature_enabled( $routing_feature, $slug, $form_id ) && \Cmatic\Lite\Submission\DestinationResolver::is_premium_configured( $settings ) ) {
			$list = FormSettings::selected_list( $settings );
		}
		$merge_fields = MappingSchema::normalize_fields( $posted['merge_fields'] ?? array(), $field_limit, false );
		if ( '' === $list || array() === $merge_fields || ! FormSettings::list_exists( $settings, $list ) ) {
			return null;
		}
		$settings['list'] = $list;
		if ( $routing_supported ) {
			$routing_entitled = Entitlements::feature_enabled( $routing_feature, $slug, $form_id );
			if ( ! $routing_entitled && \Cmatic\Lite\Submission\DestinationResolver::is_premium_configured( $settings ) ) {
				$list             = FormSettings::selected_list( $settings );
				$settings['list'] = $list;
			} elseif ( ! $routing_entitled ) {
				$settings['routing_schema'] = 1;
				$settings['base_groups']    = array( $list );
				$settings['routing_rules']  = array();
			} else {
				$routing = \Cmatic\Lite\Submission\DestinationResolver::sanitize( $list, $posted, $settings, $form_tags );
				if ( null === $routing ) {
					return null;
				}
				$settings['routing_schema'] = 1;
				$settings['base_groups']    = $routing['base_groups'];
				$settings['routing_rules']  = $routing['routing_rules'];
			}
		}
		$settings['merge_fields']       = $merge_fields;
		$total                          = $posted['total_merge_fields'] ?? null;
		$settings['total_merge_fields'] = isset( $posted['total_merge_fields'] ) ? max( count( $merge_fields ), absint( is_array( $total ) || is_scalar( $total ) ? $total : 0 ) ) : count( $merge_fields );
		foreach ( MappingSchema::slots( $field_limit ) as $key ) {
			$settings[ $key ] = sanitize_text_field( MappingSchema::str( $posted[ $key ] ?? '' ) );
		}
		if ( ! MappingSchema::has_email_mapping( $settings, $field_limit ) ) {
			return null;
		}
		if ( 'mailerlite' === $slug && ! self::boolean_mappings_valid( $settings, $form_tags, $field_limit ) ) {
			return null;
		}
		$gate = self::consent_gate( $posted, $form_tags );
		if ( null === $gate ) {
			return null;
		}
		$settings = array_merge( $settings, $gate );
		if ( Entitlements::feature_enabled( 'double_optin', $slug, $form_id ) ) {
			$consent = self::provider_consent( $gate, $posted, $slug );
			if ( null === $consent ) {
				return null;
			}
			if ( 'brevo' === $slug && 'double' === $consent['subscription_mode'] && ! self::doi_verified( $posted, $form_id, $slug, $list, $consent ) ) {
				return null;
			}
			if ( 'brevo' === $slug && 'double' === $consent['subscription_mode'] ) {
				$consent['doi_verified'] = 1;
			}
			$settings = array_merge( $settings, $consent );
		}
		if ( 'mailerlite' === $slug ) {
			$settings = self::mailerlite_options( $settings, $posted, $form_tags, $form_id );
			if ( array() === $settings ) {
				return null;
			}
		}
		return $settings;
	}

	/**
	 * @param array<string,mixed> $posted
	 * @param array<int,mixed>    $form_tags
	 * @return array<string,string>|null
	 */
	private static function consent_gate( array $posted, array $form_tags ): ?array {
		$gate  = isset( $posted['consent_gate'] ) ? sanitize_key( MappingSchema::str( $posted['consent_gate'] ) ) : 'none';
		$gate  = in_array( $gate, array( 'none', 'required' ), true ) ? $gate : '';
		$field = isset( $posted['consent_field'] ) ? sanitize_text_field( MappingSchema::str( $posted['consent_field'] ) ) : '';
		if ( '' === $gate || ( 'required' === $gate && ! self::is_consent_field( $field, $form_tags ) ) ) {
			return null;
		}
		return array(
			'consent_gate'  => $gate,
			'consent_field' => 'required' === $gate ? $field : '',
		);
	}

	/**
	 * @param array<string,string> $gate
	 * @param array<string,mixed>  $posted
	 * @return array<string,mixed>|null
	 */
	private static function provider_consent( array $gate, array $posted, string $slug ): ?array {
		$consent                      = $gate;
		$consent['subscription_mode'] = 'provider_managed';
		if ( 'brevo' !== $slug ) {
			return $consent;
		}
		$mode = isset( $posted['subscription_mode'] ) ? sanitize_key( MappingSchema::str( $posted['subscription_mode'] ) ) : 'single';
		if ( ! in_array( $mode, array( 'single', 'double' ), true ) ) {
			return null;
		}
		$consent['subscription_mode'] = $mode;
		if ( 'single' === $mode ) {
			$consent['doi_template_id']  = 0;
			$consent['doi_redirect_url'] = '';
			$consent['doi_verified']     = 0;
			return $consent;
		}
		$raw         = $posted['doi_template_id'] ?? 0;
		$template_id = absint( is_array( $raw ) || is_scalar( $raw ) ? $raw : 0 );
		$redirect    = isset( $posted['doi_redirect_url'] ) ? esc_url_raw( MappingSchema::str( $posted['doi_redirect_url'] ) ) : '';
		if ( $template_id < 1 || 'https' !== wp_parse_url( $redirect, PHP_URL_SCHEME ) ) {
			return null;
		}
		$consent['doi_template_id']  = $template_id;
		$consent['doi_redirect_url'] = $redirect;
		$consent['doi_verified']     = 0;
		return $consent;
	}

	/**
	 * @param array<string,mixed> $posted
	 * @param array<string,mixed> $consent
	 */
	private static function doi_verified( array $posted, int $form_id, string $slug, string $list, array $consent ): bool {
		$token       = isset( $posted['doi_verification_token'] ) ? sanitize_text_field( MappingSchema::str( $posted['doi_verification_token'] ) ) : '';
		$expected    = array(
			'form_id'       => $form_id,
			'provider'      => $slug,
			'list_id'       => $list,
			'template_id'   => $consent['doi_template_id'],
			'redirect_hash' => hash( 'sha256', MappingSchema::str( $consent['doi_redirect_url'] ?? '' ) ),
		);
		$current_key = \Cmatic_Lite_Esp_Credentials::get( $form_id, $slug );
		if ( '' === $current_key ) {
			return false;
		}
		$expected['credential_fingerprint'] = \Cmatic_Lite_Esp_Rest_Controller::credential_fingerprint( $current_key );
		unset( $current_key );
		return \Cmatic_Lite_Esp_Rest_Controller::verify_consent_token( $token, $expected );
	}

	/**
	 * @param array<string,mixed> $section
	 * @param array<string,mixed> $posted
	 * @param array<int,mixed>    $form_tags
	 * @return array<string,mixed>
	 */
	private static function mailerlite_options( array $section, array $posted, array $form_tags, int $form_id ): array {
		$status_entitled = Entitlements::feature_enabled( 'mailerlite_status', 'mailerlite', $form_id );
		$resub_entitled  = Entitlements::feature_enabled( 'mailerlite_resubscribe', 'mailerlite', $form_id );
		$meta_entitled   = Entitlements::feature_enabled( 'mailerlite_consent_metadata', 'mailerlite', $form_id );
		$consent_field   = MappingSchema::str( $section['consent_field'] ?? '' );
		if ( $status_entitled ) {
			$mode = sanitize_key( MappingSchema::str( $posted['status_mode'] ?? 'legacy_provider_managed' ) );
			if ( ! in_array( $mode, self::STATUS_MODES, true ) ) {
				return array();
			}
			$section['status_mode'] = $mode;
			if ( $resub_entitled ) {
				$force = ! empty( $posted['resubscribe_force'] );
				if ( $force && ( 'active' !== $mode || ! self::eligible_acceptance_field( $consent_field, $form_tags ) ) ) {
					return array();
				}
				$section['resubscribe_force'] = $force ? 1 : 0;
			}
		}
		if ( $meta_entitled ) {
			$enabled = ! empty( $posted['consent_metadata_enabled'] );
			if ( $enabled && ! self::eligible_acceptance_field( $consent_field, $form_tags ) ) {
				return array();
			}
			$section['consent_metadata_enabled'] = $enabled ? 1 : 0;
			if ( $enabled ) {
				$section['consent_snapshot'] = array(
					'field'       => $consent_field,
					'text_hash'   => hash( 'sha256', self::acceptance_text( $consent_field, $form_tags ) ),
					'captured_at' => gmdate( 'Y-m-d H:i:s' ),
				);
			}
		}
		return $section;
	}

	/**
	 * @param array<string,mixed> $section
	 * @param array<int,mixed>    $form_tags
	 */
	private static function boolean_mappings_valid( array $section, array $form_tags, int $field_limit ): bool {
		$types = array();
		foreach ( $form_tags as $tag ) {
			if ( is_array( $tag ) && isset( $tag['name'] ) && is_scalar( $tag['name'] ) ) {
				$types[ (string) $tag['name'] ] = MappingSchema::str( $tag['basetype'] ?? '' );
			}
		}
		foreach ( array_slice( (array) ( $section['merge_fields'] ?? array() ), 0, $field_limit ) as $offset => $definition ) {
			if ( ! is_array( $definition ) || 'boolean' !== sanitize_key( MappingSchema::str( $definition['type'] ?? '' ) ) ) {
				continue;
			}
			$mapping = MappingSchema::str( $section[ MappingSchema::slot( (int) $offset ) ] ?? '' );
			if ( '' === $mapping ) {
				continue;
			}
			if ( 1 !== preg_match( '/^\[([A-Za-z0-9_-]+)\]$/', $mapping, $matches ) || 'acceptance' !== ( $types[ $matches[1] ] ?? '' ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @param array<int,mixed> $form_tags
	 */
	public static function is_consent_field( string $field, array $form_tags ): bool {
		if ( 1 !== preg_match( '/^\[([a-zA-Z_][0-9a-zA-Z:._-]*)\]$/', $field, $match ) ) {
			return false;
		}
		foreach ( $form_tags as $tag ) {
			if ( is_array( $tag ) && in_array( MappingSchema::str( $tag['basetype'] ?? '' ), array( 'acceptance', 'checkbox' ), true ) && MappingSchema::str( $tag['name'] ?? '' ) === $match[1] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param array<int,mixed> $form_tags
	 */
	private static function eligible_acceptance_field( string $field, array $form_tags ): bool {
		$field = trim( $field, '[]' );
		foreach ( $form_tags as $tag ) {
			if ( is_array( $tag ) && MappingSchema::str( $tag['name'] ?? '' ) === $field && 'acceptance' === ( $tag['basetype'] ?? '' ) && ! empty( $tag['required'] ) && empty( $tag['inverted'] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param array<int,mixed> $form_tags
	 */
	private static function acceptance_text( string $field, array $form_tags ): string {
		$field = trim( $field, '[]' );
		foreach ( $form_tags as $tag ) {
			if ( is_array( $tag ) && MappingSchema::str( $tag['name'] ?? '' ) === $field ) {
				return MappingSchema::str( $tag['content'] ?? '' );
			}
		}
		return '';
	}

	private function __construct() {}
}
