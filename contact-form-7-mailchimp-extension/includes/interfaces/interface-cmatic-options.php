<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

interface Cmatic_Options_Interface {

	/**
	 * @return array
	 */
	public function get_all();

	/**
	 * @param string $key     Dot-notation key (e.g., 'install.id').
	 * @param mixed  $default Default value if not found.
	 * @return mixed
	 */
	public function get( $key, $default = null );

	/**
	 * @param string $key   Dot-notation key.
	 * @param mixed  $value Value to set.
	 * @return bool Success.
	 */
	public function set( $key, $value );

	/**
	 * @param array $data Full options data.
	 * @return bool Success.
	 */
	public function save( $data );

	/**
	 * @return void
	 */
	public function clear_cache();
}
