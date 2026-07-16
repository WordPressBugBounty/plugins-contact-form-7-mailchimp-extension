<?php
/**
 * Universal ChimpMatic Lite provider panel.
 *
 * @package contact-form-7-mailchimp-extension
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing -- Typed signatures follow the existing Lite provider convention.
final class Cmatic_Lite_Esp_Panel {
	public static function get_api_status( string $slug, array $config, int $form_id ): string {
		if ( 'mailchimp' === $slug ) {
			$has_credentials = ! empty( $config['api'] )
				|| ( isset( $config['auth_type'] ) && 'oauth' === $config['auth_type'] );
			if ( 1 === (int) ( $config['api-validation'] ?? 0 ) ) {
				return 'connected';
			}
			return $has_credentials ? 'disconnected' : 'fresh';
		}

		$settings        = self::provider_settings( $config, $slug );
		$credential      = Cmatic_Lite_Esp_Credentials::get( $form_id, $slug );
		$has_credentials = '' !== $credential;
		unset( $credential );

		if ( $has_credentials && 1 === (int) ( $settings['api-validation'] ?? 0 ) ) {
			return 'connected';
		}
		return $has_credentials ? 'disconnected' : 'fresh';
	}

	public static function render( string $slug, array $config, array $form_tags, int $form_id ): void {
		$manifest       = Cmatic_Lite_Esp_Manifest::all();
		$state          = self::get_public_state( $slug, $config, $form_id, $form_tags );
		$discount       = Cmatic_Pursuit::discount();
		$initial_slug   = in_array( $slug, array( 'brevo', 'mailerlite', 'klaviyo' ), true ) ? $slug : 'brevo';
		$field_limit    = Cmatic_Lite_Esp_Capabilities::field_limit( $initial_slug, $form_id );
		$definition     = $manifest[ $initial_slug ];
		$provider_state = $state['providers'][ $initial_slug ];
		$is_hidden      = '' === $slug || 'mailchimp' === $slug;
		$destination    = strtolower( (string) $definition['destination_singular'] );
		$destinations   = strtolower( (string) $definition['destination_plural'] );
		$data_singular  = strtolower( (string) $definition['data_singular'] );
		$data_plural    = strtolower( (string) $definition['data_plural'] );
		$person         = strtolower( (string) $definition['person_singular'] );
		/* translators: %s: provider destination type, such as list or group. */
		$progress_destination = sprintf( __( 'Choose %s', 'chimpmatic-lite' ), $destination );
		/* translators: %s: provider data name, such as contact attributes or subscriber fields. */
		$progress_mappings = sprintf( __( 'Map %s', 'chimpmatic-lite' ), $data_plural );
		/* translators: %s: provider destination plural, such as lists or groups. */
		$refresh_destinations = sprintf( __( 'Refresh %s', 'chimpmatic-lite' ), $destinations );
		/* translators: %s: email provider name. */
		$connect_to_continue = sprintf( __( 'Connect %s to continue.', 'chimpmatic-lite' ), (string) $definition['label'] );
		/* translators: 1: email provider name, 2: provider data name. */
		$mapping_description = sprintf( __( 'Match each %1$s %2$s to a Contact Form 7 field. Email address mapping is required.', 'chimpmatic-lite' ), (string) $definition['label'], $data_plural );
		/* translators: 1: provider destination type, 2: provider data name. */
		$mapping_locked = sprintf( __( 'Choose a %1$s to load its %2$s.', 'chimpmatic-lite' ), $destination, $data_plural );
		/* translators: %s: provider data name. */
		$unlock_data = sprintf( __( 'Unlock every available %s and advanced features with Chimpmatic Pro', 'chimpmatic-lite' ), $data_singular );
		/* translators: %s: email provider name. */
		$consent_gate_title = sprintf( __( 'Send to %s', 'chimpmatic-lite' ), (string) $definition['label'] );
		/* translators: %s: email provider name. */
		$consent_gate_explanation = sprintf( __( 'Choose whether every valid form submission is sent to %s or only submissions with affirmative consent.', 'chimpmatic-lite' ), (string) $definition['label'] );
		/* translators: %s: email provider name. */
		$provider_optin_title = sprintf( __( 'Confirmation in %s', 'chimpmatic-lite' ), (string) $definition['label'] );
		/* translators: %s: email provider name. */
		$confirmation_settings_link = sprintf( __( 'Open %s confirmation settings', 'chimpmatic-lite' ), (string) $definition['label'] );
		/* translators: %s: email provider name. */
		$provider_optin_explanation = sprintf( __( 'Controls whether %s requires confirmation after the form is submitted.', 'chimpmatic-lite' ), (string) $definition['label'] );
		/* translators: 1: provider person name, 2: email provider name. */
		$testing_notice = sprintf( __( 'Real submission: may create or update a %1$s in %2$s and trigger confirmation emails or automations.', 'chimpmatic-lite' ), $person, (string) $definition['label'] );
		wp_localize_script( 'cmatic-lite-esp', 'chimpmaticLiteEspState', $state );
		?>
		<section
			id="cmatic-provider-onboarding"
			class="cmatic-provider-onboarding"
			aria-labelledby="cmatic-provider-onboarding-title"
			<?php echo '' === $slug ? '' : 'hidden'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Constant HTML attribute. ?>
		>
			<span class="cmatic-provider-onboarding__kicker"><?php esc_html_e( 'Get started', 'chimpmatic-lite' ); ?></span>
			<h2 id="cmatic-provider-onboarding-title"><?php esc_html_e( 'Choose your email provider', 'chimpmatic-lite' ); ?></h2>
			<p><?php esc_html_e( 'Select one provider to begin the guided setup. You can switch providers later.', 'chimpmatic-lite' ); ?></p>
			<div class="cmatic-provider-choices">
				<?php foreach ( $manifest as $provider_slug => $provider_definition ) : ?>
					<?php $description = self::provider_description( (string) $provider_slug ); ?>
					<button type="button" class="cmatic-provider-choice" data-provider-choice="<?php echo esc_attr( (string) $provider_slug ); ?>">
						<img src="<?php echo esc_url( SPARTAN_MCE_PLUGIN_URL . 'assets/images/providers/' . $provider_slug . '.svg' ); ?>" alt="" aria-hidden="true">
						<strong><?php echo esc_html( (string) $provider_definition['label'] ); ?></strong>
						<span><?php echo esc_html( $description ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>
		</section>
		<div
			id="cmatic-provider-data"
			class="cmatic-provider-panel"
			data-cmatic-provider-view="generic"
			data-form-id="<?php echo esc_attr( (string) $form_id ); ?>"
			data-provider="<?php echo esc_attr( $slug ); ?>"
				data-field-limit="<?php echo esc_attr( (string) $field_limit ); ?>"
			<?php echo $is_hidden ? 'hidden' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Constant HTML attribute. ?>
		>
			<div id="cmatic-provider-completion" class="cmatic-provider-completion" role="status" aria-live="polite" hidden>
				<span class="cmatic-provider-completion__icon" aria-hidden="true">&#10003;</span>
				<div>
					<strong><?php esc_html_e( 'Setup complete', 'chimpmatic-lite' ); ?></strong>
					<p id="cmatic-provider-completion-outcome"></p>
					<span id="cmatic-provider-completion-meta"></span>
				</div>
			</div>
			<ol id="cmatic-provider-progress" class="cmatic-provider-progress" aria-label="<?php esc_attr_e( 'Setup progress', 'chimpmatic-lite' ); ?>">
				<li><span>1</span><b><?php esc_html_e( 'Connect', 'chimpmatic-lite' ); ?></b></li>
				<li><span>2</span><b id="cmatic-provider-progress-destination"><?php echo esc_html( $progress_destination ); ?></b></li>
				<li><span>3</span><b id="cmatic-provider-progress-mappings"><?php echo esc_html( $progress_mappings ); ?></b></li>
			</ol>
			<p id="cmatic-provider-message" class="cmatic-provider-message" role="status" aria-live="polite"></p>

			<section class="cmatic-provider-section" data-cmatic-stage="credentials">
				<div id="cmatic-provider-credentials-intro">
					<h3 id="cmatic-provider-credentials-heading">
						<?php
						printf(
							/* translators: %s: email provider name */
							esc_html__( 'Connect %s', 'chimpmatic-lite' ),
							esc_html( (string) $definition['label'] )
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
							<?php esc_html_e( 'Connect', 'chimpmatic-lite' ); ?>
						</button>
						<button type="button" class="button" id="cmatic-provider-cancel-credential" hidden><?php esc_html_e( 'Cancel', 'chimpmatic-lite' ); ?></button>
					</div>
				</div>
				<div id="cmatic-provider-connected-summary" class="cmatic-provider-connection-summary" hidden>
					<span class="cmatic-provider-connection-summary__icon" aria-hidden="true">&#10003;</span>
					<div class="cmatic-provider-connection-summary__text">
						<strong id="cmatic-provider-connected-title"></strong>
						<span><span id="cmatic-provider-credential-storage"><?php esc_html_e( 'Credential stored securely', 'chimpmatic-lite' ); ?></span> &middot; <span id="cmatic-provider-checked-status"><?php esc_html_e( 'Checked just now', 'chimpmatic-lite' ); ?></span></span>
					</div>
					<div class="cmatic-provider-actions">
						<button type="button" class="button" id="cmatic-provider-refresh"><?php echo esc_html( $refresh_destinations ); ?></button>
						<button type="button" class="button" id="cmatic-provider-replace-credential"><?php esc_html_e( 'Replace credential', 'chimpmatic-lite' ); ?></button>
						<button type="button" class="button button-link-delete" id="cmatic-provider-disconnect"><?php esc_html_e( 'Disconnect', 'chimpmatic-lite' ); ?></button>
					</div>
				</div>
				<div id="cmatic-provider-recovery-summary" class="cmatic-provider-recovery-summary" hidden>
					<div><strong id="cmatic-provider-recovery-title"></strong><p><?php esc_html_e( 'The saved credential could not be verified. Try again or enter a new one.', 'chimpmatic-lite' ); ?></p></div>
					<div class="cmatic-provider-actions">
						<button type="button" class="button button-primary" id="cmatic-provider-retry"><?php esc_html_e( 'Try again', 'chimpmatic-lite' ); ?></button>
						<button type="button" class="button" id="cmatic-provider-recovery-replace"><?php esc_html_e( 'Replace credential', 'chimpmatic-lite' ); ?></button>
						<button type="button" class="button button-link-delete" id="cmatic-provider-recovery-disconnect"><?php esc_html_e( 'Disconnect', 'chimpmatic-lite' ); ?></button>
					</div>
				</div>
			</section>

			<section class="cmatic-provider-section" data-cmatic-stage="destination">
				<h3 id="cmatic-provider-destination-heading">
					<?php
					printf(
						/* translators: %s: provider destination type, such as list or group. */
						esc_html__( 'Choose a %s', 'chimpmatic-lite' ),
						esc_html( $destination )
					);
					?>
				</h3>
				<p id="cmatic-provider-destination-description" class="description"></p>
				<div id="cmatic-provider-destination-locked" class="cmatic-provider-locked"><span>2</span><p><?php echo esc_html( $connect_to_continue ); ?></p></div>
				<div class="cmatic-provider-destination-row" id="cmatic-provider-destination-row" hidden>
					<label class="screen-reader-text" for="cmatic-provider-list" id="cmatic-provider-destination-label"></label>
					<select id="cmatic-provider-list" name="wpcf7-cmatic-provider[list]">
						<?php self::render_destination_options( $definition, $provider_state ); ?>
					</select>
				</div>
				<div id="cmatic-provider-mailerlite-groups" class="cmatic-provider-mapping-grid" aria-label="<?php esc_attr_e( 'MailerLite groups for every subscriber', 'chimpmatic-lite' ); ?>" hidden></div>
				<div id="cmatic-mailerlite-routing" hidden>
					<div aria-labelledby="cmatic-routing-heading">
						<h4 id="cmatic-routing-heading"><?php esc_html_e( 'Add subscribers to groups based on form answers', 'chimpmatic-lite' ); ?></h4>
						<p id="cmatic-routing-description" class="description"><?php esc_html_e( 'Optional. Subscribers can match more than one rule. Groups selected above are always added.', 'chimpmatic-lite' ); ?></p>
						<div id="cmatic-routing-rule-list" class="cmatic-routing-table-list"></div>
						<div class="cmatic-provider-actions cmatic-provider-actions--end"><button type="button" class="button" id="cmatic-routing-add-rule"><?php esc_html_e( 'Add rule', 'chimpmatic-lite' ); ?></button></div>
						<p id="cmatic-routing-notice" class="cmatic-defaults-fields-notice" hidden></p>
					</div>
				</div>
			</section>

			<section class="cmatic-provider-section" data-cmatic-stage="mappings">
				<h3 id="cmatic-provider-mappings-heading">
					<?php
					printf(
						/* translators: 1: provider name, 2: provider data name. */
						esc_html__( 'Map %1$s %2$s', 'chimpmatic-lite' ),
						esc_html( (string) $definition['label'] ),
						esc_html( $data_plural )
					);
					?>
				</h3>
				<p class="description" id="cmatic-provider-mapping-description">
					<?php echo esc_html( $mapping_description ); ?>
				</p>
				<div id="cmatic-provider-mappings-locked" class="cmatic-provider-locked"><span>3</span><p><?php echo esc_html( $mapping_locked ); ?></p></div>
				<div class="cmatic-provider-mapping-grid" id="cmatic-provider-mappings" <?php echo self::mappings_are_visible( $provider_state ) ? '' : 'hidden'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Constant HTML attribute. ?>>
					<?php self::render_mapping_rows( $provider_state, $form_tags, $field_limit ); ?>
					<div id="cmatic-provider-mailerlite-create-field" class="cmatic-provider-mapping-row" hidden>
						<div class="cmatic-provider-auth-field"><label for="cmatic-provider-mailerlite-field-name"><?php esc_html_e( 'Create MailerLite subscriber field', 'chimpmatic-lite' ); ?></label><input type="text" id="cmatic-provider-mailerlite-field-name" maxlength="255"></div>
						<span id="cmatic-provider-mailerlite-field-type-label"><?php esc_html_e( 'Field type', 'chimpmatic-lite' ); ?></span>
						<div id="cmatic-provider-mailerlite-field-type" class="cmatic-provider-actions" role="radiogroup" aria-labelledby="cmatic-provider-mailerlite-field-type-label">
							<span><label><input type="radio" name="cmatic-provider-mailerlite-field-type" value="text" checked> <?php esc_html_e( 'Text', 'chimpmatic-lite' ); ?></label></span>
							<span><label><input type="radio" name="cmatic-provider-mailerlite-field-type" value="number"> <?php esc_html_e( 'Number', 'chimpmatic-lite' ); ?></label></span>
							<span><label><input type="radio" name="cmatic-provider-mailerlite-field-type" value="date"> <?php esc_html_e( 'Date', 'chimpmatic-lite' ); ?></label></span>
						</div>
						<div class="cmatic-provider-actions cmatic-provider-actions--end"><button type="button" class="button" id="cmatic-provider-mailerlite-field-create"><?php esc_html_e( 'Create field', 'chimpmatic-lite' ); ?></button></div>
						<p id="cmatic-provider-mailerlite-field-notice" class="cmatic-defaults-fields-notice" hidden></p>
					</div>
				</div>
				<p
					class="cmatic-defaults-fields-notice"
					id="cmatic-provider-field-limit"
					data-pro-active="<?php echo esc_attr( $field_limit > CMATIC_LITE_FIELDS ? '1' : '0' ); ?>"
					data-current-limit="<?php echo esc_attr( (string) $field_limit ); ?>"
					data-lite-limit="<?php echo esc_attr( (string) CMATIC_LITE_FIELDS ); ?>"
					<?php echo $provider_state['total_fields'] > $field_limit ? '' : 'hidden'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Constant HTML attribute. ?>
				>
					<span id="cmatic-provider-field-limit-copy">
					<?php
					if ( $field_limit > CMATIC_LITE_FIELDS ) {
						printf(
							/* translators: 1: number of provider data fields available with Pro, 2: provider data name. */
							esc_html__( 'Up to %1$d %2$s are available with your active Chimpmatic Pro license.', 'chimpmatic-lite' ),
							(int) $field_limit,
							esc_html( $data_plural )
						);
					} else {
						printf(
							/* translators: 1: number of provider data fields included in Lite, 2: provider data name. */
							esc_html__( 'Your Lite setup includes %1$d %2$s. Email address is always included.', 'chimpmatic-lite' ),
							(int) CMATIC_LITE_FIELDS,
							esc_html( $data_plural )
						);
					}
					?>
					</span>
					<?php if ( $field_limit <= CMATIC_LITE_FIELDS ) : ?>
					<a href="<?php echo esc_url( Cmatic_Pursuit::promo_checkout( 'field_mapping_limit' ) ); ?>" target="_blank" rel="noopener noreferrer">
						<span id="cmatic-provider-field-limit-link-copy"><?php echo esc_html( $unlock_data ); ?></span>
						<?php if ( $discount > 0 ) : ?>
							<span class="cmatic-field-offer">
								<?php
								/* translators: %d: current discount percentage from the live promotion. */
								printf( esc_html__( '%d%% off', 'chimpmatic-lite' ), (int) $discount );
								?>
							</span>
						<?php endif; ?>
						<span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'chimpmatic-lite' ); ?></span>
					</a>
					<?php endif; ?>
				</p>
			</section>
			<section id="cmatic-provider-consent" class="cmatic-provider-section" aria-labelledby="cmatic-provider-consent-heading">
				<h3 id="cmatic-provider-consent-heading"><?php esc_html_e( 'Subscription and consent', 'chimpmatic-lite' ); ?></h3>
				<p id="cmatic-provider-consent-description" class="description"></p>
				<div class="cmatic-consent-policy-list">
					<div id="cmatic-provider-consent-controls" class="cmatic-consent-policy-provider">
						<div class="cmatic-consent-policy-row">
							<label class="cmatic-consent-policy-name" for="cmatic-provider-consent-gate"><strong id="cmatic-provider-consent-gate-title"><?php echo esc_html( $consent_gate_title ); ?></strong><span><?php esc_html_e( 'Form submission rule', 'chimpmatic-lite' ); ?></span></label>
							<div class="cmatic-consent-policy-control">
								<select id="cmatic-provider-consent-gate" name="wpcf7-cmatic-provider[consent_gate]">
									<option value="none"><?php esc_html_e( 'Every valid form submission', 'chimpmatic-lite' ); ?></option>
									<option value="required"><?php esc_html_e( 'Only when an acceptance field is checked', 'chimpmatic-lite' ); ?></option>
								</select>
							</div>
							<p id="cmatic-provider-consent-gate-explanation" class="cmatic-consent-policy-explanation"><?php echo esc_html( $consent_gate_explanation ); ?></p>
						</div>
						<div id="cmatic-provider-consent-field-row" class="cmatic-consent-policy-row" hidden>
							<label class="cmatic-consent-policy-name" for="cmatic-provider-consent-field"><strong><?php esc_html_e( 'Acceptance field', 'chimpmatic-lite' ); ?></strong><span><?php esc_html_e( 'Contact Form 7', 'chimpmatic-lite' ); ?></span></label>
							<div class="cmatic-consent-policy-control">
								<select id="cmatic-provider-consent-field" name="wpcf7-cmatic-provider[consent_field]">
									<option value=""><?php esc_html_e( 'Choose an acceptance field', 'chimpmatic-lite' ); ?></option>
									<?php foreach ( $form_tags as $tag ) : ?>
										<?php if ( is_array( $tag ) && 'acceptance' === ( $tag['basetype'] ?? '' ) && ! empty( $tag['name'] ) ) : ?>
											<?php $tag_value = '[' . sanitize_key( (string) $tag['name'] ) . ']'; ?>
											<option value="<?php echo esc_attr( $tag_value ); ?>"><?php echo esc_html( $tag_value ); ?></option>
										<?php endif; ?>
									<?php endforeach; ?>
								</select>
							</div>
							<p class="cmatic-consent-policy-explanation"><?php esc_html_e( 'Only Contact Form 7 acceptance fields can provide the affirmative consent required by this policy.', 'chimpmatic-lite' ); ?></p>
						</div>
						<div class="cmatic-consent-policy-row">
							<div class="cmatic-consent-policy-name"><strong id="cmatic-provider-optin-title"><?php echo esc_html( $provider_optin_title ); ?></strong><span><?php esc_html_e( 'Provider setting', 'chimpmatic-lite' ); ?></span></div>
							<div class="cmatic-consent-policy-control">
								<div id="cmatic-provider-brevo-optin" hidden>
									<label class="screen-reader-text" for="cmatic-provider-subscription-mode"><?php esc_html_e( 'Opt-in process', 'chimpmatic-lite' ); ?></label>
									<select id="cmatic-provider-subscription-mode" name="wpcf7-cmatic-provider[subscription_mode]">
										<option value="single"><?php esc_html_e( 'Add contact immediately', 'chimpmatic-lite' ); ?></option>
										<option value="double"><?php esc_html_e( 'Send a double opt-in confirmation email', 'chimpmatic-lite' ); ?></option>
									</select>
									<div id="cmatic-provider-brevo-doi" hidden>
										<label for="cmatic-provider-doi-template"><?php esc_html_e( 'Double opt-in template ID', 'chimpmatic-lite' ); ?></label>
										<input type="number" min="1" id="cmatic-provider-doi-template" name="wpcf7-cmatic-provider[doi_template_id]" value="">
										<label for="cmatic-provider-doi-redirect"><?php esc_html_e( 'Confirmation redirect URL', 'chimpmatic-lite' ); ?></label>
										<input type="url" id="cmatic-provider-doi-redirect" name="wpcf7-cmatic-provider[doi_redirect_url]" value="" placeholder="https://example.com/newsletter-confirmed/">
										<input type="hidden" id="cmatic-provider-doi-token" name="wpcf7-cmatic-provider[doi_verification_token]" value="">
										<button type="button" class="button" id="cmatic-provider-doi-verify"><?php esc_html_e( 'Verify double opt-in settings', 'chimpmatic-lite' ); ?></button>
										<span id="cmatic-provider-doi-status" role="status" aria-live="polite"></span>
									</div>
								</div>
								<div id="cmatic-provider-managed-optin" class="cmatic-consent-managed-value" hidden>
									<strong id="cmatic-provider-managed-optin-title"></strong>
									<p id="cmatic-provider-managed-optin-copy"></p>
									<a id="cmatic-provider-consent-docs" href="#" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $confirmation_settings_link ); ?></a>
								</div>
							</div>
							<p id="cmatic-provider-optin-explanation" class="cmatic-consent-policy-explanation"><?php echo esc_html( $provider_optin_explanation ); ?></p>
						</div>
					</div>
					<div id="cmatic-provider-mailerlite-options" class="cmatic-consent-policy-provider" hidden>
						<div class="cmatic-consent-policy-row">
							<label class="cmatic-consent-policy-name" for="cmatic-provider-mailerlite-status"><strong><?php esc_html_e( 'MailerLite subscriber status', 'chimpmatic-lite' ); ?></strong><span><?php esc_html_e( 'Status after submission', 'chimpmatic-lite' ); ?></span></label>
							<div class="cmatic-consent-policy-control"><select id="cmatic-provider-mailerlite-status" name="wpcf7-cmatic-provider[status_mode]"><option value="legacy_provider_managed"><?php esc_html_e( 'Active (current Chimpmatic behavior)', 'chimpmatic-lite' ); ?></option><option value="account"><?php esc_html_e( 'Use MailerLite account setting', 'chimpmatic-lite' ); ?></option><option value="active"><?php esc_html_e( 'Active', 'chimpmatic-lite' ); ?></option><option value="unconfirmed"><?php esc_html_e( 'Unconfirmed', 'chimpmatic-lite' ); ?></option></select></div>
							<p class="cmatic-consent-policy-explanation"><?php esc_html_e( 'Determines the status requested from MailerLite after the configured consent requirement passes.', 'chimpmatic-lite' ); ?></p>
						</div>
						<div class="cmatic-consent-policy-row cmatic-consent-policy-row--warning">
							<div class="cmatic-consent-policy-name"><strong><?php esc_html_e( 'Actions requiring fresh consent', 'chimpmatic-lite' ); ?></strong><span><?php esc_html_e( 'MailerLite', 'chimpmatic-lite' ); ?></span></div>
							<div class="cmatic-consent-policy-control cmatic-consent-policy-checks">
								<div id="cmatic-provider-mailerlite-resubscribe" hidden><label><input type="checkbox" id="cmatic-provider-mailerlite-resubscribe-force" name="wpcf7-cmatic-provider[resubscribe_force]" value="1"> <?php esc_html_e( 'Resubscribe previously unsubscribed subscribers', 'chimpmatic-lite' ); ?></label><p><?php esc_html_e( 'Only use this when the selected acceptance field records new permission.', 'chimpmatic-lite' ); ?></p></div>
								<div id="cmatic-provider-mailerlite-consent-metadata"><label><input type="checkbox" id="cmatic-provider-mailerlite-consent-metadata-enabled" name="wpcf7-cmatic-provider[consent_metadata_enabled]" value="1"> <?php esc_html_e( 'Record opt-in IP address and time in MailerLite', 'chimpmatic-lite' ); ?></label><p><?php esc_html_e( 'Sends the validated request IP address and current UTC time to MailerLite.', 'chimpmatic-lite' ); ?></p></div>
								<p id="cmatic-provider-mailerlite-status-notice" class="cmatic-defaults-fields-notice" hidden></p>
							</div>
							<p class="cmatic-consent-policy-explanation"><?php esc_html_e( 'These actions run only after the selected acceptance field records affirmative consent.', 'chimpmatic-lite' ); ?></p>
						</div>
					</div>
				</div>
				<p class="cmatic-consent-policy-note"><?php esc_html_e( 'These settings help record consent. They do not provide legal advice or make the form compliant by themselves.', 'chimpmatic-lite' ); ?></p>
				<div id="cmatic-provider-save-row" class="cmatic-provider-save-row" <?php echo self::mappings_are_visible( $provider_state ) ? '' : 'hidden'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Constant HTML attribute. ?>>
					<span id="cmatic-provider-save-status"></span>
					<button type="submit" class="button button-primary" id="cmatic-provider-save"><?php esc_html_e( 'Save configuration', 'chimpmatic-lite' ); ?></button>
				</div>
				<div id="cmatic-provider-field-state">
					<?php self::render_field_state_inputs( $provider_state, $field_limit ); ?>
				</div>
			</section>
			<section id="cmatic-provider-tools" class="cmatic-provider-section" aria-labelledby="cmatic-provider-tools-heading">
				<h3 id="cmatic-provider-tools-heading"><?php esc_html_e( 'Test and inspect', 'chimpmatic-lite' ); ?></h3>
				<p class="description cmatic-provider-tools-description"><?php esc_html_e( 'Inspect a subscriber or test this Contact Form 7 form without leaving this screen.', 'chimpmatic-lite' ); ?></p>
				<div class="cmatic-provider-tool-list">
					<div id="cmatic-provider-mailerlite-lookup" class="cmatic-provider-tool-row cmatic-provider-tool-row--lookup" hidden>
						<div class="cmatic-provider-tool-copy"><strong><?php esc_html_e( 'Find a subscriber', 'chimpmatic-lite' ); ?></strong><p><?php esc_html_e( 'Inspect the subscriber currently stored in MailerLite.', 'chimpmatic-lite' ); ?></p></div>
						<div class="cmatic-provider-tool-control"><div class="cmatic-provider-tool-field"><label for="cmatic-provider-mailerlite-lookup-email"><?php esc_html_e( 'Subscriber email address', 'chimpmatic-lite' ); ?></label><input type="email" id="cmatic-provider-mailerlite-lookup-email" autocomplete="off"></div><div class="cmatic-provider-actions"><button type="button" class="button button-primary" id="cmatic-provider-mailerlite-lookup-submit"><?php esc_html_e( 'Find subscriber', 'chimpmatic-lite' ); ?></button></div><div id="cmatic-provider-mailerlite-lookup-results" role="status" aria-live="polite"></div></div>
					</div>
					<div class="cmatic-provider-tool-row">
						<div class="cmatic-provider-tool-copy"><strong><?php esc_html_e( 'Preview and test this form', 'chimpmatic-lite' ); ?></strong><p><?php esc_html_e( 'Fill out this form here and send a real submission using its current Chimpmatic settings.', 'chimpmatic-lite' ); ?></p></div>
						<p id="cmatic-provider-testing-notice" class="cmatic-provider-tool-warning"><?php echo esc_html( $testing_notice ); ?></p>
						<div class="cmatic-provider-actions">
				<?php
				$tool_buttons = Cmatic_Panel_Toggles::cmatic_get_buttons();
				if ( isset( $tool_buttons['form_preview'] ) ) {
					$tool_buttons['form_preview']['label'] = __( 'Preview and test', 'chimpmatic-lite' );
					Cmatic_Panel_Toggles::cmatic_render_button( 'form_preview', $tool_buttons['form_preview'] );
				}
				?>
						</div>
					</div>
				</div>
			</section>
		</div>
		<?php
	}

	public static function render_pro_mailchimp_notice(): void {
		echo '<p>' . esc_html__( 'Mailchimp settings for this form are managed by Chimpmatic Pro.', 'chimpmatic-lite' ) . '</p>';
	}

	public static function get_public_state( string $active_slug, array $config, int $form_id, array $form_tags = array() ): array {
		$providers              = array();
		$mailchimp_creds        = ! empty( $config['api'] )
			|| ( isset( $config['auth_type'] ) && 'oauth' === $config['auth_type'] );
		$providers['mailchimp'] = array(
			'connected'          => $mailchimp_creds && 1 === (int) ( $config['api-validation'] ?? 0 ),
			'credential_present' => $mailchimp_creds,
		);

		foreach ( array( 'brevo', 'mailerlite', 'klaviyo' ) as $slug ) {
			$settings    = self::provider_settings( $config, $slug );
			$field_limit = Cmatic_Lite_Esp_Capabilities::field_limit( $slug, $form_id );
			$credential  = Cmatic_Lite_Esp_Credentials::get( $form_id, $slug );
			$has_key     = '' !== $credential;
			$lists       = self::normalize_lists( $settings );
			$selected    = self::selected_list( $settings );
			$definition  = Cmatic_Lite_Esp_Manifest::get( $slug );
			$features    = $definition['features'];
			$base_groups = Cmatic_Mailerlite_Routing_Resolver::base_groups( $settings );
			if ( 'mailerlite' === $slug && '' !== $selected ) {
				$base_groups = array_values( array_unique( array_merge( array( $selected ), array_diff( $base_groups, array( $selected ) ) ) ) );
			}
			unset( $credential );
			$provider_state               = array(
				'connected'                  => $has_key && 1 === (int) ( $settings['api-validation'] ?? 0 ),
				'credential_present'         => $has_key,
				'lists'                      => $lists,
				'selected_list'              => $selected,
				'selected_list_name'         => self::list_name( $lists, $selected ),
				'fields'                     => self::normalize_fields( $settings, $field_limit ),
				'total_fields'               => max( 0, (int) ( $settings['total_merge_fields'] ?? 0 ) ),
				'mappings'                   => self::normalize_mappings( $settings, $field_limit ),
				'advanced_consent'           => Cmatic_Lite_Esp_Capabilities::feature_enabled( 'advanced_consent', $slug, $form_id ),
				'consent_gate'               => 'required' === ( $settings['consent_gate'] ?? '' ) ? 'required' : 'none',
				'consent_field'              => sanitize_text_field( (string) ( $settings['consent_field'] ?? '' ) ),
				'subscription_mode'          => 'brevo' === $slug && 'double' === ( $settings['subscription_mode'] ?? '' ) ? 'double' : ( 'brevo' === $slug ? 'single' : 'provider_managed' ),
				'doi_template_id'            => max( 0, (int) ( $settings['doi_template_id'] ?? 0 ) ),
				'doi_redirect_url'           => esc_url_raw( (string) ( $settings['doi_redirect_url'] ?? '' ) ),
				'form_tags'                  => $form_tags,
				'routing_supported'          => ! empty( $features['multi_group_routing'] ),
				'routing_entitled'           => Cmatic_Lite_Esp_Capabilities::feature_enabled( 'mailerlite_routing', $slug, $form_id ),
				'base_groups'                => 'mailerlite' === $slug ? $base_groups : array(),
				'additional_groups'          => 'mailerlite' === $slug ? array_values( array_diff( $base_groups, array( $selected ) ) ) : array(),
				'routing_rules'              => 'mailerlite' === $slug ? self::normalize_routing_rules( $settings ) : array(),
				'status_supported'           => ! empty( $features['status_modes'] ),
				'status_entitled'            => Cmatic_Lite_Esp_Capabilities::feature_enabled( 'mailerlite_status', $slug, $form_id ),
				'status_mode'                => self::normalize_status_mode( $settings ),
				'resubscribe_entitled'       => Cmatic_Lite_Esp_Capabilities::feature_enabled( 'mailerlite_resubscribe', $slug, $form_id ),
				'resubscribe_force'          => ! empty( $settings['resubscribe_force'] ),
				'consent_metadata_supported' => ! empty( $features['consent_metadata'] ),
				'consent_metadata_entitled'  => Cmatic_Lite_Esp_Capabilities::feature_enabled( 'mailerlite_consent_metadata', $slug, $form_id ),
				'consent_metadata_enabled'   => ! empty( $settings['consent_metadata_enabled'] ),
				'create_field_supported'     => ! empty( $features['create_field_types'] ),
				'create_field_entitled'      => Cmatic_Lite_Esp_Capabilities::feature_enabled( 'mailerlite_create_field', $slug, $form_id ),
				'lookup_supported'           => ! empty( $features['lookup'] ),
			);
			$provider_state['configured'] = self::mappings_are_visible( $provider_state )
				&& self::state_has_email_mapping( $provider_state, $field_limit );
			$providers[ $slug ]           = $provider_state;
		}

		return array(
			'active_provider' => '' === $active_slug || Cmatic_Lite_Esp_Registry::has( $active_slug ) ? $active_slug : 'mailchimp',
			'providers'       => $providers,
			'form_tags'       => $form_tags,
		);
	}

	private static function normalize_routing_rules( array $settings ): array {
		$rules = array();
		foreach ( (array) ( $settings['routing_rules'] ?? array() ) as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}
			$rules[] = array(
				'id'       => sanitize_text_field( (string) ( $rule['id'] ?? '' ) ),
				'field'    => sanitize_key( (string) ( $rule['field'] ?? '' ) ),
				'value'    => sanitize_text_field( (string) ( $rule['value'] ?? '' ) ),
				'group_id' => sanitize_text_field( (string) ( $rule['group_id'] ?? '' ) ),
			);
		}
		return $rules;
	}

	private static function normalize_status_mode( array $settings ): string {
		$mode = (string) ( $settings['status_mode'] ?? 'legacy_provider_managed' );
		return in_array( $mode, array( 'legacy_provider_managed', 'account', 'active', 'unconfirmed' ), true ) ? $mode : 'legacy_provider_managed';
	}

	private static function provider_settings( array $config, string $slug ): array {
		if (
			! isset( $config['providers'][ $slug ] )
			|| ! is_array( $config['providers'][ $slug ] )
		) {
			return array();
		}
		return $config['providers'][ $slug ];
	}

	private static function selected_list( array $settings ): string {
		$selected = $settings['list'] ?? '';
		if ( is_array( $selected ) ) {
			$first    = reset( $selected );
			$selected = false === $first ? '' : $first;
		}
		return sanitize_text_field( (string) $selected );
	}

	private static function normalize_lists( array $settings ): array {
		$source = $settings['lisdata']['lists'] ?? array();
		$lists  = array();
		if ( ! is_array( $source ) ) {
			return $lists;
		}
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
					'member_count'      => max( 0, (int) ( $stats['member_count'] ?? 0 ) ),
					'merge_field_count' => max( 0, (int) ( $stats['merge_field_count'] ?? 0 ) ),
				),
			);
		}
		return $lists;
	}

	private static function list_name( array $lists, string $selected ): string {
		foreach ( $lists as $list ) {
			if ( is_array( $list ) && isset( $list['id'], $list['name'] ) && $selected === (string) $list['id'] ) {
				return (string) $list['name'];
			}
		}
		return '';
	}

	private static function normalize_fields( array $settings, int $field_limit ): array {
		$source = isset( $settings['merge_fields'] ) && is_array( $settings['merge_fields'] )
			? $settings['merge_fields']
			: array();
		$fields = array();
		foreach ( array_slice( $source, 0, $field_limit ) as $offset => $field ) {
			if ( ! is_array( $field ) || empty( $field['tag'] ) ) {
				continue;
			}
			$tag      = sanitize_text_field( (string) $field['tag'] );
			$fields[] = array(
				'tag'           => $tag,
				'name'          => sanitize_text_field( (string) ( $field['name'] ?? $tag ) ),
				'type'          => sanitize_key( (string) ( $field['type'] ?? 'text' ) ),
				'display_order' => isset( $field['display_order'] ) ? (int) $field['display_order'] : $offset,
			);
		}
		return $fields;
	}

	private static function normalize_mappings( array $settings, int $field_limit ): array {
		$mappings = array();
		foreach ( range( 3, $field_limit + 2 ) as $index ) {
			$slot              = 'field' . $index;
			$mappings[ $slot ] = sanitize_text_field( (string) ( $settings[ $slot ] ?? '' ) );
		}
		return $mappings;
	}

	private static function render_auth_fields( array $definition ): void {
		foreach ( $definition['auth_fields'] ?? array() as $field ) {
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
					type="<?php echo esc_attr( (string) $field['type'] ); ?>"
					id="cmatic-provider-auth-<?php echo esc_attr( $id ); ?>"
					data-auth-field="<?php echo esc_attr( $id ); ?>"
					value=""
					placeholder="<?php echo esc_attr( (string) $field['placeholder'] ); ?>"
					autocomplete="<?php echo esc_attr( (string) $field['autocomplete'] ); ?>"
				/>
				<small class="description"><?php echo esc_html( (string) ( $field['description'] ?? '' ) ); ?></small>
			</div>
			<?php
		}
	}

	private static function render_destination_options( array $definition, array $state ): void {
		printf(
			'<option value="">%s</option>',
			esc_html(
				sprintf(
					/* translators: %s: provider destination type */
					__( 'Choose a %s', 'chimpmatic-lite' ),
					strtolower( (string) $definition['destination_singular'] )
				)
			)
		);
		foreach ( $state['lists'] as $list ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( (string) $list['id'] ),
				selected( (string) $state['selected_list'], (string) $list['id'], false ),
				esc_html( (string) $list['name'] )
			);
		}
	}

	private static function render_mapping_rows( array $state, array $form_tags, int $field_limit ): void {
		for ( $offset = 0; $offset < $field_limit; $offset++ ) {
			$slot       = 'field' . ( $offset + 3 );
			$field      = $state['fields'][ $offset ] ?? null;
			$has_field  = is_array( $field );
			$remote_tag = $has_field ? self::scalar_string( $field['tag'] ?? '' ) : '';
			$is_email   = 'EMAIL' === strtoupper( $remote_tag );
			$is_boolean = $has_field && 'boolean' === sanitize_key( self::scalar_string( $field['type'] ?? '' ) );
			$mapping    = self::scalar_string( $state['mappings'][ $slot ] ?? '' );
			if ( '' === $mapping && $is_email ) {
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
						<?php esc_html_e( 'Required', 'chimpmatic-lite' ); ?>
					</span>
				</label>
				<select
					id="cmatic-provider-<?php echo esc_attr( $slot ); ?>"
					name="wpcf7-cmatic-provider[<?php echo esc_attr( $slot ); ?>]"
					data-mapping-slot="<?php echo esc_attr( $slot ); ?>"
					aria-required="<?php echo $is_email ? 'true' : 'false'; ?>"
					<?php echo $has_field ? '' : 'disabled'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Constant HTML attribute. ?>
				>
					<option value=""><?php esc_html_e( 'Choose...', 'chimpmatic-lite' ); ?></option>
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

	private static function render_field_state_inputs( array $state, int $field_limit ): void {
		printf(
			'<input type="hidden" name="wpcf7-cmatic-provider[total_merge_fields]" data-provider-total-fields value="%s">',
			esc_attr( (string) ( $state['total_fields'] ?? 0 ) )
		);
		foreach ( array_slice( $state['fields'] ?? array(), 0, $field_limit ) as $offset => $field ) {
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
		$descriptions = array(
			'mailchimp'  => __( 'Sign in or use an API key - Audiences', 'chimpmatic-lite' ),
			'brevo'      => __( 'API key - Lists and contact attributes', 'chimpmatic-lite' ),
			'mailerlite' => __( 'API token - Groups and subscriber fields', 'chimpmatic-lite' ),
			'klaviyo'    => __( 'Private API key - Lists and profiles', 'chimpmatic-lite' ),
		);
		return (string) ( $descriptions[ $slug ] ?? '' );
	}

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

	private static function mappings_are_visible( array $state ): bool {
		return $state['connected']
			&& '' !== (string) $state['selected_list']
			&& ! empty( $state['fields'] );
	}

	private static function state_has_email_mapping( array $state, int $field_limit ): bool {
		foreach ( array_slice( $state['fields'] ?? array(), 0, $field_limit ) as $offset => $field ) {
			if ( is_array( $field ) && 'EMAIL' === strtoupper( self::scalar_string( $field['tag'] ?? '' ) ) ) {
				return '' !== trim( self::scalar_string( $state['mappings'][ 'field' . ( $offset + 3 ) ] ?? '' ) );
			}
		}
		return false;
	}

	private static function scalar_string( $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	}

	private function __construct() {}
}
