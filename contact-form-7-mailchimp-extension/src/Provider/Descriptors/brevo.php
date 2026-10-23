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
	'slug'            => 'brevo',
	'label'           => 'Brevo',
	'description'     => __( 'API key - Lists and contact attributes', 'contact-form-7-mailchimp-extension' ),
	'base_url'        => 'https://api.brevo.com/v3',
	'headers'         => array(
		'Accept'       => 'application/json',
		'Content-Type' => 'application/json',
		'User-Agent'   => '{ua}',
		'api-key'      => '{key}',
	),
	'validation_path' => '/account',
	'lists_path'      => '/contacts/lists?limit=50&offset=0',
	'fields_path'     => '/contacts/attributes',
	'pagination'      => array(
		'type'      => 'offset',
		'items_key' => 'lists',
		'count_key' => 'count',
		'path'      => '/contacts/lists?limit=50&offset={offset}',
	),
	'lists'           => array(
		'root'              => 'lists',
		'id'                => 'id',
		'name'              => 'name',
		'member_count'      => 'totalSubscribers',
		'merge_field_count' => 0,
	),
	'fields'          => array(
		'root'           => 'attributes',
		'email_label'    => __( 'Contact email address', 'contact-form-7-mailchimp-extension' ),
		'tag'            => array( 'name' ),
		'name'           => array( 'name' ),
		'type'           => 'type',
		'type_default'   => 'text',
		'filter'         => array( 'category' => 'normal' ),
		'skip_falsy_tag' => true,
	),
	'features'        => array(
		'basic_fields'        => array(
			'email' => 'EMAIL',
			'first' => 'FIRSTNAME',
			'last'  => 'LASTNAME',
		),
		'multi_group_routing' => true,
	),
	'entitlements'    => array(
		'routing' => 'destination_routing',

	),
	'submission'      => array(
		'doi_verification' => true,
		'list_required'    => true,
	),
	'vocabulary'      => array(
		'destination_singular' => __( 'List', 'contact-form-7-mailchimp-extension' ),
		'destination_plural'   => __( 'Lists', 'contact-form-7-mailchimp-extension' ),
		'person_singular'      => __( 'Contact', 'contact-form-7-mailchimp-extension' ),
		'person_plural'        => __( 'Contacts', 'contact-form-7-mailchimp-extension' ),
		'data_singular'        => __( 'Contact attribute', 'contact-form-7-mailchimp-extension' ),
		'data_plural'          => __( 'Contact attributes', 'contact-form-7-mailchimp-extension' ),
	),
	'auth_fields'     => array(
		array(
			'id'           => 'api_key',
			'label'        => __( 'Brevo API key', 'contact-form-7-mailchimp-extension' ),
			'placeholder'  => __( 'Enter your Brevo API key', 'contact-form-7-mailchimp-extension' ),
			'description'  => __( 'Your credential is encrypted before saving and is never displayed again.', 'contact-form-7-mailchimp-extension' ),
			'type'         => 'password',
			'autocomplete' => 'new-password',
		),
	),
	'consent'         => array(
		'mode'        => 'request',
		'docs_url'    => 'https://developers.brevo.com/reference/create-doi-contact',
		'description' => __( 'Choose single opt-in or send Brevo’s confirmation email.', 'contact-form-7-mailchimp-extension' ),
	),
	'operations'      => \Cmatic\Lite\Provider\Operations\BrevoOperations::class,
);
