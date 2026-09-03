<?php
/**
 * Provider-facing admin UI metadata.
 *
 * @package contact-form-7-mailchimp-extension
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing -- Typed signatures follow the existing Lite provider convention.
final class Cmatic_Lite_Esp_Manifest {
	public static function all(): array {
		$definitions = array(
			'mailchimp'  => array(
				'destination_singular' => __( 'Audience', 'contact-form-7-mailchimp-extension' ),
				'destination_plural'   => __( 'Audiences', 'contact-form-7-mailchimp-extension' ),
				'person_singular'      => __( 'Contact', 'contact-form-7-mailchimp-extension' ),
				'person_plural'        => __( 'Contacts', 'contact-form-7-mailchimp-extension' ),
				'data_singular'        => __( 'Merge field', 'contact-form-7-mailchimp-extension' ),
				'data_plural'          => __( 'Merge fields', 'contact-form-7-mailchimp-extension' ),
				'uses_legacy_panel'    => true,
				'supports_fields'      => true,
				'auth_fields'          => array(),
				'consent'              => array(
					'mode'        => 'legacy',
					'docs_url'    => 'https://mailchimp.com/help/about-double-opt-in/',
					'description' => __( 'Mailchimp opt-in and marketing permissions are managed in the existing Pro settings.', 'contact-form-7-mailchimp-extension' ),
				),
			),
			'brevo'      => array(
				'destination_singular' => __( 'List', 'contact-form-7-mailchimp-extension' ),
				'destination_plural'   => __( 'Lists', 'contact-form-7-mailchimp-extension' ),
				'person_singular'      => __( 'Contact', 'contact-form-7-mailchimp-extension' ),
				'person_plural'        => __( 'Contacts', 'contact-form-7-mailchimp-extension' ),
				'data_singular'        => __( 'Contact attribute', 'contact-form-7-mailchimp-extension' ),
				'data_plural'          => __( 'Contact attributes', 'contact-form-7-mailchimp-extension' ),
				'uses_legacy_panel'    => false,
				'supports_fields'      => true,
				'auth_fields'          => array(
					array(
						'id'           => 'api_key',
						'label'        => __( 'Brevo API key', 'contact-form-7-mailchimp-extension' ),
						'placeholder'  => __( 'Enter your Brevo API key', 'contact-form-7-mailchimp-extension' ),
						'description'  => __( 'Your credential is encrypted before saving and is never displayed again.', 'contact-form-7-mailchimp-extension' ),
						'type'         => 'password',
						'autocomplete' => 'new-password',
					),
				),
				'consent'              => array(
					'mode'        => 'request',
					'docs_url'    => 'https://developers.brevo.com/reference/create-doi-contact',
					'description' => __( 'Choose single opt-in or send Brevo’s confirmation email.', 'contact-form-7-mailchimp-extension' ),
				),
			),
			'mailerlite' => array(
				'destination_singular' => __( 'Group', 'contact-form-7-mailchimp-extension' ),
				'destination_plural'   => __( 'Groups', 'contact-form-7-mailchimp-extension' ),
				'person_singular'      => __( 'Subscriber', 'contact-form-7-mailchimp-extension' ),
				'person_plural'        => __( 'Subscribers', 'contact-form-7-mailchimp-extension' ),
				'data_singular'        => __( 'Subscriber field', 'contact-form-7-mailchimp-extension' ),
				'data_plural'          => __( 'Subscriber fields', 'contact-form-7-mailchimp-extension' ),
				'uses_legacy_panel'    => false,
				'supports_fields'      => true,
				'features'             => array(
					'multi_group_routing' => true,
					'status_modes'        => array( 'account', 'active', 'unconfirmed' ),
					'consent_metadata'    => true,
					'create_field_types'  => array( 'text', 'number', 'date' ),
					'lookup'              => true,
				),
				'auth_fields'          => array(
					array(
						'id'           => 'api_key',
						'label'        => __( 'MailerLite API token', 'contact-form-7-mailchimp-extension' ),
						'placeholder'  => __( 'Enter your MailerLite API token', 'contact-form-7-mailchimp-extension' ),
						'description'  => __( 'Your credential is encrypted before saving and is never displayed again.', 'contact-form-7-mailchimp-extension' ),
						'type'         => 'password',
						'autocomplete' => 'new-password',
					),
				),
				'consent'              => array(
					'mode'        => 'account',
					'docs_url'    => 'https://www.mailerlite.com/help/how-to-use-double-opt-in-when-collecting-subscribers',
					'description' => __( 'Double opt-in for API subscribers is controlled in your MailerLite account.', 'contact-form-7-mailchimp-extension' ),
				),
			),
			'klaviyo'    => array(
				'destination_singular' => __( 'List', 'contact-form-7-mailchimp-extension' ),
				'destination_plural'   => __( 'Lists', 'contact-form-7-mailchimp-extension' ),
				'person_singular'      => __( 'Profile', 'contact-form-7-mailchimp-extension' ),
				'person_plural'        => __( 'Profiles', 'contact-form-7-mailchimp-extension' ),
				'data_singular'        => __( 'Profile property', 'contact-form-7-mailchimp-extension' ),
				'data_plural'          => __( 'Profile properties', 'contact-form-7-mailchimp-extension' ),
				'uses_legacy_panel'    => false,
				'supports_fields'      => true,
				'auth_fields'          => array(
					array(
						'id'           => 'api_key',
						'label'        => __( 'Klaviyo private API key', 'contact-form-7-mailchimp-extension' ),
						'placeholder'  => __( 'Enter your Klaviyo private API key', 'contact-form-7-mailchimp-extension' ),
						'description'  => __( 'Your credential is encrypted before saving and is never displayed again.', 'contact-form-7-mailchimp-extension' ),
						'type'         => 'password',
						'autocomplete' => 'new-password',
					),
				),
				'consent'              => array(
					'mode'        => 'destination',
					'docs_url'    => 'https://developers.klaviyo.com/en/reference/bulk_subscribe_profiles',
					'description' => __( 'The selected Klaviyo list controls whether confirmation is required.', 'contact-form-7-mailchimp-extension' ),
				),
			),
		);

		foreach ( $definitions as $slug => $definition ) {
			$definitions[ $slug ] = self::normalize(
				array_merge(
					array(
						'slug'  => $slug,
						'label' => Cmatic_Lite_Esp_Registry::get( $slug )->get_label(),
					),
					$definition
				)
			);
		}

		return $definitions;
	}

	public static function get( string $slug ): array {
		$all = self::all();
		return $all[ $slug ] ?? $all['mailchimp'];
	}

	public static function selector_options(): array {
		$options = array();
		foreach ( self::all() as $slug => $definition ) {
			$options[ $slug ] = (string) $definition['label'];
		}
		return $options;
	}

	private static function normalize( array $definition ): array {
		$features               = isset( $definition['features'] ) && is_array( $definition['features'] ) ? $definition['features'] : array();
		$definition['features'] = array_merge(
			array(
				'multi_group_routing' => false,
				'status_modes'        => array(),
				'consent_metadata'    => false,
				'create_field_types'  => array(),
				'lookup'              => false,
			),
			$features
		);
		return $definition;
	}

	private function __construct() {}
}
