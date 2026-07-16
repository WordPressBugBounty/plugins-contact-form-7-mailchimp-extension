<?php
/**
 * Optional provider field creation operation.
 *
 * @package contact-form-7-mailchimp-extension
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

interface Cmatic_Lite_Esp_Field_Creator_Interface {
	/**
	 * Creates one provider field and returns the normalized provider result.
	 *
	 * @param string $api_key     Provider credential.
	 * @param array  $spec        Validated field specification.
	 * @param bool   $log_enabled Whether operational logging is enabled.
	 */
	public function create_field( string $api_key, array $spec, bool $log_enabled = false ): array;
}
