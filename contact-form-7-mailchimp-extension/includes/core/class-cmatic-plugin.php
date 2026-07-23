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

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing -- Preserve the established documentation style in this legacy bootstrap class.
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
		require_once $this->dir . 'includes/interfaces/interface-cmatic-options.php';
		require_once $this->dir . 'includes/interfaces/interface-cmatic-logger.php';
		require_once $this->dir . 'includes/interfaces/interface-cmatic-api-client.php';
		require_once $this->dir . 'includes/interfaces/interface-cmatic-lite-esp-provider.php';
		require_once $this->dir . 'includes/interfaces/interface-cmatic-lite-esp-field-creator.php';
		require_once $this->dir . 'includes/interfaces/interface-cmatic-lite-esp-lookup.php';

		require_once $this->dir . 'includes/core/class-cmatic-container.php';

		require_once $this->dir . 'includes/services/class-cmatic-options-repository.php';
		require_once $this->dir . 'includes/services/class-cmatic-pro-status.php';
		require_once $this->dir . 'includes/services/class-cmatic-redirect.php';
		require_once $this->dir . 'includes/core/class-cmatic-install-data.php';
		require_once $this->dir . 'includes/core/class-cmatic-migration.php';
		require_once $this->dir . 'includes/core/class-cmatic-activator.php';
		require_once $this->dir . 'includes/core/class-cmatic-deactivator.php';
		require_once $this->dir . 'includes/services/class-cmatic-cf7-dependency.php';
		require_once $this->dir . 'includes/services/class-cmatic-pro-syncer.php';
		require_once $this->dir . 'includes/services/class-cmatic-api-key-importer.php';

		require_once $this->dir . 'includes/auth/class-cmatic-lite-credentials.php';
		require_once $this->dir . 'includes/auth/class-cmatic-lite-auth-manager.php';
	}

	private function register_lifecycle_hooks(): void {
		Cmatic_Activator::register_hooks( $this->file, $this->version );
		register_deactivation_hook( $this->file, array( 'Cmatic_Lite_Auth_Manager', 'restore_all_api_keys' ) );
	}

	private function load_module_dependencies(): void {
		$modules = array(
			'utils/class-cmatic-utils.php',
			'utils/class-cmatic-lite-get-fields.php',
			'utils/class-cmatic-pursuit.php',
			'utils/class-cmatic-file-logger.php',
			'utils/class-cmatic-remote-fetcher.php',
			'utils/class-cmatic-buster.php',
			'services/class-cmatic-cf7-tags.php',
			'services/class-cmatic-cron.php',
			'services/class-cmatic-api-service.php',
			'services/class-cmatic-form-tags.php',
			'providers/class-cmatic-lite-esp-capabilities.php',
			'providers/abstract-class-cmatic-lite-esp-provider.php',
			'providers/class-cmatic-lite-esp-mailchimp.php',
			'providers/class-cmatic-lite-esp-brevo.php',
			'providers/class-cmatic-lite-esp-mailerlite.php',
			'providers/class-cmatic-lite-esp-klaviyo.php',
			'providers/class-cmatic-lite-esp-credentials.php',
			'providers/class-cmatic-lite-esp-registry.php',
			'providers/class-cmatic-lite-esp-manifest.php',
			'services/submission/class-cmatic-email-extractor.php',
			'services/submission/class-cmatic-mailerlite-routing-resolver.php',
			'services/submission/class-cmatic-mailerlite-runtime-policy.php',
			'services/submission/class-cmatic-consent-decision.php',
			'services/submission/class-cmatic-request-ip.php',
			'services/submission/class-cmatic-mailerlite-field-normalizer.php',
			'services/submission/class-cmatic-status-resolver.php',
			'services/submission/class-cmatic-merge-vars-builder.php',
			'services/submission/class-cmatic-response-handler.php',
			'services/submission/class-cmatic-mailchimp-subscriber.php',
			'services/class-cmatic-mailerlite-degradation-reporter.php',
			'services/submission/class-cmatic-mailerlite-submission-pipeline.php',
			'services/class-cmatic-submission-handler.php',
			'api/class-cmatic-rest-lists.php',
			'api/class-cmatic-rest-settings.php',
			'api/class-cmatic-rest-form.php',
			'api/class-cmatic-lite-esp-rest-controller.php',
			'api/class-cmatic-rest-reset.php',
			'admin/class-cmatic-plugin-links.php',
			'admin/class-cmatic-deactivation-survey.php',
			'admin/class-cmatic-asset-loader.php',
			'admin/class-cmatic-admin-panel.php',
			'admin/class-cmatic-lite-esp-degrade-notice.php',
			'api/class-cmatic-log-viewer.php',
			'api/class-cmatic-contact-lookup.php',
			'api/class-cmatic-rest-oauth.php',
			'api/class-cmatic-submission-feedback.php',
			'ui/class-cmatic-header.php',
			'ui/class-cmatic-api-panel.php',
			'ui/class-cmatic-lite-esp-panel.php',
			'ui/class-cmatic-audiences.php',
			'ui/class-cmatic-data-container.php',
			'ui/class-cmatic-panel-toggles.php',
			'ui/class-cmatic-tags-preview.php',
			'ui/class-cmatic-pro-showcase.php',
			'ui/class-cmatic-banners.php',
			'ui/class-cmatic-form-classes.php',
			'ui/class-cmatic-dom-classes.php',
			'ui/class-cmatic-field-mapper.php',
			'ui/class-cmatic-sidebar-panel.php',
			'ui/class-cmatic-license-banner.php',
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
		Cmatic_Lite_Container::boot();

		Cmatic_CF7_Dependency::init();
		Cmatic_License_Banner::init();
		Cmatic_Pro_Syncer::init();

		Cmatic_Log_Viewer::init( 'chimpmatic-lite', '[Chimpmatic Lite]', 'chimpmatic-lite' );

		Cmatic_Rest_Lists::init();
		Cmatic_Rest_Settings::init();
		Cmatic_Rest_Form::init();
		Cmatic_Lite_Esp_Rest_Controller::init();
		Cmatic_Rest_Reset::init();

		Cmatic_Contact_Lookup::init();
		Cmatic_Rest_OAuth::init();
		Cmatic_Submission_Feedback::init();

		Cmatic_Deactivation_Survey::init_lite();
		Cmatic_Asset_Loader::init();

		Cmatic_CF7_Tags::init();
		Cmatic_Admin_Panel::init();
		Cmatic_Lite_Esp_Degrade_Notice::init();
		Cmatic_Submission_Handler::init();
		Cmatic_Banners::init();
		Cmatic_Form_Classes::init();
		Cmatic_Dom_Classes::init();

		Cmatic_Cron::init( $this->file );
		Cmatic_Plugin_Links::init( $this->basename );
	}

	private function load_late_dependencies(): void {
		require_once $this->dir . 'includes/ui/class-cmatic-modal.php';
		require_once $this->dir . 'includes/ui/class-cmatic-test-submission-modal.php';

		require_once $this->dir . 'includes/ui/class-cmatic-notification.php';
		require_once $this->dir . 'includes/ui/class-cmatic-notification-center.php';
		require_once $this->dir . 'includes/ui/class-cmatic-admin-bar-menu.php';

		require_once $this->dir . 'includes/admin/class-cmatic-lite-signls-privacy.php';
		require_once $this->dir . 'includes/services/signls/class-cmatic-lite-signls-contract.php';
		require_once $this->dir . 'includes/services/signls/class-cmatic-lite-signls-snapshot.php';
		require_once $this->dir . 'includes/services/signls/class-cmatic-lite-signls-failure-reason.php';
		require_once $this->dir . 'includes/services/signls/class-cmatic-lite-signls-site-collector.php';
		require_once $this->dir . 'includes/services/signls/class-cmatic-lite-signls-inventory-collector.php';
		require_once $this->dir . 'includes/services/signls/class-cmatic-lite-signls-performance-collector.php';
		require_once $this->dir . 'includes/services/signls/class-cmatic-lite-signls-product-collector.php';
		require_once $this->dir . 'includes/services/signls/class-cmatic-lite-signls-forms-collector.php';
		require_once $this->dir . 'includes/signls/loader.php';
		require_once $this->dir . 'includes/services/class-cmatic-lite-service-context.php';
	}

	private function initialize_late_components(): void {
		$test_submission_modal = new Cmatic_Test_Submission_Modal();
		$test_submission_modal->init();

		Cmatic_Notification_Center::get();
		Cmatic_Admin_Bar_Menu::instance();

		Cmatic_Lite_Signls_Privacy::init();
		$signls_registered = Signls_Sdk_Loader::register(
			array(
				'product_slug'      => 'contact-form-7-mailchimp-extension',
				'main_file'         => $this->file,
				'sdk_path'          => $this->dir . 'includes/signls',
				'sdk_version'       => '1.1.0',
				'adapter_file'      => $this->dir . 'includes/services/class-cmatic-lite-signls-adapter.php',
				'adapter_class'     => 'Cmatic_Lite_Signls_Adapter',
				'consent_reader'    => array( 'Cmatic_Lite_Signls_Privacy', 'consent_status' ),
				'install_id_reader' => static function (): string {
					return ( new Cmatic_Lite_Signls_Adapter() )->install_id();
				},
			)
		);
		if ( $signls_registered ) {
			if ( did_action( 'plugins_loaded' ) ) {
				Cmatic_Lite_Signls_Privacy::sync_sdk_consent();
			} else {
				add_action( 'plugins_loaded', array( 'Cmatic_Lite_Signls_Privacy', 'sync_sdk_consent' ), PHP_INT_MAX );
			}
		}
	}
}
