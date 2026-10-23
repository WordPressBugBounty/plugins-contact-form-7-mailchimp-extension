<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

final class Cmatic_Sync_Stats {
	private const OPTION      = 'cmatic_sync_stats';
	private const MAX_MESSAGE = 160;

	/**
	 * @param array<mixed> $result
	 */
	public static function observe( array $result, int $form_id = 0 ): void {
		try {
			if ( ! empty( $result['skipped'] ) || ! array_key_exists( 'success', $result ) || null === $result['success'] ) {
				return;
			}
			if ( $form_id <= 0 ) {
				$form_id = self::current_form_id();
			}
			if ( $form_id <= 0 ) {
				return;
			}
			$message = isset( $result['message'] ) && is_scalar( $result['message'] ) ? (string) $result['message'] : '';
			$detail  = isset( $result['detail'] ) && is_scalar( $result['detail'] ) ? (string) $result['detail'] : '';
			self::record( $form_id, (bool) $result['success'], '' !== $detail ? $detail : $message, self::provider_for( $form_id ) );
		} catch ( Throwable $error ) {
			return;
		}
	}

	public static function record( int $form_id, bool $success, string $message = '', string $provider = '' ): void {
		$all   = self::all();
		$entry = self::entry( $all[ $form_id ] ?? null );
		$now   = time();
		if ( 0 === $entry['since'] ) {
			$entry['since'] = $now;
		}
		if ( $success ) {
			$entry['ok']           = self::int( $entry['ok'] ) + 1;
			$entry['last_ok_at']   = $now;
			$entry['last_outcome'] = 'ok';
		} else {
			$entry['failed']        = self::int( $entry['failed'] ) + 1;
			$entry['last_error']    = self::redact( $message );
			$entry['last_error_at'] = $now;
			$entry['last_outcome']  = 'fail';
		}
		if ( '' !== $provider ) {
			$entry['provider'] = sanitize_key( $provider );
		}
		$all[ $form_id ] = $entry;
		update_option( self::OPTION, $all, false );
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function all(): array {
		$raw = get_option( self::OPTION, array() );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$all = array();
		foreach ( $raw as $form_id => $entry ) {
			if ( is_numeric( $form_id ) && is_array( $entry ) ) {
				$all[ (int) $form_id ] = self::entry( $entry );
			}
		}
		return $all;
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function get( int $form_id ): array {
		$all = self::all();
		return isset( $all[ $form_id ] ) ? $all[ $form_id ] : self::entry( null );
	}

	public static function has( int $form_id ): bool {
		return self::has_counts( self::get( $form_id ) );
	}

	/**
	 * @param array<string,mixed> $entry
	 */
	public static function has_counts( array $entry ): bool {
		return self::int( $entry['ok'] ?? 0 ) > 0 || self::int( $entry['failed'] ?? 0 ) > 0;
	}

	public static function forget( int $form_id ): void {
		$all = self::all();
		unset( $all[ $form_id ] );
		update_option( self::OPTION, $all, false );
	}

	/**
	 * @param array<string,mixed> $entry
	 */
	public static function is_failing( array $entry ): bool {
		return 'fail' === self::str( $entry['last_outcome'] ?? '' );
	}

	/**
	 * @param array<string,mixed> $entry
	 */
	public static function summary( array $entry ): string {
		$ok      = self::int( $entry['ok'] ?? 0 );
		$failed  = self::int( $entry['failed'] ?? 0 );
		$last_ok = self::int( $entry['last_ok_at'] ?? 0 );
		if ( $ok <= 0 && $failed <= 0 ) {
			return __( 'no syncs yet', 'contact-form-7-mailchimp-extension' );
		}
		$parts = array(
			/* translators: %s: number of synced submissions */
			sprintf( _n( '%s synced', '%s synced', $ok, 'contact-form-7-mailchimp-extension' ), number_format_i18n( $ok ) ),
			/* translators: %s: number of failed submissions */
			sprintf( _n( '%s failed', '%s failed', $failed, 'contact-form-7-mailchimp-extension' ), number_format_i18n( $failed ) ),
		);
		if ( $last_ok > 0 ) {
			/* translators: %s: human time difference */
			$parts[] = sprintf( __( 'last %s ago', 'contact-form-7-mailchimp-extension' ), human_time_diff( $last_ok ) );
		}
		return implode( ' · ', $parts );
	}

	public static function redact( string $message ): string {
		$message = (string) preg_replace( '/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', '[email]', $message );
		$message = (string) preg_replace( '/\b[a-f0-9]{32}-us\d+\b/i', '[key]', $message );
		$message = (string) preg_replace( '/\s+/', ' ', trim( $message ) );
		if ( strlen( $message ) > self::MAX_MESSAGE ) {
			$message = substr( $message, 0, self::MAX_MESSAGE - 1 ) . '…';
		}
		return $message;
	}

	private static function current_form_id(): int {
		if ( ! class_exists( 'WPCF7_Submission' ) ) {
			return 0;
		}
		$submission = WPCF7_Submission::get_instance();
		if ( ! is_object( $submission ) || ! method_exists( $submission, 'get_contact_form' ) ) {
			return 0;
		}
		$form = $submission->get_contact_form();
		if ( ! is_object( $form ) || ! method_exists( $form, 'id' ) ) {
			return 0;
		}
		return self::int( $form->id() );
	}

	private static function provider_for( int $form_id ): string {
		$config = get_option( 'cf7_mch_' . $form_id, array() );
		if ( ! is_array( $config ) || ! class_exists( 'Cmatic_Lite_Esp_Registry' ) ) {
			return '';
		}
		return (string) Cmatic_Lite_Esp_Registry::get_selected( $config );
	}

	/**
	 * @param mixed $raw
	 * @return array<string,mixed>
	 */
	private static function entry( $raw ): array {
		$raw = is_array( $raw ) ? $raw : array();
		return array(
			'ok'            => max( 0, self::int( $raw['ok'] ?? 0 ) ),
			'failed'        => max( 0, self::int( $raw['failed'] ?? 0 ) ),
			'since'         => max( 0, self::int( $raw['since'] ?? 0 ) ),
			'last_ok_at'    => max( 0, self::int( $raw['last_ok_at'] ?? 0 ) ),
			'last_error_at' => max( 0, self::int( $raw['last_error_at'] ?? 0 ) ),
			'last_error'    => self::str( $raw['last_error'] ?? '' ),
			'last_outcome'  => self::str( $raw['last_outcome'] ?? '' ),
			'provider'      => sanitize_key( self::str( $raw['provider'] ?? '' ) ),
		);
	}

	/**
	 * @param mixed $value
	 */
	public static function int( $value ): int {
		return is_int( $value ) ? $value : ( is_numeric( $value ) ? (int) $value : 0 );
	}

	/**
	 * @param mixed $value
	 */
	public static function str( $value ): string {
		return is_string( $value ) ? $value : ( is_scalar( $value ) ? (string) $value : '' );
	}

	private function __construct() {}
}
