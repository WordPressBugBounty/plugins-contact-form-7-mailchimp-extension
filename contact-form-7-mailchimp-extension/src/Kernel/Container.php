<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    hello@chimpmatic.com
 * @copyright 2014-2026 ChimpMatic
 * @license   GPL-3.0+
 */

declare(strict_types=1);

namespace Cmatic\Lite\Kernel;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing -- Typed signatures follow the existing Lite class convention.
final class Container {
	/** @var array<string,mixed> */
	private $services = array();

	/** @var array<string,callable> */
	private $factories = array();

	/**
	 * @param mixed $service
	 */
	public function set( string $id, $service ): void {
		$this->services[ $id ] = $service;
	}

	public function factory( string $id, callable $factory ): void {
		$this->factories[ $id ] = $factory;
	}

	/**
	 * @return mixed
	 */
	public function get( string $id ) {
		if ( isset( $this->services[ $id ] ) ) {
			return $this->services[ $id ];
		}
		if ( isset( $this->factories[ $id ] ) ) {
			$this->services[ $id ] = call_user_func( $this->factories[ $id ], $this );
			unset( $this->factories[ $id ] );
			return $this->services[ $id ];
		}
		return null;
	}

	public function has( string $id ): bool {
		return isset( $this->services[ $id ] ) || isset( $this->factories[ $id ] );
	}

	public function clear(): void {
		$this->services  = array();
		$this->factories = array();
	}
}
