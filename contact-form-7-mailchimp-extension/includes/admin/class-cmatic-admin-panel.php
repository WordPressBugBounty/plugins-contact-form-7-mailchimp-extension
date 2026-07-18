<?php
/**
 * CF7 admin panel integration.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.FunctionComment.MissingParamTag -- Preserve the established documentation style in this legacy global class.
final class Cmatic_Admin_Panel {

	private const PANEL_KEY = 'Chimpmatic';

	/**
	 * Settings captured before another saver runs.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private static $settings_before_save = array();

	public static function init(): void {
		add_filter( 'wpcf7_editor_panels', array( __CLASS__, 'register_panel' ) );
		add_filter( 'wpcf7_editor_panels', array( __CLASS__, 'append_lite_settings_to_pro_panel' ), 20 );
		add_action( 'wpcf7_after_save', array( __CLASS__, 'capture_settings' ), 1 );
		add_action( 'wpcf7_after_save', array( __CLASS__, 'save_settings' ), 10 );
		add_action( 'wpcf7_after_save', array( __CLASS__, 'save_provider_settings' ), 12 );
		add_action( 'wpcf7_admin_misc_pub_section', array( __CLASS__, 'render_sidebar_info' ) );
		add_action( 'wpcf7_admin_footer', array( __CLASS__, 'render_footer_banner' ), 10, 1 );
	}

	public static function register_panel( array $panels ): array {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! current_user_can( 'wpcf7_edit_contact_form', $post_id ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- Contact Form 7 registers and maps this capability.
			return $panels;
		}
		if (
			class_exists( 'Cmatic_Pro_Esp_Bridge' )
			&& Cmatic_Pro_Esp_Bridge::is_compatible()
		) {
			return $panels;
		}

		$panel_key            = defined( 'CMATIC_VERSION' ) ? 'Chimpmatic Providers' : self::PANEL_KEY;
		$panels[ $panel_key ] = array(
			'title'    => defined( 'CMATIC_VERSION' ) ? __( 'Email Providers', 'chimpmatic-lite' ) : __( 'Chimpmatic', 'chimpmatic-lite' ),
			'callback' => array( __CLASS__, 'render_panel' ),
		);

		return $panels;
	}

	public static function append_lite_settings_to_pro_panel( array $panels ): array {
		if (
			! class_exists( 'Cmatic_Pro_Esp_Bridge' )
			|| ! Cmatic_Pro_Esp_Bridge::is_compatible()
			|| ! isset( $panels[ self::PANEL_KEY ]['callback'] )
			|| 'wpcf7_chimp_add_mailchimp' !== $panels[ self::PANEL_KEY ]['callback']
		) {
			return $panels;
		}

		$panels[ self::PANEL_KEY ]['callback'] = array( __CLASS__, 'render_pro_panel_with_lite_settings' );

		return $panels;
	}

	public static function render_pro_panel_with_lite_settings( $contact_form ): void {
		if ( ! function_exists( 'wpcf7_chimp_add_mailchimp' ) ) {
			return;
		}

		wpcf7_chimp_add_mailchimp( $contact_form );
		Cmatic_Advanced_Settings::render_signls_section();
	}

	public static function render_panel( $contact_form ): void {
		$form_id   = (int) ( $contact_form->id() ?? 0 );
		$cf7_mch   = get_option( 'cf7_mch_' . $form_id, array() );
		$cf7_mch   = is_array( $cf7_mch ) ? $cf7_mch : array();
		$provider  = empty( $cf7_mch ) ? '' : Cmatic_Lite_Esp_Registry::get_selected( $cf7_mch );
		$form_tags = Cmatic_Form_Tags::get_tags_with_types( $contact_form );
		$api_valid = (int) ( $cf7_mch['api-validation'] ?? 0 );
		$list_data = isset( $cf7_mch['lisdata'] ) && is_array( $cf7_mch['lisdata'] )
		? $cf7_mch['lisdata']
		: null;

		if ( class_exists( 'Cmatic_Data_Container' ) ) {
			$extra_data = array();
			if ( isset( $cf7_mch['auth_type'] ) && 'oauth' === $cf7_mch['auth_type'] ) {
				$extra_data['auth_type'] = 'oauth';
			}
			Cmatic_Data_Container::render_open( $form_id, (string) $api_valid, $extra_data );
		} else {
			echo '<div class="cmatic-inner">';
		}

		if ( class_exists( 'Cmatic_Header' ) ) {
			Cmatic_Header::output(
				array(
					'api_status'       => '' === $provider ? null : Cmatic_Lite_Esp_Panel::get_api_status( $provider, $cf7_mch, $form_id ),
					'provider'         => $provider,
					'provider_options' => Cmatic_Lite_Esp_Manifest::selector_options(),
				)
			);
		}

		echo '<div class="cmatic-content">';
		printf(
			'<div id="cmatic-mailchimp-settings" data-cmatic-provider-view="mailchimp"%s>',
			'mailchimp' === $provider ? '' : ' hidden'
		);

		if ( defined( 'CMATIC_VERSION' ) ) {
			Cmatic_Lite_Esp_Panel::render_pro_mailchimp_notice();
		} else {
			Cmatic_Api_Panel::render( $cf7_mch, (string) $api_valid, $form_id );
			if ( class_exists( 'Cmatic_Audiences' ) ) {
				Cmatic_Audiences::render( (string) $api_valid, $list_data, $cf7_mch );
			}
			Cmatic_Field_Mapper_UI::render( $api_valid, $list_data, $cf7_mch, $form_tags, $form_id );
			Cmatic_Panel_Toggles::cmatic_render();
			if ( class_exists( 'Cmatic_Contact_Lookup' ) ) {
				Cmatic_Contact_Lookup::cmatic_render( array( 'form_id' => $form_id ) );
			}
			Cmatic_Log_Viewer::render();
			echo '<div id="cme-container" class="mce-custom-fields vc-advanced-settings">';
			Cmatic_Advanced_Settings::render();
			echo '</div>';
			echo '<div class="vc-hidden-start dev-cta mce-cta welcome-panel">';
			echo '<div class="welcome-panel-content">';
			echo Cmatic_Banners::get_welcome(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '</div></div>';
		}
		echo '</div>';

		Cmatic_Lite_Esp_Panel::render( $provider, $cf7_mch, $form_tags, $form_id );
		echo '</div>';

		if ( class_exists( 'Cmatic_Data_Container' ) ) {
			Cmatic_Data_Container::render_close();
		} else {
			echo '</div>';
		}
	}

	public static function save_settings( $contact_form ): void {
		if ( ! isset( $_POST['wpcf7-mailchimp'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The nonce is verified by can_save() before form data is read.
			return;
		}

		$form_id = (int) $contact_form->id();
		if ( ! self::can_save( $form_id ) ) {
			return;
		}

		if ( class_exists( 'Cmatic\\Metrics\\Core\\Sync' ) && class_exists( 'Cmatic\\Metrics\\Core\\Collector' ) ) {
			$payload = \Cmatic\Metrics\Core\Collector::collect( 'form_saved' );
			\Cmatic\Metrics\Core\Sync::send( $payload );
		}

		$option_name  = 'cf7_mch_' . $form_id;
		$old_settings = get_option( $option_name, array() );
		$old_settings = is_array( $old_settings ) ? $old_settings : array();
		$posted_data  = wp_unslash( $_POST['wpcf7-mailchimp'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- Verified by can_save(); sanitized below.
		if ( ! is_array( $posted_data ) ) {
			return;
		}
		$sanitized = self::sanitize_settings( $posted_data, $old_settings );
		if ( array_key_exists( 'api', $sanitized ) && (string) ( $old_settings['api'] ?? '' ) !== $sanitized['api'] ) {
			$sanitized['api-validation'] = 0;
			$sanitized['lisdata']        = array();
			$sanitized['list']           = '';
		}

		if ( ! empty( $sanitized['api'] ) && isset( $old_settings['auth_type'] ) && 'oauth' === $old_settings['auth_type'] ) {
			$auth_manager = Cmatic_Lite_Container::get( 'auth.manager' );
			if ( $auth_manager ) {
				$auth_manager->disconnect( $form_id );
			}
			unset( $sanitized['auth_type'] );
			unset( $sanitized['api_key_backup'] );
			$sanitized['lisdata']        = array();
			$sanitized['list']           = '';
			$sanitized['api-validation'] = 0;
			$old_settings                = get_option( $option_name, array() );
			$old_settings                = is_array( $old_settings ) ? $old_settings : array();
		}

		$updated_settings = array_merge( $old_settings, $sanitized );

		update_option( $option_name, self::mirror_legacy_mappings( $updated_settings ) );
	}


	private static function mirror_legacy_mappings( array $settings ): array {
		if ( empty( $settings['merge_fields'] ) || ! is_array( $settings['merge_fields'] ) ) {
			return $settings;
		}

		$row         = 1;
		$field_index = 3;
		foreach ( $settings['merge_fields'] as $merge_field ) {
			$tag       = isset( $merge_field['tag'] ) ? (string) $merge_field['tag'] : '';
			$field_key = 'field' . $field_index;
			$mapped    = isset( $settings[ $field_key ] ) ? (string) $settings[ $field_key ] : '';
			++$field_index;
			if ( '' === $tag || '' === $mapped ) {
				continue;
			}
			$field_name = preg_match( '/\[\s*([a-zA-Z_][0-9a-zA-Z:._-]*)\s*\]/', $mapped, $m ) ? $m[1] : trim( $mapped );
			if ( '' === $field_name ) {
				continue;
			}
			$settings[ "CustomKey{$row}" ]     = $tag;
			$settings[ "CustomValue{$row}" ]   = $field_name;
			$settings[ "CustomKeyType{$row}" ] = isset( $merge_field['type'] ) ? (string) $merge_field['type'] : 'text';
			if ( 'EMAIL' === $tag ) {
				$settings['email'] = $field_name;
			}
			++$row;
		}

		for ( $i = $row; $i <= 50; $i++ ) {
			if ( ! isset( $settings[ "CustomKey{$i}" ] ) && ! isset( $settings[ "CustomValue{$i}" ] ) ) {
				break;
			}
			unset( $settings[ "CustomKey{$i}" ], $settings[ "CustomValue{$i}" ], $settings[ "CustomKeyType{$i}" ] );
		}

		return $settings;
	}

	private static function sanitize_settings( array $posted, array $old ): array {
		$sanitized   = array();
		$text_fields = array( 'api', 'list', 'accept' );

		$max_index = CMATIC_LITE_FIELDS + 2;
		for ( $i = 3; $i <= $max_index; $i++ ) {
			$text_fields[] = 'field' . $i;
		}

		for ( $i = 1; $i <= 10; $i++ ) {
			$text_fields[] = 'CustomValue' . $i;
			$text_fields[] = 'CustomKey' . $i;
		}

		foreach ( $text_fields as $field ) {
			if ( isset( $posted[ $field ] ) ) {
				$value               = trim( sanitize_text_field( $posted[ $field ] ) );
				$sanitized[ $field ] = $value;
			}
		}

		if ( isset( $sanitized['api'] ) && strpos( $sanitized['api'], '•' ) !== false ) {
			if ( ! empty( $old['api'] ) && strpos( $old['api'], '•' ) === false ) {
				$sanitized['api'] = $old['api'];
			}
		}

		$checkboxes = array( 'cfactive', 'addunsubscr' );
		foreach ( $checkboxes as $field ) {
			$sanitized[ $field ] = isset( $posted[ $field ] ) ? '1' : '0';
		}

		$sanitized['confsubs'] = isset( $posted['confsubs'] ) && '1' === $posted['confsubs'] ? '1' : '0';

		return $sanitized;
	}

	/**
	 * Capture the complete option before Lite or Pro saves it.
	 *
	 * @param WPCF7_ContactForm $contact_form Contact form being saved.
	 */
	public static function capture_settings( $contact_form ): void {
		$form_id                                = (int) $contact_form->id();
		$settings                               = get_option( 'cf7_mch_' . $form_id, array() );
		self::$settings_before_save[ $form_id ] = is_array( $settings ) ? $settings : array();
	}

	/**
	 * Restore omitted state and save the provider selector/mappings.
	 *
	 * @param WPCF7_ContactForm $contact_form Contact form being saved.
	 */
	public static function save_provider_settings( $contact_form ): void {
		if ( ! isset( $_POST['wpcf7-cmatic-provider'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The nonce is verified by can_save() before form data is read.
			return;
		}
		$form_id = (int) $contact_form->id();
		if ( ! self::can_save( $form_id ) ) {
			return;
		}
		$posted = wp_unslash( $_POST['wpcf7-cmatic-provider'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- Verified by can_save(); each accepted field is sanitized below.
		if ( ! is_array( $posted ) ) {
			return;
		}
		$slug = isset( $posted['provider'] ) ? sanitize_key( (string) $posted['provider'] ) : '';
		if ( '' === $slug || ! Cmatic_Lite_Esp_Registry::has( $slug ) ) {
			return;
		}
			$field_limit = Cmatic_Lite_Esp_Capabilities::field_limit( $slug, $form_id );
		$option          = 'cf7_mch_' . $form_id;
		$current         = get_option( $option, array() );
		$current         = is_array( $current ) ? $current : array();
		$before          = self::$settings_before_save[ $form_id ] ?? array();
		if ( defined( 'CMATIC_VERSION' ) ) {
			$current = array_replace( $before, $current );
		}
		$current['provider'] = $slug;
		if ( 'mailchimp' !== $slug ) {
			$current['providers'] = isset( $current['providers'] ) && is_array( $current['providers'] ) ? $current['providers'] : array();
			$settings             = isset( $current['providers'][ $slug ] ) && is_array( $current['providers'][ $slug ] ) ? $current['providers'][ $slug ] : array();
			$list                 = 'mailerlite' === $slug
				? sanitize_text_field( (string) ( $posted['primary_group'] ?? '' ) )
				: sanitize_text_field( (string) ( $posted['list'] ?? '' ) );
			if ( 'mailerlite' === $slug && ! Cmatic_Lite_Esp_Capabilities::feature_enabled( 'mailerlite_routing', $slug, $form_id ) && Cmatic_Mailerlite_Routing_Resolver::is_premium_configured( $settings ) ) {
				$list = self::stored_primary_group( $settings );
			}
				$merge_fields = self::sanitize_provider_fields( $posted['merge_fields'] ?? array(), $field_limit );
			if ( '' === $list || empty( $merge_fields ) || ! self::provider_list_exists( $settings, $list ) ) {
				return;
			}
			$settings['list'] = $list;
			if ( 'mailerlite' === $slug ) {
				$routing_entitled = Cmatic_Lite_Esp_Capabilities::feature_enabled( 'mailerlite_routing', $slug, $form_id );
				if ( ! $routing_entitled && Cmatic_Mailerlite_Routing_Resolver::is_premium_configured( $settings ) ) {
					$list             = self::stored_primary_group( $settings );
					$settings['list'] = $list;
				} else {
					$routing = self::sanitize_mailerlite_routing( $list, $posted, $settings, $contact_form );
					if ( null === $routing ) {
						return;
					}
					$settings['routing_schema'] = 1;
					$settings['base_groups']    = $routing['base_groups'];
					$settings['routing_rules']  = $routing['routing_rules'];
				}
			}
			$settings['merge_fields']       = $merge_fields;
			$settings['total_merge_fields'] = isset( $posted['total_merge_fields'] ) ? max( count( $merge_fields ), absint( $posted['total_merge_fields'] ) ) : count( $merge_fields );
			foreach ( range( 3, $field_limit + 2 ) as $index ) {
				$key = 'field' . $index;
				if ( isset( $posted[ $key ] ) ) {
					$settings[ $key ] = sanitize_text_field( (string) $posted[ $key ] );
				}
			}
			if ( ! self::has_required_email_mapping( $settings, $field_limit ) ) {
				return;
			}
			if ( 'mailerlite' === $slug && ! self::mailerlite_boolean_mappings_valid( $settings, $contact_form, $field_limit ) ) {
				return;
			}
			if ( Cmatic_Lite_Esp_Capabilities::feature_enabled( 'advanced_consent', $slug, $form_id ) ) {
				$consent = self::sanitize_provider_consent( $posted, $slug, $contact_form );
				if ( null === $consent ) {
					return;
				}
				if ( 'brevo' === $slug && 'double' === $consent['subscription_mode'] ) {
					$token       = isset( $posted['doi_verification_token'] ) ? sanitize_text_field( (string) $posted['doi_verification_token'] ) : '';
					$expected    = array(
						'form_id'       => $form_id,
						'provider'      => $slug,
						'list_id'       => $list,
						'template_id'   => $consent['doi_template_id'],
						'redirect_hash' => hash( 'sha256', $consent['doi_redirect_url'] ),
					);
					$current_key = Cmatic_Lite_Esp_Credentials::get( $form_id, $slug );
					if ( '' === $current_key ) {
						return;
					}
					$expected['credential_fingerprint'] = Cmatic_Lite_Esp_Rest_Controller::credential_fingerprint( $current_key );
					unset( $current_key );
					if ( ! Cmatic_Lite_Esp_Rest_Controller::verify_consent_token( $token, $expected ) ) {
						return;
					}
					$consent['doi_verified'] = 1;
				}
				$settings = array_merge( $settings, $consent );
			}
			if ( 'mailerlite' === $slug ) {
				$settings = self::save_mailerlite_options( $settings, $posted, $contact_form, $form_id );
				if ( empty( $settings ) ) {
					return;
				}
				if ( ! Cmatic_Mailerlite_Runtime_Policy::apply( $settings, self::mailerlite_entitlements( $form_id ) )['degraded'] ) {
					Cmatic_Mailerlite_Degradation_Reporter::clear( $form_id );
				}
			}
				$current['providers'][ $slug ] = $settings;
		}
		update_option( $option, $current );
		unset( self::$settings_before_save[ $form_id ] );
	}

	private static function sanitize_provider_fields( $fields, int $field_limit ): array {
		if ( ! is_array( $fields ) ) {
			return array();
		}
		$sanitized = array();
		foreach ( array_slice( $fields, 0, $field_limit ) as $offset => $field ) {
			if ( ! is_array( $field ) || empty( $field['tag'] ) ) {
				continue;
			}
			$tag         = sanitize_text_field( (string) $field['tag'] );
			$sanitized[] = array(
				'tag'           => $tag,
				'name'          => sanitize_text_field( (string) ( $field['name'] ?? $tag ) ),
				'type'          => sanitize_key( (string) ( $field['type'] ?? 'text' ) ),
				'display_order' => isset( $field['display_order'] ) ? (int) $field['display_order'] : $offset,
			);
		}
		return $sanitized;
	}

	private static function provider_list_exists( array $settings, string $list_id ): bool {
		$lists = isset( $settings['lisdata']['lists'] ) && is_array( $settings['lisdata']['lists'] )
			? $settings['lisdata']['lists']
			: array();
		foreach ( $lists as $list ) {
			if ( is_array( $list ) && isset( $list['id'] ) && $list_id === (string) $list['id'] ) {
				return true;
			}
		}
		return false;
	}

	private static function stored_primary_group( array $settings ): string {
		$list = $settings['list'] ?? '';
		if ( is_array( $list ) ) {
			$list = reset( $list );
		}
		return sanitize_text_field( (string) $list );
	}

	private static function sanitize_mailerlite_routing( string $primary, array $posted, array $settings, $contact_form ): ?array {
		if ( '' === $primary || ! self::provider_list_exists( $settings, $primary ) ) {
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
			$group_id = sanitize_text_field( (string) $group_id );
			if ( '' !== $group_id && $primary !== $group_id && self::provider_list_exists( $settings, $group_id ) ) {
				$groups[] = $group_id;
			}
		}
		$groups = array_slice( array_values( array_unique( $groups ) ), 0, 20 );

		$choice_index = array();
		foreach ( Cmatic_Form_Tags::get_tags_with_types( $contact_form ) as $tag ) {
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
			if ( ! isset( $choice_index[ $field ] ) || ! in_array( $value, $choice_index[ $field ], true ) || ! self::provider_list_exists( $settings, $group_id ) ) {
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

	/**
	 * Ensures MailerLite Boolean fields only map to Contact Form 7 acceptance tags.
	 *
	 * @param array $settings     Provider settings being saved.
	 * @param mixed $contact_form Contact Form 7 form.
	 * @param int   $field_limit  Effective mapping limit.
	 */
	private static function mailerlite_boolean_mappings_valid( array $settings, $contact_form, int $field_limit ): bool {
		$types = array();
		foreach ( Cmatic_Form_Tags::get_tags_with_types( $contact_form ) as $tag ) {
			if ( is_array( $tag ) && isset( $tag['name'] ) && is_scalar( $tag['name'] ) ) {
				$types[ (string) $tag['name'] ] = self::scalar_string( $tag['basetype'] ?? '' );
			}
		}

		foreach ( array_slice( (array) ( $settings['merge_fields'] ?? array() ), 0, $field_limit ) as $offset => $definition ) {
			if ( ! is_array( $definition ) || 'boolean' !== sanitize_key( self::scalar_string( $definition['type'] ?? '' ) ) ) {
				continue;
			}
			$mapping = self::scalar_string( $settings[ 'field' . ( $offset + 3 ) ] ?? '' );
			if ( '' === $mapping ) {
				continue;
			}
			if ( 1 !== preg_match( '/^\[([A-Za-z0-9_-]+)\]$/', $mapping, $matches ) || 'acceptance' !== ( $types[ $matches[1] ] ?? '' ) ) {
				return false;
			}
		}

		return true;
	}

	private static function save_mailerlite_options( array $settings, array $posted, $contact_form, int $form_id ): array {
		$status_entitled = Cmatic_Lite_Esp_Capabilities::feature_enabled( 'mailerlite_status', 'mailerlite', $form_id );
		$resub_entitled  = Cmatic_Lite_Esp_Capabilities::feature_enabled( 'mailerlite_resubscribe', 'mailerlite', $form_id );
		$meta_entitled   = Cmatic_Lite_Esp_Capabilities::feature_enabled( 'mailerlite_consent_metadata', 'mailerlite', $form_id );
		if ( $status_entitled ) {
			$mode = sanitize_key( (string) ( $posted['status_mode'] ?? 'legacy_provider_managed' ) );
			if ( ! in_array( $mode, array( 'legacy_provider_managed', 'account', 'active', 'unconfirmed' ), true ) ) {
				return array();
			}
			$settings['status_mode'] = $mode;
			if ( $resub_entitled ) {
				$force = ! empty( $posted['resubscribe_force'] );
				if ( $force && ( 'active' !== $mode || ! self::eligible_acceptance_field( self::scalar_string( $settings['consent_field'] ?? '' ), $contact_form ) ) ) {
					return array();
				}
				$settings['resubscribe_force'] = $force ? 1 : 0;
			}
		}

		if ( $meta_entitled ) {
			$enabled = ! empty( $posted['consent_metadata_enabled'] );
			if ( $enabled && ! self::eligible_acceptance_field( self::scalar_string( $settings['consent_field'] ?? '' ), $contact_form ) ) {
				return array();
			}
			$settings['consent_metadata_enabled'] = $enabled ? 1 : 0;
			if ( $enabled ) {
				$field                        = self::scalar_string( $settings['consent_field'] ?? '' );
				$text                         = self::acceptance_text( $field, $contact_form );
				$settings['consent_snapshot'] = array(
					'field'       => $field,
					'text_hash'   => hash( 'sha256', $text ),
					'captured_at' => gmdate( 'Y-m-d H:i:s' ),
				);
			}
		}
		return $settings;
	}

	private static function eligible_acceptance_field( string $field, $contact_form ): bool {
		$field = trim( $field, '[]' );
		foreach ( Cmatic_Form_Tags::get_tags_with_types( $contact_form ) as $tag ) {
			if ( is_array( $tag ) && self::scalar_string( $tag['name'] ?? '' ) === $field && 'acceptance' === ( $tag['basetype'] ?? '' ) && ! empty( $tag['required'] ) && empty( $tag['inverted'] ) ) {
				return true;
			}
		}
		return false;
	}

	private static function acceptance_text( string $field, $contact_form ): string {
		$field = trim( $field, '[]' );
		foreach ( Cmatic_Form_Tags::get_tags_with_types( $contact_form ) as $tag ) {
			if ( is_array( $tag ) && self::scalar_string( $tag['name'] ?? '' ) === $field ) {
				return self::scalar_string( $tag['content'] ?? '' );
			}
		}
		return '';
	}

	private static function mailerlite_entitlements( int $form_id ): array {
		$result = array();
		foreach ( array( 'mailerlite_routing', 'mailerlite_status', 'mailerlite_resubscribe', 'mailerlite_consent_metadata' ) as $feature ) {
			$result[ $feature ] = Cmatic_Lite_Esp_Capabilities::feature_enabled( $feature, 'mailerlite', $form_id );
		}
		return $result;
	}

	private static function has_required_email_mapping( array $settings, int $field_limit ): bool {
		$fields = isset( $settings['merge_fields'] ) && is_array( $settings['merge_fields'] )
			? $settings['merge_fields']
			: array();
		foreach ( array_slice( $fields, 0, $field_limit ) as $offset => $field ) {
			if ( is_array( $field ) && 'EMAIL' === strtoupper( self::scalar_string( $field['tag'] ?? '' ) ) ) {
				return '' !== trim( self::scalar_string( $settings[ 'field' . ( $offset + 3 ) ] ?? '' ) );
			}
		}
		return false;
	}

	private static function sanitize_provider_consent( array $posted, string $slug, $contact_form ): ?array {
		$gate  = isset( $posted['consent_gate'] ) ? sanitize_key( (string) $posted['consent_gate'] ) : 'none';
		$gate  = in_array( $gate, array( 'none', 'required' ), true ) ? $gate : '';
		$field = isset( $posted['consent_field'] ) ? sanitize_text_field( (string) $posted['consent_field'] ) : '';
		if ( '' === $gate || ( 'required' === $gate && ! self::is_acceptance_field( $field, $contact_form ) ) ) {
			return null;
		}

		$consent = array(
			'consent_gate'      => $gate,
			'consent_field'     => 'required' === $gate ? $field : '',
			'subscription_mode' => 'provider_managed',
		);
		if ( 'brevo' !== $slug ) {
			return $consent;
		}

		$mode = isset( $posted['subscription_mode'] ) ? sanitize_key( (string) $posted['subscription_mode'] ) : 'single';
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

		$template_id = isset( $posted['doi_template_id'] ) ? absint( $posted['doi_template_id'] ) : 0;
		$redirect    = isset( $posted['doi_redirect_url'] ) ? esc_url_raw( (string) $posted['doi_redirect_url'] ) : '';
		if ( $template_id < 1 || 'https' !== wp_parse_url( $redirect, PHP_URL_SCHEME ) ) {
			return null;
		}
		$consent['doi_template_id']  = $template_id;
		$consent['doi_redirect_url'] = $redirect;
		$consent['doi_verified']     = 0;
		return $consent;
	}

	private static function is_acceptance_field( string $field, $contact_form ): bool {
		if ( 1 !== preg_match( '/^\[([a-zA-Z_][0-9a-zA-Z:._-]*)\]$/', $field, $match ) ) {
			return false;
		}
		foreach ( Cmatic_Form_Tags::get_tags_with_types( $contact_form ) as $tag ) {
			if (
				is_array( $tag )
				&& 'acceptance' === ( $tag['basetype'] ?? '' )
				&& self::scalar_string( $tag['name'] ?? '' ) === $match[1]
			) {
				return true;
			}
		}
		return false;
	}

	private static function scalar_string( $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Check the Contact Form 7 save nonce and capability.
	 *
	 * @param int $form_id Contact form ID.
	 * @return bool Whether the current request may save the form.
	 */
	private static function can_save( int $form_id ): bool {
		$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- Contact Form 7 registers this meta capability.
		return wp_verify_nonce( $nonce, sprintf( 'wpcf7-save-contact-form_%s', $form_id ) ) && current_user_can( 'wpcf7_edit_contact_form', $form_id );
	}

	public static function render_sidebar_info( int $post_id ): void {
		Cmatic_Sidebar_Panel::render_submit_info( $post_id );
	}

	public static function render_footer_banner( $post ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		Cmatic_Sidebar_Panel::render_footer_promo();
	}

	private function __construct() {}
}
