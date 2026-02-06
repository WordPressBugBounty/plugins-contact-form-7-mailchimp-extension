<?php
/**
 * Plugin deactivation handler.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

class Cmatic_Deactivator {

	private $options;

	private $lifecycle_signal;

	public function __construct() {
		$this->options          = new Cmatic_Options_Repository();
		$this->lifecycle_signal = new Cmatic_Lifecycle_Signal();
	}

	public function deactivate() {
		// Mark inactive FIRST (state must update before cleanup).
		$this->options->set( 'lifecycle.is_active', false );

		$this->record_deactivation();
		$this->lifecycle_signal->send_deactivation();

		do_action( 'cmatic_deactivated' );
	}

	private function record_deactivation() {
		$deactivations   = $this->options->get( 'lifecycle.deactivations', array() );
		$deactivations   = is_array( $deactivations ) ? $deactivations : array();
		$deactivations[] = time();

		$this->options->set( 'lifecycle.deactivations', $deactivations );
	}
}
