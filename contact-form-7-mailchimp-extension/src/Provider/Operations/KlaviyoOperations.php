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
final class KlaviyoOperations extends GenericOperations {
	public function perform_subscription( string $api_key, string $list_id, string $email, string $status, array $merge_vars, array $options ): array {
		$list_ids = array();
		foreach ( isset( $options['groups'] ) && is_array( $options['groups'] ) ? $options['groups'] : array( $list_id ) as $group ) {
			if ( is_scalar( $group ) && '' !== (string) $group ) {
				$list_ids[] = (string) $group;
			}
		}
		$list_ids = array_values( array_unique( $list_ids ) );
		if ( array() === $list_ids ) {
			$list_ids = array( $list_id );
		}
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
			),
			false
		);
		if ( ! self::succeeded( $profile ) ) {
			return $profile;
		}
		$profile_body = self::body_of( $profile );
		$profile_data = isset( $profile_body['data'] ) && is_array( $profile_body['data'] ) ? $profile_body['data'] : array();
		$profile_id   = self::str( $profile_data['id'] ?? '' );
		if ( '' === $profile_id ) {
			return $this->failure_result( 'api_error', 'Klaviyo profile upsert returned no profile ID.' );
		}

		$subscription = array();
		foreach ( $list_ids as $target_list ) {
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
											'email' => $email,
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
									'id'   => $target_list,
								),
							),
						),
					),
				),
				false
			);
			if ( ! self::succeeded( $subscription ) ) {
				break;
			}
		}
		if ( self::succeeded( $subscription ) ) {
			$subscription['subscription_status'] = 'queued';
		}
		return $this->subscription_result( $subscription, $email, $merge_vars );
	}
}
