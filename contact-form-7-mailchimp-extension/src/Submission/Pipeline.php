<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    hello@chimpmatic.com
 * @copyright 2014-2026 ChimpMatic
 * @license   GPL-3.0+
 */

declare(strict_types=1);

namespace Cmatic\Lite\Submission;

use Cmatic\Lite\Provider\Provider;
use Cmatic\Lite\Settings\FormSettings;
use Cmatic\Lite\Settings\MappingSchema;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing -- Typed signatures follow the existing Lite class convention.
final class Pipeline {
	const STATUS_MODES = array( 'legacy_provider_managed', 'account', 'active', 'unconfirmed' );

	/** @var Provider */
	private $provider;

	public function __construct( Provider $provider ) {
		$this->provider = $provider;
	}

	/**
	 * @param array<string,mixed> $section     The stored provider section.
	 * @param array<string,mixed> $policy      The Entitlements::apply() answer for that section.
	 * @param array<string,mixed> $posted_data Submitted Contact Form 7 data.
	 * @param array<int,mixed>    $form_tags   Current normalized form tags.
	 */
	public function run( int $form_id, array $section, array $policy, array $posted_data, array $form_tags, string $api_key, \Cmatic_File_Logger $logger ): void {
		$features = $this->provider->descriptor()->features();
		$modes    = is_array( $features['status_modes'] ) ? $features['status_modes'] : array();
		if ( array() !== $modes ) {
			$this->run_status_mode( $form_id, $policy, $posted_data, $form_tags, $api_key, $logger );
			return;
		}
		$this->run_legacy_status( $form_id, $section, $policy, $posted_data, $form_tags, $api_key, $logger );
	}

	/**
	 * @param array<string,mixed> $section
	 * @param array<string,mixed> $policy
	 * @param array<string,mixed> $posted_data
	 * @param array<int,mixed>    $form_tags
	 */
	private function run_legacy_status( int $form_id, array $section, array $policy, array $posted_data, array $form_tags, string $api_key, \Cmatic_File_Logger $logger ): void {
		$descriptor = $this->provider->descriptor();
		$effective  = self::effective( $policy );
		$entitled   = self::entitled( $policy );
		$email      = FieldValues::email( $effective, $posted_data );
		if ( ! is_email( $email ) ) {
			if ( $descriptor->submission_flag( 'warn_invalid_email' ) ) {
				$logger->log( 'WARNING', 'Subscription attempt with invalid email address.', $email );
			}
			Feedback::set_result( Feedback::failure( 'invalid_email', '', $email ) );
			return;
		}
		$list_id = FieldValues::replace_tags( FormSettings::raw_list( $section ), $posted_data );
		if ( $descriptor->submission_flag( 'list_required' ) && '' === $list_id ) {
			return;
		}
		$status = StatusPolicy::resolve( $effective, $posted_data, $logger );
		if ( null === $status ) {
			return;
		}
		if ( $descriptor->submission_flag( 'doi_verification' ) && 'double' === MappingSchema::str( $effective['subscription_mode'] ?? '' ) && 1 !== MappingSchema::int( $effective['doi_verified'] ?? 0 ) ) {
			return;
		}
		$merge_vars = FieldValues::build( $effective, $posted_data, self::limit( $policy ), $descriptor->submission_flag( 'zero_values' ) );
		if ( $descriptor->submission_flag( 'integer_numbers' ) ) {
			$definitions = isset( $effective['merge_fields'] ) && is_array( $effective['merge_fields'] ) ? $effective['merge_fields'] : array();
			$integers    = FieldValues::integer_fields( $merge_vars, $definitions );
			if ( null === $integers ) {
				Feedback::set_result( Feedback::failure( 'provider_field_type_error', '', $email ) );
				return;
			}
			$merge_vars = $integers;
		}
		$options = array();
		if ( ! empty( $entitled['double_optin'] ) ) {
			$options = array(
				'subscription_mode' => sanitize_key( MappingSchema::str( $effective['subscription_mode'] ?? '' ) ),
				'doi_template_id'   => max( 0, MappingSchema::int( $effective['doi_template_id'] ?? 0 ) ),
				'doi_redirect_url'  => esc_url_raw( MappingSchema::str( $effective['doi_redirect_url'] ?? '' ) ),
			);
		}
		$groups = array( $list_id );
		if ( ! empty( $entitled['routing'] ) && DestinationResolver::is_premium_configured( $section ) ) {
			$routing = DestinationResolver::resolve( $section, $posted_data, $form_tags );
			if ( ! $routing['success'] ) {
				Feedback::set_result( Feedback::failure( (string) $routing['reason'], '', $email ) );
				return;
			}
			$groups = $routing['groups'];
			$logger->log(
				'INFO',
				'Destinations resolved.',
				array(
					'count'         => count( $groups ),
					'matched_rules' => count( $routing['matched_rules'] ),
				)
			);
		}
		$options['groups'] = $groups;
		$this->provider->subscribe( $api_key, $list_id, $email, $status, $merge_vars, $form_id, $options, $logger );
	}

	/**
	 * @param array<string,mixed> $policy
	 * @param array<string,mixed> $posted_data
	 * @param array<int,mixed>    $form_tags
	 */
	private function run_status_mode( int $form_id, array $policy, array $posted_data, array $form_tags, string $api_key, \Cmatic_File_Logger $logger ): void {
		$descriptor = $this->provider->descriptor();
		$features   = $descriptor->features();
		$effective  = self::effective( $policy );
		$limit      = self::limit( $policy );
		$email      = FieldValues::email( $effective, $posted_data );
		if ( ! is_email( $email ) ) {
			Feedback::set_result( Feedback::failure( 'invalid_email', '', $email ) );
			return;
		}

		$decision = ConsentGate::decide( $effective, $posted_data, $form_tags, true );
		if ( ! $decision['allowed'] ) {
			$logger->log( 'INFO', 'Subscription skipped: required acceptance was not checked.' );
			Feedback::set_result( Feedback::skipped( 'acceptance_not_checked' ) );
			return;
		}

		$routing = DestinationResolver::resolve( $effective, $posted_data, $form_tags );
		if ( ! $routing['success'] ) {
			Feedback::set_result( Feedback::failure( (string) $routing['reason'], '', $email ) );
			return;
		}

		$status_mode = self::status_mode( $effective );
		if ( ! empty( $effective['resubscribe_force'] ) && ( 'active' !== $status_mode || ! $decision['eligible'] || ! $decision['accepted'] ) ) {
			Feedback::set_result( Feedback::failure( 'resubscribe_requires_consent', '', $email ) );
			return;
		}

		$merge_vars = FieldValues::build( $effective, $posted_data, $limit, $descriptor->submission_flag( 'zero_values' ) );
		if ( $descriptor->submission_flag( 'typed_fields' ) ) {
			$merge_vars  = FieldValues::apply_boolean_mappings( $merge_vars, $effective, $posted_data, $limit );
			$definitions = isset( $effective['merge_fields'] ) && is_array( $effective['merge_fields'] ) ? $effective['merge_fields'] : array();
			$definitions = isset( $definitions['merge_fields'] ) && is_array( $definitions['merge_fields'] ) ? $definitions['merge_fields'] : $definitions;
			$normalized  = FieldValues::normalize( $merge_vars, $definitions );
			if ( ! $normalized['success'] ) {
				Feedback::set_result( Feedback::failure( 'provider_field_type_error', '', $email ) );
				return;
			}
			$merge_vars = $normalized['fields'];
		}

		$options = array(
			'groups'      => $routing['groups'],
			'status_mode' => $status_mode,
		);
		if ( ! empty( $effective['resubscribe_force'] ) ) {
			$options['resubscribe'] = true;
		}
		if ( ! empty( $features['consent_metadata'] ) && ! empty( $effective['consent_metadata_enabled'] ) ) {
			if ( ! $decision['eligible'] || ! $decision['accepted'] ) {
				Feedback::set_result( Feedback::failure( 'consent_required', '', $email ) );
				return;
			}
			$ip = RequestIp::get();
			if ( '' === $ip ) {
				Feedback::set_result( Feedback::failure( 'consent_ip_unavailable', '', $email ) );
				return;
			}
			$options['consent_metadata'] = array(
				'ip_address'  => $ip,
				'optin_ip'    => $ip,
				'opted_in_at' => gmdate( 'Y-m-d H:i:s' ),
			);
		}

		if ( ! empty( $policy['degraded'] ) ) {
			Degradation::record( $form_id, $descriptor->slug(), self::strings( $policy['suppressed_features'] ?? array() ), self::strings( $policy['suppressed_rule_ids'] ?? array() ) );
		}

		$groups  = $routing['groups'];
		$list_id = (string) reset( $groups );
		$status  = 'unconfirmed' === $status_mode ? 'pending' : 'subscribed';
		$this->provider->subscribe( $api_key, $list_id, $email, $status, $merge_vars, $form_id, $options, $logger );
	}

	/**
	 * @param array<string,mixed> $policy
	 * @return array<string,mixed>
	 */
	private static function effective( array $policy ): array {
		return isset( $policy['effective_settings'] ) && is_array( $policy['effective_settings'] ) ? $policy['effective_settings'] : array();
	}

	/**
	 * @param array<string,mixed> $policy
	 * @return array<string,mixed>
	 */
	private static function entitled( array $policy ): array {
		return isset( $policy['entitled'] ) && is_array( $policy['entitled'] ) ? $policy['entitled'] : array();
	}

	/**
	 * @param array<string,mixed> $policy
	 */
	private static function limit( array $policy ): int {
		return max( 1, MappingSchema::int( $policy['field_limit'] ?? CMATIC_LITE_FIELDS ) );
	}

	/**
	 * @param mixed $values
	 * @return array<int,string>
	 */
	private static function strings( $values ): array {
		$result = array();
		foreach ( is_array( $values ) ? $values : array() as $value ) {
			if ( is_scalar( $value ) ) {
				$result[] = (string) $value;
			}
		}
		return $result;
	}

	/**
	 * @param array<string,mixed> $settings
	 */
	private static function status_mode( array $settings ): string {
		$mode = MappingSchema::str( $settings['status_mode'] ?? 'legacy_provider_managed' );
		return in_array( $mode, self::STATUS_MODES, true ) ? $mode : 'legacy_provider_managed';
	}
}
