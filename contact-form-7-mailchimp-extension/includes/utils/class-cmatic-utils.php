<?php
/**
 * Utility functions for ChimpMatic.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

final class Cmatic_Utils {

	const CMATIC_FB_A = 'chimpmatic';

	public static function validate_bool( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_int( $value ) || is_float( $value ) ) {
			return (bool) $value;
		}

		if ( is_string( $value ) ) {
			$v = strtolower( trim( $value ) );

			if ( '' === $v ) {
				return false;
			}

			if ( is_numeric( $v ) ) {
				return 0.0 !== (float) $v;
			}

			static $true  = array( 'true', 'on', 'yes', 'y', '1' );
			static $false = array( 'false', 'off', 'no', 'n', '0' );

			if ( in_array( $v, $true, true ) ) {
				return true;
			}
			if ( in_array( $v, $false, true ) ) {
				return false;
			}
		}

		return false;
	}

	public static function get_first( $array, $default = '' ) {
		if ( is_array( $array ) && ! empty( $array ) ) {
			return reset( $array );
		}
		return $default;
	}

	public static function get_newest_form_id(): ?int {
		$forms = get_posts(
			array(
				'post_type'      => 'wpcf7_contact_form',
				'posts_per_page' => 1,
				'orderby'        => 'ID',
				'order'          => 'DESC',
				'post_status'    => 'publish',
				'fields'         => 'ids',
			)
		);

		return ! empty( $forms ) ? (int) $forms[0] : null;
	}

	public static function get_days_since( int $timestamp ): int {
		$datetime_now  = new \DateTime( 'now' );
		$datetime_from = new \DateTime( '@' . $timestamp );

		$diff = date_diff( $datetime_now, $datetime_from );

		return (int) $diff->format( '%a' );
	}

	private function __construct() {}

	private function __clone() {}

	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton' );
	}
}
