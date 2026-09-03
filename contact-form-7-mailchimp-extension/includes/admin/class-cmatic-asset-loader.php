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
						'connectProvider'            => __( 'Connect %s', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: email provider name. */
						'connected'                  => __( '%s connected', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: email provider name. */
						'notConnected'               => __( '%s not connected', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: email provider name. */
						'inactive'                   => __( 'Reconnect %s', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: provider-specific destination name. */
						'chooseDestination'          => __( 'Choose a %s', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: provider-specific destination name. */
						'selectDestination'          => __( 'Select a %s...', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: email provider name. */
						'updateProvider'             => __( 'Update %s connection', 'contact-form-7-mailchimp-extension' ),
						/* translators: 1: email provider name, 2: destination name, 3: provider data name. */
						'connectDescription'         => __( 'Connect %1$s to choose a %2$s and map its %3$s.', 'contact-form-7-mailchimp-extension' ),
						'updateConnection'           => __( 'Update connection', 'contact-form-7-mailchimp-extension' ),
						'credential'                 => __( 'Credential', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: email provider name. */
						'checkingConnection'         => __( 'Checking your %s connection...', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: provider destination plural. */
						'loadingDestinations'        => __( 'Loading %s...', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: provider destination plural. */
						'refreshDestinations'        => __( 'Refresh %s', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: credential type. */
						'replaceCredential'          => __( 'Replace %s', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: email provider name. */
						'reconnectProvider'          => __( 'Reconnect %s', 'contact-form-7-mailchimp-extension' ),
						/* translators: 1: provider destination plural, 2: email provider name. */
						'loadingDestinationsFrom'    => __( 'Loading %1$s from %2$s...', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: email provider name. */
						'connectToContinue'          => __( 'Connect %s to continue.', 'contact-form-7-mailchimp-extension' ),
						/* translators: 1: selected destination, 2: email provider name, 3: destination type. */
						'onlyDestination'            => __( '%1$s was selected because it is your only %2$s %3$s.', 'contact-form-7-mailchimp-extension' ),
						/* translators: 1: destination count, 2: destination type plural. */
						'destinationsFound'          => __( '%1$d %2$s found. Choose where new submissions from this form should go.', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: destination type singular. */
						'oneDestinationFound'        => __( '1 %s found. Choose where new submissions from this form should go.', 'contact-form-7-mailchimp-extension' ),
						/* translators: 1: email provider name, 2: provider data name. */
						'mapProviderFields'          => __( 'Map %1$s %2$s', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: provider data name. */
						'mapData'                    => __( 'Map %s', 'contact-form-7-mailchimp-extension' ),
						/* translators: 1: email provider name, 2: provider data name. */
						'mappedFields'               => __( 'Match each %1$s %2$s to a Contact Form 7 field. Email address mapping is required.', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: provider destination plural. */
						'waitForDestinations'        => __( 'Wait while %s load.', 'contact-form-7-mailchimp-extension' ),
						/* translators: 1: provider destination type, 2: provider data name. */
						'chooseToLoadFields'         => __( 'Choose a %1$s to load its %2$s.', 'contact-form-7-mailchimp-extension' ),
						'unsavedChanges'             => __( 'Unsaved changes', 'contact-form-7-mailchimp-extension' ),
						'saveChanges'                => __( 'Save changes', 'contact-form-7-mailchimp-extension' ),
						'savedJustNow'               => __( 'Saved', 'contact-form-7-mailchimp-extension' ),
						'saveToActivate'             => __( 'Save to activate this configuration.', 'contact-form-7-mailchimp-extension' ),
						'saveConfiguration'          => __( 'Save configuration', 'contact-form-7-mailchimp-extension' ),
						'saving'                     => __( 'Saving...', 'contact-form-7-mailchimp-extension' ),
						'discardChanges'             => __( 'Discard unsaved changes and switch providers?', 'contact-form-7-mailchimp-extension' ),
						/* translators: 1: email provider name, 2: destination count, 3: provider destination name. */
						'connectedDestinationsReady' => __( '%1$s connected. %2$d %3$s ready.', 'contact-form-7-mailchimp-extension' ),
						/* translators: 1: email provider name, 2: provider data name. */
						'loadingProviderFields'      => __( 'Loading %1$s %2$s...', 'contact-form-7-mailchimp-extension' ),
						/* translators: 1: email provider name, 2: destination name. */
						'onlyDestinationSelected'    => __( '%1$s connected. %2$s was selected automatically.', 'contact-form-7-mailchimp-extension' ),
						/* translators: 1: email provider name, 2: provider data name. */
						'providerFieldsReady'        => __( '%1$s %2$s are ready.', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: email provider name. */
						'disconnectConfirm'          => __( 'Disconnect %s? Your field mappings will be kept.', 'contact-form-7-mailchimp-extension' ),
						'readyToVerify'              => __( 'Ready to verify.', 'contact-form-7-mailchimp-extension' ),
						/* translators: 1: email provider name, 2: provider destination name. */
						'missingDestination'         => __( 'Connect %1$s and choose a %2$s before saving.', 'contact-form-7-mailchimp-extension' ),
						'missingEmailMapping'        => __( 'Select a Contact Form 7 field for the required email address.', 'contact-form-7-mailchimp-extension' ),
						/* translators: 1: provider person name, 2: selected destination, 3: email provider name. */
						'setupOutcome'               => __( 'New %1$s from this form will be added to %2$s in %3$s.', 'contact-form-7-mailchimp-extension' ),
						/* translators: %d: mapped field count. */
						'mappedCount'                => __( '%d fields mapped - Saved', 'contact-form-7-mailchimp-extension' ),
						/* translators: 1: Contact Form 7 field, 2: form field type, 3: provider field name, 4: provider field type. */
						'mappingTypeWarning'         => __( '%1$s is a %2$s field, but %3$s expects %4$s. Review this mapping.', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: email provider name. */
						'requestFailed'              => __( '%s could not complete the request. Try again.', 'contact-form-7-mailchimp-extension' ),
						'consentRequiresPro'         => __( 'Advanced consent controls are available with an active Chimpmatic Pro license.', 'contact-form-7-mailchimp-extension' ),
						'doiVerified'                => __( 'DOI settings verified.', 'contact-form-7-mailchimp-extension' ),
						'doiFailed'                  => __( 'DOI settings could not be verified.', 'contact-form-7-mailchimp-extension' ),
						'managedByMailerLite'        => __( 'Managed by MailerLite', 'contact-form-7-mailchimp-extension' ),
						'mailerLiteOptin'            => __( 'MailerLite uses the Double opt-in for API and integrations setting in your account.', 'contact-form-7-mailchimp-extension' ),
						'doubleOptin'                => __( 'Double opt-in', 'contact-form-7-mailchimp-extension' ),
						'singleOptin'                => __( 'Single opt-in', 'contact-form-7-mailchimp-extension' ),
						'optinUnavailable'           => __( 'Opt-in setting unavailable', 'contact-form-7-mailchimp-extension' ),
						'klaviyoOptin'               => __( 'The selected Klaviyo list controls whether confirmation is required.', 'contact-form-7-mailchimp-extension' ),
						'consentIncomplete'          => __( 'Complete the consent and opt-in settings before saving.', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: provider group name. */
						'useGroupWithoutPro'         => __( 'Use %s when Chimpmatic Pro is inactive', 'contact-form-7-mailchimp-extension' ),
						'groupsForEverySubscriber'   => __( 'Groups for every subscriber', 'contact-form-7-mailchimp-extension' ),
						'mailerLiteGroupsHelp'       => __( 'Every subscriber successfully sent to MailerLite is added to each selected group. Mark one selected group “Use when Pro is inactive.”', 'contact-form-7-mailchimp-extension' ),
						'useWhenProInactive'         => __( 'Use when Pro is inactive', 'contact-form-7-mailchimp-extension' ),
						'routingRequiresPro'         => __( 'Additional MailerLite groups and answer-based rules require Chimpmatic Pro.', 'contact-form-7-mailchimp-extension' ),
						'routingSavedInactive'       => __( 'MailerLite group rules are saved but inactive. Subscribers are added only to the group marked “Use when Pro is inactive.” Renew Pro to restore the saved rules.', 'contact-form-7-mailchimp-extension' ),
						'contactFormField'           => __( 'Contact Form 7 field', 'contact-form-7-mailchimp-extension' ),
						'answerIs'                   => __( 'Answer is', 'contact-form-7-mailchimp-extension' ),
						'addSubscriberToGroup'       => __( 'Add subscriber to group', 'contact-form-7-mailchimp-extension' ),
						'whenThisField'              => __( 'When this field', 'contact-form-7-mailchimp-extension' ),
						'isThisValue'                => __( 'is this value', 'contact-form-7-mailchimp-extension' ),
						'when'                       => __( 'When', 'contact-form-7-mailchimp-extension' ),
						'is'                         => __( 'is', 'contact-form-7-mailchimp-extension' ),
						'addSubscriberTo'            => __( 'add subscriber to', 'contact-form-7-mailchimp-extension' ),
						'chooseField'                => __( 'Choose a field', 'contact-form-7-mailchimp-extension' ),
						'chooseValue'                => __( 'Choose a value', 'contact-form-7-mailchimp-extension' ),
						'chooseGroup'                => __( 'Choose a group', 'contact-form-7-mailchimp-extension' ),
						'removeRule'                 => __( 'Remove rule', 'contact-form-7-mailchimp-extension' ),
						/* translators: %d: routing rule number. */
						'removeRuleNumber'           => __( 'Remove rule %d', 'contact-form-7-mailchimp-extension' ),
						/* translators: %d: routing rule number. */
						'ruleNumber'                 => __( 'Rule %d', 'contact-form-7-mailchimp-extension' ),
						'routingIncomplete'          => __( 'Choose a field, an answer, and a destination group.', 'contact-form-7-mailchimp-extension' ),
						'routingInvalid'             => __( 'This rule contains an unavailable field, answer, or group.', 'contact-form-7-mailchimp-extension' ),
						'routingDuplicate'           => __( 'This rule duplicates another rule.', 'contact-form-7-mailchimp-extension' ),
						'routingFixBeforeSave'       => __( 'Complete or remove the highlighted routing rules before saving.', 'contact-form-7-mailchimp-extension' ),
						/* translators: 1: always-used group count, 2: answer-based rule count, 3: mapped subscriber-field count. */
						'mailerLiteSummary'          => __( 'Always-used groups: %1$d · Answer-based rules: %2$d · Subscriber fields mapped: %3$d · Saved', 'contact-form-7-mailchimp-extension' ),
						'proOptionsInactive'         => __( 'Saved MailerLite Pro settings are inactive. Subscribers continue with the current Active behavior; renew Pro to restore the saved settings.', 'contact-form-7-mailchimp-extension' ),
						'fieldCreationRequiresPro'   => __( 'Creating MailerLite subscriber fields requires Chimpmatic Pro.', 'contact-form-7-mailchimp-extension' ),
						'fieldCreated'               => __( 'MailerLite subscriber field created. Choose its Contact Form 7 source and save.', 'contact-form-7-mailchimp-extension' ),
						'fieldCreateUnconfirmed'     => __( 'Field creation was not confirmed. Refresh MailerLite subscriber fields before retrying.', 'contact-form-7-mailchimp-extension' ),
						'fieldRefreshRequired'       => __( 'Subscriber field created; refresh MailerLite subscriber fields before mapping it.', 'contact-form-7-mailchimp-extension' ),
						'creating'                   => __( 'Creating...', 'contact-form-7-mailchimp-extension' ),
						'createField'                => __( 'Create field', 'contact-form-7-mailchimp-extension' ),
						'findSubscriber'             => __( 'Find subscriber', 'contact-form-7-mailchimp-extension' ),
						'findingSubscriber'          => __( 'Finding subscriber...', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: MailerLite subscriber status. */
						'subscriberFound'            => __( 'Subscriber found · %s', 'contact-form-7-mailchimp-extension' ),
						'noSubscriberFound'          => __( 'No subscriber found.', 'contact-form-7-mailchimp-extension' ),
						'statusUnavailable'          => __( 'status unavailable', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: comma-separated MailerLite group names. */
						'groupsLabel'                => __( 'Groups: %s', 'contact-form-7-mailchimp-extension' ),
						'lookupFailed'               => __( 'MailerLite could not find the subscriber. Try again.', 'contact-form-7-mailchimp-extension' ),
						/* translators: 1: provider person name, 2: email provider name. */
						'testingWarning'             => __( 'Real submission: may create or update a %1$s in %2$s and trigger confirmation emails or automations.', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: provider credential label. */
						'credentialStored'           => __( '%s stored securely', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: email provider name. */
						'sendToProvider'             => __( 'Send to %s', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: email provider name. */
						'consentGateExplanation'     => __( 'Choose whether every valid form submission is sent to %s or only submissions with affirmative consent.', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: email provider name. */
						'confirmationInProvider'     => __( 'Confirmation in %s', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: email provider name. */
						'confirmationExplanation'    => __( 'Controls whether %s requires confirmation after the form is submitted.', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: email provider name. */
						'openConfirmationSettings'   => __( 'Open %s confirmation settings', 'contact-form-7-mailchimp-extension' ),
						/* translators: 1: number of provider data fields available with Pro, 2: provider data name. */
						'proDataLimit'               => __( 'Up to %1$d %2$s are available with your active Chimpmatic Pro license.', 'contact-form-7-mailchimp-extension' ),
						/* translators: 1: number of provider data fields included in Lite, 2: provider data name. */
						'liteDataLimit'              => __( 'Your Lite setup includes %1$d %2$s. Email address is always included.', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: provider data name. */
						'unlockData'                 => __( 'Unlock every available %s and advanced features with Chimpmatic Pro', 'contact-form-7-mailchimp-extension' ),
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
				'loading'            => __( 'Loading...', 'contact-form-7-mailchimp-extension' ),
				'error'              => __( 'An error occurred. Check the browser console for details.', 'contact-form-7-mailchimp-extension' ),
				'apiKeyValid'        => __( 'API Connected', 'contact-form-7-mailchimp-extension' ),
				'apiKeyInvalid'      => __( 'API Inactive', 'contact-form-7-mailchimp-extension' ),
				'findContact'        => __( 'Find contact', 'contact-form-7-mailchimp-extension' ),
				'findingContact'     => __( 'Finding contact...', 'contact-form-7-mailchimp-extension' ),
				'findAnotherContact' => __( 'Find another contact', 'contact-form-7-mailchimp-extension' ),
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
