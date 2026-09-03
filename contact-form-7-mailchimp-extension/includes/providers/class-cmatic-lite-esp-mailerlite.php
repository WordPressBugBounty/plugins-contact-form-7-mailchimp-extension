<?php
/**
 * ChimpMatic Lite multi-ESP component.
 *
 * @package contact-form-7-mailchimp-extension
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing -- Typed signatures follow the existing Lite class convention.
final class Cmatic_Lite_Esp_Mailerlite extends Cmatic_Lite_Esp_Provider implements Cmatic_Lite_Esp_Field_Creator_Interface, Cmatic_Lite_Esp_Lookup_Interface {
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
		return array(
			'Authorization' => 'Bearer ' . $api_key,
			'X-Version'     => '2026-07-01',
		);
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
				'name'          => __( 'Subscriber email address', 'contact-form-7-mailchimp-extension' ),
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
		unset( $merge_vars['EMAIL'] );
		if ( ! in_array( $status, array( 'subscribed', 'pending', 'unsubscribed' ), true ) ) {
			return $this->failure_result( 'configuration_error', 'MailerLite does not support the requested status in this integration.' );
		}

		$groups = array();
		foreach ( isset( $options['groups'] ) && is_array( $options['groups'] ) ? $options['groups'] : array( $list_id ) as $group ) {
			if ( is_scalar( $group ) && '' !== (string) $group ) {
				$groups[] = (string) $group;
			}
		}
		$groups = array_values( array_unique( $groups ) );
		if ( empty( $groups ) ) {
			return $this->failure_result( 'configuration_error', 'MailerLite requires at least one destination group.' );
		}

		$mode    = (string) ( $options['status_mode'] ?? 'legacy_provider_managed' );
		$payload = array(
			'email'  => $email,
			'fields' => (object) $merge_vars,
			'groups' => $groups,
		);
		if ( 'legacy_provider_managed' === $mode || 'active' === $mode ) {
			$payload['status'] = 'active';
		} elseif ( 'unconfirmed' === $mode ) {
			$payload['status'] = 'unconfirmed';
		} elseif ( 'account' !== $mode ) {
			return $this->failure_result( 'configuration_error', 'Invalid MailerLite status mode.' );
		}
		if ( ! empty( $options['resubscribe'] ) ) {
			$payload['resubscribe'] = true;
		}
		foreach ( (array) ( $options['consent_metadata'] ?? array() ) as $key => $value ) {
			if ( in_array( $key, array( 'ip_address', 'optin_ip', 'opted_in_at' ), true ) && is_string( $value ) ) {
				$payload[ $key ] = $value;
			}
		}
		$response = $this->request(
			$api_key,
			'POST',
			'/subscribers',
			$payload
		);
		if ( ! $response['success'] ) {
			return $response;
		}

		$returned_status = isset( $response['body']['data']['status'] ) && is_scalar( $response['body']['data']['status'] )
			? sanitize_key( (string) $response['body']['data']['status'] )
			: '';
		$valid_statuses  = 'account' === $mode
			? array( 'active', 'unconfirmed' )
			: array( 'unconfirmed' === $mode ? 'unconfirmed' : 'active' );
		if ( ! in_array( $returned_status, $valid_statuses, true ) ) {
			return $this->failure_result( 'subscriber_status_mismatch', 'MailerLite did not return the requested subscriber status.' );
		}

		$result                   = $this->subscription_result( $response, $email, $merge_vars );
		$result['data']['status'] = $returned_status;
		return $result;
	}

	public function create_field( string $api_key, array $spec, bool $log_enabled = false ): array {
		unset( $log_enabled );
		$name = sanitize_text_field( (string) ( $spec['name'] ?? '' ) );
		$type = sanitize_key( (string) ( $spec['type'] ?? '' ) );
		if ( '' === $name || strlen( $name ) > 255 || ! in_array( $type, array( 'text', 'number', 'date' ), true ) ) {
			return $this->failure_result( 'configuration_error', 'Invalid MailerLite field definition.' );
		}
		return $this->request(
			$api_key,
			'POST',
			'/fields',
			array(
				'name' => $name,
				'type' => $type,
			),
			false
		);
	}

	public function lookup( string $api_key, string $email ): array {
		$result = $this->request( $api_key, 'GET', '/subscribers/' . rawurlencode( strtolower( $email ) ), array(), false );
		if ( ! $result['success'] && 404 === (int) ( $result['status'] ?? 0 ) ) {
			return array(
				'success' => true,
				'found'   => false,
				'data'    => array(),
				'error'   => '',
			);
		}
		if ( ! $result['success'] ) {
			return $result;
		}
		$data = isset( $result['body']['data'] ) && is_array( $result['body']['data'] ) ? $result['body']['data'] : $result['body'];
		return array(
			'success' => true,
			'found'   => true,
			'data'    => $data,
			'error'   => '',
		);
	}
}
