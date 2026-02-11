<?php
/**
 * Plugin action and row links.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

class Cmatic_Plugin_Links {

	const PANEL_KEY = 'Chimpmatic';

	private static string $plugin_basename = '';

	public static function init( string $plugin_basename ): void {
		self::$plugin_basename = $plugin_basename;

		add_action( 'after_setup_theme', array( __CLASS__, 'register_action_links' ) );
		add_filter( 'plugin_row_meta', array( __CLASS__, 'filter_plugin_row_meta' ), 10, 2 );
	}

	public static function register_action_links(): void {
		add_filter(
			'plugin_action_links_' . self::$plugin_basename,
			array( __CLASS__, 'filter_action_links' )
		);
	}

	public static function filter_plugin_row_meta( array $links, string $file ): array {
		if ( $file === self::$plugin_basename ) {
			$links[] = sprintf(
				'<a href="%s" target="_blank" title="%s">%s</a>',
				esc_url( Cmatic_Pursuit::docs( 'help', 'plugin_row_meta' ) ),
				esc_attr__( 'Chimpmatic Lite Documentation', 'chimpmatic-lite' ),
				esc_html__( 'Chimpmatic Documentation', 'chimpmatic-lite' )
			);
		}

		return $links;
	}

	public static function get_settings_url( $form_id = null ) {
		if ( null === $form_id ) {
			$form_id = Cmatic_Utils::get_newest_form_id();
		}

		if ( empty( $form_id ) ) {
			return '';
		}

		return add_query_arg(
			array(
				'page'       => 'wpcf7',
				'post'       => $form_id,
				'action'     => 'edit',
				'active-tab' => self::PANEL_KEY,
			),
			admin_url( 'admin.php' )
		);
	}

	public static function get_settings_link( $form_id = null ) {
		$url = self::get_settings_url( $form_id );

		if ( empty( $url ) ) {
			return '';
		}

		return sprintf(
			'<a href="%s">%s</a>',
			esc_url( $url ),
			esc_html__( 'Settings', 'chimpmatic-lite' )
		);
	}

	public static function get_docs_link() {
		return sprintf(
			'<a href="%s" target="_blank" title="%s">%s</a>',
			esc_url( Cmatic_Pursuit::docs( 'help', 'plugins_page' ) ),
			esc_attr__( 'Chimpmatic Documentation', 'chimpmatic-lite' ),
			esc_html__( 'Docs', 'chimpmatic-lite' )
		);
	}

	public static function filter_action_links( array $links ) {
		$settings_link = self::get_settings_link();

		if ( ! empty( $settings_link ) ) {
			array_unshift( $links, $settings_link );
		}

		return $links;
	}

	public static function filter_row_meta( array $links, string $file, string $match ) {
		if ( $file === $match ) {
			$links[] = self::get_docs_link();
		}

		return $links;
	}
}
