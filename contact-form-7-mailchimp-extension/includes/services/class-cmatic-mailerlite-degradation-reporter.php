<?php
/**
 * Records visible, PII-free MailerLite feature degradation.
 *
 * @package contact-form-7-mailchimp-extension
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

final class Cmatic_Mailerlite_Degradation_Reporter {
	public const META_KEY = '_cmatic_mailerlite_degraded_features';

	/**
	 * Records the suppressed features observed by a real submission.
	 *
	 * @param int   $form_id  Contact Form 7 form ID.
	 * @param array $features Suppressed feature identifiers.
	 * @param array $rule_ids Suppressed routing-rule identifiers.
	 */
	public static function record_submission( int $form_id, array $features, array $rule_ids ): void {
		$features = self::canonical( $features );
		$rule_ids = self::canonical( $rule_ids );
		if ( empty( $features ) ) {
			return;
		}

		$current = get_post_meta( $form_id, self::META_KEY, true );
		if ( $features !== $current ) {
			update_post_meta( $form_id, self::META_KEY, $features );
		}

		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Required PII-free operational degradation diagnostic.
			error_log(
				sprintf(
					'[ChimpMatic] MailerLite license degradation: form_id=%d; features=%s; rule_ids=%s',
					$form_id,
					implode( ',', $features ),
					implode( ',', $rule_ids )
				)
			);
		}
	}

	public static function clear( int $form_id ): void {
		delete_post_meta( $form_id, self::META_KEY );
	}

	private static function canonical( array $values ): array {
		$values = array_values(
			array_unique(
				array_filter(
					array_map( 'sanitize_key', $values ),
					static function ( string $value ): bool {
						return '' !== $value;
					}
				)
			)
		);
		sort( $values, SORT_STRING );
		return $values;
	}

	private function __construct() {}
}
