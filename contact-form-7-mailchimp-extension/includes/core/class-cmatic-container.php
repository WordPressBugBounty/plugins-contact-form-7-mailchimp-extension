<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

class Cmatic_Lite_Container {

	private static $instance;

	public static function instance(): \Cmatic\Lite\Kernel\Container {
		if ( ! self::$instance instanceof \Cmatic\Lite\Kernel\Container ) {
			self::$instance = new \Cmatic\Lite\Kernel\Container();
		}
		return self::$instance;
	}

	public static function set( string $id, $service ): void {
		self::instance()->set( $id, $service );
	}

	public static function factory( string $id, callable $factory ): void {
		self::instance()->factory( $id, $factory );
	}

	public static function get( string $id ) {
		return self::instance()->get( $id );
	}

	public static function has( string $id ): bool {
		return self::instance()->has( $id );
	}

	public static function clear(): void {
		self::instance()->clear();
	}

	public static function boot(): void {
		self::factory(
			Cmatic_Options_Interface::class,
			function () {
				return Cmatic_Options_Repository::instance();
			}
		);

		self::factory(
			'options',
			function () {
				return self::get( Cmatic_Options_Interface::class );
			}
		);

		self::factory(
			'auth.manager',
			function () {
				return new Cmatic_Lite_Auth_Manager();
			}
		);

		self::factory(
			'http.transport',
			function () {
				return new \Cmatic\Lite\Provider\HttpTransport();
			}
		);

		self::factory(
			'provider.catalog',
			function ( \Cmatic\Lite\Kernel\Container $container ) {
				$transport = $container->get( 'http.transport' );
				if ( ! $transport instanceof \Cmatic\Lite\Provider\HttpTransport ) {
					$transport = new \Cmatic\Lite\Provider\HttpTransport();
				}
				return new \Cmatic\Lite\Provider\Catalog( $transport );
			}
		);

		self::factory(
			'credential.vault',
			function () {
				return new \Cmatic\Lite\Provider\CredentialVault();
			}
		);
	}
}
