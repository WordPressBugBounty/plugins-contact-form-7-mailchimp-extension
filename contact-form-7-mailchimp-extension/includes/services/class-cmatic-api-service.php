<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

final class Cmatic_Lite_Api_Service implements Cmatic_Api_Client_Interface {

	public static function mask_api_key( string $key ): string {
		return \Cmatic\Lite\Provider\Operations\MailchimpOperations::mask_api_key( $key );
	}

	public static function generate_headers( string $token ): array {
		return self::operations()->generate_headers( $token );
	}

	public static function get( string $token, string $url ): array {
		return self::operations()->get( $token, $url );
	}

	public static function put( string $token, string $url, string $body ): array {
		return self::operations()->put( $token, $url, $body );
	}

	public static function validate_key( string $input, bool $log_enabled = false ): array {
		return self::operations()->validate_key( $input, $log_enabled );
	}

	public static function get_lists( string $api_key, bool $log_enabled = false ): array {
		return self::operations()->get_lists( $api_key, $log_enabled );
	}

	public static function get_merge_fields( string $api_key, string $list_id, bool $log_enabled = false ): array {
		return self::operations()->get_merge_fields( $api_key, $list_id, $log_enabled );
	}

	private static function operations(): \Cmatic\Lite\Provider\Operations\MailchimpOperations {
		$provider   = Cmatic_Lite_Esp_Registry::get( 'mailchimp' );
		$operations = $provider instanceof \Cmatic\Lite\Provider\Provider ? $provider->operations() : null;
		if ( ! $operations instanceof \Cmatic\Lite\Provider\Operations\MailchimpOperations ) {
			throw new RuntimeException( 'Mailchimp operations are not available.' );
		}
		return $operations;
	}

	private function __construct() {}
}
