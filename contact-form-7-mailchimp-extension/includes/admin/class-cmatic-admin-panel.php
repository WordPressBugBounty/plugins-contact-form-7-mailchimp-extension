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
			$list                 = isset( $posted['list'] ) ? sanitize_text_field( (string) $posted['list'] ) : '';
				$merge_fields     = self::sanitize_provider_fields( $posted['merge_fields'] ?? array(), $field_limit );
			if ( '' === $list || empty( $merge_fields ) || ! self::provider_list_exists( $settings, $list ) ) {
				return;
			}
			$settings['list']               = $list;
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

	private static function has_required_email_mapping( array $settings, int $field_limit ): bool {
		$fields = isset( $settings['merge_fields'] ) && is_array( $settings['merge_fields'] )
			? $settings['merge_fields']
			: array();
		foreach ( array_slice( $fields, 0, $field_limit ) as $offset => $field ) {
			if ( is_array( $field ) && 'EMAIL' === strtoupper( (string) ( $field['tag'] ?? '' ) ) ) {
				return '' !== trim( (string) ( $settings[ 'field' . ( $offset + 3 ) ] ?? '' ) );
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
				&& (string) ( $tag['name'] ?? '' ) === $match[1]
			) {
				return true;
			}
		}
		return false;
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
