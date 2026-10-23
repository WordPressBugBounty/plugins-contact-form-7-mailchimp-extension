<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing -- Typed signatures follow the existing Lite provider convention.

final class Cmatic_Lite_Esp_Manifest {
	public static function all(): array {
		return Cmatic_Lite_Esp_Registry::catalog()->manifest();
	}

	public static function get( string $slug ): array {
		$all = self::all();
		return $all[ $slug ] ?? $all['mailchimp'];
	}

	public static function selector_options(): array {
		return Cmatic_Lite_Esp_Registry::catalog()->selector_options();
	}

	private function __construct() {}
}
