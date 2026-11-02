<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    hello@chimpmatic.com
 * @copyright 2014-2026 ChimpMatic
 * @license   GPL-3.0+
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

return array(
	'slug'            => 'mailerlite',
	'label'           => 'MailerLite',
	'description'     => __( 'API token - Groups and subscriber fields', 'contact-form-7-mailchimp-extension' ),
	'base_url'        => 'https://connect.mailerlite.com/api',
	'headers'         => array(
		'Accept'        => 'application/json',
		'Content-Type'  => 'application/json',
		'User-Agent'    => '{ua}',
		'Authorization' => 'Bearer {key}',
		'X-Version'     => '2026-07-01',
	),
	'validation_path' => '/groups?limit=1',
	'lists_path'      => '/groups?limit=1000',
	'fields_path'     => '/fields?limit=100',
	'pagination'      => array( 'type' => 'none' ),
	'lists'           => array(
		'root'              => 'data',
		'id'                => 'id',
		'name'              => 'name',
		'member_count'      => 'active_count',
		'merge_field_count' => 0,
	),
	'fields'          => array(
		'root'         => 'data',
		'email_label'  => __( 'Subscriber email address', 'contact-form-7-mailchimp-extension' ),
		'tag'          => array( 'key', 'name' ),
		'name'         => array( 'name' ),
		'type'         => 'type',
		'type_default' => 'text',
		'skip_tag_ci'  => array( 'email' ),
	),
	'features'        => array(
		'basic_fields'        => array(
			'email' => 'EMAIL',
			'first' => 'name',
			'last'  => 'last_name',
		),
		'multi_group_routing' => true,
		'status_modes'        => array( 'account', 'active', 'unconfirmed' ),
		'consent_metadata'    => true,
		'create_field_types'  => array( 'text', 'number', 'date' ),
		'lookup'              => true,
	),
	'entitlements'    => array(
		'routing'          => 'mailerlite_routing',
		'status'           => 'mailerlite_status',
		'resubscribe'      => 'mailerlite_resubscribe',
		'consent_metadata' => 'mailerlite_consent_metadata',
		'create_field'     => 'mailerlite_create_field',
	),
	'submission'      => array( 'typed_fields' => true ),
	'vocabulary'      => array(
		'destination_singular' => __( 'Group', 'contact-form-7-mailchimp-extension' ),
		'destination_plural'   => __( 'Groups', 'contact-form-7-mailchimp-extension' ),
		'person_singular'      => __( 'Subscriber', 'contact-form-7-mailchimp-extension' ),
		'person_plural'        => __( 'Subscribers', 'contact-form-7-mailchimp-extension' ),
		'data_singular'        => __( 'Subscriber field', 'contact-form-7-mailchimp-extension' ),
		'data_plural'          => __( 'Subscriber fields', 'contact-form-7-mailchimp-extension' ),
	),
	'auth_fields'     => array(
		array(
			'id'           => 'api_key',
			'label'        => __( 'MailerLite API token', 'contact-form-7-mailchimp-extension' ),
			'placeholder'  => __( 'Enter your MailerLite API token', 'contact-form-7-mailchimp-extension' ),
			'description'  => __( 'Your credential is encrypted before saving and is never displayed again.', 'contact-form-7-mailchimp-extension' ),
			'type'         => 'password',
			'autocomplete' => 'new-password',
		),
	),
	'consent'         => array(
		'mode'        => 'account',
		'docs_url'    => 'https://www.mailerlite.com/help/how-to-use-double-opt-in-when-collecting-subscribers',
		'description' => __( 'Double opt-in for API subscribers is controlled in your MailerLite account.', 'contact-form-7-mailchimp-extension' ),
	),
	'operations'      => \Cmatic\Lite\Provider\Operations\MailerLiteOperations::class,
);
