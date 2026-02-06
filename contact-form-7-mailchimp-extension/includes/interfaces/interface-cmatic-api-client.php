<?php
/**
 * API client interface.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

interface Cmatic_Api_Client_Interface {

	/**
	 * Validate an API key.
	 *
	 * @param string $api_key     The API key to validate.
	 * @param bool   $log_enabled Whether logging is enabled.
	 * @return array{api-validation: int} Validation result.
	 */
	public static function validate_key( string $api_key, bool $log_enabled = false ): array;

	/**
	 * Get audiences (lists) from Mailchimp.
	 *
	 * @param string $api_key     The API key.
	 * @param bool   $log_enabled Whether logging is enabled.
	 * @return array{lisdata: array, merge_fields?: array}
	 */
	public static function get_lists( string $api_key, bool $log_enabled = false ): array;

	/**
	 * Get merge fields for a specific list.
	 *
	 * @param string $api_key     The API key.
	 * @param string $list_id     The list ID.
	 * @param bool   $log_enabled Whether logging is enabled.
	 * @return array{merge_fields: array}
	 */
	public static function get_merge_fields( string $api_key, string $list_id, bool $log_enabled = false ): array;
}
