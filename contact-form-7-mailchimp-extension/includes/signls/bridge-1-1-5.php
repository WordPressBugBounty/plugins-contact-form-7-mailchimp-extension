<?php
/**
 * Versioned compatibility bridge for late Signls SDK registration.
 *
 * @package signls-sdk
 * @license GPL-3.0-or-later
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Signls_Sdk_Bridge_1_1_5', false ) ) {
	final class Signls_Sdk_Bridge_1_1_5 {

		public static function register( array $descriptor ) {
			try {
				if ( ! Signls_Sdk_Loader::register( $descriptor ) ) {
					return false;
				}
				if ( ! function_exists( 'did_action' ) || ! did_action( 'plugins_loaded' ) ) {
					return true;
				}
				return self::boot_descriptor( $descriptor );
			} catch ( \Throwable $error ) {
				return false;
			}
		}

		public static function flush_immediate( $product ) {
			try {
				if ( ! is_string( $product ) || ! preg_match( '/^[a-z0-9][a-z0-9-]{1,63}$/', $product ) ) {
					return array(
						'ok'    => false,
						'class' => 'invalid_product',
					);
				}
				if ( ! class_exists( 'Signls\\Sdk\\V1\\Runtime' ) ) {
					return array(
						'ok'    => false,
						'class' => 'runtime_unavailable',
					);
				}
				if ( is_callable( array( 'Signls\\Sdk\\V1\\Runtime', 'flush_immediate' ) ) ) {
					return \Signls\Sdk\V1\Runtime::flush_immediate( $product );
				}
				if ( is_callable( array( 'Signls\\Sdk\\V1\\Runtime', 'run' ) ) ) {
					return \Signls\Sdk\V1\Runtime::run( $product );
				}
				return array(
					'ok'    => false,
					'class' => 'runtime_incompatible',
				);
			} catch ( \Throwable $error ) {
				return array(
					'ok'    => false,
					'class' => 'runtime_failure',
				);
			}
		}

		private static function boot_descriptor( array $descriptor ) {
			if ( ! class_exists( 'Signls\\Sdk\\V1\\Runtime' ) ) {
				$sdk_path = isset( $descriptor['sdk_path'] ) ? (string) $descriptor['sdk_path'] : '';
				if ( '' === $sdk_path || ! self::require_file( rtrim( $sdk_path, '/\\' ) . '/sdk.php' ) ) {
					return false;
				}
			}
			if ( ! class_exists( 'Signls\\Sdk\\V1\\Runtime' ) ) {
				return false;
			}

			$adapter_file = isset( $descriptor['adapter_file'] ) ? (string) $descriptor['adapter_file'] : '';
			if ( '' !== $adapter_file && ! self::require_file( $adapter_file ) ) {
				return false;
			}
			return \Signls\Sdk\V1\Runtime::boot( $descriptor );
		}

		private static function require_file( $path ) {
			if ( ! is_string( $path ) || ! is_file( $path ) ) {
				return false;
			}
			set_error_handler( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Contain a transient missing-file warning during plugin replacement.
				static function ( $severity, $message ) use ( $path ) {
					return E_WARNING === $severity && false !== strpos( $message, $path );
				}
			);
			try {
				require_once $path;
				return true;
			} catch ( \Throwable $error ) {
				return false;
			} finally {
				restore_error_handler();
			}
		}
	}
}
