<?php
/**
 * Collision-safe loader for vendored Signls SDK copies.
 *
 * @package signls-sdk
 * @license GPL-3.0-or-later
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Signls_Sdk_Loader', false ) ) {
	final class Signls_Sdk_Loader {

		private static $candidates = array();

		private static $descriptors = array();

		private static $booted = false;

		private static $hooked = false;

		public static function add_candidate( $version, $bootstrap, $minimum_php = '7.4.0' ) {
			if ( ! is_string( $version ) || ! is_string( $bootstrap ) || ! is_string( $minimum_php ) ) {
				return;
			}

			if ( ! is_file( $bootstrap ) || version_compare( PHP_VERSION, $minimum_php, '<' ) ) {
				return;
			}

			self::$candidates[ $version . '|' . $bootstrap ] = array(
				'version'   => $version,
				'bootstrap' => $bootstrap,
			);
		}

		public static function register( array $descriptor ) {
			$product = isset( $descriptor['product_slug'] ) ? (string) $descriptor['product_slug'] : '';
			if ( ! preg_match( '/^[a-z0-9][a-z0-9-]{1,63}$/', $product ) ) {
				return false;
			}

			self::$descriptors[ $product ] = $descriptor;
			self::schedule_boot();
			return true;
		}

		public static function boot() {
			if ( self::$booted ) {
				return;
			}

			self::$booted = true;
			$candidate    = self::best_candidate();
			if ( null === $candidate ) {
				return;
			}

			require_once $candidate['bootstrap'];
			if ( ! class_exists( 'Signls\\Sdk\\V1\\Runtime' ) ) {
				return;
			}

			foreach ( self::$descriptors as $descriptor ) {
				try {
					$adapter_file = isset( $descriptor['adapter_file'] ) ? (string) $descriptor['adapter_file'] : '';
					if ( '' !== $adapter_file ) {
						if ( ! is_file( $adapter_file ) ) {
							continue;
						}
						require_once $adapter_file;
					}
					\Signls\Sdk\V1\Runtime::boot( $descriptor );
				} catch ( \Throwable $error ) {
					// A product's observations must never break the host plugin.
					continue;
				}
			}
		}

		public static function selected_version() {
			$candidate = self::best_candidate();
			return null === $candidate ? null : $candidate['version'];
		}

		private static function schedule_boot() {
			if ( self::$booted ) {
				return;
			}

			if ( function_exists( 'did_action' ) && did_action( 'plugins_loaded' ) ) {
				self::boot();
				return;
			}

			if ( ! self::$hooked && function_exists( 'add_action' ) ) {
				self::$hooked = true;
				add_action( 'plugins_loaded', array( __CLASS__, 'boot' ), PHP_INT_MAX );
			}
		}

		private static function best_candidate() {
			if ( empty( self::$candidates ) ) {
				return null;
			}

			$candidates = array_values( self::$candidates );
			usort(
				$candidates,
				static function ( $left, $right ) {
					return version_compare( $right['version'], $left['version'] );
				}
			);

			return $candidates[0];
		}
	}
}

Signls_Sdk_Loader::add_candidate( '1.0.0', __DIR__ . '/sdk.php', '7.4.0' );
