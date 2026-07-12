<?php
/**
 * ChimpMatic Lite multi-ESP component.
 *
 * @package contact-form-7-mailchimp-extension
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing -- Typed signatures follow the existing Lite class convention.
final class Cmatic_Lite_Esp_Registry {
	private static $providers = array();

	public static function get_selected( array $settings ): string {
		$slug = isset( $settings['provider'] ) ? sanitize_key( (string) $settings['provider'] ) : 'mailchimp';
		return self::has( $slug ) ? $slug : 'mailchimp';
	}

	public static function has( string $slug ): bool {
		return in_array( $slug, array( 'mailchimp', 'brevo', 'mailerlite', 'klaviyo' ), true );
	}

	public static function get( string $slug ): Cmatic_Lite_Esp_Provider_Interface {
		$slug = self::has( $slug ) ? $slug : 'mailchimp';
		if ( ! isset( self::$providers[ $slug ] ) ) {
			$classes                  = array(
				'mailchimp'  => 'Cmatic_Lite_Esp_Mailchimp',
				'brevo'      => 'Cmatic_Lite_Esp_Brevo',
				'mailerlite' => 'Cmatic_Lite_Esp_Mailerlite',
				'klaviyo'    => 'Cmatic_Lite_Esp_Klaviyo',
			);
			$class                    = $classes[ $slug ];
			self::$providers[ $slug ] = new $class();
		}
		return self::$providers[ $slug ];
	}

	public static function all(): array {
		$result = array();
		foreach ( array( 'mailchimp', 'brevo', 'mailerlite', 'klaviyo' ) as $slug ) {
			$result[ $slug ] = self::get( $slug );
		}
		return $result;
	}

	private function __construct() {}
}
