<?php
/**
 * ChimpMatic Lite multi-ESP component.
 *
 * @package contact-form-7-mailchimp-extension
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing -- Typed signatures follow the existing Lite class convention.
final class Cmatic_Lite_Esp_Brevo extends Cmatic_Lite_Esp_Provider {
	public function get_slug(): string {
		return 'brevo';
	}
	public function get_label(): string {
		return 'Brevo';
	}
	protected function get_base_url(): string {
		return 'https://api.brevo.com/v3';
	}
	protected function get_validation_path(): string {
		return '/account';
	}
	protected function get_lists_path(): string {
		return '/contacts/lists?limit=50&offset=0';
	}
	protected function get_next_lists_path( array $body, string $current_path ): string {
		$query = (string) wp_parse_url( $current_path, PHP_URL_QUERY );
		parse_str( $query, $parameters );
		$offset   = (int) ( $parameters['offset'] ?? 0 );
		$received = isset( $body['lists'] ) && is_array( $body['lists'] ) ? count( $body['lists'] ) : 0;
		$next     = $offset + $received;
		if ( 0 === $received || $next >= (int) ( $body['count'] ?? 0 ) ) {
			return '';
		}
		return '/contacts/lists?limit=50&offset=' . $next;
	}
	protected function get_fields_path( string $list_id ): string {
		return '/contacts/attributes';
	}
	protected function get_auth_headers( string $api_key ): array {
		return array( 'api-key' => $api_key );
	}
	protected function normalize_lists( array $body ): array {
		$lists  = array();
		$source = isset( $body['lists'] ) && is_array( $body['lists'] ) ? $body['lists'] : array();
		foreach ( $source as $list ) {
			if ( ! is_array( $list ) ) {
				continue;
			}
			if ( isset( $list['id'], $list['name'] ) ) {
				$lists[] = array(
					'id'    => (string) $list['id'],
					'name'  => sanitize_text_field( (string) $list['name'] ),
					'stats' => array(
						'member_count'      => (int) ( $list['totalSubscribers'] ?? 0 ),
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
				'name'          => __( 'Contact email address', 'chimpmatic-lite' ),
				'type'          => 'email',
				'display_order' => 0,
			),
		);
		$source = isset( $body['attributes'] ) && is_array( $body['attributes'] ) ? $body['attributes'] : array();
		foreach ( $source as $attribute ) {
			if ( ! is_array( $attribute ) ) {
				continue;
			}
			if ( 'normal' === ( $attribute['category'] ?? '' ) && ! empty( $attribute['name'] ) ) {
				$fields[] = array(
					'tag'           => sanitize_text_field( (string) $attribute['name'] ),
					'name'          => sanitize_text_field( (string) $attribute['name'] ),
					'type'          => sanitize_key( (string) ( $attribute['type'] ?? 'text' ) ),
					'display_order' => count( $fields ),
				);
			}
		}
		return $fields;
	}
	public function validate_subscription_options( string $api_key, string $list_id, array $options ): array {
		$mode = sanitize_key( (string) ( $options['subscription_mode'] ?? 'single' ) );
		if ( 'single' === $mode ) {
			return parent::validate_subscription_options( $api_key, $list_id, $options );
		}
		$template_id = max( 0, (int) ( $options['doi_template_id'] ?? 0 ) );
		$redirect    = esc_url_raw( (string) ( $options['doi_redirect_url'] ?? '' ) );
		if ( 'double' !== $mode || $template_id < 1 || (int) $list_id < 1 || 'https' !== wp_parse_url( $redirect, PHP_URL_SCHEME ) ) {
			return array(
				'success' => false,
				'error'   => 'Double opt-in settings are incomplete.',
				'data'    => array(),
			);
		}
		$response = $this->request( $api_key, 'GET', '/smtp/templates/' . $template_id );
		if ( ! $response['success'] ) {
			return array(
				'success' => false,
				'error'   => 'Brevo could not verify this template.',
				'data'    => array(),
			);
		}
		$body = $response['body'];
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
				'template_name' => sanitize_text_field( (string) ( $body['name'] ?? '' ) ),
			),
		);
	}
	protected function perform_subscription( string $api_key, string $list_id, string $email, string $status, array $merge_vars, array $options ): array {
		if ( ! in_array( $status, array( 'subscribed', 'pending', 'unsubscribed' ), true ) ) {
			return $this->failure_result( 'configuration_error', 'Brevo does not support the requested status in this integration.' );
		}
		unset( $merge_vars['EMAIL'] );
		$prepared = $this->prepare_multiple_choice_attributes( $api_key, $email, $merge_vars );
		if ( ! $prepared['success'] ) {
			return $prepared;
		}
		$merge_vars = $prepared['merge_vars'];
		$mode       = sanitize_key( (string) ( $options['subscription_mode'] ?? 'single' ) );
		if ( 'pending' === $status ) {
			if (
				'double' !== $mode
				|| (int) ( $options['doi_template_id'] ?? 0 ) < 1
				|| 'https' !== wp_parse_url( (string) ( $options['doi_redirect_url'] ?? '' ), PHP_URL_SCHEME )
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
					'includeListIds' => array( (int) $list_id ),
					'redirectionUrl' => esc_url_raw( (string) $options['doi_redirect_url'] ),
					'templateId'     => (int) $options['doi_template_id'],
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
				'listIds'          => array( (int) $list_id ),
				'emailBlacklisted' => 'unsubscribed' === $status,
				'updateEnabled'    => true,
			)
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
		if ( ! $response['success'] ) {
			if ( 404 === ( $response['status'] ?? 0 ) ) {
				return array(
					'success'    => true,
					'merge_vars' => $merge_vars,
				);
			}
			return $response;
		}

		if ( ! isset( $response['body']['attributes'] ) || ! is_array( $response['body']['attributes'] ) ) {
			return $this->failure_result( 'api_error', 'Brevo returned invalid contact attributes.' );
		}
		$attributes = $response['body']['attributes'];

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
