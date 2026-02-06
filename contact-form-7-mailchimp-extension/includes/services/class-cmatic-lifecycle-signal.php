<?php
/**
 * Lifecycle telemetry signals.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

class Cmatic_Lifecycle_Signal {

	public function send_activation() {
		if ( class_exists( 'Cmatic\Metrics\Core\Sync' ) ) {
			\Cmatic\Metrics\Core\Sync::send_lifecycle_signal( 'activation' );
		}
	}

	public function send_deactivation() {
		if ( class_exists( 'Cmatic\Metrics\Core\Sync' ) ) {
			\Cmatic\Metrics\Core\Sync::send_lifecycle_signal( 'deactivation' );
		}
	}
}
