<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing -- Preserve the established documentation style in this legacy global class.
if ( ! class_exists( 'Cmatic_Asset_Loader' ) ) {

	class Cmatic_Asset_Loader {

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
					'restUrl'         => esc_url_raw( rest_url( 'chimpmatic-lite/v1/' ) ),
					'restNonce'       => wp_create_nonce( 'wp_rest' ),
					'licenseResetUrl' => esc_url_raw( rest_url( 'chimpmatic-lite/v1/settings/reset' ) ),
					'nonce'           => wp_create_nonce( 'wp_rest' ),
					'formId'          => $form_settings['form_id'],
					'mergeFields'     => $form_settings['merge_fields'],
					'loggingEnabled'  => current_user_can( 'wpcf7_manage_integration' ) && $form_settings['logging_enabled'],
					'debugUntil'      => Cmatic_Debug_Log::until(),
					'liteFieldsLimit' => $form_settings['liteFieldsLimit'],
					'lists'           => $form_settings['lists'],
					'i18n'            => self::get_i18n_strings(),
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
					'restUrl'    => esc_url_raw( rest_url( 'chimpmatic-lite/v1' ) ),
					'nonce'      => wp_create_nonce( 'wp_rest' ),
					'manifest'   => Cmatic_Lite_Esp_Manifest::all(),
					'fieldLimit' => PHP_INT_MAX,
					'i18n'       => array(
						/* translators: %s: provider person plural. */
						'sendPeopleHere'                   => __( 'Send %s here', 'contact-form-7-mailchimp-extension' ),
						'additionalDestination'            => __( 'Also send here', 'contact-form-7-mailchimp-extension' ),
						'paidFeatureHelp'                  => __( 'Our Pro version lets you use this feature.', 'contact-form-7-mailchimp-extension' ),
						'seePro'                           => __( 'See Pro', 'contact-form-7-mailchimp-extension' ),
						'administratorConnection'          => __( 'An administrator manages this connection.', 'contact-form-7-mailchimp-extension' ),
						'chooseThreeFields'                => __( 'Choose email and three fields. Clear a mapping to choose another field.', 'contact-form-7-mailchimp-extension' ),
						/* translators: 1: provider destination singular, 2: person plural. */
						'freeDestinationHelp'              => __( 'Choose the %1$s that receives %2$s from this form.', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: email provider name. */
						'connectProvider'                  => __( 'Connect %s', 'contact-form-7-mailchimp-extension' ),
						'signedInWithMailchimp'            => __( 'Signed in with Mailchimp', 'contact-form-7-mailchimp-extension' ),
						/* translators: 1: count, 2: provider noun. */
						'tilePeople'                       => __( '%1$s %2$s', 'contact-form-7-mailchimp-extension' ),
						/* translators: 1: count, 2: provider data noun. */
						'tileData'                         => __( '%1$s %2$s', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: provider person singular. */
						'addPersonTo'                      => __( 'add %s to', 'contact-form-7-mailchimp-extension' ),
						/* translators: 1: destinations noun, 2: destination count, 3: rule count, 4: data noun, 5: mapping count. */
						'providerSummary'                  => __( '%1$s: %2$d · Answer-based rules: %3$d · %4$s mapped: %5$d · Saved', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: number of contacts */
						'tileContacts'                     => __( '%s contacts', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: number of fields */
						'tileFields'                       => __( '%s fields', 'contact-form-7-mailchimp-extension' ),
						'consentFieldMailchimp'            => __( 'Consent field', 'contact-form-7-mailchimp-extension' ),
						'chooseConsentField'               => __( 'Choose a checkbox or acceptance field', 'contact-form-7-mailchimp-extension' ),
						'consentFieldMailchimpExplanation' => __( 'Ticked sends the contact to Mailchimp, unticked leaves them out. Mark the box optional in your form, or nobody can submit without ticking it.', 'contact-form-7-mailchimp-extension' ),
						'acceptanceFieldExplanation'       => __( 'Choose a Contact Form 7 checkbox or acceptance field to record affirmative consent.', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: email provider name. */
						'connected'                        => __( '%s connected', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: email provider name. */
						'notConnected'                     => __( '%s not connected', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: email provider name. */
						'inactive'                         => __( 'Reconnect %s', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: provider-specific destination name. */
						'chooseDestination'                => __( 'Choose the %s', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: provider-specific destination name. */
						'selectDestination'                => __( 'Select the %s...', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: email provider name. */
						'updateProvider'                   => __( 'Update %s connection', 'contact-form-7-mailchimp-extension' ),
						/* translators: 1: email provider name, 2: destination name, 3: provider data name. */
						'connectDescription'               => __( 'Connect %1$s to choose the %2$s and map its %3$s.', 'contact-form-7-mailchimp-extension' ),
						'updateConnection'                 => __( 'Update connection', 'contact-form-7-mailchimp-extension' ),
						'credential'                       => __( 'Credential', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: email provider name. */
						'checkingConnection'               => __( 'Checking your %s connection...', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: provider destination plural. */
						'loadingDestinations'              => __( 'Loading %s...', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: provider destination plural. */
						'refreshDestinations'              => __( 'Refresh %s', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: credential type. */
						'replaceCredential'                => __( 'Replace %s', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: email provider name. */
						'reconnectProvider'                => __( 'Reconnect %s', 'contact-form-7-mailchimp-extension' ),
						/* translators: 1: provider destination plural, 2: email provider name. */
						'loadingDestinationsFrom'          => __( 'Loading %1$s from %2$s...', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: email provider name. */
						'connectToContinue'                => __( 'Connect %s to continue.', 'contact-form-7-mailchimp-extension' ),
						/* translators: 1: selected destination, 2: email provider name, 3: destination type. */
						'onlyDestination'                  => __( '%1$s was selected automatically from your %2$s %3$s.', 'contact-form-7-mailchimp-extension' ),
						/* translators: 1: destination count, 2: destination type plural. */
						'destinationsFound'                => __( '%1$d %2$s found. Choose where new submissions from this form should go.', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: destination type singular. */
						'oneDestinationFound'              => __( '1 %s found. Choose where new submissions from this form should go.', 'contact-form-7-mailchimp-extension' ),
						/* translators: 1: email provider name, 2: provider data name. */
						'mapProviderFields'                => __( 'Map %1$s %2$s', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: provider data name. */
						'mapData'                          => __( 'Map %s', 'contact-form-7-mailchimp-extension' ),
						/* translators: 1: email provider name, 2: provider data name. */
						'mappedFields'                     => __( 'Match each %1$s %2$s to a Contact Form 7 field. Email address mapping is required.', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: provider destination plural. */
						'waitForDestinations'              => __( 'Wait while %s load.', 'contact-form-7-mailchimp-extension' ),
						/* translators: 1: provider destination type, 2: provider data name. */
						'chooseToLoadFields'               => __( 'Choose the %1$s above to see its %2$s.', 'contact-form-7-mailchimp-extension' ),
						'unsavedChanges'                   => __( 'Unsaved changes', 'contact-form-7-mailchimp-extension' ),
						'saveChanges'                      => __( 'Save changes', 'contact-form-7-mailchimp-extension' ),
						'savedJustNow'                     => __( 'Saved', 'contact-form-7-mailchimp-extension' ),
						'saveToActivate'                   => __( 'Save to activate this configuration.', 'contact-form-7-mailchimp-extension' ),
						'saveConfiguration'                => __( 'Save configuration', 'contact-form-7-mailchimp-extension' ),
						'saving'                           => __( 'Saving...', 'contact-form-7-mailchimp-extension' ),
						'discardChanges'                   => __( 'Discard unsaved changes and switch providers?', 'contact-form-7-mailchimp-extension' ),
						/* translators: 1: email provider name, 2: destination count, 3: provider destination name. */
						'connectedDestinationsReady'       => __( '%1$s connected. %2$d %3$s ready.', 'contact-form-7-mailchimp-extension' ),
						/* translators: 1: email provider name, 2: provider data name. */
						'loadingProviderFields'            => __( 'Loading %1$s %2$s...', 'contact-form-7-mailchimp-extension' ),
						/* translators: 1: email provider name, 2: destination name. */
						'onlyDestinationSelected'          => __( '%1$s connected. %2$s was selected automatically.', 'contact-form-7-mailchimp-extension' ),
						/* translators: 1: email provider name, 2: provider data name. */
						'providerFieldsReady'              => __( '%1$s %2$s are ready.', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: email provider name. */
						'disconnectConfirm'                => __( 'Disconnect %s? Check your destination and field mappings after reconnecting.', 'contact-form-7-mailchimp-extension' ),
						'readyToVerify'                    => __( 'Ready to verify.', 'contact-form-7-mailchimp-extension' ),
						/* translators: 1: email provider name, 2: provider destination name. */
						/* translators: 1: provider person name, 2: selected destination, 3: email provider name. */
						'setupOutcome'                     => __( 'New %1$s from this form will be added to %2$s in %3$s.', 'contact-form-7-mailchimp-extension' ),
						/* translators: %d: mapped field count. */
						'mappedCount'                      => __( '%d fields mapped - Saved', 'contact-form-7-mailchimp-extension' ),
						'standardConfirmation'             => __( 'Confirmation email (double opt-in)', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: provider name. */
						'confirmationEnabled'              => __( 'Confirmation email is enabled in %s.', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: provider name. */
						'confirmationImmediate'            => __( 'Subscriptions are confirmed immediately in %s.', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: provider name. */
						'confirmationManaged'              => __( 'The selected destination follows the confirmation setting in %s.', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: provider name. */
						'changeConfirmation'               => __( 'Change this in %s', 'contact-form-7-mailchimp-extension' ),
						/* translators: 1: Contact Form 7 field, 2: form field type, 3: provider field name, 4: provider field type. */
						'mappingTypeWarning'               => __( '%1$s is a field of type %2$s, but %3$s expects %4$s. Review this mapping.', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: email provider name. */
						'requestFailed'                    => __( '%s could not complete the request. Try again.', 'contact-form-7-mailchimp-extension' ),
						'doiVerified'                      => __( 'DOI settings verified.', 'contact-form-7-mailchimp-extension' ),
						'doiFailed'                        => __( 'DOI settings could not be verified.', 'contact-form-7-mailchimp-extension' ),
						'proChip'                          => __( 'PRO', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: licence activation or renewal instruction. */
						'paidControlLabel'                 => __( 'PRO: %s', 'contact-form-7-mailchimp-extension' ),
						'chooseConsentPolicy'              => __( 'Choose the consent-box sending policy above to select its form field.', 'contact-form-7-mailchimp-extension' ),
						'mainDestination'                  => __( 'Main destination', 'contact-form-7-mailchimp-extension' ),
						/* translators: 1: person singular, 2: destination names. */
						'destinationStatus'                => __( 'This form adds each %1$s to: %2$s', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: email provider name. */
						'refreshFrom'                      => __( 'Refresh from %s', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: email provider name */
						'resetFormCopy'                    => __( 'Clears destinations, mappings, consent and status saved here. Your %s connection and the form itself are kept.', 'contact-form-7-mailchimp-extension' ),
						'forThisForm'                      => __( 'For this form.', 'contact-form-7-mailchimp-extension' ),
						'refreshing'                       => __( 'Refreshing...', 'contact-form-7-mailchimp-extension' ),
						'refreshed'                        => __( 'Refreshed.', 'contact-form-7-mailchimp-extension' ),
						'resetThisForm'                    => __( 'Reset this form', 'contact-form-7-mailchimp-extension' ),
						'resetArmed'                       => __( 'Click again to reset this form', 'contact-form-7-mailchimp-extension' ),
						'resetWarning'                     => __( 'Destinations, mappings, consent and status saved for this form will be cleared. The connection is kept.', 'contact-form-7-mailchimp-extension' ),
						'resetting'                        => __( 'Resetting...', 'contact-form-7-mailchimp-extension' ),
						'resetDone'                        => __( 'Form reset. Reloading...', 'contact-form-7-mailchimp-extension' ),
						'resetFailed'                      => __( 'The form could not be reset.', 'contact-form-7-mailchimp-extension' ),
						/* translators: 1: person plural, 2: destination singular. */
						'destinationsHelp'                 => __( '%1$s from this form are added to every ticked %2$s. The first selected destination is the main destination.', 'contact-form-7-mailchimp-extension' ),
						/* translators: 1: person singular, 2: destination plural. */
						'routingHeadingFor'                => __( 'Add each %1$s to %2$s based on form answers', 'contact-form-7-mailchimp-extension' ),
						'paidTagsDescription'              => __( 'Our Pro version lets you add tags from form answers and fixed text.', 'contact-form-7-mailchimp-extension' ),
						/* translators: 1: person singular, 2: destination plural. */
						'routingDescriptionFor'            => __( 'Our Pro version lets you add each %1$s to %2$s based on form answers.', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: destination plural. */
						'destinationsForEvery'             => __( '%s for every submission', 'contact-form-7-mailchimp-extension' ),
						'whenThisField'                    => __( 'When this field', 'contact-form-7-mailchimp-extension' ),
						'isThisValue'                      => __( 'is this value', 'contact-form-7-mailchimp-extension' ),
						'addSubscriberTo'                  => __( 'add subscriber to', 'contact-form-7-mailchimp-extension' ),
						'chooseField'                      => __( 'Choose a field', 'contact-form-7-mailchimp-extension' ),
						'chooseValue'                      => __( 'Choose a value', 'contact-form-7-mailchimp-extension' ),
						'chooseGroup'                      => __( 'Choose a group', 'contact-form-7-mailchimp-extension' ),
						'removeRule'                       => __( 'Remove rule', 'contact-form-7-mailchimp-extension' ),
						/* translators: %d: routing rule number. */
						'removeRuleNumber'                 => __( 'Remove rule %d', 'contact-form-7-mailchimp-extension' ),
						'tagMappingConflict'               => __( 'This field is selected in Save tags in. Clear its normal mapping or choose a different tag field before saving.', 'contact-form-7-mailchimp-extension' ),
						'tagsMailerLiteOffer'              => __( 'Our Pro version turns form answers into MailerLite groups.', 'contact-form-7-mailchimp-extension' ),
						'tagsOffer'                        => __( 'Our Pro version adds fixed tags, tags from answers, and tag rules.', 'contact-form-7-mailchimp-extension' ),
						'tagsMailerLite'                   => __( 'MailerLite uses groups as tags. Choose fixed groups and group rules in Groups for every submission above.', 'contact-form-7-mailchimp-extension' ),
						'tagsCampaignMonitor'              => __( 'Campaign Monitor custom fields belong to each list. Create this field with the same options in every selected list to add tags there. Lists without the field still receive the subscriber.', 'contact-form-7-mailchimp-extension' ),
						'tagsMailerLiteAnswers'            => __( 'Click a choice field to add the contact to the group with that exact answer. A group is created only when no exact match exists.', 'contact-form-7-mailchimp-extension' ),
						'routingIncomplete'                => __( 'Choose a field, an answer, and a destination.', 'contact-form-7-mailchimp-extension' ),
						'routingInvalid'                   => __( 'Choose an available field, answer, and destination.', 'contact-form-7-mailchimp-extension' ),
						'routingDuplicate'                 => __( 'This rule duplicates another rule.', 'contact-form-7-mailchimp-extension' ),
						/* translators: 1: always-used group count, 2: answer-based rule count, 3: mapped subscriber-field count. */
						'mailerLiteSummary'                => __( 'Always-used groups: %1$d · Answer-based rules: %2$d · Subscriber fields mapped: %3$d · Saved', 'contact-form-7-mailchimp-extension' ),
						'fieldCreated'                     => __( 'MailerLite subscriber field created. Choose its Contact Form 7 source and save.', 'contact-form-7-mailchimp-extension' ),
						'fieldCreateUnconfirmed'           => __( 'Field creation was not confirmed. Refresh MailerLite subscriber fields before retrying.', 'contact-form-7-mailchimp-extension' ),
						'fieldRefreshRequired'             => __( 'Subscriber field created; refresh MailerLite subscriber fields before mapping it.', 'contact-form-7-mailchimp-extension' ),
						'creating'                         => __( 'Creating...', 'contact-form-7-mailchimp-extension' ),
						'createField'                      => __( 'Create field', 'contact-form-7-mailchimp-extension' ),
						'findSubscriber'                   => __( 'Find subscriber', 'contact-form-7-mailchimp-extension' ),
						'findingSubscriber'                => __( 'Finding subscriber...', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: MailerLite subscriber status. */
						'subscriberFound'                  => __( 'Subscriber found · %s', 'contact-form-7-mailchimp-extension' ),
						'noSubscriberFound'                => __( 'No subscriber found.', 'contact-form-7-mailchimp-extension' ),
						'statusUnavailable'                => __( 'status unavailable', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: comma-separated MailerLite group names. */
						'groupsLabel'                      => __( 'Groups: %s', 'contact-form-7-mailchimp-extension' ),
						'lookupFailed'                     => __( 'MailerLite could not find the subscriber. Try again.', 'contact-form-7-mailchimp-extension' ),
						/* translators: 1: provider person name, 2: email provider name. */
						'testingWarning'                   => __( 'Real submission: may create or update a %1$s in %2$s and trigger confirmation emails or automations.', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: provider credential label. */
						'credentialStored'                 => __( '%s stored securely', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: email provider name. */
						'sendToProvider'                   => __( 'Send to %s', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: email provider name. */
						'consentGateExplanation'           => __( 'Send every valid form submission to %s, or send submissions with affirmative consent.', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: email provider name. */
						'confirmationInProvider'           => __( 'Confirmation in %s', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: email provider name. */
						'confirmationExplanation'          => __( 'Choose how %s confirms each subscription.', 'contact-form-7-mailchimp-extension' ),
					),
				)
			);
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
			$lists           = array();

			if ( $form_id > 0 ) {
				$option_name = 'cf7_mch_' . $form_id;
				$cf7_mch     = get_option( $option_name, array() );

				if ( isset( $cf7_mch['merge_fields'] ) && is_array( $cf7_mch['merge_fields'] ) ) {
					$merge_fields = $cf7_mch['merge_fields'];
				}

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
				'form_id'         => $form_id,
				'merge_fields'    => $merge_fields,
				'logging_enabled' => $logging_enabled,
				'liteFieldsLimit' => CMATIC_LITE_FIELDS,
				'lists'           => $lists,
			);
		}

		private static function get_i18n_strings(): array {
			return array(
				'loading'            => __( 'Loading...', 'contact-form-7-mailchimp-extension' ),
				/* translators: %s: date the debug log switches itself off. */
				'debugOnUntil'       => __( 'On until %s, then off by itself. Keys and addresses are redacted as they are written.', 'contact-form-7-mailchimp-extension' ),
				'debugOff'           => __( 'Off. When on, every request to the provider and every sync outcome is written to a private file for 14 days.', 'contact-form-7-mailchimp-extension' ),
				'logSourceSite'      => __( 'Site errors', 'contact-form-7-mailchimp-extension' ),
				'logSourceOwn'       => __( 'Plugin log', 'contact-form-7-mailchimp-extension' ),
				'reportBuilding'     => __( 'Building...', 'contact-form-7-mailchimp-extension' ),
				'reportFailed'       => __( 'Could not build the report.', 'contact-form-7-mailchimp-extension' ),
				'reportCopied'       => __( 'Copied.', 'contact-form-7-mailchimp-extension' ),
				'reportSelectCopy'   => __( 'Selected. Press Ctrl+C or Cmd+C.', 'contact-form-7-mailchimp-extension' ),
				'error'              => __( 'An error occurred. Check the browser console for details.', 'contact-form-7-mailchimp-extension' ),
				'findContact'        => __( 'Find contact', 'contact-form-7-mailchimp-extension' ),
				'findingContact'     => __( 'Finding contact...', 'contact-form-7-mailchimp-extension' ),
				'findAnotherContact' => __( 'Find another contact', 'contact-form-7-mailchimp-extension' ),
			);
		}
	}
}
