<?php
/**
 * MailerLite consent decision value object factory.
 *
 * @package contact-form-7-mailchimp-extension
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

final class Cmatic_Consent_Decision {
	/**
	 * Produces the consent decision consumed by the pipeline.
	 *
	 * @param array $settings    Saved provider settings.
	 * @param array $posted_data Submitted Contact Form 7 data.
	 * @param array $form_tags   Current normalized form tags.
	 */
	public static function resolve( array $settings, array $posted_data, array $form_tags ): array {
		$required = 'required' === (string) ( $settings['consent_gate'] ?? '' );
		$field    = $required ? (string) ( $settings['consent_field'] ?? '' ) : (string) ( $settings['accept'] ?? '' );
		$field    = trim( $field, '[]' );
		$tag      = self::find_tag( $field, $form_tags );
		$accepted = '' !== $field && ! empty( $posted_data[ $field ] );
		$eligible = $required && null !== $tag && 'acceptance' === (string) ( $tag['basetype'] ?? '' ) && ! empty( $tag['required'] ) && empty( $tag['inverted'] );
		$allowed  = ! $required || $accepted;

		return array(
			'configured' => '' !== $field,
			'required'   => $required,
			'accepted'   => $accepted,
			'eligible'   => $eligible,
			'allowed'    => $allowed,
			'field'      => $field,
			'reason'     => $allowed ? '' : 'acceptance_not_checked',
		);
	}

	private static function find_tag( string $field, array $form_tags ): ?array {
		foreach ( $form_tags as $tag ) {
			if ( is_array( $tag ) && isset( $tag['name'] ) && is_scalar( $tag['name'] ) && (string) $tag['name'] === $field ) {
				return $tag;
			}
		}
		return null;
	}

	private function __construct() {}
}
