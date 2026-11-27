<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

class Cmatic_Deactivator {

	private $options;

	public function __construct() {
		$this->options = Cmatic_Options_Repository::instance();
	}

	public function deactivate() {
		if ( class_exists( 'Cmatic_Pro_Syncer' ) ) {
			Cmatic_Pro_Syncer::deactivate();
		}

		$this->options->set( 'lifecycle.is_active', false );

		Cmatic_Activator::append_timestamp( $this->options, 'lifecycle.deactivations', time() );

		if ( class_exists( '\\Cmatic\\Lite\\Telemetry\\PanelActivity' ) ) {
			\Cmatic\Lite\Telemetry\PanelActivity::record( 'deactivated' );
		}
		$this->flush_signls_before_suspend();

		do_action( 'cmatic_deactivated' );
	}

	private function flush_signls_before_suspend(): void {
		try {
			if ( class_exists( 'Signls_Sdk_Bridge_1_1_7', false ) ) {
				Signls_Sdk_Bridge_1_1_7::deactivate( 'contact-form-7-mailchimp-extension' );
			}
		} catch ( Throwable $error ) {
			return;
		}
	}
}
