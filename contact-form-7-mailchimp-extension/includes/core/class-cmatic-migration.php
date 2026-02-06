<?php
/**
 * Database migration handler.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

class Cmatic_Migration {

	private const LEGACY_MCE_OPTIONS = array(
		'mce_loyalty',
		'mce_install_id',
		'mce_sent',
		'mce_show_update_news',
		'mce_show_notice',
		'mce_conten_panel_master',
		'mce_conten_tittle_master',
	);

	/**
	 * Legacy chimpmatic/cmatic options to migrate and clean up.
	 *
	 * @var array
	 */
	private const LEGACY_CMATIC_OPTIONS = array(
		'chimpmatic-update',
		'cmatic_log_on',
		'cmatic_do_activation_redirect',
		'cmatic_news_retry_count',
		'csyncr_last_weekly_run',
	);

	private $options;

	private $version;

	public function __construct( Cmatic_Options_Repository $options, $version ) {
		$this->options = $options;
		$this->version = $version;
	}

	public function run() {
		$data = $this->options->get_all();

		$data['version'] = $this->version;

		$this->migrate_install( $data );
		$this->migrate_stats( $data );
		$this->migrate_ui( $data );
		$this->migrate_cmatic_options( $data );
		$this->migrate_api_data( $data );

		$data['migrated'] = true;

		$this->options->save( $data );
		$this->cleanup_legacy_options();
	}

	public function is_migrated() {
		return (bool) $this->options->get( 'migrated', false );
	}

	private function migrate_install( &$data ) {
		if ( ! isset( $data['install'] ) ) {
			$data['install'] = array();
		}

		if ( ! isset( $data['install']['plugin_slug'] ) ) {
			$data['install']['plugin_slug'] = 'contact-form-7-mailchimp-extension';
		}

		if ( ! isset( $data['install']['activated_at'] ) && ! empty( $data['install']['quest'] ) ) {
			$data['install']['activated_at'] = gmdate( 'Y-m-d H:i:s', (int) $data['install']['quest'] );
		}
	}

	private function migrate_stats( &$data ) {
		if ( ! isset( $data['stats'] ) ) {
			$data['stats'] = array();
		}

		if ( ! isset( $data['stats']['sent'] ) ) {
			$data['stats']['sent'] = (int) $this->options->get_legacy( 'mce_sent', 0 );
		}
	}

	private function migrate_ui( &$data ) {
		if ( isset( $data['ui'] ) ) {
			return;
		}

		$panel_title   = $this->options->get_legacy( 'mce_conten_tittle_master', '' );
		$panel_content = $this->options->get_legacy( 'mce_conten_panel_master', '' );

		$data['ui'] = array(
			'news'          => (bool) $this->options->get_legacy( 'mce_show_update_news', true ),
			'notice_banner' => (bool) $this->options->get_legacy( 'mce_show_notice', true ),
			'welcome_panel' => array(
				'title'   => $panel_title ? $panel_title : 'Chimpmatic Lite is now ' . $this->version . '!',
				'content' => $panel_content ? $panel_content : '',
			),
		);
	}

	private function migrate_cmatic_options( &$data ) {
		$old_auto_update = get_option( 'chimpmatic-update' );
		if ( false !== $old_auto_update && ! isset( $data['auto_update'] ) ) {
			$data['auto_update'] = ( '1' === $old_auto_update ) ? 1 : 0;
		}

		$old_debug = get_option( 'cmatic_log_on' );
		if ( false !== $old_debug && ! isset( $data['debug'] ) ) {
			$data['debug'] = ( 'on' === $old_debug || '1' === $old_debug ) ? 1 : 0;
		}

		$old_redirect = get_option( 'cmatic_do_activation_redirect' );
		if ( false !== $old_redirect && ! isset( $data['activation_redirect'] ) ) {
			$data['activation_redirect'] = (bool) $old_redirect;
		}

		$old_news_count = get_option( 'cmatic_news_retry_count' );
		if ( false !== $old_news_count ) {
			if ( ! isset( $data['news'] ) ) {
				$data['news'] = array();
			}
			if ( ! isset( $data['news']['retry_count'] ) ) {
				$data['news']['retry_count'] = (int) $old_news_count;
			}
		}

		$old_last_run = get_option( 'csyncr_last_weekly_run' );
		if ( false !== $old_last_run ) {
			if ( ! isset( $data['telemetry'] ) ) {
				$data['telemetry'] = array();
			}
			if ( ! isset( $data['telemetry']['last_run'] ) ) {
				$data['telemetry']['last_run'] = (int) $old_last_run;
			}
		}
	}

	private function migrate_api_data( &$data ) {
		// Skip if already set.
		if ( ! empty( $data['api']['first_connected'] ) ) {
			return;
		}

		// First, try to use existing api.setup_first_success timestamp.
		$setup_first_success = isset( $data['api']['setup_first_success'] ) ? (int) $data['api']['setup_first_success'] : 0;
		if ( $setup_first_success > 1000000000 ) {
			if ( ! isset( $data['api'] ) ) {
				$data['api'] = array();
			}
			$data['api']['first_connected'] = $setup_first_success;
			return;
		}

		// Fallback: Check if any form has a successful API connection.
		global $wpdb;
		$form_options = $wpdb->get_results(
			"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'cf7_mch_%'",
			ARRAY_A
		);

		if ( empty( $form_options ) ) {
			return;
		}

		foreach ( $form_options as $row ) {
			$form_data = maybe_unserialize( $row['option_value'] );
			if ( is_array( $form_data ) && ! empty( $form_data['api-validation'] ) && 1 === (int) $form_data['api-validation'] ) {
				// Found a form with valid API - backfill first_connected with current time.
				if ( ! isset( $data['api'] ) ) {
					$data['api'] = array();
				}
				$data['api']['first_connected'] = time();
				return;
			}
		}
	}

	private function cleanup_legacy_options() {
		foreach ( self::LEGACY_MCE_OPTIONS as $option ) {
			$this->options->delete_legacy( $option );
		}

		foreach ( self::LEGACY_CMATIC_OPTIONS as $option ) {
			delete_option( $option );
		}
	}
}
