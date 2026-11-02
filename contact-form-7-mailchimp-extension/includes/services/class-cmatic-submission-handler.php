<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

use Cmatic\Lite\Settings\FormSettingsRepository;
use Cmatic\Lite\Submission\FieldValues;
use Cmatic\Lite\Submission\Router;

final class Cmatic_Submission_Handler {

	/** @var array<int, object> */
	private static array $pending = array();

	public static function init(): void {
		add_action( 'plugins_loaded', array( __CLASS__, 'register_submission_router' ), PHP_INT_MAX );
	}

	public static function register_submission_router(): void {
		if ( function_exists( 'cmatic_process_subscription' ) ) {
			remove_action( 'wpcf7_before_send_mail', 'cmatic_process_subscription' );
		}
		add_action( 'wpcf7_before_send_mail', array( __CLASS__, 'defer_submission' ) );
	}

	/** @param object $contact_form */
	public static function defer_submission( $contact_form ): void {
		if ( ! is_object( $contact_form ) || ! method_exists( $contact_form, 'id' ) ) {
			return;
		}
		if ( array() === self::$pending ) {
			add_action( 'shutdown', array( __CLASS__, 'send_after_response' ), 100 );
		}
		self::$pending[ (int) $contact_form->id() ] = $contact_form;
	}

	public static function send_after_response(): void {
		$forms         = self::$pending;
		self::$pending = array();
		if ( array() === $forms ) {
			return;
		}
		if ( ! self::hand_off_response() ) {
			$logger = new Cmatic_File_Logger( 'api-events', (bool) Cmatic_Options_Repository::get_option( 'debug', false ) );
			$logger->log( 'INFO', 'No response handoff on this server; the submission was sent before the visitor received the answer.' );
		}
		foreach ( $forms as $contact_form ) {
			self::process_submission( $contact_form );
		}
	}

	private static function hand_off_response(): bool {
		ignore_user_abort( true );
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			return (bool) fastcgi_finish_request();
		}
		if ( function_exists( 'litespeed_finish_request' ) ) {
			return (bool) litespeed_finish_request();
		}
		return false;
	}

	public static function process_submission( $contact_form ): void {
		$form_id  = (int) $contact_form->id();
		$settings = FormSettingsRepository::load( $form_id );
		$cf7_mch  = $settings->record();
		if ( 'mailchimp' === $settings->provider() ) {
			$pro_sender = function_exists( 'cmatic_process_subscription' );
			if ( $pro_sender && 'pro' === Cmatic_Lite_Pro_Entitlement::submission_owner() ) {
				cmatic_process_subscription( $contact_form );
				return;
			}

			$submission = WPCF7_Submission::get_instance();
			if ( ! $submission ) {
				return;
			}

			$email = FieldValues::extract( $cf7_mch, $submission->get_posted_data() );
			if ( $pro_sender
				&& ( ! Cmatic_Lite_Pro_Entitlement::lite_can_send( $cf7_mch )
					|| ! Router::connected( $settings )
					|| ! is_email( $email ) ) ) {
				cmatic_process_subscription( $contact_form );
				return;
			}
		}
		Router::route( $contact_form, $settings );
	}

	public static function replace_tags( string $subject, array $posted_data ): string {
		return FieldValues::replace_tags( $subject, $posted_data );
	}

	private function __construct() {}
}
