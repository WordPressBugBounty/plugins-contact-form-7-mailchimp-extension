<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    hello@chimpmatic.com
 * @copyright 2014-2026 ChimpMatic
 * @license   GPL-3.0+
 */

namespace Cmatic\Lite\Submission;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing -- Typed signatures follow the existing Lite class convention.
final class StatusPolicy {

	public static function resolve( array $cf7_mch, array $posted_data, \Cmatic_File_Logger $logger, ?array $gate = null ): ?string {
		$gate = null === $gate ? ConsentGate::decide( $cf7_mch, $posted_data, array(), false ) : $gate;
		if ( ! $gate['allowed'] ) {
			if ( ! $gate['required'] && ! empty( $cf7_mch['addunsubscr'] ) ) {
				return 'unsubscribed';
			}

			$logger->log( 'INFO', 'Subscription skipped: acceptance checkbox was not checked.' );
			Feedback::set_result( Feedback::skipped( 'acceptance_not_checked' ) );
			return null;
		}

		if ( 'double' === (string) ( $cf7_mch['subscription_mode'] ?? '' ) ) {
			return 'pending';
		}

		if ( ! empty( $cf7_mch['double_optin'] ) || ! empty( $cf7_mch['confsubs'] ) ) {
			return 'pending';
		}

		return 'subscribed';
	}

	private function __construct() {}
}
