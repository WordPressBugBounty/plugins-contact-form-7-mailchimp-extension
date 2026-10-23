<?php
/**
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
			$referer = isset( $_SERVER['HTTP_REFERER'] ) && is_string( $_SERVER['HTTP_REFERER'] )
				? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) )
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
}
