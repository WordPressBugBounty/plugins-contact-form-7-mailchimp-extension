<?php
/**
 * <body> CSS class injector.
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
	}

	public static function add_body_class( array $classes ): array {
		return array_merge( $classes, self::version_classes() );
	}

	private static function version_classes(): array {
		$classes = array();

		if ( defined( 'SPARTAN_MCE_VERSION' ) ) {
			$classes[] = sanitize_html_class( 'cmatic-' . str_replace( '.', '', SPARTAN_MCE_VERSION ) );
		}

		if ( defined( 'CMATIC_VERSION' ) ) {
			$classes[] = sanitize_html_class( 'cmatic-pro-' . str_replace( '.', '', CMATIC_VERSION ) );
		}

		return $classes;
	}
}
