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

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing -- Typed signatures follow the existing Lite class convention.
final class MailerLiteOperations extends GenericOperations {
	public function perform_subscription( string $api_key, string $list_id, string $email, string $status, array $merge_vars, array $options ): array {
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

		$mode    = self::str( $options['status_mode'] ?? 'legacy_provider_managed' );
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
		$metadata = isset( $options['consent_metadata'] ) && is_array( $options['consent_metadata'] ) ? $options['consent_metadata'] : array();
		foreach ( $metadata as $key => $value ) {
			if ( in_array( $key, array( 'ip_address', 'optin_ip', 'opted_in_at' ), true ) && is_string( $value ) ) {
				$payload[ $key ] = $value;
			}
		}
		$response = $this->request(
			$api_key,
			'POST',
			'/subscribers',
			$payload,
			false
		);
		if ( ! self::succeeded( $response ) ) {
			return $response;
		}

		$body            = self::body_of( $response );
		$data            = isset( $body['data'] ) && is_array( $body['data'] ) ? $body['data'] : array();
		$returned_status = isset( $data['status'] ) && is_scalar( $data['status'] )
			? sanitize_key( (string) $data['status'] )
			: '';
		$valid_statuses  = 'account' === $mode
			? array( 'active', 'unconfirmed' )
			: array( 'unconfirmed' === $mode ? 'unconfirmed' : 'active' );
		if ( ! in_array( $returned_status, $valid_statuses, true ) ) {
			return $this->failure_result( 'subscriber_status_mismatch', 'MailerLite did not return the requested subscriber status.' );
		}

		$result = $this->subscription_result( $response, $email, $merge_vars );
		if ( isset( $result['data'] ) && is_array( $result['data'] ) ) {
			$result['data']['status'] = $returned_status;
		}
		return $result;
	}

	public function create_field( string $api_key, array $spec, bool $log_enabled = false ): array {
		unset( $log_enabled );
		$name = sanitize_text_field( self::str( $spec['name'] ?? '' ) );
		$type = sanitize_key( self::str( $spec['type'] ?? '' ) );
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
		if ( ! self::succeeded( $result ) && 404 === self::status_of( $result ) ) {
			return array(
				'success' => true,
				'found'   => false,
				'data'    => array(),
				'error'   => '',
			);
		}
		if ( ! self::succeeded( $result ) ) {
			return $result;
		}
		$body = self::body_of( $result );
		$data = isset( $body['data'] ) && is_array( $body['data'] ) ? $body['data'] : $body;
		return array(
			'success' => true,
			'found'   => true,
			'data'    => $data,
			'error'   => '',
		);
	}
}
