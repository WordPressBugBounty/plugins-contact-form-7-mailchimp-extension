<?php
/**
 * Options repository interface.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

interface Cmatic_Options_Interface {

	/**
	 * Get all stored options.
	 *
	 * @return array
	 */
	public function get_all();

	/**
	 * Get a value using dot notation.
	 *
	 * @param string $key     Dot-notation key (e.g., 'install.id').
	 * @param mixed  $default Default value if not found.
	 * @return mixed
	 */
	public function get( $key, $default = null );

	/**
	 * Set a value using dot notation.
	 *
	 * @param string $key   Dot-notation key.
	 * @param mixed  $value Value to set.
	 * @return bool Success.
	 */
	public function set( $key, $value );

	/**
	 * Save the full options array.
	 *
	 * @param array $data Full options data.
	 * @return bool Success.
	 */
	public function save( $data );

	/**
	 * Clear internal cache.
	 *
	 * @return void
	 */
	public function clear_cache();
}
