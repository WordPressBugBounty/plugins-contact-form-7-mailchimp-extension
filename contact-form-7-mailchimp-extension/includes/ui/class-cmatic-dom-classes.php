<?php
/**
 * <html> and <body> CSS class injector (server-side, front-end only).
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adds version CSS classes to the front-end <html> and <body> elements.
 */
class Cmatic_Dom_Classes {
	/**
	 * Register the front-end class filters.
	 *
	 * @return void
	 */
	public static function init(): void {
		// <body>: core WP filter, array-based, sanitized by get_body_class().
		add_filter( 'body_class', array( __CLASS__, 'add_body_class' ) );
		// <html>: no class filter in core, so piggyback on the lang attribute string.
		add_filter( 'language_attributes', array( __CLASS__, 'add_html_class' ) );
	}

	/**
	 * Append the version class(es) to <body>.
	 *
	 * @param string[] $classes Existing body classes.
	 * @return string[]
	 */
	public static function add_body_class( array $classes ): array {
		return array_merge( $classes, self::version_classes() );
	}

	/**
	 * Inject the version class into the <html> attribute string.
	 *
	 * `language_attributes()` echoes this verbatim, so the value is escaped here.
	 * The output is normally just `lang="…"` (and `dir` for RTL) with no class —
	 * but some themes hardcode `<html class="no-js" …>`. Appending a SECOND
	 * `class=""` would be ignored by the browser (duplicate attribute → first wins
	 * per the HTML spec), so when a class is already present we merge into it.
	 *
	 * @param string $output The language_attributes() string.
	 * @return string
	 */
	public static function add_html_class( string $output ): string {
		// Front-end only — leave wp-admin's <html> untouched.
		if ( is_admin() ) {
			return $output;
		}

		$version_classes = self::version_classes();
		if ( empty( $version_classes ) ) {
			return $output;
		}

		$escaped = esc_attr( implode( ' ', $version_classes ) );

		if ( preg_match( '/class\s*=\s*"[^"]*"/i', $output ) ) {
			// Merge into the existing class attribute.
			return preg_replace(
				'/(class\s*=\s*")/i',
				'${1}' . $escaped . ' ',
				$output,
				1
			);
		}

		return $output . ' class="' . $escaped . '"';
	}

	/**
	 * Build the version classes, dots stripped:
	 *   - `cmatic-v<NNN>`     from the Lite version (SPARTAN_MCE_VERSION).
	 *   - `cmatic-pro-v<NNN>` from the Pro version (CMATIC_VERSION) — only when Pro is active.
	 *
	 * Fails closed: a constant that is undefined contributes no class
	 * (empty array if neither is defined).
	 *
	 * @return string[]
	 */
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
