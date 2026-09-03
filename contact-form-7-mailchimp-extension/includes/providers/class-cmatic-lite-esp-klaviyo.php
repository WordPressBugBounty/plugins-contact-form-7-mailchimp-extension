<?php
/**
 * ChimpMatic Lite multi-ESP component.
 *
 * @package contact-form-7-mailchimp-extension
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing -- Typed signatures follow the existing Lite class convention.
final class Cmatic_Lite_Esp_Klaviyo extends Cmatic_Lite_Esp_Provider {
	public function get_slug(): string {
		return 'klaviyo';
	}
	public function get_label(): string {
		return 'Klaviyo';
	}
	protected function get_base_url(): string {
		return 'https://a.klaviyo.com/api';
	}
	protected function get_validation_path(): string {
		return '/lists?page[size]=1';
	}
	protected function get_lists_path(): string {
		return '/lists?page[size]=10&fields[list]=name,opt_in_process';
	}
	protected function get_next_lists_path( array $body, string $current_path ): string {
		$links = isset( $body['links'] ) && is_array( $body['links'] ) ? $body['links'] : array();
		$next  = $links['next'] ?? '';
		if ( ! is_string( $next ) || '' === $next ) {
			return '';
		}
		$parts = wp_parse_url( $next );
		if ( ! is_array( $parts ) || 'a.klaviyo.com' !== ( $parts['host'] ?? '' ) || '/api/lists' !== ( $parts['path'] ?? '' ) || empty( $parts['query'] ) ) {
			return '';
		}
		return '/lists?' . $parts['query'];
	}
	protected function get_fields_path( string $list_id ): string {
		return '';
	}
	protected function get_auth_headers( string $api_key ): array {
		return array(
			'Authorization' => 'Klaviyo-API-Key ' . $api_key,
			'Accept'        => 'application/vnd.api+json',
			'Content-Type'  => 'application/vnd.api+json',
			'revision'      => '2026-04-15',
		);
	}
	protected function normalize_lists( array $body ): array {
		$lists  = array();
		$source = isset( $body['data'] ) && is_array( $body['data'] ) ? $body['data'] : array();
		foreach ( $source as $list ) {
			if ( ! is_array( $list ) ) {
				continue;
			}
			if (
				isset( $list['id'], $list['attributes'] )
				&& is_array( $list['attributes'] )
				&& isset( $list['attributes']['name'] )
			) {
					$lists[] = array(
						'id'             => sanitize_text_field( (string) $list['id'] ),
						'name'           => sanitize_text_field( (string) $list['attributes']['name'] ),
						'opt_in_process' => sanitize_key( (string) ( $list['attributes']['opt_in_process'] ?? '' ) ),
						'stats'          => array(
							'member_count'      => 0,
							'merge_field_count' => 6,
						),
					);
			}
		}
		return $lists;
	}
	protected function normalize_fields( array $body ): array {
		return array(
			array(
				'tag'           => 'EMAIL',
				'name'          => __( 'Profile email address', 'contact-form-7-mailchimp-extension' ),
				'type'          => 'email',
				'display_order' => 0,
			),
			array(
				'tag'           => 'first_name',
				'name'          => __( 'First Name', 'contact-form-7-mailchimp-extension' ),
				'type'          => 'text',
				'display_order' => 1,
			),
			array(
				'tag'           => 'last_name',
				'name'          => __( 'Last Name', 'contact-form-7-mailchimp-extension' ),
				'type'          => 'text',
				'display_order' => 2,
			),
			array(
				'tag'           => 'phone_number',
				'name'          => __( 'Phone Number', 'contact-form-7-mailchimp-extension' ),
				'type'          => 'phone',
				'display_order' => 3,
			),
			array(
				'tag'           => 'organization',
				'name'          => __( 'Organization', 'contact-form-7-mailchimp-extension' ),
				'type'          => 'text',
				'display_order' => 4,
			),
			array(
				'tag'           => 'title',
				'name'          => __( 'Title', 'contact-form-7-mailchimp-extension' ),
				'type'          => 'text',
				'display_order' => 5,
			),
		);
	}
	protected function perform_subscription( string $api_key, string $list_id, string $email, string $status, array $merge_vars, array $options ): array {
		unset( $options );
		if ( 'subscribed' !== $status ) {
			return $this->failure_result( 'configuration_error', 'Klaviyo does not support the requested status in this integration.' );
		}
		$attributes = array( 'email' => $email );
		$properties = array();
		foreach ( $merge_vars as $key => $value ) {
			if ( 'EMAIL' === $key ) {
				continue;
			}
			if ( in_array( $key, array( 'first_name', 'last_name', 'phone_number', 'organization', 'title' ), true ) ) {
				$attributes[ $key ] = $value;
			} else {
				$properties[ $key ] = $value;
			}
		}
		if ( ! empty( $properties ) ) {
			$attributes['properties'] = (object) $properties;
		}

		$profile = $this->request(
			$api_key,
			'POST',
			'/profile-import',
			array(
				'data' => array(
					'type'       => 'profile',
					'attributes' => $attributes,
				),
			)
		);
		if ( ! $profile['success'] ) {
			return $profile;
		}
		$profile_id = (string) ( $profile['body']['data']['id'] ?? '' );
		if ( '' === $profile_id ) {
			return $this->failure_result( 'api_error', 'Klaviyo profile upsert returned no profile ID.' );
		}

		$subscription = $this->request(
			$api_key,
			'POST',
			'/profile-subscription-bulk-create-jobs',
			array(
				'data' => array(
					'type'          => 'profile-subscription-bulk-create-job',
					'attributes'    => array(
						'profiles' => array(
							'data' => array(
								array(
									'type'       => 'profile',
									'id'         => $profile_id,
									'attributes' => array(
										'email'         => $email,
										'subscriptions' => array(
											'email' => array(
												'marketing' => array( 'consent' => 'SUBSCRIBED' ),
											),
										),
									),
								),
							),
						),
					),
					'relationships' => array(
						'list' => array(
							'data' => array(
								'type' => 'list',
								'id'   => $list_id,
							),
						),
					),
				),
			),
			false
		);
		return $this->subscription_result( $subscription, $email, $merge_vars );
	}
}
