<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    hello@chimpmatic.com
 * @copyright 2014-2026 ChimpMatic
 * @license   GPL-3.0+
 */

declare(strict_types=1);

namespace Cmatic\Lite\Submission;

use Cmatic\Lite\Settings\MappingSchema;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing -- Typed signatures follow the existing Lite class convention.
final class ConsentGate {
	/**
	 * @param array<string,mixed> $settings        Effective provider settings.
	 * @param array<string,mixed> $posted_data     Submitted Contact Form 7 data.
	 * @param array<int,mixed>    $form_tags       Current normalized form tags.
	 * @param bool                $submitted_value True for the MailerLite policy (the submitted value of the named field decides, and only a required gate blocks); false for the resolver policy (any configured field decides through tag replacement).
	 * @return array<string,mixed>
	 */
	public static function decide( array $settings, array $posted_data, array $form_tags, bool $submitted_value ): array {
		$required = 'required' === MappingSchema::str( $settings['consent_gate'] ?? '' );
		$raw      = MappingSchema::str( $required ? ( $settings['consent_field'] ?? '' ) : ( $settings['accept'] ?? '' ) );
		if ( $submitted_value ) {
			$field    = trim( $raw, '[]' );
			$tag      = self::find_tag( $field, $form_tags );
			$key      = FieldValues::posted_key( $field, $posted_data );
			$accepted = null !== $key && ! empty( $posted_data[ $key ] );
			$eligible = $required && null !== $tag && 'acceptance' === MappingSchema::str( $tag['basetype'] ?? '' ) && ! empty( $tag['required'] ) && empty( $tag['inverted'] );
			$allowed  = ! $required || $accepted;
			return self::answer( '' !== $field, $required, $accepted, $eligible, $allowed, $field );
		}
		$configured = '' !== trim( $raw );
		$field      = trim( trim( $raw ), '[]' );
		$tag        = self::find_tag( $field, $form_tags );
		$accepted   = $configured && ! empty( FieldValues::replace_tags( $raw, $posted_data ) );
		$eligible   = $required && null !== $tag && 'acceptance' === MappingSchema::str( $tag['basetype'] ?? '' ) && ! empty( $tag['required'] ) && empty( $tag['inverted'] );
		$allowed    = ! $configured || $accepted;
		return self::answer( $configured, $required, $accepted, $eligible, $allowed, $field );
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function answer( bool $configured, bool $required, bool $accepted, bool $eligible, bool $allowed, string $field ): array {
		return array(
			'configured' => $configured,
			'required'   => $required,
			'accepted'   => $accepted,
			'eligible'   => $eligible,
			'allowed'    => $allowed,
			'field'      => $field,
			'reason'     => $allowed ? '' : 'acceptance_not_checked',
		);
	}

	/**
	 * @param array<int,mixed> $form_tags
	 * @return array<mixed>|null
	 */
	private static function find_tag( string $field, array $form_tags ): ?array {
		$folded = array();
		foreach ( $form_tags as $tag ) {
			if ( ! is_array( $tag ) || ! isset( $tag['name'] ) || ! is_scalar( $tag['name'] ) ) {
				continue;
			}
			if ( (string) $tag['name'] === $field ) {
				return $tag;
			}
			if ( '' !== $field && 0 === strcasecmp( (string) $tag['name'], $field ) ) {
				$folded[] = $tag;
			}
		}
		return 1 === count( $folded ) ? $folded[0] : null;
	}

	private function __construct() {}
}
