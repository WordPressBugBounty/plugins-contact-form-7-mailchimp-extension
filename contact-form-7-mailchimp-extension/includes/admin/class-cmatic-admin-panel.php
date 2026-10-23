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

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.FunctionComment.MissingParamTag -- Preserve the established documentation style in this legacy global class.
final class Cmatic_Admin_Panel {

	private const PANEL_KEY = 'Chimpmatic';

	/**
	 * @var array<int, array<string, mixed>>
	 */
	private static $settings_before_save = array();

	public static function init(): void {
		add_filter( 'wpcf7_editor_panels', array( __CLASS__, 'register_panel' ) );
		add_filter( 'wpcf7_editor_panels', array( __CLASS__, 'append_lite_settings_to_pro_panel' ), 20 );
		add_action( 'wpcf7_save_contact_form', array( __CLASS__, 'authorize_save' ), 1 );
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
			'title'    => defined( 'CMATIC_VERSION' ) ? __( 'Email Providers', 'contact-form-7-mailchimp-extension' ) : __( 'Contact Form 7 Extension', 'contact-form-7-mailchimp-extension' ),
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

		ob_start();
		wpcf7_chimp_add_mailchimp( $contact_form );
		$panel = (string) ob_get_clean();

		echo self::inject_lite_rows( $panel ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The trusted Pro callback already escaped its panel; Lite only inserts its own escaped rows.
	}

	private static function inject_lite_rows( string $panel ): string {
		$rows = array(
			'cmatic-telemetry-enabled'  => static function (): void {
				Cmatic_Advanced_Settings::render_help_us_improve_row();
			},
			'cmatic-purge-on-uninstall' => static function (): void {
				Cmatic_Advanced_Settings::render_purge_row();
			},
			'cmatic-system-report'      => static function (): void {
				Cmatic_Advanced_Settings::render_report_row();
			},
		);

		foreach ( $rows as $marker_id => $renderer ) {
			if ( false !== strpos( $panel, 'id="' . $marker_id . '"' ) ) {
				continue;
			}

			$advanced_start = strpos( $panel, 'id="cme-container"' );
			$tbody_start    = false === $advanced_start ? false : strpos( $panel, '<tbody>', $advanced_start );
			$tbody_end      = false === $tbody_start ? false : strpos( $panel, '</tbody>', $tbody_start );
			if ( false === $advanced_start || false === $tbody_start || false === $tbody_end ) {
				return $panel;
			}

			ob_start();
			$renderer();
			$row = (string) ob_get_clean();

			$advanced_body = substr( $panel, $tbody_start, $tbody_end - $tbody_start );
			$license       = strpos( $advanced_body, '<th scope="row">License Reset</th>' );
			$insert_at     = false === $license ? $tbody_end : strrpos( substr( $panel, 0, $tbody_start + $license ), '<tr>' );
			if ( false === $insert_at ) {
				$insert_at = $tbody_end;
			}

			$panel = substr( $panel, 0, $insert_at ) . $row . substr( $panel, $insert_at );
		}

		return $panel;
	}

	public static function render_panel( $contact_form ): void {
		$settings  = FormSettingsRepository::load( (int) ( $contact_form->id() ?? 0 ) );
		$form_id   = $settings->form_id();
		$cf7_mch   = $settings->record();
		$provider  = $settings->is_empty() ? '' : $settings->provider();
		$form_tags = \Cmatic\Lite\Settings\FormTags::get_tags_with_types( $contact_form );
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
			if ( class_exists( 'Cmatic_Contact_Lookup' ) ) {
				Cmatic_Contact_Lookup::cmatic_render( array( 'form_id' => $form_id ) );
			}
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

	public static function authorize_save( $contact_form ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The nonce is checked before reading any values below.
		if ( ! isset( $_POST['wpcf7-mailchimp'] ) && ! isset( $_POST['wpcf7-cmatic-provider'] ) ) {
			return;
		}
		$form_id = (int) $contact_form->id();
		if ( ! self::can_save( $form_id ) ) {
			wp_die( esc_html__( 'You do not have permission to save this form.', 'contact-form-7-mailchimp-extension' ), '', array( 'response' => 403 ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce checked above; values are inspected here and sanitized by the save handlers.
		$posted       = wp_unslash( $_POST );
		$account_keys = array_fill_keys( array( 'api', 'api_key', 'auth_type', 'api_key_backup', 'api-validation', 'lisdata', 'providers' ), true );
		foreach ( array( 'wpcf7-mailchimp', 'wpcf7-cmatic-provider' ) as $name ) {
			if ( ! empty( $posted[ $name ] ) && is_array( $posted[ $name ] ) && array_intersect_key( $posted[ $name ], $account_keys ) && ! current_user_can( 'wpcf7_manage_integration' ) ) {
				wp_die( esc_html__( 'An administrator manages this connection.', 'contact-form-7-mailchimp-extension' ), '', array( 'response' => 403 ) );
			}
		}
		$provider = $posted['wpcf7-cmatic-provider'] ?? array();
		if ( is_array( $provider ) && isset( $provider['provider'] ) && is_string( $provider['provider'] ) ) {
			$limit  = \Cmatic\Lite\Settings\Entitlements::field_limit( sanitize_key( $provider['provider'] ), $form_id );
			$mapped = 0;
			foreach ( MappingSchema::arr( $provider['merge_fields'] ?? array() ) as $offset => $field ) {
				if ( is_array( $field ) && '' !== trim( MappingSchema::str( $provider[ MappingSchema::slot( (int) $offset ) ] ?? '' ) ) ) {
					++$mapped;
				}
			}
			if ( $mapped > $limit ) {
				wp_die(
					esc_html__( 'Choose email and three fields. Clear a mapping to choose another field.', 'contact-form-7-mailchimp-extension' ),
					'',
					array(
						'response'  => 400,
						'back_link' => true,
					)
				);
			}
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

		$old_settings = FormSettingsRepository::load( $form_id )->record();
		$posted_data  = wp_unslash( $_POST['wpcf7-mailchimp'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- Verified by can_save(); sanitized below.
		if ( ! is_array( $posted_data ) ) {
			return;
		}
		$sanitized = self::sanitize_settings( $posted_data, $old_settings );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- can_save() verified this form's nonce above.
		if ( isset( $_POST['wpcf7-cmatic-provider'] ) && ! array_key_exists( 'addunsubscr', $posted_data ) ) {
			$sanitized['addunsubscr'] = ! empty( $old_settings['addunsubscr'] ) ? '1' : '0';
		}
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
			$old_settings                = FormSettingsRepository::load( $form_id )->record();
		}

		$updated_settings = array_merge( $old_settings, $sanitized );

		FormSettingsRepository::store( $form_id, MappingSchema::legacy_mirror( $updated_settings ) );

		try {
			Signls_Sdk_Bridge_1_1_7::relevant_change( 'contact-form-7-mailchimp-extension' );
		} catch ( \Throwable $exception ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Signal bookkeeping must never alter the Contact Form 7 save result.
		}
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
	 * @param WPCF7_ContactForm $contact_form Contact form being saved.
	 */
	public static function capture_settings( $contact_form ): void {
		$form_id                                = (int) $contact_form->id();
		self::$settings_before_save[ $form_id ] = FormSettingsRepository::load( $form_id )->record();
	}

	/**
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
		$field_limit = \Cmatic\Lite\Settings\Entitlements::field_limit( $slug, $form_id );
		$current     = FormSettingsRepository::load( $form_id )->record();
		$before      = self::$settings_before_save[ $form_id ] ?? array();
		if ( defined( 'CMATIC_VERSION' ) ) {
			$current = array_replace( $before, $current );
		}
		$current['provider'] = $slug;
		$settings            = new FormSettings( $form_id, $current );
		$saved               = \Cmatic\Lite\Settings\SettingsValidator::provider_save( $slug, $settings->section( $slug ), $posted, $field_limit, $contact_form, $form_id );
		if ( null === $saved ) {
			FormSettingsRepository::remember_provider( $form_id, $slug );
			return;
		}
		$current = $settings->with_section( $slug, $saved )->record();
		if ( 'mailchimp' === $slug ) {
			$current = MappingSchema::legacy_mirror( $current );
		}
		if ( 'mailerlite' === $slug && ! \Cmatic\Lite\Settings\Entitlements::apply( 'mailerlite', $saved, $form_id )['degraded'] ) {
			\Cmatic\Lite\Submission\Degradation::clear( $form_id );
		}
		FormSettingsRepository::store( $form_id, $current );
		unset( self::$settings_before_save[ $form_id ] );
	}

	/**
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
