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
	'slug'            => 'mailchimp',
	'label'           => 'Mailchimp',
	'description'     => __( 'Sign in or use an API key - Audiences', 'contact-form-7-mailchimp-extension' ),
	'probe_url'       => 'https://login.mailchimp.com/',
	'base_url'        => 'https://{dc}.api.mailchimp.com/3.0',
	'headers'         => array(
		'Content-Type'  => 'application/json',
		'Authorization' => 'apikey {key_part}',
		'User-Agent'    => '{ua}; WordPress/{wp}',
	),
	'validation_path' => '/',
	'lists_path'      => '/lists?count=999',
	'fields_path'     => '/lists/{list_id}/merge-fields?count=50',
	'pagination'      => array( 'type' => 'none' ),
	'lists'           => array( 'raw' => true ),
	'fields'          => array( 'raw' => true ),
	'features'        => array(
		'basic_fields'        => array(
			'email' => 'EMAIL',
			'first' => 'FNAME',
			'last'  => 'LNAME',
		),
		'double_optin'        => true,
		'oauth'               => true,
		'multi_group_routing' => true,
	),
	'entitlements'    => array( 'routing' => 'destination_routing' ),
	'submission'      => array( 'warn_invalid_email' => true ),
	'vocabulary'      => array(
		'destination_singular' => __( 'Audience', 'contact-form-7-mailchimp-extension' ),
		'destination_plural'   => __( 'Audiences', 'contact-form-7-mailchimp-extension' ),
		'person_singular'      => __( 'Contact', 'contact-form-7-mailchimp-extension' ),
		'person_plural'        => __( 'Contacts', 'contact-form-7-mailchimp-extension' ),
		'data_singular'        => __( 'Merge field', 'contact-form-7-mailchimp-extension' ),
		'data_plural'          => __( 'Merge fields', 'contact-form-7-mailchimp-extension' ),
	),
	'auth_fields'     => array(
		array(
			'id'           => 'api_key',
			'label'        => __( 'Mailchimp API key', 'contact-form-7-mailchimp-extension' ),
			'placeholder'  => __( 'Paste your Mailchimp API key', 'contact-form-7-mailchimp-extension' ),
			'description'  => __( 'Create a key in Mailchimp under Account, Extras, API keys. It is stored in your WordPress database and never displayed again.', 'contact-form-7-mailchimp-extension' ),
			'type'         => 'password',
			'autocomplete' => 'new-password',
		),
	),
	'consent'         => array(
		'mode'        => 'mailchimp',
		'docs_url'    => 'https://mailchimp.com/help/about-double-opt-in',
		'description' => __( 'Choose which submissions reach Mailchimp and whether a confirmation email goes out first.', 'contact-form-7-mailchimp-extension' ),
	),
	'operations'      => \Cmatic\Lite\Provider\Operations\MailchimpOperations::class,
);
