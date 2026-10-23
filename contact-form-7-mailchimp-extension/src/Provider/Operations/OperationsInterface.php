<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    hello@chimpmatic.com
 * @copyright 2014-2026 ChimpMatic
 * @license   GPL-3.0+
 */

declare(strict_types=1);

namespace Cmatic\Lite\Provider\Operations;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.FunctionComment.Missing -- Typed signatures follow the existing Lite interface convention.
interface OperationsInterface {
	public function validate_key( string $api_key, bool $log_enabled = false ): array;
	public function get_lists( string $api_key, bool $log_enabled = false ): array;
	public function get_merge_fields( string $api_key, string $list_id, bool $log_enabled = false ): array;
	public function validate_subscription_options( string $api_key, string $list_id, array $options ): array;
	public function perform_subscription( string $api_key, string $list_id, string $email, string $status, array $merge_vars, array $options ): array;
	public function subscribe( string $api_key, string $list_id, string $email, string $status, array $merge_vars, int $form_id, array $options, \Cmatic_File_Logger $logger ): void;
	public function create_field( string $api_key, array $spec, bool $log_enabled = false ): array;
	public function lookup( string $api_key, string $email ): array;
	public function request( string $api_key, string $method, string $path, array $body = array(), bool $retryable = true ): array;
	public function normalize_lists( array $body ): array;
	public function normalize_fields( array $body ): array;
}
