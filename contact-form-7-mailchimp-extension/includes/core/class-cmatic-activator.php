<?php
/**
 * Plugin activation handler.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

class Cmatic_Activator {

	private const INITIALIZED_FLAG = 'cmatic_initialized';

	private $options;

	private $install_data;

	private $migration;

	private $pro_status;

	private $redirect;

	private $lifecycle_signal;

	private $version;

	public function __construct( $version ) {
		$this->version          = $version;
		$this->options          = new Cmatic_Options_Repository();
		$this->install_data     = new Cmatic_Install_Data( $this->options );
		$this->migration        = new Cmatic_Migration( $this->options, $version );
		$this->pro_status       = new Cmatic_Pro_Status( $this->options );
		$this->redirect         = new Cmatic_Redirect( $this->options );
		$this->lifecycle_signal = new Cmatic_Lifecycle_Signal();
	}

	public function activate() {
		$this->do_activation( true );
	}

	public function ensure_initialized() {
		// Fast check - auto-loaded option, near-zero cost.
		if ( get_option( self::INITIALIZED_FLAG ) ) {
			// Flag exists, but verify data is actually complete.
			$install_id = $this->options->get( 'install.id' );
			if ( ! empty( $install_id ) ) {
				return; // Data exists, truly initialized.
			}
			// Flag exists but data is missing - delete flag and continue.
			delete_option( self::INITIALIZED_FLAG );
		}

		// Run initialization (idempotent).
		$this->do_activation( false );
	}

	private function do_activation( $is_normal_activation ) {
		// 1. BULLETPROOF: Ensure install_id and quest exist FIRST.
		$this->install_data->ensure();

		// 2. Migrate legacy options (safe to run multiple times).
		$this->migration->run();

		// 3. Update Pro plugin status.
		$this->pro_status->update();

		// 4. Record activation in lifecycle.
		$this->record_activation();

		// 5. Send lifecycle signal (only on normal activation, not fallback).
		if ( $is_normal_activation ) {
			$this->lifecycle_signal->send_activation();
		}

		// 6. Schedule redirect (only on normal activation).
		if ( $is_normal_activation ) {
			$this->redirect->schedule();
		}

		// 7. Mark plugin as active (for missed deactivation detection).
		$this->options->set( 'lifecycle.is_active', true );

		// 8. Mark as initialized (auto-loaded option for fast checks).
		add_option( self::INITIALIZED_FLAG, true );

		// 9. Fire action for extensibility.
		do_action( 'cmatic_activated', $is_normal_activation );
	}

	private function record_activation() {
		$activations   = $this->options->get( 'lifecycle.activations', array() );
		$activations   = is_array( $activations ) ? $activations : array();
		$activations[] = time();

		$this->options->set( 'lifecycle.activations', $activations );
		$this->options->set( 'lifecycle.is_reactivation', count( $activations ) > 1 );
	}

	public function get_redirect() {
		return $this->redirect;
	}

	public function get_pro_status() {
		return $this->pro_status;
	}

	public static function is_initialized() {
		return (bool) get_option( self::INITIALIZED_FLAG );
	}

	public static function clear_initialized_flag() {
		return delete_option( self::INITIALIZED_FLAG );
	}

	public function verify_lifecycle_state(): void {
		$thinks_active = $this->options->get( 'lifecycle.is_active', false );

		// If we think we're inactive (or never set), nothing to check.
		if ( ! $thinks_active ) {
			return;
		}

		// Check if plugin is actually active in WordPress.
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$actually_active = is_plugin_active( SPARTAN_MCE_PLUGIN_BASENAME );

		// If state matches reality, all is well.
		if ( $actually_active ) {
			return;
		}

		// MISMATCH DETECTED: We think we're active, but WordPress says no.
		// This means deactivation hook was missed (update, FTP delete, etc).
		$this->handle_missed_deactivation();
	}

	private function handle_missed_deactivation(): void {
		// 1. Update state flag FIRST.
		$this->options->set( 'lifecycle.is_active', false );

		// 2. Record missed deactivation (for analytics).
		$missed   = $this->options->get( 'lifecycle.missed_deactivations', array() );
		$missed   = is_array( $missed ) ? $missed : array();
		$missed[] = array(
			'timestamp' => time(),
			'type'      => 'self_healing',
		);
		$this->options->set( 'lifecycle.missed_deactivations', $missed );

		// 3. Clear cron (idempotent).
		Cmatic_Cron::unschedule();

		// 4. Send telemetry signal (if class exists).
		$this->lifecycle_signal->send_deactivation();

		// 5. Clear initialized flag so next activation runs fully.
		delete_option( self::INITIALIZED_FLAG );

		// 6. Fire action for extensibility.
		do_action( 'cmatic_missed_deactivation_handled' );
	}

	public static function register_hooks( string $plugin_file, string $version ): void {
		// Activation hook.
		register_activation_hook(
			$plugin_file,
			function () use ( $version ) {
				$activator = new self( $version );
				$activator->activate();
			}
		);

		// Deactivation hook.
		register_deactivation_hook(
			$plugin_file,
			function () {
				$deactivator = new Cmatic_Deactivator();
				$deactivator->deactivate();
			}
		);

		// CRITICAL FALLBACK: Catch missed activations AND deactivations on admin_init.
		add_action(
			'admin_init',
			function () use ( $version ) {
				$activator = new self( $version );

				// Check for missed deactivation FIRST (before initialization).
				$activator->verify_lifecycle_state();

				// Then ensure initialized (existing fallback).
				$activator->ensure_initialized();
				$activator->get_redirect()->maybe_redirect();
			},
			5
		);

		// Update Pro status on plugins_loaded (late priority).
		add_action(
			'plugins_loaded',
			function () use ( $version ) {
				$activator = new self( $version );
				$activator->get_pro_status()->update();
			},
			99
		);
	}
}
