<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    hello@chimpmatic.com
 * @copyright 2014-2026 ChimpMatic
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

final class Cmatic_Lite_Signls_Outcome {
	public static function record( string $provider, string $operation, bool $success, string $failure_class, $value, string $fallback ): void {
		try {
			$reason        = class_exists( 'Cmatic_Lite_Signls_Failure_Reason' )
				? Cmatic_Lite_Signls_Failure_Reason::from_value( $value, $fallback )
				: array(
					'code'   => $fallback,
					'sample' => '',
				);
			$failure_class = $success ? 'unknown' : self::failure_class_for_reason( $reason['code'], $failure_class );
			$recorded      = \Signls\Sdk\V1\CounterStore::record_outcome(
				'contact-form-7-mailchimp-extension',
				$provider,
				$operation,
				$success,
				$failure_class,
				$success ? '' : $reason['code'],
				$success ? '' : $reason['sample']
			);
			if ( $recorded ) {
				Signls_Sdk_Bridge_1_1_7::relevant_change( 'contact-form-7-mailchimp-extension' );
			}
		} catch ( Throwable $error ) {
			return;
		}
	}

	public static function failure_class_for_reason( string $reason_code, string $fallback ): string {
		$map = array(
			'dns'                => 'transport_dns',
			'tls'                => 'transport_tls',
			'timeout'            => 'transport_timeout',
			'rate_limit'         => 'http_429',
			'http_4xx'           => 'http_4xx',
			'http_5xx'           => 'http_5xx',
			'revoked_credential' => 'auth',
			'permission'         => 'auth',
			'configuration'      => 'configuration',
			'validation'         => 'validation',
		);
		return isset( $map[ $reason_code ] ) ? $map[ $reason_code ] : $fallback;
	}

	private function __construct() {}
}
