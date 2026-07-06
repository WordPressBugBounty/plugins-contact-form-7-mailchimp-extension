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

final class Cmatic_Admin_Panel {

	private const PANEL_KEY = 'Chimpmatic';

	public static function init(): void {
		add_filter( 'wpcf7_editor_panels', array( __CLASS__, 'register_panel' ) );
		add_action( 'wpcf7_after_save', array( __CLASS__, 'save_settings' ) );
		add_action( 'wpcf7_admin_misc_pub_section', array( __CLASS__, 'render_sidebar_info' ) );
		add_action( 'wpcf7_admin_footer', array( __CLASS__, 'render_footer_banner' ), 10, 1 );
	}

	public static function register_panel( array $panels ): array {
		if ( defined( 'CMATIC_VERSION' ) ) {
			return $panels;
		}

		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! current_user_can( 'wpcf7_edit_contact_form', $post_id ) ) {
			return $panels;
		}

		$panels[ self::PANEL_KEY ] = array(
			'title'    => __( 'Chimpmatic', 'chimpmatic-lite' ),
			'callback' => array( __CLASS__, 'render_panel' ),
		);

		return $panels;
	}

	public static function render_panel( $contact_form ): void {
		$form_id  = $contact_form->id() ?? 0;
		$cf7_mch  = get_option( 'cf7_mch_' . $form_id, array() );
		$cf7_mch  = is_array( $cf7_mch ) ? $cf7_mch : array();

		$form_tags = Cmatic_Form_Tags::get_tags_with_types( $contact_form );
		$api_valid = (int) ( $cf7_mch['api-validation'] ?? 0 );
		$list_data = isset( $cf7_mch['lisdata'] ) && is_array( $cf7_mch['lisdata'] ) ? $cf7_mch['lisdata'] : null;

		// Render container.
		if ( class_exists( 'Cmatic_Data_Container' ) ) {
			$extra_data = array();
			if ( isset( $cf7_mch['auth_type'] ) && 'oauth' === $cf7_mch['auth_type'] ) {
				$extra_data['auth_type'] = 'oauth';
			}
			Cmatic_Data_Container::render_open( $form_id, (string) $api_valid, $extra_data );
		} else {
			echo '<div class="cmatic-inner">';
		}

		// Header.
		if ( class_exists( 'Cmatic_Header' ) ) {
			// A pristine form (no key, no OAuth) has not FAILED anything: show a
			// neutral "Not Connected", never the red "API Inactive" error state.
			$has_credentials = ! empty( $cf7_mch['api'] ) || ( isset( $cf7_mch['auth_type'] ) && 'oauth' === $cf7_mch['auth_type'] );
			if ( 1 === $api_valid ) {
				$api_status = 'connected';
			} elseif ( ! $has_credentials ) {
				$api_status = 'fresh';
			} else {
				$api_status = ( 0 === $api_valid ) ? 'disconnected' : null;
			}
			Cmatic_Header::output( array( 'api_status' => $api_status ) );
		}

		echo '<div class="cmatic-content">';

		// API Panel.
		Cmatic_Api_Panel::render( $cf7_mch, (string) $api_valid, $form_id );

		// Audiences.
		if ( class_exists( 'Cmatic_Audiences' ) ) {
			Cmatic_Audiences::render( (string) $api_valid, $list_data, $cf7_mch );
		}

		// Field mapping.
		Cmatic_Field_Mapper_UI::render( $api_valid, $list_data, $cf7_mch, $form_tags, $form_id );

		// Toggles.
		Cmatic_Panel_Toggles::cmatic_render();

		// Contact Lookup.
		if ( class_exists( 'Cmatic_Contact_Lookup' ) ) {
			Cmatic_Contact_Lookup::cmatic_render( array( 'form_id' => $form_id ) );
		}

		// Log Viewer.
		Cmatic_Log_Viewer::render();

		// Advanced Settings.
		echo '<div id="cme-container" class="mce-custom-fields vc-advanced-settings">';
		Cmatic_Advanced_Settings::render();
		echo '</div>';

		// Welcome banner.
		echo '<div class="vc-hidden-start dev-cta mce-cta welcome-panel">';
		echo '<div class="welcome-panel-content">';
		echo Cmatic_Banners::get_welcome(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div></div>';

		echo '</div>'; // .cmatic-content

		if ( class_exists( 'Cmatic_Data_Container' ) ) {
			Cmatic_Data_Container::render_close();
		} else {
			echo '</div>';
		}
	}

	public static function save_settings( $contact_form ): void {
		if ( ! isset( $_POST['wpcf7-mailchimp'] ) ) {
			return;
		}

		// Verify nonce (defense-in-depth, CF7 already checked at request level).
		$form_id      = $contact_form->id();
		$nonce_action = sprintf( 'wpcf7-save-contact-form_%s', $form_id );
		$nonce        = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
			return;
		}

		// Verify capability.
		if ( ! current_user_can( 'wpcf7_edit_contact_form', $form_id ) ) {
			return;
		}

		// Trigger telemetry.
		if ( class_exists( 'Cmatic\\Metrics\\Core\\Sync' ) && class_exists( 'Cmatic\\Metrics\\Core\\Collector' ) ) {
			$payload = \Cmatic\Metrics\Core\Collector::collect( 'form_saved' );
			\Cmatic\Metrics\Core\Sync::send( $payload );
		}

		$option_name  = 'cf7_mch_' . $form_id;
		$old_settings = get_option( $option_name, array() );
		$posted_data  = wp_unslash( $_POST['wpcf7-mailchimp'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in sanitize_settings().
		$sanitized    = self::sanitize_settings( $posted_data, $old_settings );

		// OAuth→manual transition: if user enters an API key while OAuth is connected,
		// disconnect OAuth and use the new key. Matches Pro behavior (cmatic-functions.php L576-588).
		if ( ! empty( $sanitized['api'] ) && isset( $old_settings['auth_type'] ) && 'oauth' === $old_settings['auth_type'] ) {
			$auth_manager = Cmatic_Lite_Container::get( 'auth.manager' );
			if ( $auth_manager ) {
				$auth_manager->disconnect( $form_id );
			}
			unset( $sanitized['auth_type'] );
			unset( $sanitized['api_key_backup'] );
			$sanitized['lisdata'] = array();
			$sanitized['list']    = '';
			$sanitized['api-validation'] = 0;
			// Re-read old_settings after disconnect modified it.
			$old_settings = get_option( $option_name, array() );
		}

		if ( empty( $sanitized['api'] ) ) {
			// Don't delete option if OAuth is connected — api is legitimately empty.
			$auth_manager = Cmatic_Lite_Container::get( 'auth.manager' );
			if ( $auth_manager && $auth_manager->has_oauth( $form_id ) ) {
				// Preserve OAuth state — merge sanitized fields (checkboxes, etc.) into existing settings.
				$updated_settings = self::mirror_legacy_mappings( array_merge( $old_settings, $sanitized ) );
				update_option( $option_name, $updated_settings );
				return;
			}
			delete_option( $option_name );
			return;
		}

		$updated_settings = array_merge( $old_settings, $sanitized );

		// Remove excess field mappings beyond lite limit.
		$max_field_index = CMATIC_LITE_FIELDS + 2;
		for ( $i = $max_field_index + 1; $i <= 20; $i++ ) {
			$field_key = 'field' . $i;
			if ( isset( $updated_settings[ $field_key ] ) ) {
				unset( $updated_settings[ $field_key ] );
			}
		}

		update_option( $option_name, self::mirror_legacy_mappings( $updated_settings ) );
	}


	/**
	 * Write-through mirror: keep the legacy CustomKey/CustomValue rows in
	 * lockstep with the new-schema mapping (merge_fields + field3+). A save
	 * otherwise leaves STALE legacy rows behind, and Pro builds up to
	 * 1.7.8.04 read ONLY those rows at submission time - that mismatch
	 * shipped "[_user_last_name]" literals into real Mailchimp records.
	 * Purging is not an option while old Pro is in the field, so mirror.
	 */
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
			// Legacy rows store the bare field name; take the first mail-tag.
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

		// Clear stale contiguous rows beyond what was just mirrored.
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

		// Add field mappings.
		$max_index = CMATIC_LITE_FIELDS + 2;
		for ( $i = 3; $i <= $max_index; $i++ ) {
			$text_fields[] = 'field' . $i;
		}

		// Add custom fields.
		for ( $i = 1; $i <= 10; $i++ ) {
			$text_fields[] = 'CustomValue' . $i;
			$text_fields[] = 'CustomKey' . $i;
		}

		// Sanitize text fields.
		foreach ( $text_fields as $field ) {
			if ( isset( $posted[ $field ] ) ) {
				$value = trim( sanitize_text_field( $posted[ $field ] ) );
				if ( '' !== $value ) {
					$sanitized[ $field ] = $value;
				}
			}
		}

		// Preserve masked API key.
		if ( isset( $sanitized['api'] ) && strpos( $sanitized['api'], '•' ) !== false ) {
			if ( ! empty( $old['api'] ) && strpos( $old['api'], '•' ) === false ) {
				$sanitized['api'] = $old['api'];
			}
		}

		// Per-form checkbox fields (global toggles handled via REST API, not here).
		$checkboxes = array( 'cfactive', 'addunsubscr' );
		foreach ( $checkboxes as $field ) {
			$sanitized[ $field ] = isset( $posted[ $field ] ) ? '1' : '0';
		}

		// Select field: confsubs (double opt-in) - preserve actual value.
		$sanitized['confsubs'] = isset( $posted['confsubs'] ) && '1' === $posted['confsubs'] ? '1' : '0';

		return $sanitized;
	}

	public static function render_sidebar_info( int $post_id ): void {
		Cmatic_Sidebar_Panel::render_submit_info( $post_id );
	}

	public static function render_footer_banner( $post ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		Cmatic_Sidebar_Panel::render_footer_promo();
	}

	private function __construct() {}
}
