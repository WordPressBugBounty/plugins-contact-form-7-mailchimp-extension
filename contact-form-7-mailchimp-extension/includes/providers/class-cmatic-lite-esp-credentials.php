<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing -- Typed signatures follow the existing Lite class convention.

final class Cmatic_Lite_Esp_Credentials {
	private static $fallback;

	public static function save( int $form_id, string $provider, string $api_key ): bool {
		return self::vault()->store( $form_id, $provider, $api_key );
	}

	public static function get( int $form_id, string $provider ): string {
		return self::vault()->read( $form_id, $provider );
	}

	public static function has( int $form_id, string $provider ): bool {
		return '' !== self::vault()->read( $form_id, $provider );
	}

	public static function delete( int $form_id, string $provider ): bool {
		return self::vault()->forget( $form_id, $provider );
	}

	public static function vault(): \Cmatic\Lite\Provider\CredentialVault {
		$vault = Cmatic_Lite_Container::has( 'credential.vault' ) ? Cmatic_Lite_Container::get( 'credential.vault' ) : null;
		if ( $vault instanceof \Cmatic\Lite\Provider\CredentialVault ) {
			return $vault;
		}
		if ( ! self::$fallback instanceof \Cmatic\Lite\Provider\CredentialVault ) {
			self::$fallback = new \Cmatic\Lite\Provider\CredentialVault();
		}
		return self::$fallback;
	}

	private function __construct() {}
}
