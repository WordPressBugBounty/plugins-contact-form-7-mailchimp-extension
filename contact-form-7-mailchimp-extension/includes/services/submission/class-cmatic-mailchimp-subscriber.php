<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

class Cmatic_Mailchimp_Subscriber {

	public static function subscribe( string $api_key, string $list_id, string $email, string $status, array $merge_vars, int $form_id, Cmatic_File_Logger $logger ): void {
		Cmatic_Lite_Esp_Registry::get( 'mailchimp' )->subscribe( $api_key, $list_id, $email, $status, $merge_vars, $form_id, array(), $logger );
	}
}
