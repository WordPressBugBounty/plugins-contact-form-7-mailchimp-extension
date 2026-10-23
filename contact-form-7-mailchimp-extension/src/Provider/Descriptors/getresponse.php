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
	'slug'            => 'getresponse',
	'label'           => 'GetResponse',
	'description'     => __( 'API key - Campaigns and contact fields', 'contact-form-7-mailchimp-extension' ),
	'base_url'        => 'https://api.getresponse.com/v3',
	'headers'         => array(
		'Accept'       => 'application/json',
		'Content-Type' => 'application/json',
		'User-Agent'   => '{ua}',
		'X-Auth-Token' => 'api-key {key}',
	),
	'response_format' => array(
		'total_pages_header'  => 'TotalPages',
		'current_page_header' => 'CurrentPage',
	),
	'validation_path' => '/accounts',
	'lists_path'      => '/campaigns',
	'fields_path'     => '/custom-fields',
	'pagination'      => array( 'type' => 'none' ),
	'protocol'        => array(
		'lists'     => array(
			'path'   => '/campaigns?page={page}&perPage=100',
			'paging' => 'headers',
			'id'     => 'campaignId',
			'limit'  => 100,
		),
		'fields'    => array(
			'path'   => '/custom-fields?page={page}&perPage=100',
			'paging' => 'headers',
			'id'     => 'customFieldId',
			'limit'  => 100,
		),
		'subscribe' => array(
			'path'        => '/contacts',
			'email'       => 'email',
			'list'        => 'campaign.campaignId',
			'name'        => 'name',
			'fields'      => 'customFieldValues',
			'format'      => 'arrays',
			'field_key'   => 'customFieldId',
			'field_value' => 'value',
			'codes'       => array( 202 ),
			'queued'      => true,
			'duplicates'  => array( array( 'status' => 409 ) ),
		),
	),
	'lists'           => array(
		'root' => 'items',
		'id'   => 'campaignId',
		'name' => 'name',
	),
	'fields'          => array(
		'root'         => 'items',
		'email_label'  => __( 'Contact email address', 'contact-form-7-mailchimp-extension' ),
		'name_label'   => __( 'Contact name', 'contact-form-7-mailchimp-extension' ),
		'tag'          => array( 'customFieldId' ),
		'name'         => array( 'name', 'customFieldId' ),
		'type_keys'    => array( 'valueType', 'type' ),
		'format_keys'  => array( 'format', 'fieldType' ),
		'formats'      => array( 'checkbox', 'multi_select', 'radio', 'single_select' ),
		'type_default' => 'string',
		'type_map'     => array(
			'string'        => 'text',
			'number'        => 'number',
			'date'          => 'date',
			'datetime'      => 'date',
			'checkbox'      => 'multiple-choice',
			'multi_select'  => 'multiple-choice',
			'radio'         => 'dropdown',
			'single_select' => 'dropdown',
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
		'destination_singular' => __( 'Campaign', 'contact-form-7-mailchimp-extension' ),
		'destination_plural'   => __( 'Campaigns', 'contact-form-7-mailchimp-extension' ),
		'person_singular'      => __( 'Contact', 'contact-form-7-mailchimp-extension' ),
		'person_plural'        => __( 'Contacts', 'contact-form-7-mailchimp-extension' ),
		'data_singular'        => __( 'Contact field', 'contact-form-7-mailchimp-extension' ),
		'data_plural'          => __( 'Contact fields', 'contact-form-7-mailchimp-extension' ),
	),
	'auth_fields'     => array(
		array(
			'id'           => 'api_key',
			'label'        => __( 'GetResponse API key', 'contact-form-7-mailchimp-extension' ),
			'placeholder'  => __( 'Enter your GetResponse API key', 'contact-form-7-mailchimp-extension' ),
			'description'  => __( 'Your credential is encrypted before saving and is never displayed again.', 'contact-form-7-mailchimp-extension' ),
			'type'         => 'password',
			'autocomplete' => 'new-password',
		),
	),
	'consent'         => array(
		'mode'        => 'destination',
		'docs_url'    => 'https://apidocs.getresponse.com/v3/case-study/adding-contacts',
		'description' => __( 'Each selected GetResponse campaign controls its own confirmation. Accepted contacts are queued; campaigns with double opt-in show them after confirmation.', 'contact-form-7-mailchimp-extension' ),
	),
	'operations'      => \Cmatic\Lite\Provider\Operations\GenericOperations::class,
);
