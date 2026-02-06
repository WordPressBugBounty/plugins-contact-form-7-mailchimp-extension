<?php
/**
 * Post-activation redirect handler.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

class Cmatic_Redirect {

	private $options;

	public function __construct( Cmatic_Options_Repository $options ) {
		$this->options = $options;
	}

	public function schedule() {
		if ( ! $this->can_redirect() ) {
			return;
		}

		$this->options->set( 'activation_redirect', true );
	}

	public function maybe_redirect() {
		if ( ! $this->options->get( 'activation_redirect', false ) ) {
			return;
		}

		$this->options->set( 'activation_redirect', false );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['activate-multi'] ) ) {
			return;
		}

		$form_id = absint( Cmatic_Utils::get_newest_form_id() ?? 0 );
		$url     = admin_url( 'admin.php?page=wpcf7&post=' . $form_id . '&action=edit&active-tab=Chimpmatic' );

		wp_safe_redirect( $url );
		exit;
	}

	public function can_redirect() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['activate-multi'] ) ) {
			return false;
		}

		if ( is_network_admin() ) {
			return false;
		}

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return false;
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return false;
		}

		return true;
	}
}
