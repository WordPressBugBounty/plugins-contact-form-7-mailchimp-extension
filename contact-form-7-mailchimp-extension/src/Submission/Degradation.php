<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    hello@chimpmatic.com
 * @copyright 2014-2026 ChimpMatic
 * @license   GPL-3.0+
 */

declare(strict_types=1);

namespace Cmatic\Lite\Submission;

use Cmatic\Lite\Settings\Entitlements;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing -- Typed signatures follow the existing Lite class convention.
final class Degradation {
	public const META_KEY = '_cmatic_mailerlite_degraded_features';

	/**
	 * @param int          $form_id  Contact Form 7 form ID.
	 * @param string       $slug     Provider slug.
	 * @param array<mixed> $features Suppressed feature identifiers.
	 * @param array<mixed> $rule_ids Suppressed routing-rule identifiers.
	 */
	public static function record( int $form_id, string $slug, array $features, array $rule_ids ): void {
		if ( 'mailerlite' !== $slug ) {
			return;
		}
		$features = Entitlements::canonical( $features );
		$rule_ids = Entitlements::canonical( $rule_ids );
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

	private function __construct() {}
}
