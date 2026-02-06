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
		// Double opt-in enabled (per-form setting).
		if ( ! empty( $cf7_mch['double_optin'] ) || ! empty( $cf7_mch['confsubs'] ) ) {
			return 'pending';
		}

		// Acceptance checkbox required.
		if ( ! empty( $cf7_mch['accept'] ) ) {
			$acceptance = Cmatic_Email_Extractor::replace_tags( $cf7_mch['accept'], $posted_data );

			if ( empty( $acceptance ) ) {
				// Add as unsubscribed if configured.
				if ( ! empty( $cf7_mch['addunsubscr'] ) ) {
					return 'unsubscribed';
				}

				$logger->log( 'INFO', 'Subscription skipped: acceptance checkbox was not checked.' );
				Cmatic_Submission_Feedback::set_result( Cmatic_Submission_Feedback::skipped( 'acceptance_not_checked' ) );
				return null;
			}
		}

		return 'subscribed';
	}
}
