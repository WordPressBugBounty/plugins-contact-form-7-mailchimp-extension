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
	'slug'            => 'klaviyo',
	'label'           => 'Klaviyo',
	'description'     => __( 'Private API key - Lists and profiles', 'contact-form-7-mailchimp-extension' ),
	'base_url'        => 'https://a.klaviyo.com/api',
	'headers'         => array(
		'Accept'        => 'application/vnd.api+json',
		'Content-Type'  => 'application/vnd.api+json',
		'User-Agent'    => '{ua}',
		'Authorization' => 'Klaviyo-API-Key {key}',
		'revision'      => '2026-04-15',
	),
	'validation_path' => '/lists?page[size]=1',
	'lists_path'      => '/lists?page[size]=10&fields[list]=name,opt_in_process',
	'fields_path'     => '',
	'pagination'      => array(
		'type'   => 'next_link',
		'host'   => 'a.klaviyo.com',
		'path'   => '/api/lists',
		'rebase' => '/lists?{query}',
	),
	'lists'           => array(
		'root'              => 'data',
		'id'                => 'id',
		'sanitize_id'       => true,
		'name'              => 'attributes.name',
		'extra'             => array( 'opt_in_process' => 'attributes.opt_in_process' ),
		'member_count'      => '',
		'merge_field_count' => 6,
	),
	'fields'          => array(
		'static' => array(
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
		),
	),
	'features'        => array(
		'basic_fields'        => array(
			'email' => 'EMAIL',
			'first' => 'first_name',
			'last'  => 'last_name',
		),
		'multi_group_routing' => true,
	),
	'entitlements'    => array(
		'routing' => 'destination_routing',

	),
	'submission'      => array( 'list_required' => true ),
	'vocabulary'      => array(
		'destination_singular' => __( 'List', 'contact-form-7-mailchimp-extension' ),
		'destination_plural'   => __( 'Lists', 'contact-form-7-mailchimp-extension' ),
		'person_singular'      => __( 'Profile', 'contact-form-7-mailchimp-extension' ),
		'person_plural'        => __( 'Profiles', 'contact-form-7-mailchimp-extension' ),
		'data_singular'        => __( 'Profile property', 'contact-form-7-mailchimp-extension' ),
		'data_plural'          => __( 'Profile properties', 'contact-form-7-mailchimp-extension' ),
	),
	'auth_fields'     => array(
		array(
			'id'           => 'api_key',
			'label'        => __( 'Klaviyo private API key', 'contact-form-7-mailchimp-extension' ),
			'placeholder'  => __( 'Enter your Klaviyo private API key', 'contact-form-7-mailchimp-extension' ),
			'description'  => __( 'Your credential is encrypted before saving and is never displayed again.', 'contact-form-7-mailchimp-extension' ),
			'type'         => 'password',
			'autocomplete' => 'new-password',
		),
	),
	'consent'         => array(
		'mode'        => 'destination',
		'docs_url'    => 'https://developers.klaviyo.com/en/reference/bulk_subscribe_profiles',
		'description' => __( 'The selected Klaviyo list controls whether confirmation is required.', 'contact-form-7-mailchimp-extension' ),
	),
	'operations'      => \Cmatic\Lite\Provider\Operations\KlaviyoOperations::class,
);
