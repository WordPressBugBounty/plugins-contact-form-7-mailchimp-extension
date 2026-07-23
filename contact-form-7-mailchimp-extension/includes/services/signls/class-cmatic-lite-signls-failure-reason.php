<?php
/**
 * Deterministic Signls failure classification and scrubbing.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

final class Cmatic_Lite_Signls_Failure_Reason {

	private const CODES = array(
		'revoked_credential',
		'deleted_destination',
		'merge_mismatch',
		'required_field',
		'invalid_value',
		'rate_limit',
		'plan_limit',
		'permission',
		'duplicate',
		'timeout',
		'dns',
		'tls',
		'http_4xx',
		'http_5xx',
		'configuration',
		'validation',
		'remote_rejected',
		'unknown',
	);

	/**
	 * Convert any provider failure shape into a closed code and safe sample.
	 *
	 * @param mixed  $value    Provider value, result, error or exception.
	 * @param string $fallback Closed fallback code or safe fallback message.
	 * @return array{code:string,sample:string}
	 */
	public static function from_value( $value, string $fallback ): array {
		$text   = self::extract_text( $value );
		$status = self::extract_status( $value );
		$code   = self::classify( $text, $status );

		if ( 'unknown' === $code && in_array( $fallback, self::CODES, true ) ) {
			$code = $fallback;
		}
		if ( '' === $text && ! in_array( $fallback, self::CODES, true ) ) {
			$text = $fallback;
		}

		return array(
			'code'   => $code,
			'sample' => self::scrub( $text ),
		);
	}

	/**
	 * Read only conventional bounded error fields; never serialize a whole payload.
	 *
	 * @param mixed $value Candidate failure value.
	 */
	private static function extract_text( $value ): string {
		if ( is_wp_error( $value ) ) {
			return (string) $value->get_error_message();
		}
		if ( $value instanceof Throwable ) {
			return (string) $value->getMessage();
		}
		if ( is_scalar( $value ) ) {
			return (string) $value;
		}
		if ( ! is_array( $value ) ) {
			return '';
		}

		$parts = array();
		foreach ( array( 'reason', 'code', 'title', 'type', 'error', 'message', 'detail' ) as $key ) {
			if ( isset( $value[ $key ] ) && is_scalar( $value[ $key ] ) ) {
				$parts[] = (string) $value[ $key ];
			}
		}
		if ( isset( $value['errors'] ) && is_array( $value['errors'] ) ) {
			foreach ( array_slice( $value['errors'], 0, 3 ) as $error ) {
				if ( is_scalar( $error ) ) {
					$parts[] = (string) $error;
				} elseif ( is_array( $error ) ) {
					foreach ( array( 'code', 'title', 'message', 'detail' ) as $key ) {
						if ( isset( $error[ $key ] ) && is_scalar( $error[ $key ] ) ) {
							$parts[] = (string) $error[ $key ];
						}
					}
				}
			}
		}
		return implode( ' ', array_slice( $parts, 0, 12 ) );
	}

	/**
	 * Extract a conventional HTTP status without inspecting arbitrary members.
	 *
	 * @param mixed $value Candidate failure value.
	 */
	private static function extract_status( $value ): int {
		if ( ! is_array( $value ) ) {
			return 0;
		}
		foreach ( array( 'status', 'status_code', 'http_code' ) as $key ) {
			if ( isset( $value[ $key ] ) && is_numeric( $value[ $key ] ) ) {
				return (int) $value[ $key ];
			}
		}
		return 0;
	}

	private static function classify( string $text, int $status ): string {
		$value = strtolower( $text );
		$rules = array(
			'revoked_credential'  => '/\b(revoked|invalid api key|invalid access token|expired (api )?(key|token|credential)|unauthori[sz]ed|authentication failed)\b/u',
			'deleted_destination' => '/\b(list|audience|group|destination)\b.{0,32}\b(deleted|does not exist|not found|missing|unknown)\b|\b(deleted|missing|unknown)\b.{0,32}\b(list|audience|group|destination)\b/u',
			'merge_mismatch'      => '/\b(merge[ _-]?(field|tag)|field mapping)\b.{0,32}\b(mismatch|invalid|unknown|missing|not found)\b|\b(mismatch)\b.{0,32}\b(merge|field)\b/u',
			'required_field'      => '/\b(required (field|value)|field .{0,24} is required|missing required)\b/u',
			'invalid_value'       => '/\b(invalid value|value .{0,24} invalid|bad value|malformed value)\b/u',
			'rate_limit'          => '/\b(rate.?limit|too many requests|backoff)\b/u',
			'plan_limit'          => '/\b(plan|quota|account)\b.{0,32}\b(limit|upgrade|exceeded|maximum)\b|\b(limit|quota)\b.{0,32}\b(plan|account)\b/u',
			'permission'          => '/\b(forbidden|permission denied|insufficient permission|access denied|not permitted)\b/u',
			'duplicate'           => '/\b(duplicate|already exists|member exists|already subscribed)\b/u',
			'timeout'             => '/\b(timed? out|timeout|curl error 28)\b/u',
			'dns'                 => '/\b(dns|name resolution|could not resolve|unknown host|curl error 6)\b/u',
			'tls'                 => '/\b(tls|ssl|certificate|handshake|curl error (35|51|58|60|64|66|77|80|82|83|90|91))\b/u',
			'configuration'       => '/\b(configuration|config error|not configured|setup incomplete)\b/u',
			'validation'          => '/\b(validation|failed to validate|schema invalid)\b/u',
			'remote_rejected'     => '/\b(rejected|declined|provider api error|api error)\b/u',
		);
		foreach ( $rules as $code => $pattern ) {
			if ( 1 === preg_match( $pattern, $value ) ) {
				return $code;
			}
		}
		if ( 429 === $status ) {
			return 'rate_limit';
		}
		if ( 401 === $status ) {
			return 'revoked_credential';
		}
		if ( 403 === $status ) {
			return 'permission';
		}
		if ( $status >= 500 && $status <= 599 ) {
			return 'http_5xx';
		}
		if ( $status >= 400 && $status <= 499 ) {
			return 'http_4xx';
		}
		return 'unknown';
	}

	private static function scrub( string $text ): string {
		$text = wp_check_invalid_utf8( $text, true );
		$text = wp_strip_all_tags( $text, true );
		$text = self::replace( '/[\p{Cc}\p{Cf}]+/u', ' ', $text );
		$text = self::replace( '/\b[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}\b/iu', '[redacted]', $text );
		$text = self::replace( '~\b(?:https?://|www\.)[^\s<>]+~iu', '[redacted]', $text );
		$text = self::replace( '/\?[^\s<>]+/u', '[redacted]', $text );
		$text = self::replace( '/\b(?:25[0-5]|2[0-4][0-9]|[01]?[0-9]?[0-9])(?:\.(?:25[0-5]|2[0-4][0-9]|[01]?[0-9]?[0-9])){3}\b/u', '[redacted]', $text );
		$text = self::replace( '/(?<![[:alnum:]])(?:[A-F0-9]{0,4}:){2,7}[A-F0-9]{0,4}(?![[:alnum:]])/iu', '[redacted]', $text );
		$text = self::replace( '/\b[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\b/iu', '[redacted]', $text );
		$text = self::replace( '/\b(?:bearer|basic|token|api[_ -]?key)\s*[:=]?\s*[^\s,;]+/iu', '[redacted]', $text );
		$text = self::replace( '/\b[0-9a-f]{12,}\b/iu', '[redacted]', $text );
		$text = self::replace( '/\b(?=[A-Z0-9_+\/=\-]{12,}\b)(?=[A-Z0-9_+\/=\-]*[0-9_+\/=\-])[A-Z0-9_+\/=\-]+\b/iu', '[redacted]', $text );
		$text = trim( self::replace( '/\s+/u', ' ', $text ) );
		$text = self::utf8_prefix( $text, 50 );

		if ( self::contains_forbidden_pattern( $text ) ) {
			return '';
		}
		return $text;
	}

	private static function replace( string $pattern, string $replacement, string $text ): string {
		$result = preg_replace( $pattern, $replacement, $text );
		return is_string( $result ) ? $result : '';
	}

	private static function contains_forbidden_pattern( string $text ): bool {
		$patterns = array(
			'/\b[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}\b/iu',
			'~\b(?:https?://|www\.)~iu',
			'/\b(?:25[0-5]|2[0-4][0-9]|[01]?[0-9]?[0-9])(?:\.(?:25[0-5]|2[0-4][0-9]|[01]?[0-9]?[0-9])){3}\b/u',
			'/\b[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\b/iu',
			'/\b(?:bearer|basic|token|api[_ -]?key)\s*[:=]/iu',
		);
		foreach ( $patterns as $pattern ) {
			if ( 1 === preg_match( $pattern, $text ) ) {
				return true;
			}
		}
		return false;
	}

	private static function utf8_prefix( string $value, int $maximum ): string {
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $maximum, 'UTF-8' );
		}
		if ( 1 === preg_match( '/^.{0,' . $maximum . '}/us', $value, $match ) ) {
			return $match[0];
		}
		return substr( $value, 0, $maximum );
	}
}
