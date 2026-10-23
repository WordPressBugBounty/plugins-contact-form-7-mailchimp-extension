<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

final class Cmatic_Request_Ip {
	public static function get(): string {
		$remote = isset( $_SERVER['REMOTE_ADDR'] ) && is_string( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '';
		$ip     = apply_filters( 'cmatic_lite_client_ip', $remote, $remote );
		return is_string( $ip ) && filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	private function __construct() {}
}
