<?php
/**
 * Form submission handler.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

final class Cmatic_Submission_Handler {

	public static function init(): void {
		add_action( 'plugins_loaded', array( __CLASS__, 'register_submission_router' ), PHP_INT_MAX );
	}

	public static function register_submission_router(): void {
		if ( function_exists( 'cmatic_process_subscription' ) ) {
			remove_action( 'wpcf7_before_send_mail', 'cmatic_process_subscription' );
		}
		add_action( 'wpcf7_before_send_mail', array( __CLASS__, 'process_submission' ) );
	}

	public static function process_submission( $contact_form ): void {
		$form_id  = (int) $contact_form->id();
		$cf7_mch  = get_option( 'cf7_mch_' . $form_id );
		$cf7_mch  = is_array( $cf7_mch ) ? $cf7_mch : array();
		$provider = Cmatic_Lite_Esp_Registry::get_selected( $cf7_mch );
		if ( 'mailchimp' !== $provider ) {
			self::process_provider_submission( $contact_form, $cf7_mch, $provider );
			return;
		}
		if ( function_exists( 'cmatic_process_subscription' ) ) {
			cmatic_process_subscription( $contact_form );
			return;
		}

		$submission = WPCF7_Submission::get_instance();
		if ( ! $submission ) {
			return;
		}

		if ( ! self::is_configured( $cf7_mch, $form_id ) ) {
			return;
		}

		$log_enabled = (bool) Cmatic_Options_Repository::get_option( 'debug', false );
		$logger      = new Cmatic_File_Logger( 'api-events', $log_enabled );
		$posted_data = $submission->get_posted_data();

		$email = Cmatic_Email_Extractor::extract( $cf7_mch, $posted_data );
		if ( ! is_email( $email ) ) {
			$logger->log( 'WARNING', 'Subscription attempt with invalid email address.', $email );
			Cmatic_Submission_Feedback::set_result( Cmatic_Submission_Feedback::failure( 'invalid_email', '', $email ) );
			return;
		}

		$list_value = $cf7_mch['list'] ?? '';
		if ( is_array( $list_value ) ) {
			$list_value = (string) ( reset( $list_value ) ?: '' );
		}
		$list_id = Cmatic_Email_Extractor::replace_tags( $list_value, $posted_data );
		$status  = Cmatic_Status_Resolver::resolve( $cf7_mch, $posted_data, $logger );

		if ( null === $status ) {
			return;
		}

		$merge_vars = Cmatic_Merge_Vars_Builder::build( $cf7_mch, $posted_data );

		$api_key = self::resolve_api_key( $form_id, $cf7_mch );

		Cmatic_Mailchimp_Subscriber::subscribe( $api_key, $list_id, $email, $status, $merge_vars, $form_id, $logger );
	}

	/**
	 * Process a non-Mailchimp provider submission.
	 *
	 * @param WPCF7_ContactForm   $contact_form Contact form being submitted.
	 * @param array<string,mixed> $config       Complete form integration config.
	 * @param string              $slug         Selected provider slug.
	 */
	private static function process_provider_submission( $contact_form, array $config, string $slug ): void {
		$form_id   = (int) $contact_form->id();
		$providers = isset( $config['providers'] ) && is_array( $config['providers'] ) ? $config['providers'] : array();
		$settings  = isset( $providers[ $slug ] ) && is_array( $providers[ $slug ] ) ? $providers[ $slug ] : array();
		if ( 1 !== (int) ( $settings['api-validation'] ?? 0 ) ) {
			return;
		}
		$api_key = Cmatic_Lite_Esp_Credentials::get( $form_id, $slug );
		if ( '' === $api_key ) {
			return;
		}
		$submission = WPCF7_Submission::get_instance();
		if ( ! $submission ) {
			return;
		}
		$posted_data = $submission->get_posted_data();
		$logger      = new Cmatic_File_Logger( 'api-events-' . $slug, (bool) Cmatic_Options_Repository::get_option( 'debug', false ) );
		$advanced    = Cmatic_Lite_Esp_Capabilities::feature_enabled( 'advanced_consent', $slug, $form_id );
		$runtime     = $settings;
		if ( ! $advanced ) {
			foreach ( array( 'consent_gate', 'consent_field', 'subscription_mode', 'doi_template_id', 'doi_redirect_url', 'doi_verified' ) as $premium_key ) {
				unset( $runtime[ $premium_key ] );
			}
		}
		$email = Cmatic_Email_Extractor::extract( $runtime, $posted_data );
		if ( ! is_email( $email ) ) {
			Cmatic_Submission_Feedback::set_result( Cmatic_Submission_Feedback::failure( 'invalid_email', '', $email ) );
			return;
		}
		$list_value = $settings['list'] ?? '';
		if ( is_array( $list_value ) ) {
			$first_list = reset( $list_value );
			$list_value = false === $first_list ? '' : (string) $first_list;
		}
		$list_id = Cmatic_Email_Extractor::replace_tags( (string) $list_value, $posted_data );
		if ( '' === $list_id ) {
			return;
		}
		$status = Cmatic_Status_Resolver::resolve( $runtime, $posted_data, $logger );
		if ( null === $status ) {
			return;
		}
		if (
			$advanced
			&& 'brevo' === $slug
			&& 'double' === ( $runtime['subscription_mode'] ?? '' )
			&& 1 !== (int) ( $runtime['doi_verified'] ?? 0 )
		) {
			return;
		}
		$field_limit = Cmatic_Lite_Esp_Capabilities::field_limit( $slug, $form_id );
		$merge_vars  = Cmatic_Merge_Vars_Builder::build( $runtime, $posted_data, $field_limit );
		$options     = $advanced
			? array(
				'subscription_mode' => sanitize_key( (string) ( $runtime['subscription_mode'] ?? '' ) ),
				'doi_template_id'   => max( 0, (int) ( $runtime['doi_template_id'] ?? 0 ) ),
				'doi_redirect_url'  => esc_url_raw( (string) ( $runtime['doi_redirect_url'] ?? '' ) ),
			)
			: array();
		Cmatic_Lite_Esp_Registry::get( $slug )->subscribe( $api_key, $list_id, $email, $status, $merge_vars, $form_id, $options, $logger );
	}

	private static function is_configured( $cf7_mch, int $form_id = 0 ): bool {
		if ( empty( $cf7_mch ) ) {
			return false;
		}

		if ( ! empty( $cf7_mch['api-validation'] )
			&& 1 === (int) $cf7_mch['api-validation']
			&& ! empty( $cf7_mch['api'] ) ) {
			return true;
		}

		if ( isset( $cf7_mch['auth_type'] ) && 'oauth' === $cf7_mch['auth_type']
			&& ! empty( $cf7_mch['api-validation'] )
			&& 1 === (int) $cf7_mch['api-validation'] ) {
			$auth_manager = Cmatic_Lite_Container::get( 'auth.manager' );
			if ( $auth_manager && $auth_manager->has_oauth( $form_id ) ) {
				return true;
			}
		}

		if ( $form_id && empty( $cf7_mch['api'] ) ) {
			$auth_manager = Cmatic_Lite_Container::get( 'auth.manager' );
			if ( $auth_manager && $auth_manager->has_oauth( $form_id ) ) {
				return true;
			}
		}

		return false;
	}

	private static function resolve_api_key( int $form_id, array $cf7_mch ): string {
		$auth_manager = Cmatic_Lite_Container::get( 'auth.manager' );
		if ( $auth_manager ) {
			return $auth_manager->resolve_api_key( $form_id, '', $cf7_mch );
		}
		return $cf7_mch['api'] ?? '';
	}

	public static function replace_tags( string $subject, array $posted_data ): string {
		return Cmatic_Email_Extractor::replace_tags( $subject, $posted_data );
	}

	private function __construct() {}
}
