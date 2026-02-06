<?php
/**
 * Dependency container.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

class Cmatic_Lite_Container {

	private static $services = array();

	private static $factories = array();

	public static function set( string $id, $service ): void {
		self::$services[ $id ] = $service;
	}

	public static function factory( string $id, callable $factory ): void {
		self::$factories[ $id ] = $factory;
	}

	public static function get( string $id ) {
		// Return cached instance if exists.
		if ( isset( self::$services[ $id ] ) ) {
			return self::$services[ $id ];
		}

		// Build from factory if exists.
		if ( isset( self::$factories[ $id ] ) ) {
			self::$services[ $id ] = call_user_func( self::$factories[ $id ] );
			unset( self::$factories[ $id ] );
			return self::$services[ $id ];
		}

		return null;
	}

	public static function has( string $id ): bool {
		return isset( self::$services[ $id ] ) || isset( self::$factories[ $id ] );
	}

	public static function clear(): void {
		self::$services  = array();
		self::$factories = array();
	}

	public static function boot(): void {
		// Register Options Repository.
		self::factory(
			Cmatic_Options_Interface::class,
			function () {
				return new Cmatic_Options_Repository();
			}
		);

		// Alias for backward compatibility.
		self::factory(
			'options',
			function () {
				return self::get( Cmatic_Options_Interface::class );
			}
		);
	}
}
