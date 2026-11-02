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
final class BrevoOperations extends GenericOperations {
	public function validate_subscription_options( string $api_key, string $list_id, array $options ): array {
		$mode = sanitize_key( self::str( $options['subscription_mode'] ?? 'single' ) );
		if ( 'single' === $mode ) {
			return parent::validate_subscription_options( $api_key, $list_id, $options );
		}
		$template_id = max( 0, self::int( $options['doi_template_id'] ?? 0 ) );
		$redirect    = esc_url_raw( self::str( $options['doi_redirect_url'] ?? '' ) );
		if ( 'double' !== $mode || $template_id < 1 || (int) $list_id < 1 || 'https' !== wp_parse_url( $redirect, PHP_URL_SCHEME ) ) {
			return array(
				'success' => false,
				'error'   => 'Double opt-in settings are incomplete.',
				'data'    => array(),
			);
		}
		$response = $this->request( $api_key, 'GET', '/smtp/templates/' . $template_id );
		if ( ! self::succeeded( $response ) ) {
			return array(
				'success' => false,
				'error'   => 'Brevo could not verify this template.',
				'data'    => array(),
			);
		}
		$body = self::body_of( $response );
		if ( true !== ( $body['isActive'] ?? false ) || true !== ( $body['doiTemplate'] ?? false ) ) {
			return array(
				'success' => false,
				'error'   => 'Choose an active Brevo double opt-in template.',
				'data'    => array(),
			);
		}
		return array(
			'success' => true,
			'error'   => '',
			'data'    => array(
				'template_id'   => $template_id,
				'template_name' => sanitize_text_field( self::str( $body['name'] ?? '' ) ),
			),
		);
	}

	public function perform_subscription( string $api_key, string $list_id, string $email, string $status, array $merge_vars, array $options ): array {
		if ( ! in_array( $status, array( 'subscribed', 'pending', 'unsubscribed' ), true ) ) {
			return $this->failure_result( 'configuration_error', 'Brevo does not support the requested status in this integration.' );
		}
		unset( $merge_vars['EMAIL'] );
		$prepared = $this->prepare_multiple_choice_attributes( $api_key, $email, $merge_vars );
		if ( ! self::succeeded( $prepared ) ) {
			return $prepared;
		}
		$merge_vars = isset( $prepared['merge_vars'] ) && is_array( $prepared['merge_vars'] ) ? $prepared['merge_vars'] : array();
		$list_ids   = array();
		foreach ( isset( $options['groups'] ) && is_array( $options['groups'] ) ? $options['groups'] : array( $list_id ) as $group ) {
			if ( is_scalar( $group ) && '' !== (string) $group ) {
				$list_ids[] = (int) $group;
			}
		}
		$list_ids = array_values( array_unique( $list_ids ) );
		if ( array() === $list_ids ) {
			$list_ids = array( (int) $list_id );
		}
		$mode = sanitize_key( self::str( $options['subscription_mode'] ?? 'single' ) );
		if ( 'pending' === $status ) {
			$redirect = self::str( $options['doi_redirect_url'] ?? '' );
			if (
				'double' !== $mode
				|| self::int( $options['doi_template_id'] ?? 0 ) < 1
				|| 'https' !== wp_parse_url( $redirect, PHP_URL_SCHEME )
			) {
				return $this->failure_result( 'configuration_error', 'Brevo double opt-in configuration is invalid.' );
			}
			$response = $this->request(
				$api_key,
				'POST',
				'/contacts/doubleOptinConfirmation',
				array(
					'email'          => $email,
					'attributes'     => (object) $merge_vars,
					'includeListIds' => $list_ids,
					'redirectionUrl' => esc_url_raw( $redirect ),
					'templateId'     => self::int( $options['doi_template_id'] ?? 0 ),
				),
				false
			);
			return $this->subscription_result( $response, $email, $merge_vars );
		}
		$response = $this->request(
			$api_key,
			'POST',
			'/contacts',
			array(
				'email'            => $email,
				'attributes'       => (object) $merge_vars,
				'listIds'          => $list_ids,
				'emailBlacklisted' => 'unsubscribed' === $status,
				'updateEnabled'    => true,
			),
			false
		);
		return $this->subscription_result( $response, $email, $merge_vars );
	}

	private function prepare_multiple_choice_attributes( string $api_key, string $email, array $merge_vars ): array {
		$array_tags = array_filter( $merge_vars, 'is_array' );
		if ( empty( $array_tags ) ) {
			return array(
				'success'    => true,
				'merge_vars' => $merge_vars,
			);
		}

		$response = $this->request(
			$api_key,
			'GET',
			'/contacts/' . rawurlencode( strtolower( $email ) ) . '?identifierType=email_id',
			array(),
			false
		);
		if ( ! self::succeeded( $response ) ) {
			if ( 404 === self::status_of( $response ) ) {
				return array(
					'success'    => true,
					'merge_vars' => $merge_vars,
				);
			}
			return $response;
		}

		$body = self::body_of( $response );
		if ( ! isset( $body['attributes'] ) || ! is_array( $body['attributes'] ) ) {
			return $this->failure_result( 'api_error', 'Brevo returned invalid contact attributes.' );
		}
		$attributes = $body['attributes'];

		foreach ( $array_tags as $tag => $new_values ) {
			if ( ! array_key_exists( $tag, $attributes ) ) {
				continue;
			}
			$existing_values = $attributes[ $tag ];
			if ( ! is_array( $existing_values ) || count( $existing_values ) !== count( array_filter( $existing_values, 'is_string' ) ) ) {
				return $this->failure_result( 'api_error', 'Brevo returned an invalid multiple-choice attribute.' );
			}
			foreach ( $new_values as $new_value ) {
				if ( ! in_array( $new_value, $existing_values, true ) ) {
					$existing_values[] = $new_value;
				}
			}
			$merge_vars[ $tag ] = array_values( $existing_values );
		}

		return array(
			'success'    => true,
			'merge_vars' => $merge_vars,
		);
	}
}
