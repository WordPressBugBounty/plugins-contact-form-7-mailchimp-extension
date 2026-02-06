<?php
/**
 * Custom CF7 form tags registration.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

class Cmatic_CF7_Tags {

	public static function init(): void {
		add_filter( 'wpcf7_special_mail_tags', array( __CLASS__, 'handle_special_tags' ), 10, 3 );
		add_action( 'wpcf7_init', array( __CLASS__, 'register_form_tags' ), 11 );

		if ( ! is_admin() ) {
			add_filter( 'wpcf7_form_tag', array( __CLASS__, 'populate_referer_tag' ) );
		}
	}

	public static function handle_special_tags( ?string $output, string $name, string $html ): string {
		if ( '_domain' === $name ) {
			return self::get_domain();
		}

		if ( '_formID' === $name ) {
			return (string) self::get_form_id();
		}

		return $output ?? '';
	}

	public static function register_form_tags(): void {
		if ( ! function_exists( 'wpcf7_add_form_tag' ) ) {
			return;
		}

		wpcf7_add_form_tag( '_domain', array( __CLASS__, 'get_domain' ) );
		wpcf7_add_form_tag( '_formID', array( __CLASS__, 'get_form_id' ) );
	}

	public static function populate_referer_tag( array $form_tag ): array {
		if ( 'referer-page' === $form_tag['name'] ) {
			$referer = isset( $_SERVER['HTTP_REFERER'] )
				? esc_url( wp_unslash( $_SERVER['HTTP_REFERER'] ) )
				: '';

			$form_tag['values'][] = $referer;
		}

		return $form_tag;
	}

	public static function get_domain(): string {
		$url = strtolower( trim( get_home_url() ) );
		$url = preg_replace( '/^https?:\/\//i', '', $url );
		$url = preg_replace( '/^www\./i', '', $url );

		$parts = explode( '/', $url );

		return trim( $parts[0] );
	}

	public static function get_form_id(): int {
		if ( ! class_exists( 'WPCF7_ContactForm' ) ) {
			return 0;
		}

		$form = WPCF7_ContactForm::get_current();

		return $form ? $form->id() : 0;
	}

	public static function get_form_tags(): array {
		if ( ! class_exists( 'WPCF7_FormTagsManager' ) ) {
			return array();
		}

		$manager   = WPCF7_FormTagsManager::get_instance();
		$form_tags = $manager->get_scanned_tags();

		return is_array( $form_tags ) ? $form_tags : array();
	}

	public static function get_mail_tags_html(): string {
		if ( ! class_exists( 'WPCF7_ContactForm' ) ) {
			return '';
		}

		$contact_form = WPCF7_ContactForm::get_current();
		if ( ! $contact_form ) {
			return '';
		}

		$mail_tags = $contact_form->collect_mail_tags();
		if ( empty( $mail_tags ) ) {
			return '';
		}

		$output = '';
		foreach ( $mail_tags as $tag_name ) {
			if ( ! empty( $tag_name ) && 'opt-in' !== $tag_name ) {
				$output .= '<span class="mailtag code used">[' . esc_html( $tag_name ) . ']</span>';
			}
		}

		return $output;
	}

	public static function get_referer_html(): string {
		$referer_url = isset( $_SERVER['HTTP_REFERER'] ) && ! empty( $_SERVER['HTTP_REFERER'] )
			? esc_url( wp_unslash( $_SERVER['HTTP_REFERER'] ) )
			: 'Direct Visit';

		$html  = '<p style="display: none !important"><span class="wpcf7-form-control-wrap referer-page">';
		$html .= '<input type="hidden" name="referer-page" ';
		$html .= 'value="' . esc_attr( $referer_url ) . '" ';
		$html .= 'data-value="' . esc_attr( $referer_url ) . '" ';
		$html .= 'class="wpcf7-form-control wpcf7-text referer-page" aria-invalid="false">';
		$html .= '</span></p>' . "\n";

		return $html;
	}
}
