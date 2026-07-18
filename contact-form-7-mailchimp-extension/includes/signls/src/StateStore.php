<?php
/**
 * Product-scoped non-autoloaded state.
 *
 * @package signls-sdk
 * @license GPL-3.0-or-later
 */

namespace Signls\Sdk\V1;

defined( 'ABSPATH' ) || exit;

final class StateStore {

	private $product;

	private $option_name;

	public function __construct( string $product ) {
		$this->product     = $product;
		$this->option_name = 'signls_sdk_v1_' . substr( hash( 'sha256', $product ), 0, 12 );
	}

	public function product(): string {
		return $this->product;
	}

	public function option_name(): string {
		return $this->option_name;
	}

	public function all(): array {
		$value = get_option( $this->option_name, array() );
		return is_array( $value ) ? $value : array();
	}

	public function get( string $key, $default = null ) {
		$state = $this->all();
		return array_key_exists( $key, $state ) ? $state[ $key ] : $default;
	}

	public function set( string $key, $value ): bool {
		$state         = $this->all();
		$state[ $key ] = $value;
		return $this->replace( $state );
	}

	public function set_many( array $values ): bool {
		return $this->replace( array_merge( $this->all(), $values ) );
	}

	public function delete_keys( array $keys ): bool {
		$state = $this->all();
		foreach ( $keys as $key ) {
			unset( $state[ $key ] );
		}
		return $this->replace( $state );
	}

	public function delete(): bool {
		return delete_option( $this->option_name );
	}

	private function replace( array $state ): bool {
		if ( false === get_option( $this->option_name, false ) ) {
			return add_option( $this->option_name, $state, '', false );
		}
		return update_option( $this->option_name, $state, false );
	}
}
