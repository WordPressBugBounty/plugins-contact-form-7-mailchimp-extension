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
		$this->options          = Cmatic_Options_Repository::instance();
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
		if ( get_option( self::INITIALIZED_FLAG ) ) {
			$install_id = $this->options->get( 'install.id' );
			if ( ! empty( $install_id ) ) {
				return;
			}
			delete_option( self::INITIALIZED_FLAG );
		}

		$this->do_activation( false );
	}

	private function do_activation( $is_normal_activation ) {
		$this->install_data->ensure();

		$this->migration->run();

		$this->pro_status->update();

		$this->record_activation();

		if ( $is_normal_activation ) {
			$this->lifecycle_signal->send_activation();
		}

		if ( $is_normal_activation ) {
			$this->redirect->schedule();
		}

		$this->options->set( 'lifecycle.is_active', true );

		add_option( self::INITIALIZED_FLAG, true );

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

		if ( ! $thinks_active ) {
			return;
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$actually_active = is_plugin_active( SPARTAN_MCE_PLUGIN_BASENAME );

		if ( $actually_active ) {
			return;
		}

		$this->handle_missed_deactivation();
	}

	private function handle_missed_deactivation(): void {
		$this->options->set( 'lifecycle.is_active', false );

		$missed   = $this->options->get( 'lifecycle.missed_deactivations', array() );
		$missed   = is_array( $missed ) ? $missed : array();
		$missed[] = array(
			'timestamp' => time(),
			'type'      => 'self_healing',
		);
		$this->options->set( 'lifecycle.missed_deactivations', $missed );

		Cmatic_Cron::unschedule();

		$this->lifecycle_signal->send_deactivation();

		delete_option( self::INITIALIZED_FLAG );

		do_action( 'cmatic_missed_deactivation_handled' );
	}

	public static function register_hooks( string $plugin_file, string $version ): void {
		register_activation_hook(
			$plugin_file,
			function () use ( $version ) {
				$activator = new self( $version );
				$activator->activate();
			}
		);

		register_deactivation_hook(
			$plugin_file,
			function () {
				$deactivator = new Cmatic_Deactivator();
				$deactivator->deactivate();
			}
		);

		add_action(
			'admin_init',
			function () use ( $version ) {
				$activator = new self( $version );

				$activator->verify_lifecycle_state();

				$activator->ensure_initialized();
				$activator->get_redirect()->maybe_redirect();
			},
			5
		);

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
