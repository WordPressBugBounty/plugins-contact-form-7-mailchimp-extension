<?php
/**
 * Logger interface.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

interface Cmatic_Logger_Interface {

	/**
	 * Log a message.
	 *
	 * @param string $level   Log level (INFO, ERROR, WARNING, DEBUG, CRITICAL).
	 * @param string $message Log message.
	 * @param mixed  $context Optional context data.
	 * @return void
	 */
	public function log( string $level, string $message, $context = null ): void;
}
