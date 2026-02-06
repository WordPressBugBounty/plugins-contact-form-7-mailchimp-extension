<?php
/**
 * Lite field restrictions and limits.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

class Cmatic_Lite_Get_Fields {

	public static function cmatic_lite_fields() {
		return array(
			'source',
			'ip_signup',
			'subscribed',
			'timestamp_signup',
			'member_rating',
			'location',
			'email_client',
			'vip',
			'language',
			'email_type',
			'consents_to_one_to_one_messaging',
		);
	}

	public static function cmatic_lite_sections() {
		return array(
			'tags',
			'interests',
			'marketing_permissions',
		);
	}

	public static function cmatic_lite_merge_fields() {
		return 6;
	}
}
