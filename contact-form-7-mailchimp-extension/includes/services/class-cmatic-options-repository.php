<?php
/**
 * Options repository.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

class Cmatic_Options_Repository implements Cmatic_Options_Interface {

	private const OPTION_NAME = 'cmatic';

	private $cache = null;

	public function get_all() {
		if ( null === $this->cache ) {
			$this->cache = get_option( self::OPTION_NAME, array() );
			if ( ! is_array( $this->cache ) ) {
				$this->cache = array();
			}
		}
		return $this->cache;
	}

	public function get( $key, $default = null ) {
		$data  = $this->get_all();
		$keys  = explode( '.', $key );
		$value = $data;

		foreach ( $keys as $k ) {
			if ( ! isset( $value[ $k ] ) ) {
				return $default;
			}
			$value = $value[ $k ];
		}

		return $value;
	}

	public function set( $key, $value ) {
		$data = $this->get_all();
		$keys = explode( '.', $key );
		$ref  = &$data;

		foreach ( $keys as $i => $k ) {
			if ( count( $keys ) - 1 === $i ) {
				$ref[ $k ] = $value;
			} else {
				if ( ! isset( $ref[ $k ] ) || ! is_array( $ref[ $k ] ) ) {
					$ref[ $k ] = array();
				}
				$ref = &$ref[ $k ];
			}
		}

		$this->cache = $data;
		return update_option( self::OPTION_NAME, $data );
	}

	public function save( $data ) {
		$this->cache = $data;
		return update_option( self::OPTION_NAME, $data );
	}

	public function get_legacy( $name, $default = null ) {
		return get_option( $name, $default );
	}

	public function delete_legacy( $name ) {
		return delete_option( $name );
	}

	public function clear_cache() {
		$this->cache = null;
	}

	// =========================================================================
	// STATIC API (for global access without instantiation)
	// =========================================================================

	private static $instance = null;

	private static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function get_option( string $key, $default = null ) {
		return self::instance()->get( $key, $default );
	}

	public static function set_option( string $key, $value ): bool {
		return self::instance()->set( $key, $value );
	}

	public static function get_all_options(): array {
		return self::instance()->get_all();
	}
}
