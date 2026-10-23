<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    hello@chimpmatic.com
 * @copyright 2014-2026 ChimpMatic
 * @license   GPL-3.0+
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

use Cmatic\Lite\Settings\FormSettings;
use Cmatic\Lite\Settings\FormSettingsRepository;
use Cmatic\Lite\Settings\MappingSchema;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing -- Typed signatures follow the existing Lite provider convention.
final class Cmatic_Lite_Esp_Panel {
	/**
	 * @param array<array-key, mixed> $config
	 */
	public static function get_api_status( string $slug, array $config, int $form_id ): string {
		if ( 'mailchimp' === $slug ) {
			$has_credentials = ! empty( $config['api'] )
				|| ( isset( $config['auth_type'] ) && 'oauth' === $config['auth_type'] );
			if ( 1 === \Cmatic\Lite\Settings\MappingSchema::int( $config['api-validation'] ?? 0 ) ) {
				return 'connected';
			}
			return $has_credentials ? 'disconnected' : 'fresh';
		}

		$settings        = ( new FormSettings( $form_id, $config ) )->section( $slug );
		$credential      = Cmatic_Lite_Esp_Credentials::get( $form_id, $slug );
		$has_credentials = '' !== $credential;
		unset( $credential );

		if ( $has_credentials && 1 === MappingSchema::int( $settings['api-validation'] ?? 0 ) ) {
			return 'connected';
		}
		return $has_credentials ? 'disconnected' : 'fresh';
	}

	/**
	 * @param array<array-key, mixed> $config
	 * @param array<array-key, mixed> $form_tags
	 */
	public static function render( string $slug, array $config, array $form_tags, int $form_id ): void {
		$license_view   = array(
			'state'   => 'none',
			'tooltip' => __( 'Available with our Pro version', 'contact-form-7-mailchimp-extension' ),
			'message' => '',
		);
		$paid_keys      = \Cmatic\Lite\Admin\PanelExtras::paid_keys();
		$manifest       = Cmatic_Lite_Esp_Manifest::all();
		$state          = self::get_public_state( $slug, $config, $form_id, $form_tags );
		$initial_slug   = Cmatic_Lite_Esp_Registry::has( $slug ) ? $slug : 'brevo';
		$field_limit    = PHP_INT_MAX;
		$definition     = $manifest[ $initial_slug ];
		$provider_state = $state['providers'][ $initial_slug ];
		$is_hidden      = '' === $slug;
		$destination    = strtolower( \Cmatic\Lite\Settings\MappingSchema::str( $definition['destination_singular'] ) );
		$destinations   = strtolower( \Cmatic\Lite\Settings\MappingSchema::str( $definition['destination_plural'] ) );
		$data_plural    = strtolower( \Cmatic\Lite\Settings\MappingSchema::str( $definition['data_plural'] ) );
		$person         = strtolower( \Cmatic\Lite\Settings\MappingSchema::str( $definition['person_singular'] ) );
		/* translators: %s: provider destination type, such as list or group. */
		$progress_destination = sprintf( __( 'Choose %s', 'contact-form-7-mailchimp-extension' ), $destination );
		/* translators: %s: provider data name, such as contact attributes or subscriber fields. */
		$progress_mappings = sprintf( __( 'Map %s', 'contact-form-7-mailchimp-extension' ), $data_plural );
		/* translators: %s: provider destination plural, such as lists or groups. */
		$refresh_destinations = sprintf( __( 'Refresh %s', 'contact-form-7-mailchimp-extension' ), $destinations );
		/* translators: %s: email provider name. */
		$connect_to_continue = sprintf( __( 'Connect %s to continue.', 'contact-form-7-mailchimp-extension' ), \Cmatic\Lite\Settings\MappingSchema::str( $definition['label'] ) );
		/* translators: 1: email provider name, 2: provider data name. */
		$mapping_description = sprintf( __( 'Match each %1$s %2$s to a Contact Form 7 field. Email address mapping is required.', 'contact-form-7-mailchimp-extension' ), \Cmatic\Lite\Settings\MappingSchema::str( $definition['label'] ), $data_plural );
		/* translators: 1: provider destination type, 2: provider data name. */
		$mapping_locked = sprintf( __( 'Choose the %1$s above to see its %2$s.', 'contact-form-7-mailchimp-extension' ), $destination, $data_plural );
		/* translators: %s: email provider name. */
		$consent_gate_title = sprintf( __( 'Send to %s', 'contact-form-7-mailchimp-extension' ), \Cmatic\Lite\Settings\MappingSchema::str( $definition['label'] ) );
		/* translators: %s: email provider name. */
		$consent_gate_explanation = sprintf( __( 'Send every valid form submission to %s, or send submissions with affirmative consent.', 'contact-form-7-mailchimp-extension' ), \Cmatic\Lite\Settings\MappingSchema::str( $definition['label'] ) );
		/* translators: %s: email provider name. */
		$provider_optin_title = __( 'Confirmation email (double opt-in)', 'contact-form-7-mailchimp-extension' );
		/* translators: %s: email provider name. */
		$provider_optin_explanation = sprintf( __( 'Choose how %s confirms each subscription.', 'contact-form-7-mailchimp-extension' ), \Cmatic\Lite\Settings\MappingSchema::str( $definition['label'] ) );
		/* translators: 1: provider person name, 2: email provider name. */
		$testing_notice = sprintf( __( 'Real submission: may create or update a %1$s in %2$s and trigger confirmation emails or automations.', 'contact-form-7-mailchimp-extension' ), $person, \Cmatic\Lite\Settings\MappingSchema::str( $definition['label'] ) );
		wp_localize_script( 'cmatic-lite-esp', 'chimpmaticLiteEspState', $state );
		?>
		<section
			id="cmatic-provider-onboarding"
			class="cmatic-provider-onboarding"
			aria-labelledby="cmatic-provider-onboarding-title"
			<?php echo '' === $slug ? '' : 'hidden'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Constant HTML attribute. ?>
		>
			<span class="cmatic-provider-onboarding__kicker"><?php esc_html_e( 'Get started', 'contact-form-7-mailchimp-extension' ); ?></span>
			<h2 id="cmatic-provider-onboarding-title"><?php esc_html_e( 'Choose your email provider', 'contact-form-7-mailchimp-extension' ); ?></h2>
			<p><?php esc_html_e( 'Select one provider to begin the guided setup. You can switch providers later.', 'contact-form-7-mailchimp-extension' ); ?></p>
			<div class="cmatic-provider-choices">
				<?php foreach ( $manifest as $provider_slug => $provider_definition ) : ?>
					<?php $description = self::provider_description( (string) $provider_slug ); ?>
					<button type="button" class="cmatic-provider-choice" data-provider-choice="<?php echo esc_attr( (string) $provider_slug ); ?>">
						<img src="<?php echo esc_url( SPARTAN_MCE_PLUGIN_URL . 'assets/images/providers/' . $provider_slug . '.svg' ); ?>" alt="" aria-hidden="true">
						<strong><?php echo esc_html( \Cmatic\Lite\Settings\MappingSchema::str( $provider_definition['label'] ) ); ?></strong>
						<span><?php echo esc_html( $description ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>
		</section>
		<div
			id="cmatic-provider-data"
			class="<?php echo esc_attr( 'cmatic-provider-panel cmatic-lite-preview' ); ?>"
			data-cmatic-provider-view="generic"
			data-cmatic-owner="lite"
			data-manage-integration="<?php echo current_user_can( 'wpcf7_manage_integration' ) ? '1' : '0'; ?>"
			data-license-active="0"
			data-license-state="<?php echo esc_attr( $license_view['state'] ); ?>"
			data-pro-url="https://chimpmatic.com/pro"
			data-paid-hint="<?php echo esc_attr( $license_view['tooltip'] ); ?>"
			data-free-fields="<?php echo esc_attr( (string) CMATIC_LITE_FIELDS ); ?>"
			data-paid-keys="<?php echo esc_attr( (string) wp_json_encode( $paid_keys ) ); ?>"
			data-form-id="<?php echo esc_attr( (string) $form_id ); ?>"
			data-provider="<?php echo esc_attr( $slug ); ?>"
				data-field-limit="<?php echo esc_attr( (string) $field_limit ); ?>"
			<?php echo $is_hidden ? 'hidden' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Constant HTML attribute. ?>
		>

			<div id="cmatic-provider-completion" class="cmatic-provider-completion" role="status" aria-live="polite" hidden>
				<span class="cmatic-provider-completion__icon" aria-hidden="true">&#10003;</span>
				<div>
					<strong><?php esc_html_e( 'Setup complete', 'contact-form-7-mailchimp-extension' ); ?></strong>
					<p id="cmatic-provider-completion-outcome"></p>
					<span id="cmatic-provider-completion-meta"></span>
				</div>
			</div>
			<ol id="cmatic-provider-progress" class="cmatic-provider-progress" aria-label="<?php esc_attr_e( 'Setup progress', 'contact-form-7-mailchimp-extension' ); ?>">
				<li><span>1</span><b><?php esc_html_e( 'Connect', 'contact-form-7-mailchimp-extension' ); ?></b></li>
				<li><span>2</span><b id="cmatic-provider-progress-destination"><?php echo esc_html( $progress_destination ); ?></b></li>
				<li><span>3</span><b id="cmatic-provider-progress-mappings"><?php echo esc_html( $progress_mappings ); ?></b></li>
			</ol>
			<p id="cmatic-provider-message" class="cmatic-provider-message" role="status" aria-live="polite"></p>
			<p id="cmatic-provider-tag-note" class="cmatic-provider-message" role="status" hidden></p>

			<section class="cmatic-provider-section" data-cmatic-stage="credentials">
				<div id="cmatic-provider-credentials-intro">
					<h3 id="cmatic-provider-credentials-heading">
						<?php
						printf(
							/* translators: %s: email provider name */
							esc_html__( 'Connect %s', 'contact-form-7-mailchimp-extension' ),
							esc_html( \Cmatic\Lite\Settings\MappingSchema::str( $definition['label'] ) )
						);
						?>
					</h3>
					<p id="cmatic-provider-credentials-description" class="description"></p>
				</div>
				<div class="cmatic-provider-auth-row" id="cmatic-provider-auth-row">
					<div id="cmatic-provider-auth-fields">
						<?php self::render_auth_fields( $definition ); ?>
					</div>
					<div class="cmatic-provider-actions">
						<button type="button" class="button button-primary" id="cmatic-provider-connect" disabled>
							<?php esc_html_e( 'Connect', 'contact-form-7-mailchimp-extension' ); ?>
						</button>
						<button type="button" class="button" id="cmatic-provider-cancel-credential" hidden><?php esc_html_e( 'Cancel', 'contact-form-7-mailchimp-extension' ); ?></button>
					</div>
					<div id="cmatic-provider-oauth" class="cmatic-provider-oauth" hidden>
						<span class="cmatic-provider-oauth__or"><?php esc_html_e( 'or', 'contact-form-7-mailchimp-extension' ); ?></span>
						<button type="button" class="button cmatic-oauth-connect" data-form-id="<?php echo esc_attr( (string) $form_id ); ?>">
							<span data-cmatic-oauth-action-label><?php esc_html_e( 'Sign in with Mailchimp', 'contact-form-7-mailchimp-extension' ); ?></span>
							<span class="cmatic-oauth-status" role="status" aria-live="polite" hidden></span>
						</button>
					</div>
				</div>
				<div id="cmatic-provider-connected-summary" class="cmatic-provider-connection-summary" hidden>
					<span class="cmatic-provider-connection-summary__icon" aria-hidden="true">&#10003;</span>
					<div class="cmatic-provider-connection-summary__text">
						<strong id="cmatic-provider-connected-title"></strong>
						<span><span id="cmatic-provider-credential-storage"><?php esc_html_e( 'Credential stored securely', 'contact-form-7-mailchimp-extension' ); ?></span> &middot; <span id="cmatic-provider-checked-status"><?php esc_html_e( 'Checked just now', 'contact-form-7-mailchimp-extension' ); ?></span></span>
					</div>
					<div class="cmatic-provider-actions">
						<button type="button" class="button" id="cmatic-provider-refresh"><?php echo esc_html( $refresh_destinations ); ?></button>
						<button type="button" class="button" id="cmatic-provider-replace-credential"><?php esc_html_e( 'Replace credential', 'contact-form-7-mailchimp-extension' ); ?></button>
						<button type="button" class="button button-link-delete" id="cmatic-provider-disconnect"><?php esc_html_e( 'Disconnect', 'contact-form-7-mailchimp-extension' ); ?></button>
					</div>
				</div>
				<div id="cmatic-provider-recovery-summary" class="cmatic-provider-recovery-summary" hidden>
					<div><strong id="cmatic-provider-recovery-title"></strong><p><?php esc_html_e( 'The saved credential could not be verified. Try again or enter a new one.', 'contact-form-7-mailchimp-extension' ); ?></p></div>
					<div class="cmatic-provider-actions">
						<button type="button" class="button button-primary" id="cmatic-provider-retry"><?php esc_html_e( 'Try again', 'contact-form-7-mailchimp-extension' ); ?></button>
						<button type="button" class="button" id="cmatic-provider-recovery-replace"><?php esc_html_e( 'Replace credential', 'contact-form-7-mailchimp-extension' ); ?></button>
						<button type="button" class="button button-link-delete" id="cmatic-provider-recovery-disconnect"><?php esc_html_e( 'Disconnect', 'contact-form-7-mailchimp-extension' ); ?></button>
					</div>
				</div>
			</section>

			<section class="cmatic-provider-section" data-cmatic-stage="destination">
				<h3 id="cmatic-provider-destination-heading">
					<?php
					printf(
						/* translators: %s: provider destination type, such as list or group. */
						esc_html__( 'Choose the %s', 'contact-form-7-mailchimp-extension' ),
						esc_html( $destination )
					);
					?>
				</h3>
				<p id="cmatic-provider-destination-description" class="description"></p>
				<div id="cmatic-provider-destination-locked" class="cmatic-provider-locked"><p><?php echo esc_html( $connect_to_continue ); ?></p></div>
				<div class="cmatic-provider-destination-row" id="cmatic-provider-destination-row" hidden>
					<label class="screen-reader-text" for="cmatic-provider-list" id="cmatic-provider-destination-label"></label>
					<select id="cmatic-provider-list" name="wpcf7-cmatic-provider[list]">
						<?php self::render_destination_options( $definition, $provider_state ); ?>
					</select>
				</div>
				<div id="cmatic-provider-mailerlite-groups" class="cmatic-provider-mapping-grid" aria-label="<?php esc_attr_e( 'Destinations for every submission', 'contact-form-7-mailchimp-extension' ); ?>" hidden></div>
				<div id="cmatic-mailerlite-routing" hidden>
					<div aria-labelledby="cmatic-routing-heading">
						<h4 id="cmatic-routing-heading"><?php esc_html_e( 'Add subscribers to groups based on form answers', 'contact-form-7-mailchimp-extension' ); ?></h4>
						<p id="cmatic-routing-description" class="description"><?php esc_html_e( 'Optional. Subscribers can match more than one rule. Groups selected above are always added.', 'contact-form-7-mailchimp-extension' ); ?></p>
						<div id="cmatic-routing-rule-list" class="cmatic-routing-table-list"></div>
						<div class="cmatic-provider-actions cmatic-provider-actions--end"><button type="button" class="button" id="cmatic-routing-add-rule"><?php esc_html_e( 'Add rule', 'contact-form-7-mailchimp-extension' ); ?></button></div>
						<p id="cmatic-routing-notice" class="cmatic-defaults-fields-notice" hidden></p>
					</div>
				</div>
			</section>

			<section class="cmatic-provider-section" data-cmatic-stage="mappings">
				<h3 id="cmatic-provider-mappings-heading">
					<?php
					printf(
						/* translators: 1: provider name, 2: provider data name. */
						esc_html__( 'Map %1$s %2$s', 'contact-form-7-mailchimp-extension' ),
						esc_html( \Cmatic\Lite\Settings\MappingSchema::str( $definition['label'] ) ),
						esc_html( $data_plural )
					);
					?>
				</h3>
				<p class="description" id="cmatic-provider-mapping-description">
					<?php echo esc_html( $mapping_description ); ?>
				</p>
				<div id="cmatic-provider-mappings-locked" class="cmatic-provider-locked"><p><?php echo esc_html( $mapping_locked ); ?></p></div>
				<div class="cmatic-provider-mapping-grid" id="cmatic-provider-mappings" <?php echo self::mappings_are_visible( $provider_state ) ? '' : 'hidden'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Constant HTML attribute. ?>>
					<?php self::render_mapping_rows( $provider_state, $form_tags, $field_limit ); ?>
					<div id="cmatic-provider-mailerlite-create-field" class="cmatic-provider-mapping-row" hidden>
						<div class="cmatic-provider-auth-field"><label for="cmatic-provider-mailerlite-field-name"><?php esc_html_e( 'Create MailerLite subscriber field', 'contact-form-7-mailchimp-extension' ); ?></label><input type="text" id="cmatic-provider-mailerlite-field-name" maxlength="255"></div>
						<span id="cmatic-provider-mailerlite-field-type-label"><?php esc_html_e( 'Field type', 'contact-form-7-mailchimp-extension' ); ?></span>
						<div id="cmatic-provider-mailerlite-field-type" class="cmatic-provider-actions" role="radiogroup" aria-labelledby="cmatic-provider-mailerlite-field-type-label">
							<span><label><input type="radio" name="cmatic-provider-mailerlite-field-type" value="text" checked> <?php esc_html_e( 'Text', 'contact-form-7-mailchimp-extension' ); ?></label></span>
							<span><label><input type="radio" name="cmatic-provider-mailerlite-field-type" value="number"> <?php esc_html_e( 'Number', 'contact-form-7-mailchimp-extension' ); ?></label></span>
							<span><label><input type="radio" name="cmatic-provider-mailerlite-field-type" value="date"> <?php esc_html_e( 'Date', 'contact-form-7-mailchimp-extension' ); ?></label></span>
						</div>
						<div class="cmatic-provider-actions cmatic-provider-actions--end"><button type="button" class="button" id="cmatic-provider-mailerlite-field-create"><?php esc_html_e( 'Create field', 'contact-form-7-mailchimp-extension' ); ?></button></div>
						<p id="cmatic-provider-mailerlite-field-notice" class="cmatic-defaults-fields-notice" hidden></p>
					</div>
				</div>

				<button type="button" class="button" id="cmatic-klaviyo-add-property" hidden><?php esc_html_e( 'Add a profile property', 'contact-form-7-mailchimp-extension' ); ?></button>
				<div id="cmatic-provider-extra-details" class="cmatic-provider-details">
					<p id="cmatic-free-mapping-note" class="description" hidden><?php esc_html_e( 'Map every field with our Pro version.', 'contact-form-7-mailchimp-extension' ); ?></p>
					<?php \Cmatic\Lite\Admin\PanelExtras::cards( $slug, $config, $form_tags, $form_id ); ?>
					<?php do_action( 'cmatic_provider_panel_cards', $slug, $config, $form_tags, $form_id ); ?>
				</div>
			</section>
			<section id="cmatic-provider-consent" class="cmatic-provider-section" aria-labelledby="cmatic-provider-consent-heading">
				<h3 id="cmatic-provider-consent-heading"><?php esc_html_e( 'Subscription and consent', 'contact-form-7-mailchimp-extension' ); ?></h3>
				<p id="cmatic-provider-consent-description" class="description"></p>
				<div class="cmatic-consent-policy-list">
					<div id="cmatic-provider-consent-controls" class="cmatic-consent-policy-provider">
						<div class="cmatic-consent-policy-row">
							<label class="cmatic-consent-policy-name" for="cmatic-provider-consent-gate"><strong id="cmatic-provider-consent-gate-title"><?php echo esc_html( $consent_gate_title ); ?></strong><span><?php esc_html_e( 'Form submission rule', 'contact-form-7-mailchimp-extension' ); ?></span></label>
							<div class="cmatic-consent-policy-control">
								<select id="cmatic-provider-consent-gate" name="wpcf7-cmatic-provider[consent_gate]">
									<option value="none"><?php esc_html_e( 'Every valid form submission', 'contact-form-7-mailchimp-extension' ); ?></option>
									<option value="required"><?php esc_html_e( 'When a consent box is ticked', 'contact-form-7-mailchimp-extension' ); ?></option>
								</select>
							</div>
							<p id="cmatic-provider-consent-gate-explanation" class="cmatic-consent-policy-explanation"><?php echo esc_html( $consent_gate_explanation ); ?></p>
						</div>
						<div id="cmatic-provider-consent-field-row" class="cmatic-consent-policy-row" hidden>
							<label class="cmatic-consent-policy-name" for="cmatic-provider-consent-field"><strong><?php esc_html_e( 'Acceptance field', 'contact-form-7-mailchimp-extension' ); ?></strong><span><?php esc_html_e( 'Contact Form 7', 'contact-form-7-mailchimp-extension' ); ?></span></label>
							<div class="cmatic-consent-policy-control">
								<select id="cmatic-provider-consent-field" name="wpcf7-cmatic-provider[consent_field]">
									<option value=""><?php esc_html_e( 'Choose a checkbox or acceptance field', 'contact-form-7-mailchimp-extension' ); ?></option>
									<?php foreach ( $form_tags as $tag ) : ?>
										<?php if ( is_array( $tag ) && in_array( self::scalar_string( $tag['basetype'] ?? '' ), array( 'acceptance', 'checkbox' ), true ) && ! empty( $tag['name'] ) ) : ?>
											<?php $tag_value = '[' . self::scalar_string( $tag['name'] ) . ']'; ?>
											<option value="<?php echo esc_attr( $tag_value ); ?>" data-basetype="<?php echo esc_attr( self::scalar_string( $tag['basetype'] ) ); ?>"><?php echo esc_html( $tag_value . ' - ' . self::scalar_string( $tag['basetype'] ) ); ?></option>
										<?php endif; ?>
									<?php endforeach; ?>
								</select>
							</div>
							<p class="cmatic-consent-policy-explanation"><?php esc_html_e( 'A Contact Form 7 checkbox or acceptance field records permission. Tick it to send the submission.', 'contact-form-7-mailchimp-extension' ); ?></p>
						</div>
						<div id="cmatic-provider-optin-row" class="cmatic-consent-policy-row">
							<label class="cmatic-consent-policy-name"><strong id="cmatic-provider-optin-title"><?php echo esc_html( $provider_optin_title ); ?></strong><span><?php esc_html_e( 'Provider setting', 'contact-form-7-mailchimp-extension' ); ?></span></label>
							<div class="cmatic-consent-policy-control">
								<div id="cmatic-provider-brevo-optin" hidden>
									<label class="screen-reader-text" for="cmatic-provider-subscription-mode"><?php esc_html_e( 'Opt-in process', 'contact-form-7-mailchimp-extension' ); ?></label>
									<select id="cmatic-provider-subscription-mode" name="wpcf7-cmatic-provider[subscription_mode]">
										<option value="single"><?php esc_html_e( 'Add contact immediately', 'contact-form-7-mailchimp-extension' ); ?></option>
										<option value="double"><?php esc_html_e( 'Send a double opt-in confirmation email', 'contact-form-7-mailchimp-extension' ); ?></option>
									</select>
									<div id="cmatic-provider-brevo-doi" hidden>
										<label for="cmatic-provider-doi-template"><?php esc_html_e( 'Double opt-in template ID', 'contact-form-7-mailchimp-extension' ); ?></label>
										<input type="number" min="1" id="cmatic-provider-doi-template" name="wpcf7-cmatic-provider[doi_template_id]" value="">
										<label for="cmatic-provider-doi-redirect"><?php esc_html_e( 'Confirmation redirect URL', 'contact-form-7-mailchimp-extension' ); ?></label>
										<input type="url" id="cmatic-provider-doi-redirect" name="wpcf7-cmatic-provider[doi_redirect_url]" value="" placeholder="https://example.com/newsletter-confirmed">
										<input type="hidden" id="cmatic-provider-doi-token" name="wpcf7-cmatic-provider[doi_verification_token]" value="">
										<button type="button" class="button" id="cmatic-provider-doi-verify"><?php esc_html_e( 'Verify double opt-in settings', 'contact-form-7-mailchimp-extension' ); ?></button>
										<span id="cmatic-provider-doi-status" role="status" aria-live="polite"></span>
									</div>
								</div>
							</div>
							<p id="cmatic-provider-optin-explanation" class="cmatic-consent-policy-explanation"><?php echo esc_html( $provider_optin_explanation ); ?></p>
						</div>
					</div>
					<div id="cmatic-provider-mailchimp-options" class="cmatic-consent-policy-provider" hidden>
						<div class="cmatic-consent-policy-row">
							<label class="cmatic-consent-policy-name" for="cmatic-provider-mailchimp-double-optin"><strong><?php esc_html_e( 'Contact status', 'contact-form-7-mailchimp-extension' ); ?></strong><span><?php esc_html_e( 'Status after submission', 'contact-form-7-mailchimp-extension' ); ?></span></label>
							<div class="cmatic-consent-policy-control">
								<select id="cmatic-provider-mailchimp-double-optin" name="wpcf7-cmatic-provider[double_optin]">
									<option value="subscribed"><?php esc_html_e( 'Add them right away (Subscribed)', 'contact-form-7-mailchimp-extension' ); ?></option>
									<option value="pending"><?php esc_html_e( 'Send a confirmation email first (Pending)', 'contact-form-7-mailchimp-extension' ); ?></option>
								</select>
							</div>
							<p class="cmatic-consent-policy-explanation"><?php esc_html_e( 'Pending sends the audience’s confirmation email and adds the contact once they confirm. Subscribed adds them at once.', 'contact-form-7-mailchimp-extension' ); ?></p>
						</div>
					</div>
					<?php \Cmatic\Lite\Admin\PanelExtras::rows( $slug, $config, $form_tags, $form_id ); ?>
					<?php do_action( 'cmatic_provider_panel_rows', $slug, $config, $form_tags, $form_id ); ?>
					<div id="cmatic-provider-mailerlite-options" class="cmatic-consent-policy-provider" hidden>
						<div class="cmatic-consent-policy-row">
							<label class="cmatic-consent-policy-name" for="cmatic-provider-mailerlite-status"><strong><?php esc_html_e( 'MailerLite subscriber status', 'contact-form-7-mailchimp-extension' ); ?></strong><span><?php esc_html_e( 'Status after submission', 'contact-form-7-mailchimp-extension' ); ?></span></label>
							<div class="cmatic-consent-policy-control"><select id="cmatic-provider-mailerlite-status" name="wpcf7-cmatic-provider[status_mode]"><option value="legacy_provider_managed"><?php esc_html_e( 'Active (default)', 'contact-form-7-mailchimp-extension' ); ?></option><option value="account"><?php esc_html_e( 'Use MailerLite account setting', 'contact-form-7-mailchimp-extension' ); ?></option><option value="active"><?php esc_html_e( 'Active', 'contact-form-7-mailchimp-extension' ); ?></option><option value="unconfirmed"><?php esc_html_e( 'Unconfirmed', 'contact-form-7-mailchimp-extension' ); ?></option></select></div>
							<p class="cmatic-consent-policy-explanation"><?php esc_html_e( 'Determines the status requested from MailerLite after the configured consent requirement passes.', 'contact-form-7-mailchimp-extension' ); ?></p>
						</div>
						<div class="cmatic-consent-policy-row cmatic-consent-policy-row--warning">
							<div class="cmatic-consent-policy-name"><strong><?php esc_html_e( 'Actions requiring fresh consent', 'contact-form-7-mailchimp-extension' ); ?></strong><span><?php esc_html_e( 'MailerLite', 'contact-form-7-mailchimp-extension' ); ?></span></div>
							<div class="cmatic-consent-policy-control cmatic-consent-policy-checks">
								<div id="cmatic-provider-mailerlite-resubscribe" hidden><label><input type="checkbox" id="cmatic-provider-mailerlite-resubscribe-force" name="wpcf7-cmatic-provider[resubscribe_force]" value="1"> <?php esc_html_e( 'Resubscribe previously unsubscribed subscribers', 'contact-form-7-mailchimp-extension' ); ?></label><p><?php esc_html_e( 'Use this when the selected acceptance field records fresh permission.', 'contact-form-7-mailchimp-extension' ); ?></p></div>
								<div id="cmatic-provider-mailerlite-consent-metadata"><label><input type="checkbox" id="cmatic-provider-mailerlite-consent-metadata-enabled" name="wpcf7-cmatic-provider[consent_metadata_enabled]" value="1"> <?php esc_html_e( 'Record opt-in IP address and time in MailerLite', 'contact-form-7-mailchimp-extension' ); ?></label><p><?php esc_html_e( 'Sends the validated request IP address and current UTC time to MailerLite.', 'contact-form-7-mailchimp-extension' ); ?></p></div>
								<p id="cmatic-provider-mailerlite-status-notice" class="cmatic-defaults-fields-notice" hidden></p>
							</div>
							<p class="cmatic-consent-policy-explanation"><?php esc_html_e( 'These actions follow affirmative consent recorded by the selected acceptance field.', 'contact-form-7-mailchimp-extension' ); ?></p>
						</div>
					</div>
				</div>
				<p class="cmatic-consent-policy-note"><?php esc_html_e( 'These settings help record consent. They do not provide legal advice or make the form compliant by themselves.', 'contact-form-7-mailchimp-extension' ); ?></p>
				<div id="cmatic-provider-save-row" class="cmatic-provider-save-row" <?php echo self::mappings_are_visible( $provider_state ) ? '' : 'hidden'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Constant HTML attribute. ?>>
					<span id="cmatic-provider-save-status"></span>
					<button type="submit" class="button button-primary" id="cmatic-provider-save"><?php esc_html_e( 'Save configuration', 'contact-form-7-mailchimp-extension' ); ?></button>
				</div>
				<div id="cmatic-provider-field-state">
					<?php self::render_field_state_inputs( $provider_state, $field_limit ); ?>
				</div>
			</section>
			<section id="cmatic-provider-tools" class="cmatic-provider-section" aria-labelledby="cmatic-provider-tools-heading">
				<h3 id="cmatic-provider-tools-heading"><?php esc_html_e( 'Test and inspect', 'contact-form-7-mailchimp-extension' ); ?></h3>
				<p class="description cmatic-provider-tools-description"><?php esc_html_e( 'Inspect delivery or test this Contact Form 7 form here.', 'contact-form-7-mailchimp-extension' ); ?></p>
				<div class="cmatic-provider-tool-list">
					<div id="cmatic-provider-mailerlite-lookup" class="cmatic-provider-tool-row cmatic-provider-tool-row--lookup" hidden>
						<div class="cmatic-provider-tool-copy"><strong><?php esc_html_e( 'Find a subscriber', 'contact-form-7-mailchimp-extension' ); ?></strong><p><?php esc_html_e( 'Inspect the subscriber currently stored in MailerLite.', 'contact-form-7-mailchimp-extension' ); ?></p></div>
						<div class="cmatic-provider-tool-control"><div class="cmatic-provider-tool-field"><label for="cmatic-provider-mailerlite-lookup-email"><?php esc_html_e( 'Subscriber email address', 'contact-form-7-mailchimp-extension' ); ?></label><input type="email" id="cmatic-provider-mailerlite-lookup-email" autocomplete="off"></div><div class="cmatic-provider-actions"><button type="button" class="button button-primary" id="cmatic-provider-mailerlite-lookup-submit"><?php esc_html_e( 'Find subscriber', 'contact-form-7-mailchimp-extension' ); ?></button></div><div id="cmatic-provider-mailerlite-lookup-results" role="status" aria-live="polite"></div></div>
					</div>
					<div class="cmatic-provider-tool-row">
						<div class="cmatic-provider-tool-copy"><strong><?php esc_html_e( 'Preview and test this form', 'contact-form-7-mailchimp-extension' ); ?></strong><p><?php esc_html_e( 'Fill out this form here and send a real submission using its current Contact Form 7 Extension settings.', 'contact-form-7-mailchimp-extension' ); ?></p></div>
						<p id="cmatic-provider-testing-notice" class="cmatic-provider-tool-warning"><?php echo esc_html( $testing_notice ); ?></p>
						<div class="cmatic-provider-actions">
				<?php
				$tool_buttons = Cmatic_Panel_Toggles::cmatic_get_buttons();
				if ( isset( $tool_buttons['form_preview'] ) ) {
					$tool_buttons['form_preview']['label'] = __( 'Preview and test', 'contact-form-7-mailchimp-extension' );
					Cmatic_Panel_Toggles::cmatic_render_button( 'form_preview', $tool_buttons['form_preview'] );
				}
				?>
						</div>
					</div>
					<?php \Cmatic\Lite\Admin\PanelExtras::tool_rows( $slug, $config, $form_tags, $form_id ); ?>
					<?php do_action( 'cmatic_provider_panel_tool_rows', $slug, $config, $form_tags, $form_id ); ?>
				</div>
			</section>
			<section id="cmatic-provider-advanced" class="cmatic-provider-section cmatic-provider-section--collapsible" aria-labelledby="cmatic-provider-advanced-heading">
				<h3 id="cmatic-provider-advanced-heading" class="cmatic-provider-section__heading"><button type="button" class="cmatic-provider-section__toggle" aria-expanded="false" aria-controls="cmatic-provider-advanced-body"><?php esc_html_e( 'Advanced', 'contact-form-7-mailchimp-extension' ); ?><span class="cmatic-provider-section__chevron" aria-hidden="true"></span></button></h3>
				<div id="cmatic-provider-advanced-body" hidden>
					<p class="description"><?php esc_html_e( 'What to reach for when a form stops syncing and nothing above says why. Rows marked for every form apply site-wide and save as you change them.', 'contact-form-7-mailchimp-extension' ); ?></p>
					<div class="cmatic-provider-tool-list cmatic-adv-rows">
						<?php Cmatic_Advanced_Settings::render_card_rows( array( 'debug', 'report', 'refresh', 'context', 'reset', 'purge' ), $slug ); ?>
						<?php \Cmatic\Lite\Admin\PanelExtras::advanced_rows( $slug, $config, $form_tags, $form_id ); ?>
					<?php do_action( 'cmatic_provider_panel_advanced_rows', $slug, $config, $form_tags, $form_id ); ?>
						<?php Cmatic_Advanced_Settings::render_card_rows( defined( 'CMATIC_VERSION' ) ? array( 'telemetry', 'license' ) : array( 'telemetry', 'license', 'auto_update', 'backlink' ), $slug ); ?>
					</div>
				</div>
			</section>
		</div>
		<?php
	}

	public static function render_pro_mailchimp_notice(): void {
		echo '<p>' . esc_html__( 'Manage Mailchimp settings in this panel.', 'contact-form-7-mailchimp-extension' ) . '</p>';
	}

	/**
	 * @param array<array-key, mixed> $config
	 * @param array<array-key, mixed> $form_tags
	 * @return array{active_provider: string, providers: array<string,array<string,mixed>>, form_tags: array<array-key,mixed>}
	 */
	public static function get_public_state( string $active_slug, array $config, int $form_id, array $form_tags = array() ): array {
		$providers              = array();
		$providers['mailchimp'] = Cmatic_Mailchimp_Panel_Store::public_state(
			$form_id,
			$config,
			PHP_INT_MAX,
			$form_tags
		);

		foreach ( Cmatic_Lite_Esp_Registry::catalog()->api_key_slugs() as $slug ) {
			$settings    = ( new FormSettings( $form_id, $config ) )->section( $slug );
			$field_limit = PHP_INT_MAX;
			$credential  = Cmatic_Lite_Esp_Credentials::get( $form_id, $slug );
			$has_key     = '' !== $credential;
			$lists       = self::normalize_lists( $settings );
			$selected    = FormSettings::selected_list( $settings );
			$definition  = Cmatic_Lite_Esp_Manifest::get( $slug );
			$features    = \Cmatic\Lite\Settings\MappingSchema::arr( $definition['features'] ?? array() );
			$base_groups = \Cmatic\Lite\Submission\DestinationResolver::base_groups( $settings );
			if ( '' !== $selected ) {
				$base_groups = array_values( array_unique( array_merge( array( $selected ), array_diff( $base_groups, array( $selected ) ) ) ) );
			}
			unset( $credential );
			$provider_state               = array(
				'has_saved_mappings'         => array() !== array_filter( MappingSchema::mappings( $settings, PHP_INT_MAX ) ),
				'connected'                  => $has_key && 1 === MappingSchema::int( $settings['api-validation'] ?? 0 ),
				'credential_present'         => $has_key,
				'lists'                      => $lists,
				'selected_list'              => $selected,
				'selected_list_name'         => self::list_name( $lists, $selected ),
				'fields'                     => MappingSchema::normalize_fields( $settings['merge_fields'] ?? array(), $field_limit, false ),
				'total_fields'               => max( 0, MappingSchema::int( $settings['total_merge_fields'] ?? 0 ) ),
				'mappings'                   => MappingSchema::mappings( $settings, $field_limit ),
				'double_optin_entitled'      => \Cmatic\Lite\Settings\Entitlements::feature_enabled( 'double_optin', $slug, $form_id ),
				'consent_gate_entitled'      => true,
				'consent_gate'               => 'required' === ( $settings['consent_gate'] ?? '' ) ? 'required' : 'none',
				'consent_field'              => sanitize_text_field( MappingSchema::str( $settings['consent_field'] ?? '' ) ),
				'subscription_mode'          => 'brevo' === $slug && 'double' === ( $settings['subscription_mode'] ?? '' ) ? 'double' : ( 'brevo' === $slug ? 'single' : 'provider_managed' ),
				'doi_template_id'            => max( 0, MappingSchema::int( $settings['doi_template_id'] ?? 0 ) ),
				'doi_redirect_url'           => esc_url_raw( MappingSchema::str( $settings['doi_redirect_url'] ?? '' ) ),
				'form_tags'                  => $form_tags,
				'routing_supported'          => ! empty( $features['multi_group_routing'] ),
				'routing_entitled'           => \Cmatic\Lite\Settings\Entitlements::feature_enabled( \Cmatic\Lite\Settings\Entitlements::routing_feature( $slug ), $slug, $form_id ),
				'base_groups'                => $base_groups,
				'additional_groups'          => array_values( array_diff( $base_groups, array( $selected ) ) ),
				'routing_rules'              => self::normalize_routing_rules( $settings ),
				'status_supported'           => ! empty( $features['status_modes'] ),
				'status_entitled'            => \Cmatic\Lite\Settings\Entitlements::feature_enabled( 'mailerlite_status', $slug, $form_id ),
				'status_mode'                => self::normalize_status_mode( $settings ),
				'resubscribe_entitled'       => \Cmatic\Lite\Settings\Entitlements::feature_enabled( 'mailerlite_resubscribe', $slug, $form_id ),
				'resubscribe_force'          => ! empty( $settings['resubscribe_force'] ),
				'consent_metadata_supported' => ! empty( $features['consent_metadata'] ),
				'consent_metadata_entitled'  => \Cmatic\Lite\Settings\Entitlements::feature_enabled( 'mailerlite_consent_metadata', $slug, $form_id ),
				'consent_metadata_enabled'   => ! empty( $settings['consent_metadata_enabled'] ),
				'create_field_supported'     => ! empty( $features['create_field_types'] ),
				'create_field_entitled'      => \Cmatic\Lite\Settings\Entitlements::feature_enabled( 'mailerlite_create_field', $slug, $form_id ),
				'lookup_supported'           => ! empty( $features['lookup'] ),
			);
			$provider_state['configured'] = self::mappings_are_visible( $provider_state )
				&& MappingSchema::has_email_mapping( array_merge( $provider_state['mappings'], array( 'merge_fields' => $provider_state['fields'] ) ), $field_limit );
			$providers[ $slug ]           = $provider_state;
		}

		foreach ( $providers as &$provider_state ) {
			$provider_state['account_label'] = implode( ', ', array_column( is_array( $provider_state['lists'] ?? null ) ? $provider_state['lists'] : array(), 'name' ) );
		}
		unset( $provider_state );

		return array(
			'can_manage_integration' => current_user_can( 'wpcf7_manage_integration' ),
			'active_provider'        => '' === $active_slug || Cmatic_Lite_Esp_Registry::has( $active_slug ) ? $active_slug : 'mailchimp',
			'providers'              => $providers,
			'form_tags'              => $form_tags,
		);
	}

	/**
	 * @param array<array-key, mixed> $settings
	 * @return array<array-key, mixed>
	 */
	private static function normalize_routing_rules( array $settings ): array {
		return \Cmatic\Lite\Submission\DestinationResolver::normalize_rules( $settings );
	}

	/**
	 * @param array<array-key, mixed> $settings
	 */
	private static function normalize_status_mode( array $settings ): string {
		$mode = \Cmatic\Lite\Settings\MappingSchema::str( $settings['status_mode'] ?? 'legacy_provider_managed' );
		return in_array( $mode, array( 'legacy_provider_managed', 'account', 'active', 'unconfirmed' ), true ) ? $mode : 'legacy_provider_managed';
	}

	/**
	 * @param array<array-key, mixed> $settings
	 * @return array<array-key, mixed>
	 */
	private static function normalize_lists( array $settings ): array {
		$source = FormSettings::lists( $settings );
		$lists  = array();
		foreach ( $source as $list ) {
			if ( ! is_array( $list ) || ! isset( $list['id'], $list['name'] ) ) {
				continue;
			}
			$stats   = isset( $list['stats'] ) && is_array( $list['stats'] ) ? $list['stats'] : array();
			$lists[] = array(
				'id'             => sanitize_text_field( (string) $list['id'] ),
				'name'           => sanitize_text_field( (string) $list['name'] ),
				'opt_in_process' => sanitize_key( (string) ( $list['opt_in_process'] ?? '' ) ),
				'stats'          => array(
					'member_count'      => max( 0, MappingSchema::int( $stats['member_count'] ?? 0 ) ),
					'merge_field_count' => max( 0, MappingSchema::int( $stats['merge_field_count'] ?? 0 ) ),
				),
			);
		}
		return $lists;
	}

	/**
	 * @param array<array-key, mixed> $lists
	 */
	private static function list_name( array $lists, string $selected ): string {
		foreach ( $lists as $list ) {
			if ( is_array( $list ) && isset( $list['id'], $list['name'] ) && $selected === (string) $list['id'] ) {
				return (string) $list['name'];
			}
		}
		return '';
	}

	/**
	 * @param array<array-key, mixed> $definition
	 */
	private static function render_auth_fields( array $definition ): void {
		foreach ( \Cmatic\Lite\Settings\MappingSchema::arr( $definition['auth_fields'] ?? array() ) as $field ) {
			if ( ! is_array( $field ) || ! isset( $field['id'], $field['label'], $field['type'], $field['placeholder'], $field['autocomplete'] ) ) {
				continue;
			}
			$id = sanitize_key( (string) $field['id'] );
			?>
			<div class="cmatic-provider-auth-field">
				<label for="cmatic-provider-auth-<?php echo esc_attr( $id ); ?>">
					<?php echo esc_html( (string) $field['label'] ); ?>
				</label>
				<input
					type="<?php echo esc_attr( 'password' === (string) $field['type'] ? 'text' : (string) $field['type'] ); ?>"
					class="<?php echo 'password' === (string) $field['type'] ? 'cmatic-provider-key' : ''; ?>"
					id="cmatic-provider-auth-<?php echo esc_attr( $id ); ?>"
					data-auth-field="<?php echo esc_attr( $id ); ?>"
					value=""
					placeholder="<?php echo esc_attr( (string) $field['placeholder'] ); ?>"
					autocomplete="<?php echo esc_attr( (string) $field['autocomplete'] ); ?>"
					data-lpignore="true"
					data-1p-ignore
					data-bwignore
				/>
				<small class="description"><?php echo esc_html( (string) ( $field['description'] ?? '' ) ); ?></small>
			</div>
			<?php
		}
	}

	/**
	 * @param array<array-key, mixed> $definition
	 * @param array<array-key, mixed> $state
	 */
	private static function render_destination_options( array $definition, array $state ): void {
		printf(
			'<option value="">%s</option>',
			esc_html(
				sprintf(
					/* translators: %s: provider destination type */
					__( 'Choose the %s', 'contact-form-7-mailchimp-extension' ),
					strtolower( \Cmatic\Lite\Settings\MappingSchema::str( $definition['destination_singular'] ) )
				)
			)
		);
		foreach ( \Cmatic\Lite\Settings\MappingSchema::arr( $state['lists'] ?? array() ) as $list ) {
			if ( ! is_array( $list ) || ! isset( $list['id'], $list['name'] ) ) {
				continue;
			}
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( \Cmatic\Lite\Settings\MappingSchema::str( $list['id'] ) ),
				selected( \Cmatic\Lite\Settings\MappingSchema::str( $state['selected_list'] ), \Cmatic\Lite\Settings\MappingSchema::str( $list['id'] ), false ),
				esc_html( \Cmatic\Lite\Settings\MappingSchema::str( $list['name'] ) )
			);
		}
	}

	/**
	 * @param array<array-key, mixed> $form_tags
	 * @param array<array-key, mixed> $state
	 */
	private static function render_mapping_rows( array $state, array $form_tags, int $field_limit ): void {
		for ( $offset = 0, $rows = max( 1, min( $field_limit, count( MappingSchema::arr( $state['fields'] ?? array() ) ) ) ); $offset < $rows; $offset++ ) {
			$slot       = 'field' . ( $offset + 3 );
			$field      = \Cmatic\Lite\Settings\MappingSchema::arr( $state['fields'] ?? array() )[ $offset ] ?? null;
			$has_field  = is_array( $field );
			$remote_tag = $has_field ? self::scalar_string( $field['tag'] ?? '' ) : '';
			$is_email   = 'EMAIL' === strtoupper( $remote_tag );
			$is_boolean = $has_field && 'boolean' === sanitize_key( self::scalar_string( $field['type'] ?? '' ) );
			$mapping    = self::scalar_string( \Cmatic\Lite\Settings\MappingSchema::arr( $state['mappings'] ?? array() )[ $slot ] ?? '' );
			if ( '' === $mapping && $is_email && empty( $state['has_saved_mappings'] ) ) {
				$mapping = self::first_email_tag( $form_tags );
			}
			?>
			<div
				class="cmatic-provider-mapping-row"
				data-slot="<?php echo esc_attr( $slot ); ?>"
				data-remote-tag="<?php echo esc_attr( $remote_tag ); ?>"
				data-required="<?php echo $is_email ? '1' : '0'; ?>"
				<?php echo $has_field ? '' : 'hidden'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Constant HTML attribute. ?>
			>
				<label for="cmatic-provider-<?php echo esc_attr( $slot ); ?>">
					<span data-field-label><?php echo esc_html( $has_field ? self::scalar_string( $field['name'] ?? '' ) : $slot ); ?></span>
					<span class="mce-type" data-field-type><?php echo esc_html( $has_field ? self::scalar_string( $field['type'] ?? '' ) : '' ); ?></span>
					<span class="mce-required" data-required-label <?php echo $is_email ? '' : 'hidden'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Constant HTML attribute. ?>>
						<?php esc_html_e( 'Required', 'contact-form-7-mailchimp-extension' ); ?>
					</span>
				</label>
				<select
					id="cmatic-provider-<?php echo esc_attr( $slot ); ?>"
					name="wpcf7-cmatic-provider[<?php echo esc_attr( $slot ); ?>]"
					data-mapping-slot="<?php echo esc_attr( $slot ); ?>"
					aria-required="<?php echo $is_email ? 'true' : 'false'; ?>"
					<?php echo $has_field ? '' : 'disabled'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Constant HTML attribute. ?>
				>
					<option value=""><?php esc_html_e( 'Choose...', 'contact-form-7-mailchimp-extension' ); ?></option>
					<?php foreach ( $form_tags as $tag ) : ?>
						<?php
						if ( ! is_array( $tag ) || empty( $tag['name'] ) ) {
							continue;
						}
						$tag_value = '[' . self::scalar_string( $tag['name'] ) . ']';
						$basetype  = sanitize_key( self::scalar_string( $tag['basetype'] ?? '' ) );
						$disabled  = ( $is_email && 'email' !== $basetype ) || ( $is_boolean && 'acceptance' !== $basetype );
						?>
						<option
							value="<?php echo esc_attr( $tag_value ); ?>"
							data-basetype="<?php echo esc_attr( $basetype ); ?>"
							<?php echo selected( $mapping, $tag_value, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- selected() is escaped. ?>
							<?php echo $disabled ? 'disabled' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Constant HTML attribute. ?>
						>
							<?php echo esc_html( $tag_value . ' - ' . $basetype ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<?php
		}
	}

	/**
	 * @param array<array-key, mixed> $state
	 */
	private static function render_field_state_inputs( array $state, int $field_limit ): void {
		printf(
			'<input type="hidden" name="wpcf7-cmatic-provider[total_merge_fields]" data-provider-total-fields value="%s">',
			esc_attr( \Cmatic\Lite\Settings\MappingSchema::str( $state['total_fields'] ?? 0 ) )
		);
		foreach ( array_slice( \Cmatic\Lite\Settings\MappingSchema::arr( $state['fields'] ?? array() ), 0, $field_limit ) as $offset => $field ) {
			if ( ! is_array( $field ) || empty( $field['tag'] ) ) {
				continue;
			}
			foreach ( array( 'tag', 'name', 'type', 'display_order' ) as $property ) {
				printf(
					'<input type="hidden" name="wpcf7-cmatic-provider[merge_fields][%1$d][%2$s]" data-provider-field="%2$s" data-provider-field-index="%1$d" value="%3$s">',
					(int) $offset,
					esc_attr( $property ),
					esc_attr( self::scalar_string( $field[ $property ] ?? '' ) )
				);
			}
		}
	}

	private static function provider_description( string $slug ): string {
		$catalog = Cmatic_Lite_Esp_Registry::catalog();
		return $catalog->has( $slug ) ? $catalog->descriptor( $slug )->description() : '';
	}

	/**
	 * @param array<array-key, mixed> $form_tags
	 */
	private static function first_email_tag( array $form_tags ): string {
		foreach ( $form_tags as $tag ) {
			if (
				is_array( $tag )
				&& ! empty( $tag['name'] )
				&& 'email' === ( $tag['basetype'] ?? '' )
			) {
				return '[' . self::scalar_string( $tag['name'] ) . ']';
			}
		}
		return '';
	}

	/**
	 * @param array<array-key, mixed> $state
	 */
	private static function mappings_are_visible( array $state ): bool {
		return $state['connected']
			&& '' !== \Cmatic\Lite\Settings\MappingSchema::str( $state['selected_list'] )
			&& ! empty( $state['fields'] );
	}

	/**
	 * @param mixed $value
	 */
	private static function scalar_string( $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	}

	private function __construct() {}
}
