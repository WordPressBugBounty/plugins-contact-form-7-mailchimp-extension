<?php
/**
 * MailerLite submission application service.
 *
 * @package contact-form-7-mailchimp-extension
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

final class Cmatic_Mailerlite_Submission_Pipeline {
	/**
	 * MailerLite provider adapter.
	 *
	 * @var Cmatic_Lite_Esp_Provider_Interface
	 */
	private Cmatic_Lite_Esp_Provider_Interface $provider;

	public function __construct( Cmatic_Lite_Esp_Provider_Interface $provider ) {
		$this->provider = $provider;
	}

	/**
	 * Validates, routes, normalizes, and submits one CF7 payload.
	 *
	 * @param int                $form_id     Contact Form 7 form ID.
	 * @param array              $settings    Saved provider settings.
	 * @param array              $posted_data Submitted Contact Form 7 data.
	 * @param array              $form_tags   Current normalized form tags.
	 * @param string             $api_key     Provider credential.
	 * @param int                $field_limit Effective mapping limit.
	 * @param Cmatic_File_Logger $logger      Operational logger.
	 */
	public function process( int $form_id, array $settings, array $posted_data, array $form_tags, string $api_key, int $field_limit, Cmatic_File_Logger $logger ): void {
		$entitlements = array(
			'mailerlite_routing'          => Cmatic_Lite_Esp_Capabilities::feature_enabled( 'mailerlite_routing', 'mailerlite', $form_id ),
			'mailerlite_status'           => Cmatic_Lite_Esp_Capabilities::feature_enabled( 'mailerlite_status', 'mailerlite', $form_id ),
			'mailerlite_resubscribe'      => Cmatic_Lite_Esp_Capabilities::feature_enabled( 'mailerlite_resubscribe', 'mailerlite', $form_id ),
			'mailerlite_consent_metadata' => Cmatic_Lite_Esp_Capabilities::feature_enabled( 'mailerlite_consent_metadata', 'mailerlite', $form_id ),
		);
		$policy       = Cmatic_Mailerlite_Runtime_Policy::apply( $settings, $entitlements );
		$effective    = $policy['effective_settings'];
		$email        = Cmatic_Email_Extractor::extract( $effective, $posted_data );
		if ( ! is_email( $email ) ) {
			Cmatic_Submission_Feedback::set_result( Cmatic_Submission_Feedback::failure( 'invalid_email', '', $email ) );
			return;
		}

		$decision = Cmatic_Consent_Decision::resolve( $effective, $posted_data, $form_tags );
		if ( ! $decision['allowed'] ) {
			$logger->log( 'INFO', 'Subscription skipped: required acceptance was not checked.' );
			Cmatic_Submission_Feedback::set_result( Cmatic_Submission_Feedback::skipped( 'acceptance_not_checked' ) );
			return;
		}

		$routing = Cmatic_Mailerlite_Routing_Resolver::resolve( $effective, $posted_data, $form_tags );
		if ( ! $routing['success'] ) {
			Cmatic_Submission_Feedback::set_result( Cmatic_Submission_Feedback::failure( (string) $routing['reason'], '', $email ) );
			return;
		}

		$status_mode = self::status_mode( $effective );
		if ( ! empty( $effective['resubscribe_force'] ) && ( 'active' !== $status_mode || ! $decision['eligible'] || ! $decision['accepted'] ) ) {
			Cmatic_Submission_Feedback::set_result( Cmatic_Submission_Feedback::failure( 'resubscribe_requires_consent', '', $email ) );
			return;
		}

		$merge_vars = Cmatic_Merge_Vars_Builder::build( $effective, $posted_data, $field_limit );
		$merge_vars = Cmatic_Mailerlite_Field_Normalizer::apply_boolean_mappings( $merge_vars, $effective, $posted_data, $field_limit );
		$normalized = Cmatic_Mailerlite_Field_Normalizer::normalize( $merge_vars, (array) ( $effective['merge_fields']['merge_fields'] ?? $effective['merge_fields'] ?? array() ) );
		if ( ! $normalized['success'] ) {
			Cmatic_Submission_Feedback::set_result( Cmatic_Submission_Feedback::failure( 'provider_field_type_error', '', $email ) );
			return;
		}

		$options = array(
			'groups'      => $routing['groups'],
			'status_mode' => $status_mode,
		);
		if ( ! empty( $effective['resubscribe_force'] ) ) {
			$options['resubscribe'] = true;
		}
		if ( ! empty( $effective['consent_metadata_enabled'] ) ) {
			if ( ! $decision['eligible'] || ! $decision['accepted'] ) {
				Cmatic_Submission_Feedback::set_result( Cmatic_Submission_Feedback::failure( 'consent_required', '', $email ) );
				return;
			}
			$ip = Cmatic_Request_Ip::get();
			if ( '' === $ip ) {
				Cmatic_Submission_Feedback::set_result( Cmatic_Submission_Feedback::failure( 'consent_ip_unavailable', '', $email ) );
				return;
			}
			$options['consent_metadata'] = array(
				'ip_address'  => $ip,
				'optin_ip'    => $ip,
				'opted_in_at' => gmdate( 'Y-m-d H:i:s' ),
			);
		}

		if ( $policy['degraded'] ) {
			Cmatic_Mailerlite_Degradation_Reporter::record_submission( $form_id, $policy['suppressed_features'], $policy['suppressed_rule_ids'] );
		}

		$groups  = $routing['groups'];
		$list_id = (string) reset( $groups );
		$status  = 'unconfirmed' === $status_mode ? 'pending' : 'subscribed';
		$this->provider->subscribe( $api_key, $list_id, $email, $status, $normalized['fields'], $form_id, $options, $logger );
	}

	private static function status_mode( array $settings ): string {
		$mode = (string) ( $settings['status_mode'] ?? 'legacy_provider_managed' );
		return in_array( $mode, array( 'legacy_provider_managed', 'account', 'active', 'unconfirmed' ), true ) ? $mode : 'legacy_provider_managed';
	}
}
