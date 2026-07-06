<?php
/**
 * <html> and <body> CSS class injector.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

class Cmatic_Dom_Classes {

	public static function init(): void {
		add_filter( 'body_class', array( __CLASS__, 'add_body_class' ) );
		add_filter( 'language_attributes', array( __CLASS__, 'add_html_class' ) );
	}

	public static function add_body_class( array $classes ): array {
		return array_merge( $classes, self::version_classes() );
	}

	public static function add_html_class( string $output ): string {
		if ( is_admin() ) {
			return $output;
		}

		$version_classes = self::version_classes();
		if ( empty( $version_classes ) ) {
			return $output;
		}

		$escaped = esc_attr( implode( ' ', $version_classes ) );

		if ( preg_match( '/class\s*=\s*"[^"]*"/i', $output ) ) {
			return preg_replace( '/(class\s*=\s*")/i', '${1}' . $escaped . ' ', $output, 1 );
		}

		return $output . ' class="' . $escaped . '"';
	}

	private static function version_classes(): array {
		$classes = array();

		if ( defined( 'SPARTAN_MCE_VERSION' ) ) {
			$classes[] = sanitize_html_class( 'cmatic-v' . str_replace( '.', '', SPARTAN_MCE_VERSION ) );
		}

		if ( defined( 'CMATIC_VERSION' ) ) {
			$classes[] = sanitize_html_class( 'cmatic-pro-v' . str_replace( '.', '', CMATIC_VERSION ) );
		}

		return $classes;
	}
}
