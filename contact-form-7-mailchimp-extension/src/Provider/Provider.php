<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    hello@chimpmatic.com
 * @copyright 2014-2026 ChimpMatic
 * @license   GPL-3.0+
 */

declare(strict_types=1);

namespace Cmatic\Lite\Provider;

use Cmatic\Lite\Provider\Operations\OperationsInterface;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing -- Typed signatures follow the existing Lite class convention.
final class Provider implements \Cmatic_Lite_Esp_Provider_Interface, \Cmatic_Lite_Esp_Field_Creator_Interface, \Cmatic_Lite_Esp_Lookup_Interface {
	/** @var Descriptor */
	private $descriptor;

	/** @var OperationsInterface */
	private $operations;

	public function __construct( Descriptor $descriptor, OperationsInterface $operations ) {
		$this->descriptor = $descriptor;
		$this->operations = $operations;
	}

	public function descriptor(): Descriptor {
		return $this->descriptor;
	}

	public function operations(): OperationsInterface {
		return $this->operations;
	}

	public function get_slug(): string {
		return $this->descriptor->slug();
	}

	public function get_label(): string {
		return $this->descriptor->label();
	}

	public function validate_key( string $api_key, bool $log_enabled = false ): array {
		return $this->operations->validate_key( $api_key, $log_enabled );
	}

	public function get_lists( string $api_key, bool $log_enabled = false ): array {
		return $this->operations->get_lists( $api_key, $log_enabled );
	}

	public function get_merge_fields( string $api_key, string $list_id, bool $log_enabled = false ): array {
		return $this->operations->get_merge_fields( $api_key, $list_id, $log_enabled );
	}

	public function validate_subscription_options( string $api_key, string $list_id, array $options ): array {
		return $this->operations->validate_subscription_options( $api_key, $list_id, $options );
	}

	public function subscribe( string $api_key, string $list_id, string $email, string $status, array $merge_vars, int $form_id, array $options, \Cmatic_File_Logger $logger ): void {
		$this->operations->subscribe( $api_key, $list_id, $email, $status, $merge_vars, $form_id, $options, $logger );
	}

	public function create_field( string $api_key, array $spec, bool $log_enabled = false ): array {
		return $this->operations->create_field( $api_key, $spec, $log_enabled );
	}

	public function lookup( string $api_key, string $email ): array {
		return $this->operations->lookup( $api_key, $email );
	}

	protected function request( string $api_key, string $method, string $path, array $body = array(), bool $retryable = true ): array {
		return $this->operations->request( $api_key, $method, $path, $body, $retryable );
	}

	protected function normalize_lists( array $body ): array {
		return $this->operations->normalize_lists( $body );
	}

	protected function normalize_fields( array $body ): array {
		return $this->operations->normalize_fields( $body );
	}

	protected function perform_subscription( string $api_key, string $list_id, string $email, string $status, array $merge_vars, array $options ): array {
		return $this->operations->perform_subscription( $api_key, $list_id, $email, $status, $merge_vars, $options );
	}
}
