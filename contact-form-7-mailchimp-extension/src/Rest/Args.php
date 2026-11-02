<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    hello@chimpmatic.com
 * @copyright 2014-2026 ChimpMatic
 * @license   GPL-3.0+
 */

namespace Cmatic\Lite\Rest;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing -- Typed signatures follow the existing Lite class convention.
final class Args {

	/**
	 * @return array<string,mixed>
	 */
	public static function of( string $type, string $sanitize, bool $required = true ): array {
		return array(
			'required'          => $required,
			'type'              => $type,
			'sanitize_callback' => $sanitize,
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function form_id( bool $required = true ): array {
		return self::of( 'integer', 'absint', $required );
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function positive_form_id(): array {
		return self::form_id() + array(
			'validate_callback' => function ( $param ) {
				return is_numeric( $param ) && $param > 0;
			},
		);
	}

	/**
	 * @param array<int,string> $allowed
	 */
	public static function one_of( array $allowed ): callable {
		return function ( $param ) use ( $allowed ) {
			return in_array( $param, $allowed, true );
		};
	}

	private function __construct() {}
}
