<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

interface Cmatic_Lite_Esp_Lookup_Interface {
	/**
	 * @param string $api_key Provider credential.
	 * @param string $email   Subscriber email address.
	 */
	public function lookup( string $api_key, string $email ): array;
}
