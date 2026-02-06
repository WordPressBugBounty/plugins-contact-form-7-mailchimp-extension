<?php
/**
 * Asset cache busting utility.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

class Cmatic_Buster {

	private string $plugin_version;

	private bool $is_debug;

	private static ?self $instance = null;

	public function __construct( string $plugin_version = SPARTAN_MCE_VERSION, ?bool $is_debug = null ) {
		$this->plugin_version = $plugin_version;
		$this->is_debug       = $is_debug ?? ( defined( 'WP_DEBUG' ) && WP_DEBUG );
	}

	public function get_version( string $file_path ): string {
		$version_parts = array( $this->plugin_version );

		if ( file_exists( $file_path ) ) {
			$version_parts[] = (string) filemtime( $file_path );
			$version_parts[] = substr( md5_file( $file_path ), 0, 8 );
		}

		if ( $this->is_debug ) {
			$version_parts[] = (string) time();
		}

		return implode( '-', $version_parts );
	}

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}
