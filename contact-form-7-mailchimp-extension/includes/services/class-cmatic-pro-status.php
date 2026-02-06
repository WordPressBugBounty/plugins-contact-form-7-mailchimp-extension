<?php
/**
 * Pro plugin status detection.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

class Cmatic_Pro_Status {

	private const PRO_PLUGIN_FILE = 'chimpmatic/chimpmatic.php';

	private $options;

	public function __construct( Cmatic_Options_Repository $options ) {
		$this->options = $options;
	}

	public function update() {
		$status = array(
			'installed'       => $this->is_installed(),
			'activated'       => $this->is_activated(),
			'version'         => $this->get_version(),
			'licensed'        => $this->is_licensed(),
			'license_expires' => $this->get_license_expiry(),
		);

		$current = $this->options->get( 'install.pro', array() );

		if ( $current !== $status ) {
			$this->options->set( 'install.pro', $status );
		}
	}

	public function is_installed() {
		return file_exists( WP_PLUGIN_DIR . '/' . self::PRO_PLUGIN_FILE );
	}

	public function is_activated() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return is_plugin_active( self::PRO_PLUGIN_FILE );
	}

	public function is_licensed() {
		if ( function_exists( 'cmatic_is_blessed' ) ) {
			return cmatic_is_blessed();
		}

		$license = $this->options->get_legacy( 'chimpmatic_license_activation', array() );
		return ! empty( $license['activated'] );
	}

	private function get_version() {
		if ( defined( 'CMATIC_VERSION' ) ) {
			return CMATIC_VERSION;
		}

		$file = WP_PLUGIN_DIR . '/' . self::PRO_PLUGIN_FILE;
		if ( ! file_exists( $file ) ) {
			return null;
		}

		$data = get_file_data( $file, array( 'Version' => 'Version' ) );
		return isset( $data['Version'] ) ? $data['Version'] : null;
	}

	private function get_license_expiry() {
		$license = $this->options->get_legacy( 'chimpmatic_license_activation', array() );

		if ( empty( $license['expires_at'] ) ) {
			return null;
		}

		return is_numeric( $license['expires_at'] )
			? (int) $license['expires_at']
			: (int) strtotime( (string) $license['expires_at'] );
	}
}
