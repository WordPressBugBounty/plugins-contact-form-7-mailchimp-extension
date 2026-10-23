<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

final class Cmatic_Mailchimp_Panel_Store {

	const EXCLUDED_TYPES = array( 'address', 'birthday', 'imageurl', 'zip' );
	const SLOT_MAX       = 52;

	/**
	 * @return array<string,mixed>
	 */
	public static function settings( int $form_id ): array {
		$config = get_option( 'cf7_mch_' . $form_id, array() );

		return is_array( $config ) ? $config : array();
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
			return self::str( $manager->resolve_api_key( $form_id, '', $config ) );
		}

		return self::str( $config['api'] ?? '' );
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
		if ( 1 !== self::num( $validation['api-validation'] ?? 0 ) ) {
			return new WP_Error( 'invalid_provider_key', esc_html__( 'Mailchimp rejected this key.', 'contact-form-7-mailchimp-extension' ), array( 'status' => 400 ) );
		}
		$lists = Cmatic_Lite_Api_Service::get_lists( $key, $logging );
		$items = isset( $lists['lisdata']['lists'] ) && is_array( $lists['lisdata']['lists'] ) ? $lists['lisdata']['lists'] : array();
		if ( array() === $items && ! isset( $lists['lisdata']['lists'] ) ) {
			return new WP_Error( 'provider_lists_failed', esc_html__( 'The key is valid, but the audiences could not be loaded.', 'contact-form-7-mailchimp-extension' ), array( 'status' => 502 ) );
		}
		$previous    = self::str( $config['api'] ?? '' );
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
		$selected = self::selected_list( $config );
		if ( $key_changed || ( '' !== $selected && ! self::list_exists( $items, $selected ) ) ) {
			$changes = array_merge( $changes, self::empty_account_state() );
		}
		if ( $drop_oauth ) {
			$manager = Cmatic_Lite_Container::get( 'auth.manager' );
			if ( is_object( $manager ) && method_exists( $manager, 'disconnect' ) ) {
				$manager->disconnect( $form_id );
			}
		}
		$config = array_merge( self::settings( $form_id ), $changes );
		if ( $drop_oauth ) {
			unset( $config['auth_type'], $config['api_key_backup'], $config['oauth_account_name'] );
		}
		update_option( 'cf7_mch_' . $form_id, $config );
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
		$config = self::settings( $form_id );
		if ( '' === $list_id ) {
			return array(
				'success'            => true,
				'provider'           => 'mailchimp',
				'list_id'            => '',
				'merge_fields'       => array(),
				'locked_fields'      => array(),
				'total_merge_fields' => 0,
				'mappings'           => self::empty_mappings( $limit ),
			);
		}
		if ( ! self::list_exists( self::raw_lists( $config ), $list_id ) ) {
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
		$mappings = self::reconcile_mappings( $config, $capped, $limit );
		$config   = array_merge(
			self::settings( $form_id ),
			array(
				'merge_fields'       => $capped,
				'locked_fields'      => $locked,
				'total_merge_fields' => count( $all ),
			)
		);
		update_option( 'cf7_mch_' . $form_id, $config );
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
		$config                   = self::settings( $form_id );
		$config['api']            = '';
		$config['api-validation'] = 0;
		unset( $config['auth_type'], $config['api_key_backup'], $config['oauth_account_name'] );
		update_option( 'cf7_mch_' . $form_id, $config );

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
		$fields   = self::normalize_stored_fields( $config, $limit );
		$lists    = self::normalize_lists( $config );
		$selected = self::selected_list( $config );
		$accept   = trim( self::str( $config['accept'] ?? '' ) );
		$mappings = array();
		foreach ( range( 3, $limit + 2 ) as $index ) {
			$mappings[ 'field' . $index ] = sanitize_text_field( self::str( $config[ 'field' . $index ] ?? '' ) );
		}
		$connected = self::credential_present( $config ) && 1 === self::num( $config['api-validation'] ?? 0 );
		$mapped    = false;
		foreach ( $fields as $offset => $field ) {
			if ( 'EMAIL' === strtoupper( self::str( $field['tag'] ) ) ) {
				$mapped = '' !== self::str( $mappings[ 'field' . ( $offset + 3 ) ] ?? '' );
			}
		}
		$base_groups = Cmatic_Mailerlite_Routing_Resolver::base_groups( $config );
		if ( '' !== $selected ) {
			$base_groups = array_values( array_unique( array_merge( array( $selected ), array_diff( $base_groups, array( $selected ) ) ) ) );
		}
		$state = array(
			'connected'                  => $connected,
			'credential_present'         => self::credential_present( $config ),
			'auth_type'                  => self::auth_type( $config ),
			'lists'                      => $lists,
			'selected_list'              => $selected,
			'selected_list_name'         => self::list_name( $lists, $selected ),
			'fields'                     => $fields,
			'locked_fields'              => self::normalize_locked_fields( $config ),
			'total_fields'               => max( count( $fields ), self::num( $config['total_merge_fields'] ?? 0 ) ),
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
			'routing_entitled'           => Cmatic_Lite_Esp_Capabilities::feature_enabled( 'destination_routing', 'mailchimp', $form_id ),
			'base_groups'                => $base_groups,
			'additional_groups'          => array_values( array_diff( $base_groups, array( $selected ) ) ),
			'routing_rules'              => Cmatic_Mailerlite_Routing_Resolver::normalize_rules( $config ),
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
		$list         = sanitize_text_field( self::str( $posted['primary_group'] ?? '' ) );
		if ( '' === $list ) {
			$list = sanitize_text_field( self::str( $posted['list'] ?? '' ) );
		}
		$merge_fields = self::sanitize_fields( $posted['merge_fields'] ?? array(), $limit );
		if ( '' === $list || array() === $merge_fields || ! self::list_exists( self::raw_lists( $current ), $list ) ) {
			return null;
		}
		$form_id  = is_object( $contact_form ) && method_exists( $contact_form, 'id' ) ? (int) $contact_form->id() : 0;
		$entitled = Cmatic_Lite_Esp_Capabilities::feature_enabled( 'destination_routing', 'mailchimp', $form_id );
		if ( ! $entitled && Cmatic_Mailerlite_Routing_Resolver::is_premium_configured( $current ) ) {
			$list = self::selected_list( $current );
		} else {
			$form_tags = class_exists( 'Cmatic_Form_Tags' ) && is_object( $contact_form ) ? Cmatic_Form_Tags::get_tags_with_types( $contact_form ) : array();
			$routing   = Cmatic_Mailerlite_Routing_Resolver::sanitize( $list, $posted, $current, $form_tags );
			if ( null === $routing ) {
				return null;
			}
			$current['routing_schema'] = 1;
			$current['base_groups']    = $routing['base_groups'];
			$current['routing_rules']  = $routing['routing_rules'];
		}
		$current['list']               = $list;
		$current['merge_fields']       = $merge_fields;
		$current['total_merge_fields'] = max( count( $merge_fields ), absint( self::num( $posted['total_merge_fields'] ?? 0 ) ) );
		foreach ( range( 3, $limit + 2 ) as $index ) {
			$key = 'field' . $index;
			if ( isset( $posted[ $key ] ) ) {
				$current[ $key ] = sanitize_text_field( self::str( $posted[ $key ] ) );
			}
		}
		$email_mapped = false;
		foreach ( $merge_fields as $offset => $field ) {
			if ( 'EMAIL' === strtoupper( self::str( $field['tag'] ) ) ) {
				$email_mapped = '' !== trim( self::str( $current[ 'field' . ( $offset + 3 ) ] ?? '' ) );
			}
		}
		if ( ! $email_mapped ) {
			return null;
		}
		$gate  = 'required' === sanitize_key( self::str( $posted['consent_gate'] ?? 'none' ) );
		$field = sanitize_text_field( self::str( $posted['consent_field'] ?? '' ) );
		if ( $gate && ! self::is_consent_field( $field, $contact_form ) ) {
			return null;
		}
		$current['accept']   = $gate ? $field : ' ';
		$current['confsubs'] = 'pending' === sanitize_key( self::str( $posted['double_optin'] ?? '' ) ) ? '1' : '0';

		return $current;
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
			$name     = self::str( $tag->name ?? '' );
			$basetype = self::str( $tag->basetype ?? '' );
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
		foreach ( self::raw_lists( $config ) as $list ) {
			if ( ! is_array( $list ) || ! isset( $list['id'], $list['name'] ) ) {
				continue;
			}
			$stats   = isset( $list['stats'] ) && is_array( $list['stats'] ) ? $list['stats'] : array();
			$lists[] = array(
				'id'             => sanitize_text_field( self::str( $list['id'] ) ),
				'name'           => sanitize_text_field( self::str( $list['name'] ) ),
				'opt_in_process' => ! empty( $list['double_optin'] ) ? 'double_opt_in' : 'single_opt_in',
				'stats'          => array(
					'member_count'      => max( 0, self::num( $stats['member_count'] ?? 0 ) ),
					'merge_field_count' => max( 0, self::num( $stats['merge_field_count'] ?? 0 ) ),
				),
			);
		}

		return $lists;
	}

	/**
	 * @param array<string,mixed> $config
	 * @return array<int,mixed>
	 */
	private static function raw_lists( array $config ): array {
		if ( ! isset( $config['lisdata'] ) || ! is_array( $config['lisdata'] ) ) {
			return array();
		}

		return isset( $config['lisdata']['lists'] ) && is_array( $config['lisdata']['lists'] ) ? $config['lisdata']['lists'] : array();
	}

	/**
	 * @param array<int,mixed> $lists
	 */
	private static function list_exists( array $lists, string $list_id ): bool {
		foreach ( $lists as $list ) {
			if ( is_array( $list ) && isset( $list['id'] ) && is_scalar( $list['id'] ) && $list_id === self::str( $list['id'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param array<int,array<string,mixed>> $lists
	 */
	private static function list_name( array $lists, string $selected ): string {
		foreach ( $lists as $list ) {
			if ( $selected === self::str( $list['id'] ) ) {
				return self::str( $list['name'] );
			}
		}

		return '';
	}

	/**
	 * @param array<string,mixed> $config
	 */
	private static function selected_list( array $config ): string {
		$selected = $config['list'] ?? '';
		if ( is_array( $selected ) ) {
			$first    = reset( $selected );
			$selected = false === $first ? '' : $first;
		}

		return trim( sanitize_text_field( self::str( $selected ) ) );
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
				return self::num( is_array( $a ) ? ( $a['display_order'] ?? 0 ) : 0 ) - self::num( is_array( $b ) ? ( $b['display_order'] ?? 0 ) : 0 );
			}
		);
		foreach ( $raw as $field ) {
			if ( ! is_array( $field ) || empty( $field['tag'] ) ) {
				continue;
			}
			$tag  = sanitize_text_field( self::str( $field['tag'] ) );
			$type = sanitize_key( self::str( $field['type'] ?? 'text' ) );
			if ( 'EMAIL' === strtoupper( $tag ) || in_array( $type, self::EXCLUDED_TYPES, true ) ) {
				continue;
			}
			$fields[] = array(
				'tag'           => $tag,
				'name'          => sanitize_text_field( self::str( $field['name'] ?? $tag ) ),
				'type'          => $type,
				'display_order' => self::num( $field['display_order'] ?? count( $fields ) ),
			);
		}

		return $fields;
	}

	/**
	 * @param array<string,mixed> $config
	 * @return array<int,array<string,mixed>>
	 */
	private static function normalize_stored_fields( array $config, int $limit ): array {
		return self::sanitize_fields( $config['merge_fields'] ?? array(), $limit );
	}

	/**
	 * @param array<string,mixed> $config
	 * @return array<int,array<string,mixed>>
	 */
	private static function normalize_locked_fields( array $config ): array {
		return self::sanitize_fields( $config['locked_fields'] ?? array(), self::SLOT_MAX );
	}

	/**
	 * @param mixed $fields
	 * @return array<int,array<string,mixed>>
	 */
	public static function sanitize_fields( $fields, int $limit ): array {
		if ( ! is_array( $fields ) ) {
			return array();
		}
		$sanitized = array();
		foreach ( array_slice( $fields, 0, $limit ) as $offset => $field ) {
			if ( ! is_array( $field ) || empty( $field['tag'] ) ) {
				continue;
			}
			$tag         = sanitize_text_field( self::str( $field['tag'] ) );
			$sanitized[] = array(
				'tag'           => $tag,
				'name'          => sanitize_text_field( self::str( $field['name'] ?? $tag ) ),
				'type'          => sanitize_key( self::str( $field['type'] ?? 'text' ) ),
				'display_order' => isset( $field['display_order'] ) ? self::num( $field['display_order'] ) : self::num( $offset ),
			);
		}

		return $sanitized;
	}

	/**
	 * @param array<string,mixed>            $config
	 * @param array<int,array<string,mixed>> $fields
	 * @return array<string,string>
	 */
	private static function reconcile_mappings( array $config, array $fields, int $limit ): array {
		$by_tag = array();
		foreach ( self::previous_fields( $config ) as $offset => $old ) {
			$by_tag[ strtoupper( self::str( $old['tag'] ) ) ] = sanitize_text_field( self::str( $config[ 'field' . ( $offset + 3 ) ] ?? '' ) );
		}
		$mappings = self::empty_mappings( $limit );
		foreach ( $fields as $offset => $field ) {
			$mappings[ 'field' . ( $offset + 3 ) ] = $by_tag[ strtoupper( self::str( $field['tag'] ) ) ] ?? '';
		}

		return $mappings;
	}

	/**
	 * @param array<string,mixed> $config
	 * @return array<int,array<string,mixed>>
	 */
	private static function previous_fields( array $config ): array {
		$own = self::normalize_stored_fields( $config, self::SLOT_MAX );
		if ( array() !== $own ) {
			return $own;
		}
		if ( ! isset( $config['merge-vars'] ) || ! is_array( $config['merge-vars'] ) ) {
			return array();
		}
		$fields = array( array( 'tag' => 'EMAIL', 'name' => 'Email', 'type' => 'email', 'display_order' => 0 ) );
		foreach ( $config['merge-vars'] as $field ) {
			if ( is_array( $field ) && ! empty( $field['tag'] ) && 'EMAIL' !== strtoupper( self::str( $field['tag'] ) ) ) {
				$fields[] = array( 'tag' => self::str( $field['tag'] ), 'name' => self::str( $field['name'] ?? '' ), 'type' => self::str( $field['type'] ?? 'text' ), 'display_order' => count( $fields ) );
			}
		}

		return $fields;
	}

	/**
	 * @return array<string,string>
	 */
	private static function empty_mappings( int $limit ): array {
		$mappings = array();
		foreach ( range( 3, $limit + 2 ) as $index ) {
			$mappings[ 'field' . $index ] = '';
		}

		return $mappings;
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function empty_account_state(): array {
		$state = array(
			'list'               => '',
			'merge_fields'       => array(),
			'locked_fields'      => array(),
			'total_merge_fields' => 0,
		);
		foreach ( range( 3, self::SLOT_MAX ) as $index ) {
			$state[ 'field' . $index ] = '';
		}

		return $state;
	}

	/**
	 * @param mixed $value
	 */
	private static function str( $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * @param mixed $value
	 */
	private static function num( $value ): int {
		return is_numeric( $value ) ? (int) $value : 0;
	}
}
