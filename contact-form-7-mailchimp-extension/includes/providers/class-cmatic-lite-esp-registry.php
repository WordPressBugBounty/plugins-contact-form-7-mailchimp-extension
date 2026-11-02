<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing -- Typed signatures follow the existing Lite class convention.

final class Cmatic_Lite_Esp_Registry {
	private static $fallback;

	public static function catalog(): \Cmatic\Lite\Provider\Catalog {
		$catalog = Cmatic_Lite_Container::has( 'provider.catalog' ) ? Cmatic_Lite_Container::get( 'provider.catalog' ) : null;
		if ( $catalog instanceof \Cmatic\Lite\Provider\Catalog ) {
			return $catalog;
		}
		if ( ! self::$fallback instanceof \Cmatic\Lite\Provider\Catalog ) {
			self::$fallback = new \Cmatic\Lite\Provider\Catalog( new \Cmatic\Lite\Provider\HttpTransport() );
		}
		return self::$fallback;
	}

	public static function get_selected( array $settings ): string {
		return self::catalog()->selected( $settings );
	}

	public static function has( string $slug ): bool {
		return self::catalog()->has( $slug );
	}

	public static function get( string $slug ): Cmatic_Lite_Esp_Provider_Interface {
		return self::catalog()->get( $slug );
	}

	public static function all(): array {
		return self::catalog()->all();
	}

	private function __construct() {}
}
