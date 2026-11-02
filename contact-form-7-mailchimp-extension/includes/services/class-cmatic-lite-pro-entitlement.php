<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

final class Cmatic_Lite_Pro_Entitlement {

	public static function active(): bool {
		return defined( 'CMATIC_VERSION' )
			&& function_exists( 'cmatic_is_blessed' )
			&& (bool) cmatic_is_blessed();
	}

	public static function submission_owner(): string {
		$contract = function_exists( 'cmatic_pro_submission_contract' ) && function_exists( 'cmatic_pro_owns_submission' );
		return self::resolve_submission_owner(
			function_exists( 'cmatic_process_subscription' ),
			$contract,
			$contract && (bool) cmatic_pro_owns_submission()
		);
	}

	public static function resolve_submission_owner( bool $pro_sender_present, bool $contract_present, bool $pro_owns ): string {
		if ( ! $pro_sender_present ) {
			return 'lite';
		}
		if ( ! $contract_present ) {
			return 'pro';
		}
		return $pro_owns ? 'pro' : 'lite';
	}

	/**
	 * @param array<string,mixed> $cf7_mch
	 */
	public static function lite_can_send( array $cf7_mch ): bool {
		$consent_required = $cf7_mch['consent_required'] ?? '';
		if ( ! is_scalar( $consent_required ) || '' !== trim( (string) $consent_required ) ) {
			return false;
		}
		$accept_raw = $cf7_mch['accept'] ?? '';
		if ( ! is_scalar( $accept_raw ) ) {
			return false;
		}
		$accept = trim( (string) $accept_raw );
		if ( '' !== $accept && 1 !== preg_match( '/^\[[A-Za-z0-9_\-]+\]$/', $accept ) ) {
			return false;
		}
		if ( empty( $cf7_mch['merge_fields'] ) || ! is_array( $cf7_mch['merge_fields'] ) ) {
			return false;
		}
		foreach ( $cf7_mch['merge_fields'] as $idx => $merge_field ) {
			if ( is_array( $merge_field ) && 'EMAIL' === ( $merge_field['tag'] ?? '' ) ) {
				$field_key = 'field' . ( (int) $idx + 3 );
				$mapped    = $cf7_mch[ $field_key ] ?? '';
				return is_scalar( $mapped ) && '' !== trim( (string) $mapped );
			}
		}
		return false;
	}

	private function __construct() {}
}
