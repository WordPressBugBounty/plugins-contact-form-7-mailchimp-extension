<?php
/**
 * Request service context.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

final class Cmatic_Lite_Service_Context {

	private const PRODUCT = 'contact-form-7-mailchimp-extension';

	private const TRIGGERS = array( 'promo_pricing_refresh', 'oauth_admin_connect' );

	public static function payload( string $trigger ): array {
		if ( ! in_array( $trigger, self::TRIGGERS, true )
			|| ! class_exists( 'Cmatic_Lite_Signls_Privacy' )
			|| 'enabled' !== Cmatic_Lite_Signls_Privacy::consent_status()
			|| ! class_exists( 'Signls_Sdk_Loader' )
			|| ! class_exists( 'Signls\Sdk\V1\Runtime' )
			|| ! class_exists( 'Signls\Sdk\V1\SiteIdentity' )
			|| ! defined( 'SPARTAN_MCE_VERSION' ) ) {
			return array();
		}

		try {
			$state = \Signls\Sdk\V1\Runtime::state( self::PRODUCT );
			if ( 'enabled' !== ( $state['consent_status'] ?? '' ) ) {
				return array();
			}

			$last_acknowledged = self::state_uint( $state, 'last_acknowledged_at', time() + 300 );
			$retry_attempt     = self::state_uint( $state, 'retry_attempt', 1000000 );
			$sdk_version       = Signls_Sdk_Loader::selected_version();
			$plugin_version    = (string) SPARTAN_MCE_VERSION;
			if ( null === $last_acknowledged
				|| null === $retry_attempt
				|| ! is_string( $sdk_version )
				|| ! self::version_token( $plugin_version, 64 )
				|| ! self::version_token( $sdk_version, 32 ) ) {
				return array();
			}

			$install_id = ( new Cmatic_Install_Data( Cmatic_Options_Repository::instance() ) )->get_install_id();
			$site_id    = ( new \Signls\Sdk\V1\SiteIdentity() )->site_id();
			if ( 1 !== preg_match( '/^(?:[a-f0-9]{12}|[a-f0-9]{32})$/', $install_id )
				|| 1 !== preg_match( '/^[a-f0-9]{32}$/', $site_id ) ) {
				return array();
			}

			$heartbeat_state = $retry_attempt > 0 ? 'retrying' : ( $last_acknowledged > 0 ? 'healthy' : 'never' );

			return array(
				'context_version'                => 1,
				'install_id'                     => $install_id,
				'site_id'                        => $site_id,
				'product_slug'                   => self::PRODUCT,
				'edition'                        => 'lite',
				'plugin_version'                 => $plugin_version,
				'sdk_version'                    => $sdk_version,
				'heartbeat_state'                => $heartbeat_state,
				'heartbeat_last_acknowledged_at' => $last_acknowledged,
				'heartbeat_retry_attempt'        => $retry_attempt,
				'trigger'                        => $trigger,
			);
		} catch ( \Throwable $error ) {
			return array();
		}
	}

	public static function headers( string $trigger ): array {
		$payload = self::payload( $trigger );
		if ( empty( $payload ) ) {
			return array();
		}

		return array(
			'X-Chimpmatic-Context-Version'                => (string) $payload['context_version'],
			'X-Chimpmatic-Install-Id'                     => $payload['install_id'],
			'X-Chimpmatic-Site-Id'                        => $payload['site_id'],
			'X-Chimpmatic-Product-Slug'                   => $payload['product_slug'],
			'X-Chimpmatic-Edition'                        => $payload['edition'],
			'X-Chimpmatic-Plugin-Version'                 => $payload['plugin_version'],
			'X-Chimpmatic-Sdk-Version'                    => $payload['sdk_version'],
			'X-Chimpmatic-Heartbeat-State'                => $payload['heartbeat_state'],
			'X-Chimpmatic-Heartbeat-Last-Acknowledged-At' => (string) $payload['heartbeat_last_acknowledged_at'],
			'X-Chimpmatic-Heartbeat-Retry-Attempt'        => (string) $payload['heartbeat_retry_attempt'],
			'X-Chimpmatic-Trigger'                        => $payload['trigger'],
		);
	}

	private static function state_uint( array $state, string $key, int $maximum ): ?int {
		if ( ! array_key_exists( $key, $state ) ) {
			return 0;
		}
		$value = $state[ $key ];
		return is_int( $value ) && $value >= 0 && $value <= $maximum ? $value : null;
	}

	private static function version_token( string $value, int $maximum ): bool {
		return strlen( $value ) <= $maximum && 1 === preg_match( '/^[0-9A-Za-z][0-9A-Za-z._+-]*$/', $value );
	}

	private function __construct() {}
}
