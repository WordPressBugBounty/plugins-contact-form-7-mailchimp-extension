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

		$install_id = Cmatic_Options_Repository::get_option( 'install.id', '' );
		if ( ! empty( $install_id ) ) {
			$classes[] = sanitize_html_class( $install_id );
		}

		$first_connected = Cmatic_Options_Repository::get_option( 'api.first_connected', 0 );
		if ( ! empty( $first_connected ) ) {
			$classes[] = 'cmatic-conn';
		} else {
			$classes[] = 'cmatic-disconn';
		}

		$lisdata   = Cmatic_Options_Repository::get_option( 'lisdata', array() );
		$lists     = isset( $lisdata['lists'] ) && is_array( $lisdata['lists'] ) ? $lisdata['lists'] : array();
		$aud_count = count( $lists );
		$classes[] = 'cmatic-aud-' . $aud_count;

		$contact_form = wpcf7_get_current_contact_form();
		if ( $contact_form ) {
			$form_id   = $contact_form->id();
			$cf7_mch   = get_option( 'cf7_mch_' . $form_id, array() );
			$mapped    = self::count_mapped_fields( $cf7_mch );
			$total     = self::count_total_merge_fields( $cf7_mch );
			$classes[] = 'cmatic-mapd' . $mapped . '-' . $total;
		}

		if ( defined( 'SPARTAN_MCE_VERSION' ) ) {
			$version   = str_replace( '.', '', SPARTAN_MCE_VERSION );
			$classes[] = 'cmatic-' . $version;
		}

		if ( defined( 'CMATIC_VERSION' ) ) {
			$pro_version = str_replace( '.', '', CMATIC_VERSION );
			$classes[]   = 'cmatic-pro-' . $pro_version;
		}

		if ( $contact_form ) {
			$form_sent = (int) ( $cf7_mch['stats_sent'] ?? 0 );
			$classes[] = 'cmatic-sent-' . $form_sent;
		}

		$total_sent = (int) Cmatic_Options_Repository::get_option( 'stats.sent', 0 );
		$classes[]  = 'cmatic-total-' . $total_sent;

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
