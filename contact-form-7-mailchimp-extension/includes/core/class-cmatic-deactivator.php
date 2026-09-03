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

	public function __construct() {
		$this->options = Cmatic_Options_Repository::instance();
	}

	public function deactivate() {
		if ( class_exists( 'Cmatic_Pro_Syncer' ) ) {
			Cmatic_Pro_Syncer::deactivate();
		}

		$this->options->set( 'lifecycle.is_active', false );

		$this->record_deactivation();

		$this->flush_signls_before_suspend();

		do_action( 'cmatic_deactivated' );
	}

	private function record_deactivation() {
		$source   = $this->options->get( 'lifecycle.deactivations', array() );
		$items    = is_array( $source ) && isset( $source['items'] ) && is_array( $source['items'] ) ? $source['items'] : ( is_array( $source ) ? $source : array() );
		$reported = is_array( $source ) && isset( $source['reported_total'] ) && is_scalar( $source['reported_total'] ) ? (int) $source['reported_total'] : count( $items );
		$total    = max( count( $items ), $reported );
		$items[]  = time();
		$items    = array_values(
			array_filter(
				array_map(
					static function ( $value ): int {
						return is_scalar( $value ) ? (int) $value : 0;
					},
					$items
				)
			)
		);
		sort( $items, SORT_NUMERIC );
		++$total;
		$items = array_slice( $items, -256 );
		$this->options->set(
			'lifecycle.deactivations',
			array(
				'items'          => $items,
				'reported_total' => $total,
				'truncated'      => $total > count( $items ),
			)
		);
	}

	private function flush_signls_before_suspend(): void {
		try {
			if ( class_exists( 'Signls_Sdk_Bridge_1_1_7', false ) ) {
				Signls_Sdk_Bridge_1_1_7::deactivate( 'contact-form-7-mailchimp-extension' );
			}
		} catch ( Throwable $error ) {
			// Signals must never change the deactivation result.
			return;
		}
	}
}
