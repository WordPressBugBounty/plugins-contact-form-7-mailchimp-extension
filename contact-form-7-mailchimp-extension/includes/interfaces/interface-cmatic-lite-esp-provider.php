<?php
/**
 * ChimpMatic Lite multi-ESP component.
 *
 * @package contact-form-7-mailchimp-extension
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Typed signatures follow the existing Lite interface convention.
interface Cmatic_Lite_Esp_Provider_Interface {
	public function get_slug(): string;
	public function get_label(): string;
	public function validate_key( string $api_key, bool $log_enabled = false ): array;
	public function get_lists( string $api_key, bool $log_enabled = false ): array;
	public function get_merge_fields( string $api_key, string $list_id, bool $log_enabled = false ): array;
	public function validate_subscription_options( string $api_key, string $list_id, array $options ): array;
	public function subscribe( string $api_key, string $list_id, string $email, string $status, array $merge_vars, int $form_id, array $options, Cmatic_File_Logger $logger ): void;
}
