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
	'slug'            => 'moosend',
	'label'           => 'Moosend',
	'description'     => __( 'API key - Email lists and subscriber fields', 'contact-form-7-mailchimp-extension' ),
	'base_url'        => 'https://api.moosend.com/v3',
	'headers'         => array(
		'Accept'       => 'application/json',
		'Content-Type' => 'application/json',
		'User-Agent'   => '{ua}',
	),
	'validation_path' => '/lists/1/1.json',
	'lists_path'      => '/lists/1/100.json',
	'fields_path'     => '/lists/{list_id}/details.json',
	'pagination'      => array( 'type' => 'none' ),
	'protocol'        => array(
		'query_key' => 'apikey',
		'envelope'  => array(
			'code'    => 'Code',
			'success' => 0,
			'error'   => 'Error',
		),
		'lists'     => array(
			'path'    => '/lists/{page}/100.json',
			'root'    => 'Context.MailingLists',
			'id'      => 'ID',
			'name'    => 'Name',
			'paging'  => 'pages',
			'current' => 'Context.Paging.CurrentPage',
			'total'   => 'Context.Paging.TotalPageCount',
			'limit'   => 100,
		),
		'fields'    => array(
			'root' => 'Context.CustomFieldsDefinition',
			'id'   => 'ID',
			'name' => 'Name',
		),
		'subscribe' => array(
			'path'        => '/subscribers/{list_id}/subscribe.json',
			'email'       => 'Email',
			'name'        => 'Name',
			'fields'      => 'CustomFields',
			'format'      => 'pairs',
			'codes'       => array( 200 ),
			'ack_email'   => 'Context.Email',
			'status_path' => 'Context.SubscribeType',
			'status_map'  => array( 1 => 'subscribed' ),
			'defaults'    => array( 'HasExternalDoubleOptIn' => false ),
			'create_only' => true,
		),
		'lookup'    => array(
			'path'                => '/subscribers/{list_id}/view.json?Email={email}',
			'root'                => 'Context',
			'email'               => 'Email',
			'absent_path'         => 'Code',
			'absent_value'        => 300,
			'absent_error_prefix' => 'MEMBER_NOT_FOUND:',
		),
	),
	'lists'           => array(
		'root'         => 'items',
		'id'           => 'ID',
		'name'         => 'Name',
		'member_count' => 'ActiveMemberCount',
	),
	'fields'          => array(
		'root'        => 'items',
		'email_label' => __( 'Subscriber email address', 'contact-form-7-mailchimp-extension' ),
		'name_label'  => __( 'Subscriber name', 'contact-form-7-mailchimp-extension' ),
		'tag'         => array( 'Name' ),
		'name'        => array( 'Name' ),
		'type'        => 'Type',
		'type_map'    => array(
			0 => 'text',
			1 => 'number',
			2 => 'date',
			3 => 'dropdown',
			5 => 'boolean',
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
	'submission'      => array(
		'list_required' => true,
		'zero_values'   => true,
	),
	'vocabulary'      => array(
		'destination_singular' => __( 'Email list', 'contact-form-7-mailchimp-extension' ),
		'destination_plural'   => __( 'Email lists', 'contact-form-7-mailchimp-extension' ),
		'person_singular'      => __( 'Subscriber', 'contact-form-7-mailchimp-extension' ),
		'person_plural'        => __( 'Subscribers', 'contact-form-7-mailchimp-extension' ),
		'data_singular'        => __( 'Subscriber field', 'contact-form-7-mailchimp-extension' ),
		'data_plural'          => __( 'Subscriber fields', 'contact-form-7-mailchimp-extension' ),
	),
	'auth_fields'     => array(
		array(
			'id'           => 'api_key',
			'label'        => __( 'Moosend API key', 'contact-form-7-mailchimp-extension' ),
			'placeholder'  => __( 'Enter your Moosend API key', 'contact-form-7-mailchimp-extension' ),
			'description'  => __( 'Your credential is encrypted before saving and is never displayed again.', 'contact-form-7-mailchimp-extension' ),
			'type'         => 'password',
			'autocomplete' => 'new-password',
		),
	),
	'consent'         => array(
		'mode'        => 'destination',
		'docs_url'    => 'https://docs.moosend.com/api-documentation',
		'description' => __( 'Moosend adds new subscribers using the selected list. Existing contacts are left unchanged to preserve their fields and subscription preferences. External double opt-in is not asserted.', 'contact-form-7-mailchimp-extension' ),
	),
	'operations'      => \Cmatic\Lite\Provider\Operations\GenericOperations::class,
);
