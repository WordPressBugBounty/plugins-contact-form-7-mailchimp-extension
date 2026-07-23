<?php
/**
 * ChimpMatic Lite aggregate Signls adapter.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

final class Cmatic_Lite_Signls_Adapter implements \Signls\Sdk\V1\ProductAdapterInterface {

	private const PRODUCT = 'contact-form-7-mailchimp-extension';

	public function product_slug(): string {
		return self::PRODUCT;
	}

	public function product_version(): string {
		return defined( 'SPARTAN_MCE_VERSION' ) ? (string) SPARTAN_MCE_VERSION : 'unknown';
	}

	public function signal_sharing_enabled(): bool {
		return 'enabled' === Cmatic_Options_Repository::get_option( 'signls.consent_status', 'unset' );
	}

	public function install_id(): string {
		$install = new Cmatic_Install_Data( Cmatic_Options_Repository::instance() );
		return (string) $install->get_install_id();
	}

	public function contract(): array {
		return Cmatic_Lite_Signls_Contract::get();
	}

	public function snapshot(): array {
		if ( ! $this->signal_sharing_enabled() ) {
			return array();
		}

		$forms    = new Cmatic_Lite_Signls_Forms_Collector();
		$snapshot = new Cmatic_Lite_Signls_Snapshot(
			new Cmatic_Lite_Signls_Site_Collector(),
			new Cmatic_Lite_Signls_Inventory_Collector(),
			new Cmatic_Lite_Signls_Performance_Collector(),
			new Cmatic_Lite_Signls_Product_Collector( $forms ),
			$forms
		);
		return $snapshot->collect();
	}
}
