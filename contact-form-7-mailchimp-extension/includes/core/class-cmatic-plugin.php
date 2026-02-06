<?php
/**
 * Main plugin class.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

final class Cmatic_Plugin {

	private $file;

	private $version;

	private $dir;

	private $basename;

	public function __construct( string $file, string $version ) {
		$this->file     = $file;
		$this->version  = $version;
		$this->dir      = plugin_dir_path( $file );
		$this->basename = plugin_basename( $file );
	}

	public function init(): void {
		$this->load_core_dependencies();
		$this->register_lifecycle_hooks();
		$this->load_module_dependencies();
		$this->initialize_components();
		$this->load_late_dependencies();
		$this->initialize_late_components();
	}

	private function load_core_dependencies(): void {
		// Load interfaces first.
		require_once $this->dir . 'includes/interfaces/interface-cmatic-options.php';
		require_once $this->dir . 'includes/interfaces/interface-cmatic-logger.php';
		require_once $this->dir . 'includes/interfaces/interface-cmatic-api-client.php';

		// Load container.
		require_once $this->dir . 'includes/core/class-cmatic-container.php';

		// Load services.
		require_once $this->dir . 'includes/services/class-cmatic-options-repository.php';
		require_once $this->dir . 'includes/services/class-cmatic-pro-status.php';
		require_once $this->dir . 'includes/services/class-cmatic-redirect.php';
		require_once $this->dir . 'includes/services/class-cmatic-lifecycle-signal.php';
		require_once $this->dir . 'includes/core/class-cmatic-install-data.php';
		require_once $this->dir . 'includes/core/class-cmatic-migration.php';
		require_once $this->dir . 'includes/core/class-cmatic-activator.php';
		require_once $this->dir . 'includes/core/class-cmatic-deactivator.php';
		require_once $this->dir . 'includes/services/class-cmatic-cf7-dependency.php';
		require_once $this->dir . 'includes/services/class-cmatic-pro-syncer.php';
		require_once $this->dir . 'includes/services/class-cmatic-api-key-importer.php';
	}

	private function register_lifecycle_hooks(): void {
		Cmatic_Activator::register_hooks( $this->file, $this->version );
	}

	private function load_module_dependencies(): void {
		$modules = array(
			// Utils.
			'utils/class-cmatic-utils.php',
			'utils/class-cmatic-lite-get-fields.php',
			'utils/class-cmatic-pursuit.php',
			'utils/class-cmatic-file-logger.php',
			'utils/class-cmatic-remote-fetcher.php',
			'utils/class-cmatic-buster.php',
			// Services.
			'services/class-cmatic-cf7-tags.php',
			'services/class-cmatic-cron.php',
			'services/class-cmatic-api-service.php',
			'services/class-cmatic-form-tags.php',
			// Submission handling (load before handler).
			'services/submission/class-cmatic-email-extractor.php',
			'services/submission/class-cmatic-status-resolver.php',
			'services/submission/class-cmatic-merge-vars-builder.php',
			'services/submission/class-cmatic-response-handler.php',
			'services/submission/class-cmatic-mailchimp-subscriber.php',
			'services/class-cmatic-submission-handler.php',
			// REST API Controllers.
			'api/class-cmatic-rest-lists.php',
			'api/class-cmatic-rest-settings.php',
			'api/class-cmatic-rest-form.php',
			'api/class-cmatic-rest-reset.php',
			// Admin.
			'admin/class-cmatic-plugin-links.php',
			'admin/class-cmatic-deactivation-survey.php',
			'admin/class-cmatic-asset-loader.php',
			'admin/class-cmatic-admin-panel.php',
			// API.
			'api/class-cmatic-log-viewer.php',
			'api/class-cmatic-contact-lookup.php',
			'api/class-cmatic-submission-feedback.php',
			// UI.
			'ui/class-cmatic-header.php',
			'ui/class-cmatic-api-panel.php',
			'ui/class-cmatic-audiences.php',
			'ui/class-cmatic-data-container.php',
			'ui/class-cmatic-panel-toggles.php',
			'ui/class-cmatic-tags-preview.php',
			'ui/class-cmatic-banners.php',
			'ui/class-cmatic-form-classes.php',
			'ui/class-cmatic-field-mapper.php',
			'ui/class-cmatic-sidebar-panel.php',
			'ui/class-cmatic-advanced-settings.php',
		);

		foreach ( $modules as $module ) {
			$path = $this->dir . 'includes/' . $module;
			if ( file_exists( $path ) ) {
				require_once $path;
			}
		}
	}

	private function initialize_components(): void {
		// Boot service container.
		Cmatic_Lite_Container::boot();

		// Core services (no dependencies).
		Cmatic_CF7_Dependency::init();
		Cmatic_Pro_Syncer::init();

		// Logging.
		Cmatic_Log_Viewer::init( 'chimpmatic-lite', '[ChimpMatic Lite]', 'chimpmatic-lite' );

		// REST API Controllers.
		Cmatic_Rest_Lists::init();
		Cmatic_Rest_Settings::init();
		Cmatic_Rest_Form::init();
		Cmatic_Rest_Reset::init();

		// API Services.
		Cmatic_Contact_Lookup::init();
		Cmatic_Submission_Feedback::init();

		// Admin UI.
		Cmatic_Deactivation_Survey::init_lite();
		Cmatic_Asset_Loader::init();

		// CF7 Integration.
		Cmatic_CF7_Tags::init();
		Cmatic_Admin_Panel::init();
		Cmatic_Submission_Handler::init();
		Cmatic_Banners::init();
		Cmatic_Form_Classes::init();

		// Background Tasks.
		Cmatic_Cron::init( $this->file );
		Cmatic_Plugin_Links::init( $this->basename );
	}

	private function load_late_dependencies(): void {
		// UI Components (Modals).
		require_once $this->dir . 'includes/ui/class-cmatic-modal.php';
		require_once $this->dir . 'includes/ui/class-cmatic-test-submission-modal.php';

		// Admin Bar Notification System.
		require_once $this->dir . 'includes/ui/class-cmatic-notification.php';
		require_once $this->dir . 'includes/ui/class-cmatic-notification-center.php';
		require_once $this->dir . 'includes/ui/class-cmatic-admin-bar-menu.php';

		// Signals (Telemetry System) - has its own PSR-4 autoloader.
		require_once $this->dir . 'includes/signals/autoload.php';
	}

	private function initialize_late_components(): void {
		// Test Submission Modal.
		$test_submission_modal = new Cmatic_Test_Submission_Modal();
		$test_submission_modal->init();

		// Admin Bar.
		Cmatic_Notification_Center::get();
		Cmatic_Admin_Bar_Menu::instance();

		// Signals (Telemetry).
		Cmatic\Metrics\Bootstrap::init(
			array(
				'plugin_basename' => $this->basename,
				'endpoint_url'    => 'https://ping.chimpmatic.com/wp-json/chimpmatic/v1/telemetry',
			)
		);
	}
}
