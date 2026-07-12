<?php
/**
 * ChimpMatic Lite multi-ESP component.
 *
 * @package contact-form-7-mailchimp-extension
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing -- Typed signatures follow the existing Lite class convention.
final class Cmatic_Lite_Esp_Mailerlite extends Cmatic_Lite_Esp_Provider {
	public function get_slug(): string {
		return 'mailerlite';
	}
	public function get_label(): string {
		return 'MailerLite';
	}
	protected function get_base_url(): string {
		return 'https://connect.mailerlite.com/api';
	}
	protected function get_validation_path(): string {
		return '/groups?limit=1';
	}
	protected function get_lists_path(): string {
		return '/groups?limit=1000';
	}
	protected function get_next_lists_path( array $body, string $current_path ): string {
		return '';
	}
	protected function get_fields_path( string $list_id ): string {
		return '/fields?limit=100';
	}
	protected function get_auth_headers( string $api_key ): array {
		return array( 'Authorization' => 'Bearer ' . $api_key );
	}
	protected function normalize_lists( array $body ): array {
		$lists  = array();
		$source = isset( $body['data'] ) && is_array( $body['data'] ) ? $body['data'] : array();
		foreach ( $source as $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}
			if ( isset( $group['id'], $group['name'] ) ) {
				$lists[] = array(
					'id'    => (string) $group['id'],
					'name'  => sanitize_text_field( (string) $group['name'] ),
					'stats' => array(
						'member_count'      => (int) ( $group['active_count'] ?? 0 ),
						'merge_field_count' => 0,
					),
				);
			}
		}
		return $lists;
	}
	protected function normalize_fields( array $body ): array {
		$fields = array(
			array(
				'tag'           => 'EMAIL',
				'name'          => __( 'Subscriber Email', 'chimpmatic-lite' ),
				'type'          => 'email',
				'display_order' => 0,
			),
		);
		$source = isset( $body['data'] ) && is_array( $body['data'] ) ? $body['data'] : array();
		foreach ( $source as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$key = (string) ( $field['key'] ?? $field['name'] ?? '' );
			if ( '' !== $key && 'email' !== strtolower( $key ) ) {
				$fields[] = array(
					'tag'           => sanitize_text_field( $key ),
					'name'          => sanitize_text_field( (string) ( $field['name'] ?? $key ) ),
					'type'          => sanitize_key( (string) ( $field['type'] ?? 'text' ) ),
					'display_order' => count( $fields ),
				);
			}
		}
		return $fields;
	}
	protected function perform_subscription( string $api_key, string $list_id, string $email, string $status, array $merge_vars, array $options ): array {
		$provider_managed = 'provider_managed' === ( $options['subscription_mode'] ?? '' );
		unset( $merge_vars['EMAIL'] );
		$status_map = array(
			'subscribed'   => 'active',
			'pending'      => 'unconfirmed',
			'unsubscribed' => 'unsubscribed',
		);
		if ( ! isset( $status_map[ $status ] ) ) {
			return $this->failure_result( 'configuration_error', 'MailerLite does not support the requested status in this integration.' );
		}
		if ( $provider_managed ) {
			$status_map[ $status ] = 'active';
		}
		$response = $this->request(
			$api_key,
			'POST',
			'/subscribers',
			array(
				'email'  => $email,
				'fields' => (object) $merge_vars,
				'groups' => array( $list_id ),
				'status' => $status_map[ $status ],
			)
		);
		return $this->subscription_result( $response, $email, $merge_vars );
	}
}
