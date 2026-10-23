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
	'slug'            => 'emailoctopus',
	'label'           => 'EmailOctopus',
	'description'     => __( 'API key - Lists and contact fields', 'contact-form-7-mailchimp-extension' ),
	'base_url'        => 'https://api.emailoctopus.com',
	'headers'         => array(
		'Accept'        => 'application/json',
		'Content-Type'  => 'application/json',
		'User-Agent'    => '{ua}',
		'Authorization' => 'Bearer {key}',
	),
	'validation_path' => '/lists?limit=1',
	'lists_path'      => '/lists?limit=100',
	'fields_path'     => '/lists/{list_id}',
	'pagination'      => array( 'type' => 'none' ),
	'protocol'        => array(
		'lists'     => array(
			'path'   => '/lists?limit=100',
			'root'   => 'data',
			'id'     => 'id',
			'paging' => 'cursor',
			'next'   => 'paging.next.starting_after',
			'limit'  => 100,
		),
		'fields'    => array(
			'root' => 'fields',
			'id'   => 'tag',
			'name' => 'label',
		),
		'subscribe' => array(
			'path'            => '/lists/{list_id}/contacts',
			'email'           => 'email_address',
			'fields'          => 'fields',
			'format'          => 'map',
			'codes'           => array( 201 ),
			'ack_email'       => 'email_address',
			'status_path'     => 'status',
			'status_map'      => array(
				'subscribed' => 'subscribed',
				'pending'    => 'pending',
			),
			'preserve_errors' => true,
			'duplicates'      => array(
				array(
					'status'     => 409,
					'error_path' => 'type',
					'value'      => 'https://emailoctopus.com/api-documentation/v2#already-exists',
				),
			),
		),
		'lookup'    => array(
			'path'          => '/lists/{list_id}/contacts/{email_hash}',
			'email'         => 'email_address',
			'absent_status' => 404,
		),
	),
	'lists'           => array(
		'root'         => 'items',
		'id'           => 'id',
		'name'         => 'name',
		'member_count' => 'counts.0.subscribed',
	),
	'fields'          => array(
		'root'        => 'items',
		'email_label' => __( 'Contact email address', 'contact-form-7-mailchimp-extension' ),
		'skip_tag_ci' => array( 'emailaddress' ),
		'tag'         => array( 'tag' ),
		'name'        => array( 'label', 'tag' ),
		'type'        => 'type',
		'type_map'    => array(
			'text'            => 'text',
			'number'          => 'number',
			'date'            => 'date',
			'choice_single'   => 'dropdown',
			'choice_multiple' => 'multiple-choice',
		),
	),
	'features'        => array(
		'basic_fields'        => array(
			'email' => 'EMAIL',
			'first' => 'FirstName',
			'last'  => 'LastName',
		),
		'multi_group_routing' => true,
	),
	'entitlements'    => array( 'routing' => 'destination_routing' ),
	'submission'      => array(
		'list_required'   => true,
		'zero_values'     => true,
		'integer_numbers' => true,
	),
	'vocabulary'      => array(
		'destination_singular' => __( 'List', 'contact-form-7-mailchimp-extension' ),
		'destination_plural'   => __( 'Lists', 'contact-form-7-mailchimp-extension' ),
		'person_singular'      => __( 'Contact', 'contact-form-7-mailchimp-extension' ),
		'person_plural'        => __( 'Contacts', 'contact-form-7-mailchimp-extension' ),
		'data_singular'        => __( 'Contact field', 'contact-form-7-mailchimp-extension' ),
		'data_plural'          => __( 'Contact fields', 'contact-form-7-mailchimp-extension' ),
	),
	'auth_fields'     => array(
		array(
			'id'           => 'api_key',
			'label'        => __( 'EmailOctopus API key', 'contact-form-7-mailchimp-extension' ),
			'placeholder'  => __( 'Enter your EmailOctopus API key', 'contact-form-7-mailchimp-extension' ),
			'description'  => __( 'Your credential is encrypted before saving and is never displayed again.', 'contact-form-7-mailchimp-extension' ),
			'type'         => 'password',
			'autocomplete' => 'new-password',
		),
	),
	'consent'         => array(
		'mode'        => 'destination',
		'docs_url'    => 'https://emailoctopus.com/api-documentation/v2',
		'description' => __( 'The selected EmailOctopus list controls confirmation. Existing contacts are not overwritten.', 'contact-form-7-mailchimp-extension' ),
	),
	'operations'      => \Cmatic\Lite\Provider\Operations\GenericOperations::class,
);
