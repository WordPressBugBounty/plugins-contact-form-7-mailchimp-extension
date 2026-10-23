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
	'slug'            => 'campaignmonitor',
	'label'           => 'Campaign Monitor',
	'description'     => __( 'API key - Client lists and subscriber fields', 'contact-form-7-mailchimp-extension' ),
	'base_url'        => 'https://api.createsend.com/api/v3.3',
	'headers'         => array(
		'Accept'        => 'application/json',
		'Content-Type'  => 'application/json',
		'User-Agent'    => '{ua}',
		'Authorization' => 'Basic {basic_key}',
	),
	'response_format' => array(
		'string_key' => 'EmailAddress',
		'error_key'  => 'Message',
	),
	'validation_path' => '/clients.json',
	'lists_path'      => '/clients.json',
	'fields_path'     => '/lists/{list_id}/customfields.json',
	'pagination'      => array( 'type' => 'none' ),
	'protocol'        => array(
		'lists'     => array(
			'id'          => 'ClientID',
			'name'        => 'Name',
			'limit'       => 100,
			'deduplicate' => true,
			'children'    => array(
				'path'        => '/clients/{parent_id}/lists.json',
				'id'          => 'ListID',
				'name'        => 'Name',
				'deduplicate' => true,
			),
		),
		'fields'    => array(),
		'subscribe' => array(
			'path'        => '/subscribers/{list_id}.json',
			'email'       => 'EmailAddress',
			'name'        => 'Name',
			'fields'      => 'CustomFields',
			'format'      => 'repeat',
			'field_key'   => 'Key',
			'field_value' => 'Value',
			'defaults'    => array(
				'ConsentToTrack'                         => 'No',
				'Resubscribe'                            => false,
				'RestartSubscriptionBasedAutoresponders' => false,
			),
			'ack_email'   => 'EmailAddress',
		),
	),
	'lists'           => array(
		'root'        => 'lists',
		'id'          => 'ListID',
		'name'        => 'Name',
		'sanitize_id' => true,
	),
	'fields'          => array(
		'root'         => 'fields',
		'email_label'  => __( 'Subscriber email address', 'contact-form-7-mailchimp-extension' ),
		'name_label'   => __( 'Subscriber name', 'contact-form-7-mailchimp-extension' ),
		'tag'          => array( 'Key' ),
		'name'         => array( 'FieldName', 'Key' ),
		'type'         => 'DataType',
		'type_default' => 'Text',
		'type_map'     => array(
			'Text'            => 'text',
			'Number'          => 'number',
			'Date'            => 'date',
			'MultiSelectOne'  => 'dropdown',
			'MultiSelectMany' => 'multiple-choice',
			'Country'         => 'text',
			'USState'         => 'text',
		),
	),
	'features'        => array(
		'basic_fields'        => array(
			'email' => 'EMAIL',
			'name'  => 'NAME',
		),
		'multi_group_routing' => true,
	),
	'entitlements'    => array( 'routing' => 'destination_routing' ),
	'submission'      => array( 'list_required' => true ),
	'vocabulary'      => array(
		'destination_singular' => __( 'List', 'contact-form-7-mailchimp-extension' ),
		'destination_plural'   => __( 'Lists', 'contact-form-7-mailchimp-extension' ),
		'person_singular'      => __( 'Subscriber', 'contact-form-7-mailchimp-extension' ),
		'person_plural'        => __( 'Subscribers', 'contact-form-7-mailchimp-extension' ),
		'data_singular'        => __( 'Subscriber field', 'contact-form-7-mailchimp-extension' ),
		'data_plural'          => __( 'Subscriber fields', 'contact-form-7-mailchimp-extension' ),
	),
	'auth_fields'     => array(
		array(
			'id'           => 'api_key',
			'label'        => __( 'Campaign Monitor API key', 'contact-form-7-mailchimp-extension' ),
			'placeholder'  => __( 'Enter your Campaign Monitor API key', 'contact-form-7-mailchimp-extension' ),
			'description'  => __( 'Your credential is encrypted before saving and is never displayed again.', 'contact-form-7-mailchimp-extension' ),
			'type'         => 'password',
			'autocomplete' => 'new-password',
		),
	),
	'consent'         => array(
		'mode'        => 'destination',
		'docs_url'    => 'https://www.campaignmonitor.com/api/v3-3/subscribers',
		'description' => __( 'The selected Campaign Monitor list controls confirmation. Email open and click tracking is disabled for contacts submitted by this form.', 'contact-form-7-mailchimp-extension' ),
	),
	'operations'      => \Cmatic\Lite\Provider\Operations\GenericOperations::class,
);
