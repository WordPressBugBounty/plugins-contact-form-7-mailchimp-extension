<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    hello@chimpmatic.com
 * @copyright 2014-2026 ChimpMatic
 * @license   GPL-3.0+
 */

namespace Cmatic\Lite\Admin;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing -- Typed signatures follow the existing Lite class convention.
final class ProFloorNotice {

	public const FLOOR = '1.8.2.00';

	private const PRO_FILE = 'chimpmatic/chimpmatic.php';

	public static function register(): void {
		Notices::boot();
	}

	public static function outdated_version(): string {
		$version = defined( 'CMATIC_VERSION' ) ? (string) CMATIC_VERSION : self::header_version();
		return ( '' !== $version && version_compare( $version, self::FLOOR, '<' ) ) ? $version : '';
	}

	public static function render(): void {}

	private static function header_version(): string {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$file = WP_PLUGIN_DIR . '/' . self::PRO_FILE;
		if ( ! is_plugin_active( self::PRO_FILE ) || ! is_readable( $file ) ) {
			return '';
		}
		$data = get_file_data( $file, array( 'version' => 'Version' ) );
		return (string) ( $data['version'] ?? '' );
	}
}
