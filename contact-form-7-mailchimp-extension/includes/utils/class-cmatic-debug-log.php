<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

final class Cmatic_Debug_Log {

	const AUTO_OFF   = 14 * DAY_IN_SECONDS;
	const MAX_BYTES  = 2097152;
	const TAIL_LINES = 500;
	const DIR_NAME   = 'chimpmatic';
	const FILE_STEM  = 'chimpmatic-lite';

	public static function logging(): bool {
		$data = Cmatic_Options_Repository::get_all_options();
		if ( empty( $data['debug'] ) ) {
			return false;
		}

		return ! isset( $data['debug_until'] ) || time() < (int) $data['debug_until'];
	}

	public static function until(): int {
		$data = Cmatic_Options_Repository::get_all_options();

		return self::logging() ? (int) ( $data['debug_until'] ?? 0 ) : 0;
	}

	public static function switched_on(): void {
		Cmatic_Options_Repository::set_option( 'debug_until', time() + self::AUTO_OFF );
		self::ensure_dir();
		self::write( 'INFO', 'Debug log switched on; it switches itself off in 14 days.' );
	}

	public static function switched_off(): void {
		Cmatic_Options_Repository::set_option( 'debug_until', 0 );
	}

	public static function expire(): bool {
		$data = Cmatic_Options_Repository::get_all_options();
		if ( empty( $data['debug'] ) ) {
			return false;
		}
		if ( ! isset( $data['debug_until'] ) ) {
			Cmatic_Options_Repository::set_option( 'debug_until', time() + self::AUTO_OFF );

			return false;
		}
		if ( time() < (int) $data['debug_until'] ) {
			return false;
		}
		Cmatic_Options_Repository::set_option( 'debug', 0 );
		Cmatic_Options_Repository::set_option( 'debug_until', 0 );

		return true;
	}

	public static function dir(): string {
		$uploads = wp_upload_dir( null, false );
		$base    = is_array( $uploads ) && ! empty( $uploads['basedir'] ) ? (string) $uploads['basedir'] : WP_CONTENT_DIR . '/uploads';
		$dir     = rtrim( $base, '/' ) . '/' . self::DIR_NAME;

		return rtrim( (string) apply_filters( 'cmatic_debug_log_dir', $dir ), '/' );
	}

	public static function path(): string {
		return self::dir() . '/' . self::FILE_STEM . '-' . self::token() . '.log';
	}

	private static function token(): string {
		$token = (string) Cmatic_Options_Repository::get_option( 'log_token', '' );
		if ( ! preg_match( '/^[a-f0-9]{16}$/', $token ) ) {
			$token = substr( bin2hex( random_bytes( 8 ) ), 0, 16 );
			Cmatic_Options_Repository::set_option( 'log_token', $token );
		}

		return $token;
	}

	public static function ensure_dir(): bool {
		$dir = self::dir();
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return false;
		}
		if ( ! is_file( $dir . '/index.php' ) ) {
			file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- a guard file in the plugin's own directory.
		}
		if ( ! is_file( $dir . '/.htaccess' ) ) {
			file_put_contents( $dir . '/.htaccess', "# ChimpMatic debug logs. Denied to the web; the plugin reads them itself.\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder deny,allow\nDeny from all\n</IfModule>\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- a guard file in the plugin's own directory.
		}

		return wp_is_writable( $dir );
	}

	public static function write( string $level, string $message ): void {
		if ( ! self::logging() || ! self::ensure_dir() ) {
			return;
		}
		$path = self::path();
		if ( is_file( $path ) && (int) filesize( $path ) > self::MAX_BYTES ) {
			rename( $path, $path . '.1' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- rotating the plugin's own log file.
		}
		$line = sprintf( "[%s] %s %s\n", gmdate( 'Y-m-d H:i:s' ), strtoupper( substr( $level, 0, 8 ) ), self::redact( $message ) );
		file_put_contents( $path, $line, FILE_APPEND | LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- appending to the plugin's own log file.
	}

	public static function redact( string $text ): string {
		$text = (string) preg_replace( '/(bearer|token|api[_-]?key|x-api-key|authorization)(["\s:=]+)[A-Za-z0-9._\-]{8,}/i', '$1$2[key]', $text );
		$text = (string) preg_replace( '/\b[A-Za-z0-9_\-]{32,}\b/', '[token]', $text );

		return (string) preg_replace_callback(
			'/([A-Za-z0-9._%+\-])([A-Za-z0-9._%+\-]*)@([A-Za-z0-9.\-]+\.[A-Za-z]{2,})/',
			static function ( array $m ): string {
				return $m[1] . str_repeat( '*', max( 1, strlen( $m[2] ) ) ) . '@' . $m[3];
			},
			$text
		);
	}

	/**
	 * @return array<int,string>
	 */
	public static function tail( int $lines = self::TAIL_LINES ): array {
		return self::tail_of( self::path(), $lines );
	}

	/**
	 * @return array<int,string>
	 */
	public static function tail_of( string $path, int $lines ): array {
		if ( '' === $path || ! is_file( $path ) || ! is_readable( $path ) ) {
			return array();
		}
		$size  = (int) filesize( $path );
		$chunk = min( $size, max( 65536, $lines * 400 ) );
		if ( $chunk <= 0 ) {
			return array();
		}
		$fh = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- reading the tail of a log file.
		if ( false === $fh ) {
			return array();
		}
		fseek( $fh, -$chunk, SEEK_END );
		$data = (string) fread( $fh, $chunk ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- reading the tail of a log file.
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- reading the tail of a log file.
		$all = explode( "\n", rtrim( $data, "\n" ) );
		if ( $chunk < $size && count( $all ) > 1 ) {
			array_shift( $all );
		}

		return array_values( array_slice( $all, -$lines ) );
	}

	public static function clear(): bool {
		$path = self::path();
		foreach ( array( $path, $path . '.1' ) as $file ) {
			if ( is_file( $file ) ) {
				wp_delete_file( $file );
			}
		}
		if ( self::logging() ) {
			self::write( 'INFO', 'Debug log cleared by an administrator.' );
		}

		return ! is_file( $path . '.1' );
	}

	public static function site_log_path(): string {
		if ( defined( 'WP_DEBUG_LOG' ) ) {
			$configured = constant( 'WP_DEBUG_LOG' );
			if ( is_string( $configured ) && '' !== $configured ) {
				return $configured;
			}
			if ( $configured ) {
				return WP_CONTENT_DIR . '/debug.log';
			}
		}
		$ini = (string) ini_get( 'error_log' );
		if ( '' !== $ini && ! in_array( strtolower( $ini ), array( 'syslog', 'stderr' ), true ) && is_file( $ini ) ) {
			return $ini;
		}

		return '';
	}

	/**
	 * @return array<int,string>
	 */
	public static function site_log_tail( bool $ours_only, int $lines = self::TAIL_LINES ): array {
		$all = self::tail_of( self::site_log_path(), $ours_only ? $lines * 4 : $lines );
		if ( ! $ours_only ) {
			return $all;
		}
		$needles = array( 'chimpmatic', 'cmatic', 'contact-form-7-mailchimp-extension' );
		$kept    = array_values(
			array_filter(
				$all,
				static function ( string $line ) use ( $needles ): bool {
					foreach ( $needles as $needle ) {
						if ( false !== stripos( $line, $needle ) ) {
							return true;
						}
					}

					return false;
				}
			)
		);

		return array_slice( $kept, -$lines );
	}
}
