<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

use Cmatic\Lite\Settings\FormSettings;
use Cmatic\Lite\Settings\FormSettingsRepository;
use Cmatic\Lite\Settings\MappingSchema;

final class Cmatic_Mailchimp_Panel_Store {

	const EXCLUDED_TYPES = array( 'address', 'birthday', 'imageurl', 'zip' );
	const SLOT_MAX       = MappingSchema::SLOT_MAX;

	/**
	 * @return array<string,mixed>
	 */
	public static function settings( int $form_id ): array {
		return FormSettingsRepository::load( $form_id )->record();
	}

	/**
	 * @param array<string,mixed> $config
	 */
	public static function credential_present( array $config ): bool {
		return ! empty( $config['api'] ) || ( isset( $config['auth_type'] ) && 'oauth' === $config['auth_type'] );
	}

	/**
	 * @param array<string,mixed> $config
	 */
	public static function auth_type( array $config ): string {
		if ( isset( $config['auth_type'] ) && 'oauth' === $config['auth_type'] ) {
			return 'oauth';
		}

		return empty( $config['api'] ) ? '' : 'api_key';
	}

	/**
	 * @param array<string,mixed> $config
	 */
	public static function resolve_key( int $form_id, array $config, string $submitted = '' ): string {
		if ( '' !== $submitted ) {
			return $submitted;
		}
		$manager = Cmatic_Lite_Container::get( 'auth.manager' );
		if ( is_object( $manager ) && method_exists( $manager, 'resolve_api_key' ) ) {
			return MappingSchema::str( $manager->resolve_api_key( $form_id, '', $config ) );
		}

		return MappingSchema::str( $config['api'] ?? '' );
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public static function connect( int $form_id, string $submitted ) {
		$config = self::settings( $form_id );
		$key    = self::resolve_key( $form_id, $config, $submitted );
		if ( '' === $key ) {
			return new WP_Error( 'missing_provider_key', esc_html__( 'Enter a Mailchimp API key or sign in with Mailchimp first.', 'contact-form-7-mailchimp-extension' ), array( 'status' => 400 ) );
		}
		$logging    = Cmatic_Debug_Log::logging();
		$validation = Cmatic_Lite_Api_Service::validate_key( $key, $logging );
		if ( 1 !== MappingSchema::num( $validation['api-validation'] ?? 0 ) ) {
			return new WP_Error( 'invalid_provider_key', esc_html__( 'Mailchimp rejected this key.', 'contact-form-7-mailchimp-extension' ), array( 'status' => 400 ) );
		}
		$lists = Cmatic_Lite_Api_Service::get_lists( $key, $logging );
		$items = isset( $lists['lisdata']['lists'] ) && is_array( $lists['lisdata']['lists'] ) ? $lists['lisdata']['lists'] : array();
		if ( array() === $items && ! isset( $lists['lisdata']['lists'] ) ) {
			return new WP_Error( 'provider_lists_failed', esc_html__( 'The key is valid, but the audiences could not be loaded.', 'contact-form-7-mailchimp-extension' ), array( 'status' => 502 ) );
		}
		$previous    = MappingSchema::str( $config['api'] ?? '' );
		$key_changed = '' !== $submitted && ( '' === $previous || ! hash_equals( $previous, $submitted ) );
		$changes     = array(
			'api-validation' => 1,
			'lisdata'        => isset( $lists['lisdata'] ) && is_array( $lists['lisdata'] ) ? $lists['lisdata'] : array(),
		);
		$drop_oauth  = false;
		if ( '' !== $submitted ) {
			$changes['api'] = $submitted;
			$drop_oauth     = $key_changed && 'oauth' === self::auth_type( $config );
		}
		$selected = FormSettings::selected_list( $config );
		if ( $key_changed || ( '' !== $selected && ! FormSettings::list_exists( array( 'lisdata' => array( 'lists' => $items ) ), $selected ) ) ) {
			$changes = array_merge( $changes, MappingSchema::cleared_mailchimp_account() );
		}
		if ( $drop_oauth ) {
			$manager = Cmatic_Lite_Container::get( 'auth.manager' );
			if ( is_object( $manager ) && method_exists( $manager, 'disconnect' ) ) {
				$manager->disconnect( $form_id );
			}
		}
		$config = FormSettingsRepository::merge( $form_id, $changes, $drop_oauth ? array( 'auth_type', 'api_key_backup', 'oauth_account_name' ) : array() );
		if ( ! Cmatic_Options_Repository::get_option( 'api.first_connected' ) ) {
			Cmatic_Options_Repository::set_option( 'api.first_connected', time() );
		}

		return array(
			'success'            => true,
			'provider'           => 'mailchimp',
			'connected'          => true,
			'credential_present' => true,
			'key_changed'        => $key_changed,
			'auth_type'          => self::auth_type( $config ),
			'lists'              => self::normalize_lists( $config ),
		);
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public static function fields( int $form_id, string $list_id, int $limit ) {
		$limit  = PHP_INT_MAX;
		$config = self::settings( $form_id );
		if ( '' === $list_id ) {
			return array(
				'success'            => true,
				'provider'           => 'mailchimp',
				'list_id'            => '',
				'merge_fields'       => array(),
				'locked_fields'      => array(),
				'total_merge_fields' => 0,
				'mappings'           => array(),
			);
		}
		if ( ! FormSettings::list_exists( $config, $list_id ) ) {
			return new WP_Error( 'invalid_provider_list', esc_html__( 'Select an audience returned by Mailchimp.', 'contact-form-7-mailchimp-extension' ), array( 'status' => 400 ) );
		}
		$key = self::resolve_key( $form_id, $config );
		if ( '' === $key ) {
			return new WP_Error( 'missing_provider_key', esc_html__( 'Connect Mailchimp first.', 'contact-form-7-mailchimp-extension' ), array( 'status' => 400 ) );
		}
		$result = Cmatic_Lite_Api_Service::get_merge_fields( $key, $list_id, Cmatic_Debug_Log::logging() );
		$raw    = $result['merge_fields']['merge_fields'] ?? null;
		if ( ! is_array( $raw ) ) {
			return new WP_Error( 'provider_fields_failed', esc_html__( 'Mailchimp merge fields could not be loaded; existing mappings were preserved.', 'contact-form-7-mailchimp-extension' ), array( 'status' => 502 ) );
		}
		$all      = self::normalize_remote_fields( $raw );
		$capped   = array_slice( $all, 0, $limit );
		$locked   = array_values( array_slice( $all, $limit ) );
		$mappings = MappingSchema::reconcile( MappingSchema::previous_mappings( MappingSchema::legacy_fields( $config ), $config, true ), $capped, $limit, true );
		if ( ! Cmatic_Options_Repository::get_option( 'api.audience_selected' ) ) {
			Cmatic_Options_Repository::set_option( 'api.audience_selected', time() );
		}

		return array(
			'success'            => true,
			'provider'           => 'mailchimp',
			'list_id'            => $list_id,
			'merge_fields'       => $capped,
			'locked_fields'      => $locked,
			'total_merge_fields' => count( $all ),
			'mappings'           => $mappings,
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function disconnect( int $form_id ): array {
		$manager = Cmatic_Lite_Container::get( 'auth.manager' );
		if ( is_object( $manager ) && method_exists( $manager, 'disconnect' ) ) {
			$manager->disconnect( $form_id );
		}
		FormSettingsRepository::merge(
			$form_id,
			array(
				'api'            => '',
				'api-validation' => 0,
			),
			array( 'auth_type', 'api_key_backup', 'oauth_account_name' )
		);

		return array(
			'success'            => true,
			'provider'           => 'mailchimp',
			'connected'          => false,
			'credential_present' => false,
		);
	}

	/**
	 * @param array<string,mixed> $config
	 * @param array<int,mixed>    $form_tags
	 * @return array<string,mixed>
	 */
	public static function public_state( int $form_id, array $config, int $limit, array $form_tags ): array {
		$limit       = PHP_INT_MAX;
		$fields      = MappingSchema::normalize_fields( $config['merge_fields'] ?? array(), $limit );
		$lists       = self::normalize_lists( $config );
		$selected    = FormSettings::selected_list( $config );
		$accept      = trim( MappingSchema::str( $config['accept'] ?? '' ) );
		$mappings    = MappingSchema::mappings( $config, $limit );
		$connected   = self::credential_present( $config ) && 1 === MappingSchema::num( $config['api-validation'] ?? 0 );
		$offset      = MappingSchema::email_offset( $fields, $limit, true );
		$mapped      = null !== $offset && '' !== MappingSchema::str( $mappings[ MappingSchema::slot( $offset ) ] ?? '' );
		$base_groups = \Cmatic\Lite\Submission\DestinationResolver::base_groups( $config );
		if ( '' !== $selected ) {
			$base_groups = array_values( array_unique( array_merge( array( $selected ), array_diff( $base_groups, array( $selected ) ) ) ) );
		}
		$state               = array(
			'has_saved_mappings'         => array() !== array_filter( $mappings ),
			'connected'                  => $connected,
			'credential_present'         => self::credential_present( $config ),
			'auth_type'                  => self::auth_type( $config ),
			'lists'                      => $lists,
			'selected_list'              => $selected,
			'selected_list_name'         => self::list_name( $lists, $selected ),
			'fields'                     => $fields,
			'locked_fields'              => MappingSchema::normalize_fields( $config['locked_fields'] ?? array(), MappingSchema::SLOT_MAX ),
			'total_fields'               => max( count( $fields ), MappingSchema::num( $config['total_merge_fields'] ?? 0 ) ),
			'mappings'                   => $mappings,
			'double_optin_entitled'      => true,
			'consent_gate'               => '' !== $accept ? 'required' : 'none',
			'consent_field'              => $accept,
			'double_optin'               => ! empty( $config['confsubs'] ) ? 'pending' : 'subscribed',
			'subscription_mode'          => 'provider_managed',
			'doi_template_id'            => 0,
			'doi_redirect_url'           => '',
			'form_tags'                  => $form_tags,
			'routing_supported'          => true,
			'routing_entitled'           => \Cmatic\Lite\Settings\Entitlements::feature_enabled( 'destination_routing', 'mailchimp', $form_id ),
			'base_groups'                => $base_groups,
			'additional_groups'          => array_values( array_diff( $base_groups, array( $selected ) ) ),
			'routing_rules'              => \Cmatic\Lite\Submission\DestinationResolver::normalize_rules( $config ),
			'status_supported'           => false,
			'status_entitled'            => false,
			'status_mode'                => 'legacy_provider_managed',
			'resubscribe_entitled'       => false,
			'resubscribe_force'          => false,
			'consent_metadata_supported' => false,
			'consent_metadata_entitled'  => false,
			'consent_metadata_enabled'   => false,
			'create_field_supported'     => false,
			'create_field_entitled'      => false,
			'lookup_supported'           => false,
		);
		$state['configured'] = $connected && '' !== $selected && array() !== $fields && $mapped;

		return $state;
	}

	/**
	 * @param array<string,mixed> $current
	 * @param array<string,mixed> $posted
	 * @param mixed               $contact_form
	 * @return array<string,mixed>|null
	 */
	public static function save( array $current, array $posted, int $limit, $contact_form ): ?array {
		$form_id = is_object( $contact_form ) && method_exists( $contact_form, 'id' ) ? (int) $contact_form->id() : 0;
		return \Cmatic\Lite\Settings\SettingsValidator::provider_save( 'mailchimp', $current, $posted, $limit, $contact_form, $form_id );
	}

	/**
	 * @param mixed $contact_form
	 */
	public static function is_consent_field( string $value, $contact_form ): bool {
		if ( ! is_object( $contact_form ) || ! method_exists( $contact_form, 'scan_form_tags' ) || ! preg_match( '/^\[([A-Za-z0-9_\-]+)\]$/', $value, $m ) ) {
			return false;
		}
		foreach ( (array) $contact_form->scan_form_tags() as $tag ) {
			if ( ! is_object( $tag ) ) {
				continue;
			}
			$name     = MappingSchema::str( $tag->name ?? '' );
			$basetype = MappingSchema::str( $tag->basetype ?? '' );
			if ( $name === $m[1] && in_array( $basetype, array( 'checkbox', 'acceptance' ), true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param array<string,mixed> $config
	 * @return array<int,array<string,mixed>>
	 */
	public static function normalize_lists( array $config ): array {
		$lists = array();
		foreach ( FormSettings::lists( $config ) as $list ) {
			if ( ! is_array( $list ) || ! isset( $list['id'], $list['name'] ) ) {
				continue;
			}
			$stats   = isset( $list['stats'] ) && is_array( $list['stats'] ) ? $list['stats'] : array();
			$lists[] = array(
				'id'             => sanitize_text_field( MappingSchema::str( $list['id'] ) ),
				'name'           => sanitize_text_field( MappingSchema::str( $list['name'] ) ),
				'opt_in_process' => ! empty( $list['double_optin'] ) ? 'double_opt_in' : 'single_opt_in',
				'stats'          => array(
					'member_count'      => max( 0, MappingSchema::num( $stats['member_count'] ?? 0 ) ),
					'merge_field_count' => max( 0, MappingSchema::num( $stats['merge_field_count'] ?? 0 ) ),
				),
			);
		}

		return $lists;
	}

	/**
	 * @param mixed $fields
	 * @return array<int,array<string,mixed>>
	 */
	public static function sanitize_fields( $fields, int $limit ): array {
		return MappingSchema::normalize_fields( $fields, $limit );
	}

	/**
	 * @param array<int,array<string,mixed>> $lists
	 */
	private static function list_name( array $lists, string $selected ): string {
		foreach ( $lists as $list ) {
			if ( $selected === MappingSchema::str( $list['id'] ) ) {
				return MappingSchema::str( $list['name'] );
			}
		}

		return '';
	}

	/**
	 * @param array<mixed> $raw
	 * @return array<int,array<string,mixed>>
	 */
	private static function normalize_remote_fields( array $raw ): array {
		$fields = array(
			array(
				'tag'           => 'EMAIL',
				'name'          => __( 'Email address', 'contact-form-7-mailchimp-extension' ),
				'type'          => 'email',
				'display_order' => 0,
			),
		);
		usort(
			$raw,
			static function ( $a, $b ): int {
				return MappingSchema::num( is_array( $a ) ? ( $a['display_order'] ?? 0 ) : 0 ) - MappingSchema::num( is_array( $b ) ? ( $b['display_order'] ?? 0 ) : 0 );
			}
		);
		foreach ( $raw as $field ) {
			if ( ! is_array( $field ) || empty( $field['tag'] ) ) {
				continue;
			}
			$tag  = sanitize_text_field( MappingSchema::str( $field['tag'] ) );
			$type = sanitize_key( MappingSchema::str( $field['type'] ?? 'text' ) );
			if ( 'EMAIL' === strtoupper( $tag ) || in_array( $type, self::EXCLUDED_TYPES, true ) ) {
				continue;
			}
			$fields[] = array(
				'tag'           => $tag,
				'name'          => sanitize_text_field( MappingSchema::str( $field['name'] ?? $tag ) ),
				'type'          => $type,
				'display_order' => MappingSchema::num( $field['display_order'] ?? count( $fields ) ),
			);
		}

		return $fields;
	}
}
