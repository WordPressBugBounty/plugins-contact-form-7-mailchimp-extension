<?php
/**
 * CF7 form CSS class injector.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

class Cmatic_Form_Classes {
	public static function init(): void {
		add_filter( 'wpcf7_form_class_attr', array( __CLASS__, 'add_classes' ) );
	}

	public static function add_classes( string $class_attr ): string {
		$classes = array();

		// 1. Install ID (pure, no prefix) - stored at install.id.
		$install_id = Cmatic_Options_Repository::get_option( 'install.id', '' );
		if ( ! empty( $install_id ) ) {
			$classes[] = sanitize_html_class( $install_id );
		}

		// 2. API connection status - check if first_connected timestamp exists.
		$first_connected = Cmatic_Options_Repository::get_option( 'api.first_connected', 0 );
		if ( ! empty( $first_connected ) ) {
			$classes[] = 'cmatic-conn';
		} else {
			$classes[] = 'cmatic-disconn';
		}

		// 3. Audience count - stored in lisdata.lists array.
		$lisdata   = Cmatic_Options_Repository::get_option( 'lisdata', array() );
		$lists     = isset( $lisdata['lists'] ) && is_array( $lisdata['lists'] ) ? $lisdata['lists'] : array();
		$aud_count = count( $lists );
		$classes[] = 'cmatic-aud-' . $aud_count;

		// 4. Mapped fields (form-specific).
		$contact_form = wpcf7_get_current_contact_form();
		if ( $contact_form ) {
			$form_id   = $contact_form->id();
			$cf7_mch   = get_option( 'cf7_mch_' . $form_id, array() );
			$mapped    = self::count_mapped_fields( $cf7_mch );
			$total     = self::count_total_merge_fields( $cf7_mch );
			$classes[] = 'cmatic-mapd' . $mapped . '-' . $total;
		}

		// 5. Lite version (SPARTAN_MCE_VERSION).
		if ( defined( 'SPARTAN_MCE_VERSION' ) ) {
			$version   = str_replace( '.', '', SPARTAN_MCE_VERSION );
			$classes[] = 'cmatic-v' . $version;
		}

		// 6. Pro version (CMATIC_VERSION) if active.
		if ( defined( 'CMATIC_VERSION' ) ) {
			$pro_version = str_replace( '.', '', CMATIC_VERSION );
			$classes[]   = 'cmatic-pro-v' . $pro_version;
		}

		// 7. Per-form sent count.
		if ( $contact_form ) {
			$form_sent  = (int) ( $cf7_mch['stats_sent'] ?? 0 );
			$classes[] = 'cmatic-sent-' . $form_sent;
		}

		// 8. Global total sent.
		$total_sent = (int) Cmatic_Options_Repository::get_option( 'stats.sent', 0 );
		$classes[]  = 'cmatic-total-' . $total_sent;

		// Append to existing classes.
		if ( ! empty( $classes ) ) {
			$class_attr .= ' ' . implode( ' ', $classes );
		}

		return $class_attr;
	}

	private static function count_mapped_fields( array $cf7_mch ): int {
		$merge_fields = isset( $cf7_mch['merge_fields'] ) && is_array( $cf7_mch['merge_fields'] )
			? $cf7_mch['merge_fields']
			: array();

		if ( empty( $merge_fields ) ) {
			return 0;
		}

		$mapped = 0;
		foreach ( $merge_fields as $index => $field ) {
			$field_key = 'field' . ( $index + 3 );
			if ( ! empty( $cf7_mch[ $field_key ] ) && '--' !== $cf7_mch[ $field_key ] ) {
				++$mapped;
			}
		}

		return $mapped;
	}

	private static function count_total_merge_fields( array $cf7_mch ): int {
		$merge_fields = isset( $cf7_mch['merge_fields'] ) && is_array( $cf7_mch['merge_fields'] )
			? $cf7_mch['merge_fields']
			: array();

		return count( $merge_fields );
	}
}
