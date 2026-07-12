<?php
/**
 * ChimpMatic Lite multi-ESP component.
 *
 * @package contact-form-7-mailchimp-extension
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing -- Typed signatures follow the existing Lite class convention.
final class Cmatic_Lite_Esp_Mailchimp implements Cmatic_Lite_Esp_Provider_Interface {
	public function get_slug(): string {
		return 'mailchimp';
	}
	public function get_label(): string {
		return 'Mailchimp';
	}
	public function validate_key( string $api_key, bool $log_enabled = false ): array {
		return Cmatic_Lite_Api_Service::validate_key( $api_key, $log_enabled );
	}
	public function get_lists( string $api_key, bool $log_enabled = false ): array {
		return Cmatic_Lite_Api_Service::get_lists( $api_key, $log_enabled );
	}
	public function get_merge_fields( string $api_key, string $list_id, bool $log_enabled = false ): array {
		return Cmatic_Lite_Api_Service::get_merge_fields( $api_key, $list_id, $log_enabled );
	}
	public function validate_subscription_options( string $api_key, string $list_id, array $options ): array {
		unset( $api_key, $list_id, $options );
		return array(
			'success' => true,
			'error'   => '',
			'data'    => array(),
		);
	}
	public function subscribe( string $api_key, string $list_id, string $email, string $status, array $merge_vars, int $form_id, array $options, Cmatic_File_Logger $logger ): void {
		unset( $options );
		Cmatic_Mailchimp_Subscriber::subscribe( $api_key, $list_id, $email, $status, $merge_vars, $form_id, $logger );
	}
}
