<?php
/**
 * Subscription status resolver.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

class Cmatic_Status_Resolver {

	public static function resolve( array $cf7_mch, array $posted_data, Cmatic_File_Logger $logger ): ?string {
		$consent_required = 'required' === (string) ( $cf7_mch['consent_gate'] ?? '' );
		$consent_field    = $consent_required
			? (string) ( $cf7_mch['consent_field'] ?? '' )
			: (string) ( $cf7_mch['accept'] ?? '' );

		if ( '' !== trim( $consent_field ) ) {
			$acceptance = Cmatic_Email_Extractor::replace_tags( $consent_field, $posted_data );

			if ( empty( $acceptance ) ) {
				if ( ! $consent_required && ! empty( $cf7_mch['addunsubscr'] ) ) {
					return 'unsubscribed';
				}

				$logger->log( 'INFO', 'Subscription skipped: acceptance checkbox was not checked.' );
				Cmatic_Submission_Feedback::set_result( Cmatic_Submission_Feedback::skipped( 'acceptance_not_checked' ) );
				return null;
			}
		}

		if ( 'double' === (string) ( $cf7_mch['subscription_mode'] ?? '' ) ) {
			return 'pending';
		}

		if ( ! empty( $cf7_mch['double_optin'] ) || ! empty( $cf7_mch['confsubs'] ) ) {
			return 'pending';
		}

		return 'subscribed';
	}
}
