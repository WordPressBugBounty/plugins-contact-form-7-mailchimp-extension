<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    hello@chimpmatic.com
 * @copyright 2014-2026 ChimpMatic
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'cmatic_lite_alias_table' ) ) {
	/**
	 * @return array<string,string>
	 */
	function cmatic_lite_alias_table(): array {
		return array(
			'Cmatic_Lite_Esp_Capabilities'       => 'Cmatic\\Lite\\Settings\\Entitlements',
			'Cmatic_Submission_Feedback'         => 'Cmatic\\Lite\\Submission\\Feedback',
			'Cmatic_Sync_Stats'                  => 'Cmatic\\Lite\\Submission\\Stats',
			'Cmatic_Email_Extractor'             => 'Cmatic\\Lite\\Submission\\FieldValues',
			'Cmatic_Merge_Vars_Builder'          => 'Cmatic\\Lite\\Submission\\FieldValues',
			'Cmatic_Status_Resolver'             => 'Cmatic\\Lite\\Submission\\StatusPolicy',
			'Cmatic_Mailerlite_Routing_Resolver' => 'Cmatic\\Lite\\Submission\\DestinationResolver',
			'Cmatic_Form_Tags'                   => 'Cmatic\\Lite\\Settings\\FormTags',
		);
	}

	function cmatic_lite_register_aliases(): void {
		foreach ( cmatic_lite_alias_table() as $legacy => $modern ) {
			if ( class_exists( $legacy, false ) || interface_exists( $legacy, false ) ) {
				continue;
			}
			class_alias( $modern, $legacy );
		}
	}

	cmatic_lite_register_aliases();
}
