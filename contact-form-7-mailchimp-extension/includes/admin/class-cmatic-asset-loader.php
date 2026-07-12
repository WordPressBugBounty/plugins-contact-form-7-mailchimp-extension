<?php
/**
 * Admin asset loader.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing -- Preserve the established documentation style in this legacy global class.
if ( ! class_exists( 'Cmatic_Asset_Loader' ) ) {

	class Cmatic_Asset_Loader {

		private static array $scripts = array();

		private static array $styles = array();

		public static function init(): void {
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_cf7_frontend_styles' ) );
			add_filter( 'admin_body_class', array( __CLASS__, 'add_body_class' ) );
		}

		public static function enqueue_admin_assets( ?string $hook_suffix ): void {
			if ( null === $hook_suffix || false === strpos( $hook_suffix, 'wpcf7' ) ) {
				return;
			}

			self::enqueue_styles();
			self::enqueue_lite_js();
			self::enqueue_oauth_js();

			$is_pro_installed = defined( 'CMATIC_VERSION' );
			$is_pro_blessed   = function_exists( 'cmatic_is_blessed' ) && cmatic_is_blessed();

			if ( $is_pro_installed ) {
				self::enqueue_pro_js( $is_pro_blessed );
			}
		}

		private static function enqueue_styles(): void {
			$css_file_path = SPARTAN_MCE_PLUGIN_DIR . 'assets/css/chimpmatic-lite.css';
			wp_enqueue_style(
				'chimpmatic-lite-css',
				SPARTAN_MCE_PLUGIN_URL . 'assets/css/chimpmatic-lite.css',
				array(),
				Cmatic_Buster::instance()->get_version( $css_file_path )
			);

			$modal_css_path = SPARTAN_MCE_PLUGIN_DIR . 'assets/css/chimpmatic-lite-deactivate.css';
			wp_enqueue_style(
				'cmatic-modal-css',
				SPARTAN_MCE_PLUGIN_URL . 'assets/css/chimpmatic-lite-deactivate.css',
				array(),
				Cmatic_Buster::instance()->get_version( $modal_css_path )
			);

			wp_enqueue_style( 'site-health' );

			self::$styles['chimpmatic-lite-css'] = $css_file_path;
			self::$styles['cmatic-modal-css']    = $modal_css_path;
		}

		private static function enqueue_lite_js(): void {
			$js_file_path = SPARTAN_MCE_PLUGIN_DIR . 'assets/js/chimpmatic-lite.js';
			wp_enqueue_script(
				'chimpmatic-lite-js',
				SPARTAN_MCE_PLUGIN_URL . 'assets/js/chimpmatic-lite.js',
				array(),
				Cmatic_Buster::instance()->get_version( $js_file_path ),
				true
			);

			$form_settings = self::get_form_settings();

			wp_localize_script(
				'chimpmatic-lite-js',
				'chimpmaticLite',
				array(
					'restUrl'          => esc_url_raw( rest_url( 'chimpmatic-lite/v1/' ) ),
					'restNonce'        => wp_create_nonce( 'wp_rest' ),
					'licenseResetUrl'  => esc_url_raw( rest_url( 'chimpmatic-lite/v1/settings/reset' ) ),
					'nonce'            => wp_create_nonce( 'wp_rest' ),
					'pluginUrl'        => SPARTAN_MCE_PLUGIN_URL,
					'formId'           => $form_settings['form_id'],
					'mergeFields'      => $form_settings['merge_fields'],
					'loggingEnabled'   => $form_settings['logging_enabled'],
					'totalMergeFields' => $form_settings['totalMergeFields'],
					'liteFieldsLimit'  => $form_settings['liteFieldsLimit'],
					'lists'            => $form_settings['lists'],
					'i18n'             => self::get_i18n_strings(),
				)
			);

			$provider_js_path = SPARTAN_MCE_PLUGIN_DIR . 'assets/js/cmatic-lite-esp.js';
			wp_enqueue_script(
				'cmatic-lite-esp',
				SPARTAN_MCE_PLUGIN_URL . 'assets/js/cmatic-lite-esp.js',
				array( 'chimpmatic-lite-js' ),
				Cmatic_Buster::instance()->get_version( $provider_js_path ),
				true
			);
			wp_localize_script(
				'cmatic-lite-esp',
				'chimpmaticLiteEsp',
				array(
					'restUrl'    => esc_url_raw( rest_url( 'chimpmatic-lite/v1/' ) ),
					'nonce'      => wp_create_nonce( 'wp_rest' ),
					'manifest'   => Cmatic_Lite_Esp_Manifest::all(),
					'fieldLimit' => Cmatic_Lite_Esp_Capabilities::field_limit( '', (int) $form_settings['form_id'] ),
					'i18n'       => array(
						/* translators: %s: email provider name. */
						'connectProvider'            => __( 'Connect %s', 'chimpmatic-lite' ),
						/* translators: %s: email provider name. */
						'connected'                  => __( '%s connected', 'chimpmatic-lite' ),
						/* translators: %s: email provider name. */
						'notConnected'               => __( '%s not connected', 'chimpmatic-lite' ),
						/* translators: %s: email provider name. */
						'inactive'                   => __( 'Reconnect %s', 'chimpmatic-lite' ),
						/* translators: %s: provider-specific destination name. */
						'chooseDestination'          => __( 'Choose a %s', 'chimpmatic-lite' ),
						/* translators: %s: provider-specific destination name. */
						'selectDestination'          => __( 'Select a %s...', 'chimpmatic-lite' ),
						/* translators: %s: email provider name. */
						'updateProvider'             => __( 'Update %s connection', 'chimpmatic-lite' ),
						/* translators: 1: email provider name, 2: destination name. */
						'connectDescription'         => __( 'Connect %1$s to choose a %2$s and map its fields.', 'chimpmatic-lite' ),
						'updateConnection'           => __( 'Update connection', 'chimpmatic-lite' ),
						/* translators: %s: email provider name. */
						'checkingConnection'         => __( 'Checking your %s connection...', 'chimpmatic-lite' ),
						/* translators: %s: provider destination plural. */
						'loadingDestinations'        => __( 'Loading %s...', 'chimpmatic-lite' ),
						/* translators: %s: provider destination plural. */
						'refreshDestinations'        => __( 'Refresh %s', 'chimpmatic-lite' ),
						/* translators: %s: credential type. */
						'replaceCredential'          => __( 'Replace %s', 'chimpmatic-lite' ),
						/* translators: %s: email provider name. */
						'reconnectProvider'          => __( 'Reconnect %s', 'chimpmatic-lite' ),
						/* translators: 1: provider destination plural, 2: email provider name. */
						'loadingDestinationsFrom'    => __( 'Loading %1$s from %2$s...', 'chimpmatic-lite' ),
						/* translators: %s: email provider name. */
						'connectToContinue'          => __( 'Connect %s to continue.', 'chimpmatic-lite' ),
						/* translators: 1: selected destination, 2: email provider name, 3: destination type. */
						'onlyDestination'            => __( '%1$s was selected because it is your only %2$s %3$s.', 'chimpmatic-lite' ),
						/* translators: 1: destination count, 2: destination type plural. */
						'destinationsFound'          => __( '%1$d %2$s found. Choose where new submissions from this form should go.', 'chimpmatic-lite' ),
						/* translators: %s: destination type singular. */
						'oneDestinationFound'        => __( '1 %s found. Choose where new submissions from this form should go.', 'chimpmatic-lite' ),
						/* translators: %s: email provider name. */
						'mapProviderFields'          => __( 'Map %s fields', 'chimpmatic-lite' ),
						/* translators: %s: email provider name. */
						'mappedFields'               => __( 'Match each %s field to a Contact Form 7 field. Subscriber Email is required.', 'chimpmatic-lite' ),
						/* translators: %s: provider destination plural. */
						'waitForDestinations'        => __( 'Wait while %s load.', 'chimpmatic-lite' ),
						/* translators: %s: provider destination type. */
						'chooseToLoadFields'         => __( 'Choose a %s to load its fields.', 'chimpmatic-lite' ),
						'unsavedChanges'             => __( 'Unsaved changes', 'chimpmatic-lite' ),
						'saveChanges'                => __( 'Save changes', 'chimpmatic-lite' ),
						'savedJustNow'               => __( 'Saved', 'chimpmatic-lite' ),
						'saveToActivate'             => __( 'Save to activate this configuration.', 'chimpmatic-lite' ),
						'saveConfiguration'          => __( 'Save configuration', 'chimpmatic-lite' ),
						'saving'                     => __( 'Saving...', 'chimpmatic-lite' ),
						'discardChanges'             => __( 'Discard unsaved changes and switch providers?', 'chimpmatic-lite' ),
						/* translators: 1: email provider name, 2: destination count. */
						'connectedDestinationsReady' => __( '%1$s connected. %2$d destinations are ready.', 'chimpmatic-lite' ),
						/* translators: %s: email provider name. */
						'loadingProviderFields'      => __( 'Loading %s fields...', 'chimpmatic-lite' ),
						/* translators: 1: email provider name, 2: destination name. */
						'onlyDestinationSelected'    => __( '%1$s connected. %2$s was selected automatically.', 'chimpmatic-lite' ),
						/* translators: %s: email provider name. */
						'providerFieldsReady'        => __( '%s fields are ready.', 'chimpmatic-lite' ),
						/* translators: %s: email provider name. */
						'disconnectConfirm'          => __( 'Disconnect %s? Your field mappings will be kept.', 'chimpmatic-lite' ),
						'readyToVerify'              => __( 'Ready to verify.', 'chimpmatic-lite' ),
						'missingDestination'         => __( 'Connect a provider and choose a destination before saving.', 'chimpmatic-lite' ),
						'missingEmailMapping'        => __( 'Select a Contact Form 7 field for Subscriber Email.', 'chimpmatic-lite' ),
						/* translators: 1: selected destination, 2: email provider name. */
						'setupOutcome'               => __( 'New submissions from this form will be added to %1$s in %2$s.', 'chimpmatic-lite' ),
						/* translators: %d: mapped field count. */
						'mappedCount'                => __( '%d fields mapped - Saved', 'chimpmatic-lite' ),
						/* translators: 1: Contact Form 7 field, 2: form field type, 3: provider field name, 4: provider field type. */
						'mappingTypeWarning'         => __( '%1$s is a %2$s field, but %3$s expects %4$s. Review this mapping.', 'chimpmatic-lite' ),
						'requestFailed'              => __( 'Provider request failed.', 'chimpmatic-lite' ),
						'consentRequiresPro'         => __( 'Advanced consent controls are available with an active Chimpmatic Pro license.', 'chimpmatic-lite' ),
						'doiVerified'                => __( 'DOI settings verified.', 'chimpmatic-lite' ),
						'doiFailed'                  => __( 'DOI settings could not be verified.', 'chimpmatic-lite' ),
						'managedByMailerLite'        => __( 'Managed by MailerLite', 'chimpmatic-lite' ),
						'mailerLiteOptin'            => __( 'MailerLite uses the Double opt-in for API and integrations setting in your account.', 'chimpmatic-lite' ),
						'doubleOptin'                => __( 'Double opt-in', 'chimpmatic-lite' ),
						'singleOptin'                => __( 'Single opt-in', 'chimpmatic-lite' ),
						'optinUnavailable'           => __( 'Opt-in setting unavailable', 'chimpmatic-lite' ),
						'klaviyoOptin'               => __( 'The selected Klaviyo list controls whether confirmation is required.', 'chimpmatic-lite' ),
						'consentIncomplete'          => __( 'Complete the consent and opt-in settings before saving.', 'chimpmatic-lite' ),
					),
				)
			);
			self::$scripts['cmatic-lite-esp'] = $provider_js_path;

			self::$scripts['chimpmatic-lite-js'] = $js_file_path;
		}

		private static function enqueue_pro_js( bool $is_pro_blessed ): void {
			$pro_js_path = SPARTAN_MCE_PLUGIN_DIR . 'assets/js/chimpmatic.js';

			wp_enqueue_script(
				'chimpmatic-pro',
				SPARTAN_MCE_PLUGIN_URL . 'assets/js/chimpmatic.js',
				array(),
				Cmatic_Buster::instance()->get_version( $pro_js_path ),
				true
			);

			wp_localize_script(
				'chimpmatic-pro',
				'chmConfig',
				array(
					'restUrl'   => rest_url( 'chimpmatic/v1/' ),
					'nonce'     => wp_create_nonce( 'wp_rest' ),
					'isBlessed' => $is_pro_blessed,
				)
			);

			wp_localize_script(
				'chimpmatic-pro',
				'wpApiSettings',
				array(
					'root'  => esc_url_raw( rest_url() ),
					'nonce' => wp_create_nonce( 'wp_rest' ),
				)
			);

			self::$scripts['chimpmatic-pro'] = $pro_js_path;
		}

		private static function enqueue_oauth_js(): void {
			$oauth_js_path = SPARTAN_MCE_PLUGIN_DIR . 'assets/js/cmatic-oauth-admin.js';
			if ( ! file_exists( $oauth_js_path ) ) {
				return;
			}

			wp_enqueue_script(
				'cmatic-oauth-admin',
				SPARTAN_MCE_PLUGIN_URL . 'assets/js/cmatic-oauth-admin.js',
				array( 'chimpmatic-lite-js' ),
				Cmatic_Buster::instance()->get_version( $oauth_js_path ),
				true
			);

			$form_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only asset context; authorization is enforced by WordPress admin.

			wp_localize_script(
				'cmatic-oauth-admin',
				'chimpmaticOAuth',
				array(
					'restUrl' => esc_url_raw( rest_url( 'chimpmatic-lite/v1/' ) ),
					'nonce'   => wp_create_nonce( 'wp_rest' ),
					'formId'  => $form_id,
				)
			);

			self::$scripts['cmatic-oauth-admin'] = $oauth_js_path;
		}


		public static function enqueue_cf7_frontend_styles( ?string $hook_suffix ): void {
			if ( null === $hook_suffix || 'toplevel_page_wpcf7' !== $hook_suffix ) {
				return;
			}

			$form_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only asset context; authorization is enforced by WordPress admin.
			if ( ! $form_id ) {
				return;
			}

			$cf7_path = WP_PLUGIN_DIR . '/contact-form-7/';
			$cf7_url  = plugins_url( '/', $cf7_path . 'wp-contact-form-7.php' );

			if ( ! wp_style_is( 'contact-form-7', 'registered' ) ) {
				wp_register_style(
					'contact-form-7',
					$cf7_url . 'includes/css/styles.css',
					array(),
					defined( 'WPCF7_VERSION' ) ? WPCF7_VERSION : '5.0',
					'all'
				);
			}

			wp_enqueue_style( 'contact-form-7' );
		}

		public static function add_body_class( ?string $classes ): string {
			$classes = $classes ?? '';
			$screen  = get_current_screen();

			if ( $screen && strpos( $screen->id, 'wpcf7' ) !== false ) {
				$classes .= ' chimpmatic-lite';

				if ( function_exists( 'cmatic_is_blessed' ) && cmatic_is_blessed() ) {
					$classes .= ' chimpmatic';
				}
			}

			return $classes;
		}

		private static function get_form_settings(): array {
			$form_id         = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only asset context; authorization is enforced by WordPress admin.
			$merge_fields    = array();
			$logging_enabled = false;
			$total_merge     = 0;
			$lists           = array();

			if ( $form_id > 0 ) {
				$option_name = 'cf7_mch_' . $form_id;
				$cf7_mch     = get_option( $option_name, array() );

				if ( isset( $cf7_mch['merge_fields'] ) && is_array( $cf7_mch['merge_fields'] ) ) {
					$merge_fields = $cf7_mch['merge_fields'];
				}

				$total_merge     = isset( $cf7_mch['total_merge_fields'] ) ? (int) $cf7_mch['total_merge_fields'] : 0;
				$logging_enabled = ! empty( $cf7_mch['logfileEnabled'] );

				if ( isset( $cf7_mch['lisdata']['lists'] ) && is_array( $cf7_mch['lisdata']['lists'] ) ) {
					foreach ( $cf7_mch['lisdata']['lists'] as $list ) {
						if ( isset( $list['id'], $list['name'] ) ) {
							$lists[] = array(
								'id'           => $list['id'],
								'name'         => $list['name'],
								'member_count' => isset( $list['stats']['member_count'] ) ? (int) $list['stats']['member_count'] : 0,
								'field_count'  => isset( $list['stats']['merge_field_count'] ) ? (int) $list['stats']['merge_field_count'] : 0,
							);
						}
					}
				}
			}

			return array(
				'form_id'          => $form_id,
				'merge_fields'     => $merge_fields,
				'logging_enabled'  => $logging_enabled,
				'totalMergeFields' => $total_merge,
				'liteFieldsLimit'  => CMATIC_LITE_FIELDS,
				'lists'            => $lists,
			);
		}

		private static function get_i18n_strings(): array {
			return array(
				'loading'       => __( 'Loading...', 'chimpmatic-lite' ),
				'error'         => __( 'An error occurred. Check the browser console for details.', 'chimpmatic-lite' ),
				'apiKeyValid'   => __( 'API Connected', 'chimpmatic-lite' ),
				'apiKeyInvalid' => __( 'API Inactive', 'chimpmatic-lite' ),
			);
		}

		public static function get_registered_scripts(): array {
			return self::$scripts;
		}

		public static function get_registered_styles(): array {
			return self::$styles;
		}
	}
}
