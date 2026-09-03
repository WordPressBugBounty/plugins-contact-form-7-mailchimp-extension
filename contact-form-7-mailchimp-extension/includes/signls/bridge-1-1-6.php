<?php
/**
 * Versioned compatibility facade for shared Signls SDK runtimes.
 *
 * @package signls-sdk
 * @license GPL-3.0-or-later
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Signls_Sdk_Bridge_1_1_6', false ) ) {
	final class Signls_Sdk_Bridge_1_1_6 {

		/**
		 * @param array<string, mixed> $descriptor Product descriptor.
		 */
		public static function register( array $descriptor ): bool {
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

		public static function enable( string $product, string $source, int $notice_version ): bool {
			if ( ! self::valid_product( $product ) ) {
				return false;
			}
			try {
				if (
					! self::runtime_available()
					|| ! is_callable( array( 'Signls\\Sdk\\V1\\Runtime', 'enable' ) )
					|| ! \Signls\Sdk\V1\Runtime::enable( $product, $source, $notice_version )
				) {
					return false;
				}
				self::flush_legacy_runtime( $product );
				return true;
			} catch ( \Throwable $error ) {
				return false;
			}
		}

		public static function disable( string $product, string $source, int $notice_version ): bool {
			if ( ! self::valid_product( $product ) ) {
				return false;
			}
			try {
				return self::runtime_available()
					&& is_callable( array( 'Signls\\Sdk\\V1\\Runtime', 'disable' ) )
					&& \Signls\Sdk\V1\Runtime::disable( $product, $source, $notice_version );
			} catch ( \Throwable $error ) {
				return false;
			}
		}

		public static function relevant_change( string $product ): bool {
			if ( ! self::valid_product( $product ) ) {
				return false;
			}
			try {
				if (
					! self::runtime_available()
					|| ! is_callable( array( 'Signls\\Sdk\\V1\\Runtime', 'relevant_change' ) )
				) {
					return false;
				}
				\Signls\Sdk\V1\Runtime::relevant_change( $product );
				self::flush_legacy_runtime( $product );
				return true;
			} catch ( \Throwable $error ) {
				return false;
			}
		}

		/**
		 * @return array<string, mixed>
		 */
		public static function deactivate( string $product ): array {
			if ( ! self::valid_product( $product ) ) {
				return self::result( false, 'invalid_product', true );
			}
			$result = self::result( false, 'runtime_unavailable', false );
			try {
				if (
					self::runtime_available()
					&& is_callable( array( 'Signls\\Sdk\\V1\\Runtime', 'relevant_change' ) )
				) {
					\Signls\Sdk\V1\Runtime::relevant_change( $product );
					$result = self::flush_immediate( $product );
				}
			} catch ( \Throwable $error ) {
				$result = self::result( false, 'runtime_failure', false );
			} finally {
				self::suspend( $product );
			}
			return $result;
		}

		/**
		 * @return array<string, mixed>
		 */
		public static function flush_immediate( string $product ): array {
			if ( ! self::valid_product( $product ) ) {
				return self::result( false, 'invalid_product', true );
			}
			try {
				if ( ! self::runtime_available() ) {
					return self::result( false, 'runtime_unavailable', false );
				}
				if ( is_callable( array( 'Signls\\Sdk\\V1\\Runtime', 'flush_immediate' ) ) ) {
					return \Signls\Sdk\V1\Runtime::flush_immediate( $product );
				}
				if ( is_callable( array( 'Signls\\Sdk\\V1\\Runtime', 'run' ) ) ) {
					$result = \Signls\Sdk\V1\Runtime::run( $product );
					self::settle_legacy_delivery( $product, $result );
					return $result;
				}
				return self::result( false, 'runtime_incompatible', false );
			} catch ( \Throwable $error ) {
				return self::result( false, 'runtime_failure', false );
			}
		}

		public static function suspend( string $product ): bool {
			if ( ! self::valid_product( $product ) ) {
				return false;
			}
			try {
				if (
					self::runtime_available()
					&& is_callable( array( 'Signls\\Sdk\\V1\\Runtime', 'suspend' ) )
				) {
					\Signls\Sdk\V1\Runtime::suspend( $product );
					return true;
				}
				self::clear_product_schedules( $product );
				return true;
			} catch ( \Throwable $error ) {
				self::clear_product_schedules( $product );
				return false;
			}
		}

		/**
		 * @return array<string, mixed>
		 */
		public static function state( string $product ): array {
			if ( ! self::valid_product( $product ) ) {
				return array();
			}
			try {
				if (
					self::runtime_available()
					&& is_callable( array( 'Signls\\Sdk\\V1\\Runtime', 'state' ) )
				) {
					return \Signls\Sdk\V1\Runtime::state( $product );
				}
			} catch ( \Throwable $error ) {
				return array();
			}
			return array();
		}

		private static function flush_legacy_runtime( string $product ): void {
			if ( is_callable( array( 'Signls\\Sdk\\V1\\Runtime', 'flush_immediate' ) ) ) {
				return;
			}
			self::flush_immediate( $product );
		}

		/**
		 * @param array<string, mixed> $result Delivery result.
		 */
		private static function settle_legacy_delivery( string $product, array $result ): void {
			if ( empty( $result['ok'] ) && empty( $result['permanent'] ) && empty( $result['quarantined'] ) ) {
				return;
			}
			if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
				$hash = substr( hash( 'sha256', $product ), 0, 12 );
				wp_clear_scheduled_hook( 'signls_sdk_v1_' . $hash . '_refresh' );
			}
		}

		private static function clear_product_schedules( string $product ): void {
			if ( ! function_exists( 'wp_clear_scheduled_hook' ) ) {
				return;
			}
			$hash = substr( hash( 'sha256', $product ), 0, 12 );
			wp_clear_scheduled_hook( 'signls_sdk_v1_' . $hash . '_routine' );
			wp_clear_scheduled_hook( 'signls_sdk_v1_' . $hash . '_refresh' );
		}

		/**
		 * @param array<string, mixed> $descriptor Product descriptor.
		 */
		private static function boot_descriptor( array $descriptor ): bool {
			if ( ! self::runtime_available() ) {
				$sdk_path = isset( $descriptor['sdk_path'] ) && is_string( $descriptor['sdk_path'] ) ? $descriptor['sdk_path'] : '';
				if ( '' === $sdk_path || ! self::require_file( rtrim( $sdk_path, '/\\' ) . '/sdk.php' ) ) {
					return false;
				}
			}
			if (
				! self::runtime_available()
				|| ! is_callable( array( 'Signls\\Sdk\\V1\\Runtime', 'boot' ) )
			) {
				return false;
			}

			$adapter_file = isset( $descriptor['adapter_file'] ) && is_string( $descriptor['adapter_file'] ) ? $descriptor['adapter_file'] : '';
			if ( '' !== $adapter_file && ! self::require_file( $adapter_file ) ) {
				return false;
			}
			return \Signls\Sdk\V1\Runtime::boot( $descriptor );
		}

		private static function runtime_available(): bool {
			return class_exists( 'Signls\\Sdk\\V1\\Runtime' );
		}

		private static function valid_product( string $product ): bool {
			return 1 === preg_match( '/^[a-z0-9][a-z0-9-]{1,63}$/D', $product );
		}

		/**
		 * @return array<string, mixed>
		 */
		private static function result( bool $ok, string $class, bool $permanent ): array {
			return array(
				'ok'        => (bool) $ok,
				'class'     => (string) $class,
				'status'    => 0,
				'permanent' => (bool) $permanent,
			);
		}

		private static function require_file( string $path ): bool {
			if ( ! is_file( $path ) ) {
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
