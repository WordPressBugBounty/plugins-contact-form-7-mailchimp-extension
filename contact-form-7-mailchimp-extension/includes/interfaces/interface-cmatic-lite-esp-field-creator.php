<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

interface Cmatic_Lite_Esp_Field_Creator_Interface {
	/**
	 * @param string $api_key     Provider credential.
	 * @param array  $spec        Validated field specification.
	 * @param bool   $log_enabled Whether operational logging is enabled.
	 */
	public function create_field( string $api_key, array $spec, bool $log_enabled = false ): array;
}
