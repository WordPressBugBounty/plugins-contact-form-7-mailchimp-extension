<?php
/**
 * Optional provider subscriber lookup operation.
 *
 * @package contact-form-7-mailchimp-extension
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

interface Cmatic_Lite_Esp_Lookup_Interface {
	/**
	 * Looks up one subscriber without exposing the credential.
	 *
	 * @param string $api_key Provider credential.
	 * @param string $email   Subscriber email address.
	 */
	public function lookup( string $api_key, string $email ): array;
}
