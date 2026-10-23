<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

class Cmatic_Advanced_Settings {
	public static function render(): void {
		?>
		<table class="form-table mt0 description">
		<tbody>

			<tr class="">
			<th scope="row"><?php esc_html_e( 'Unsubscribed', 'contact-form-7-mailchimp-extension' ); ?></th>
			<td>
				<fieldset><legend class="screen-reader-text"><span><?php esc_html_e( 'Unsubscribed', 'contact-form-7-mailchimp-extension' ); ?></span></legend>
				<label class="cmatic-toggle">
					<input type="checkbox" id="wpcf7-mailchimp-addunsubscr" name="wpcf7-mailchimp[addunsubscr]" data-field="unsubscribed" value="1" <?php checked( Cmatic_Options_Repository::get_option( 'unsubscribed', false ), true ); ?> />
					<span class="cmatic-toggle-slider"></span>
				</label>
				<span class="cmatic-toggle-label"><?php esc_html_e( 'Marks submitted contacts as unsubscribed.', 'contact-form-7-mailchimp-extension' ); ?></span>
				<a href="<?php echo esc_url( Cmatic_Pursuit::docs( 'mailchimp-integration-faq', 'unsubscribed_help' ) ); ?>" class="helping-field" target="_blank" title="<?php esc_attr_e( 'Get help with Custom Fields', 'contact-form-7-mailchimp-extension' ); ?>"> <?php esc_html_e( 'Learn More', 'contact-form-7-mailchimp-extension' ); ?> </a>
				</fieldset>
			</td>
			</tr>

			<tr>
			<th scope="row"><?php esc_html_e( 'Debug Logger', 'contact-form-7-mailchimp-extension' ); ?></th>
			<td>
				<fieldset><legend class="screen-reader-text"><span><?php esc_html_e( 'Debug Logger', 'contact-form-7-mailchimp-extension' ); ?></span></legend>
				<label class="cmatic-toggle">
					<input type="checkbox" id="wpcf7-mailchimp-logfileEnabled" data-field="debug" value="1" <?php checked( (bool) Cmatic_Options_Repository::get_option( 'debug', false ), true ); ?> />
					<span class="cmatic-toggle-slider"></span>
				</label>
				<span class="cmatic-toggle-label"><?php esc_html_e( 'Enables activity logging to help troubleshoot form issues.', 'contact-form-7-mailchimp-extension' ); ?></span>
				</fieldset>
			</td>
			</tr>

			<tr>
			<th scope="row"><?php esc_html_e( 'Developer', 'contact-form-7-mailchimp-extension' ); ?></th>
			<td>
				<fieldset><legend class="screen-reader-text"><span><?php esc_html_e( 'Developer', 'contact-form-7-mailchimp-extension' ); ?></span></legend>
				<label class="cmatic-toggle">
					<input type="checkbox" id="wpcf7-mailchimp-cf-support" data-field="backlink" value="1" <?php checked( Cmatic_Options_Repository::get_option( 'backlink', false ), true ); ?> />
					<span class="cmatic-toggle-slider"></span>
				</label>
				<span class="cmatic-toggle-label"><?php esc_html_e( 'A backlink to my site, not compulsory, but appreciated', 'contact-form-7-mailchimp-extension' ); ?></span>
				</fieldset>
			</td>
			</tr>

			<tr>
			<th scope="row"><?php esc_html_e( 'Auto Update', 'contact-form-7-mailchimp-extension' ); ?></th>
			<td>
				<fieldset><legend class="screen-reader-text"><span><?php esc_html_e( 'Auto Update', 'contact-form-7-mailchimp-extension' ); ?></span></legend>
				<label class="cmatic-toggle">
					<input type="checkbox" id="chimpmatic-update" data-field="auto_update" value="1" <?php checked( (bool) Cmatic_Options_Repository::get_option( 'auto_update', true ), true ); ?> />
					<span class="cmatic-toggle-slider"></span>
				</label>
				<span class="cmatic-toggle-label"><?php esc_html_e( 'Auto Update Chimpmatic Lite', 'contact-form-7-mailchimp-extension' ); ?></span>
				</fieldset>
			</td>
			</tr>

			<?php self::render_purge_row(); ?>

			<?php self::render_report_row(); ?>

			<?php self::render_help_us_improve_row(); ?>

			<tr>
			<th scope="row"><?php esc_html_e( 'License Reset', 'contact-form-7-mailchimp-extension' ); ?></th>
			<td>
				<fieldset><legend class="screen-reader-text"><span><?php esc_html_e( 'License Reset', 'contact-form-7-mailchimp-extension' ); ?></span></legend>
				<button type="button" id="cmatic-license-reset-btn" class="button"><?php esc_html_e( 'Reset License Data', 'contact-form-7-mailchimp-extension' ); ?></button>
				<div id="cmatic-license-reset-message" style="margin-top: 10px;"></div>
				<small class="description"><?php esc_html_e( 'Clears all cached license data. Use this if you see "zombie activation" issues after deactivating your license.', 'contact-form-7-mailchimp-extension' ); ?></small>
				</fieldset>
			</td>
			</tr>

		</tbody>
		</table>
		<?php
	}

	public static function render_help_us_improve_row(): void {
		?>
		<tr>
		<th scope="row"><?php esc_html_e( 'Help Us Improve', 'contact-form-7-mailchimp-extension' ); ?></th>
		<td>
			<fieldset><legend class="screen-reader-text"><span><?php esc_html_e( 'Help Us Improve Chimpmatic', 'contact-form-7-mailchimp-extension' ); ?></span></legend>
			<label class="cmatic-toggle">
				<input type="checkbox" id="cmatic-telemetry-enabled" data-field="telemetry" value="1" <?php checked( Cmatic_Lite_Signls_Privacy::consent_status(), 'enabled' ); ?> />
				<span class="cmatic-toggle-slider"></span>
			</label>
			<span class="cmatic-toggle-label"><?php esc_html_e( 'Help us improve the plugin.', 'contact-form-7-mailchimp-extension' ); ?></span>
			<a href="https://chimpmatic.com/privacy" class="helping-field" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Learn about it', 'contact-form-7-mailchimp-extension' ); ?></a>
			</fieldset>
		</td>
		</tr>
		<?php
	}

	public static function render_purge_row(): void {
		?>
		<tr>
		<th scope="row"><?php esc_html_e( 'On uninstall', 'contact-form-7-mailchimp-extension' ); ?></th>
		<td>
			<fieldset><legend class="screen-reader-text"><span><?php esc_html_e( 'On uninstall', 'contact-form-7-mailchimp-extension' ); ?></span></legend>
			<label class="cmatic-toggle">
				<input type="checkbox" id="cmatic-purge-on-uninstall" data-field="purge_on_uninstall" value="1" <?php checked( (bool) Cmatic_Options_Repository::get_option( 'purge_on_uninstall', false ), true ); ?> />
				<span class="cmatic-toggle-slider"></span>
			</label>
			<span class="cmatic-toggle-label"><?php esc_html_e( 'Remove settings, keys and per-form data when the plugin is deleted. Off keeps them for a reinstall.', 'contact-form-7-mailchimp-extension' ); ?></span>
			</fieldset>
		</td>
		</tr>
		<?php
	}
	public static function render_report_row(): void {
		?>
		<tr>
		<th scope="row"><?php esc_html_e( 'System report', 'contact-form-7-mailchimp-extension' ); ?></th>
		<td>
			<fieldset><legend class="screen-reader-text"><span><?php esc_html_e( 'System report', 'contact-form-7-mailchimp-extension' ); ?></span></legend>
			<textarea id="cmatic-system-report" class="large-text code" rows="10" readonly spellcheck="false" placeholder="<?php esc_attr_e( 'Build the report to see it here.', 'contact-form-7-mailchimp-extension' ); ?>"></textarea>
			<p>
				<button type="button" class="button" id="cmatic-report-build"><?php esc_html_e( 'Build report', 'contact-form-7-mailchimp-extension' ); ?></button>
				<button type="button" class="button" id="cmatic-report-copy" disabled><?php esc_html_e( 'Copy', 'contact-form-7-mailchimp-extension' ); ?></button>
				<span class="description" id="cmatic-report-feedback" aria-live="polite"></span>
			</p>
			<span class="cmatic-toggle-label"><?php esc_html_e( 'What support asks for first. No keys, no addresses.', 'contact-form-7-mailchimp-extension' ); ?></span>
			</fieldset>
		</td>
		</tr>
		<?php
	}

	/**
	 * @param array<int,string> $keys
	 */
	public static function render_card_rows( array $keys, string $slug = '' ): void {
		$site     = __( 'For every form on this site.', 'contact-form-7-mailchimp-extension' );
		$form     = __( 'This form only.', 'contact-form-7-mailchimp-extension' );
		$manifest = '' === $slug ? array() : Cmatic_Lite_Esp_Manifest::get( $slug );
		$label    = self::text( $manifest['label'] ?? '', __( 'your provider', 'contact-form-7-mailchimp-extension' ) );
		$plural   = strtolower( self::text( $manifest['destination_plural'] ?? '', __( 'destinations', 'contact-form-7-mailchimp-extension' ) ) );
		foreach ( $keys as $key ) {
			switch ( $key ) {
				case 'debug':
					$until = Cmatic_Debug_Log::until();
					$state = $until > 0
						/* translators: %s: date */
						? sprintf( __( 'On until %s, then off by itself. Keys and addresses are redacted as they are written.', 'contact-form-7-mailchimp-extension' ), wp_date( self::text( get_option( 'date_format' ), 'F j, Y' ), $until ) )
						: __( 'Off. When on, every request to the provider and every sync outcome is written to a private file for 14 days.', 'contact-form-7-mailchimp-extension' );
					ob_start();
					Cmatic_Log_Viewer::render_card();
					$viewer = (string) ob_get_clean();
					self::tool_row(
						'debug',
						__( 'Debug log', 'contact-form-7-mailchimp-extension' ),
						$site . ' ' . __( 'Private file, never served by the web server; the viewer reads it for you.', 'contact-form-7-mailchimp-extension' ),
						self::check_html( 'wpcf7-mailchimp-logfileEnabled', 'debug', Cmatic_Debug_Log::logging(), __( 'Write a debug log', 'contact-form-7-mailchimp-extension' ) )
						. '<p class="description" id="cmatic-debug-state" data-server="1">' . esc_html( $state ) . '</p>' . $viewer
					);
					break;
				case 'report':
					self::tool_row(
						'report',
						__( 'System report', 'contact-form-7-mailchimp-extension' ),
						$site . ' ' . __( 'What support asks for first. No keys, no addresses.', 'contact-form-7-mailchimp-extension' ),
						'<textarea class="cmatic-adv-report" id="cmatic-system-report" rows="10" readonly spellcheck="false" placeholder="' . esc_attr__( 'Build the report to see it here.', 'contact-form-7-mailchimp-extension' ) . '"></textarea>'
						. '<div class="cmatic-provider-actions"><button type="button" class="button" id="cmatic-report-build">' . esc_html__( 'Build report', 'contact-form-7-mailchimp-extension' ) . '</button> '
						. '<button type="button" class="button" id="cmatic-report-copy" disabled>' . esc_html__( 'Copy', 'contact-form-7-mailchimp-extension' ) . '</button> '
						. '<a class="button" id="cmatic-report-support" href="' . esc_url( Cmatic_Pursuit::url( 'https://chimpmatic.com/contact', 'plugin', 'advanced_report', 'support' ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Open support', 'contact-form-7-mailchimp-extension' ) . '</a> '
						. '<span id="cmatic-report-feedback" aria-live="polite"></span></div>'
					);
					break;
				case 'refresh':
					self::tool_row(
						'refresh',
						/* translators: %s: email provider name */
						sprintf( __( 'Refresh from %s', 'contact-form-7-mailchimp-extension' ), $label ),
						/* translators: %s: destination plural */
						$form . ' ' . sprintf( __( 'Forgets the cached %s, fields and tags and reads them again. The fix when something new is missing from a dropdown.', 'contact-form-7-mailchimp-extension' ), $plural ),
						'<div class="cmatic-provider-actions"><button type="button" class="button" id="cmatic-advanced-refresh">' . esc_html__( 'Refresh now', 'contact-form-7-mailchimp-extension' ) . '</button> <span id="cmatic-advanced-refresh-feedback" aria-live="polite"></span></div>',
						' data-cmatic-refresh-row'
					);
					break;
				case 'context':
					self::tool_row(
						'context',
						__( 'Sender context in emails', 'contact-form-7-mailchimp-extension' ),
						$site . ' ' . __( 'One switch for every Contact Form 7 form on this site.', 'contact-form-7-mailchimp-extension' ),
						self::check_html( 'cmatic-sender-context', 'sender_context', Cmatic_Sender_Context::enabled(), __( 'Add sender context to notification emails', 'contact-form-7-mailchimp-extension' ) )
						. '<p class="description">' . esc_html__( 'Four lines at the end of every email a form sends to the site owner: a geo location link for the visitor address, the page and form and time, the referrer, and the browser. Emails addressed to the visitor are left alone. On until you switch it off.', 'contact-form-7-mailchimp-extension' ) . '</p>'
						. '<pre class="cmatic-adv-sample">' . esc_html( Cmatic_Sender_Context::sample() ) . '</pre>'
					);
					break;
				case 'reset':
					self::tool_row(
						'reset',
						__( 'Reset this form', 'contact-form-7-mailchimp-extension' ),
						/* translators: %s: email provider name */
						$form . ' ' . sprintf( __( 'Forgets the %s key, destinations, mappings, consent and status saved here. The form itself is untouched.', 'contact-form-7-mailchimp-extension' ), $label ),
						'<div class="cmatic-provider-actions"><button type="button" class="button" id="cmatic-advanced-form-reset">' . esc_html__( 'Reset this form', 'contact-form-7-mailchimp-extension' ) . '</button> <span id="cmatic-advanced-form-reset-feedback" aria-live="polite"></span></div>'
					);
					break;
				case 'purge':
					self::tool_row(
						'purge',
						__( 'On uninstall', 'contact-form-7-mailchimp-extension' ),
						$site,
						self::check_html( 'cmatic-purge-on-uninstall', 'purge_on_uninstall', (bool) Cmatic_Options_Repository::get_option( 'purge_on_uninstall', false ), __( 'Remove settings, keys, per-form data and logs when the plugin is deleted', 'contact-form-7-mailchimp-extension' ) )
					);
					break;
				case 'telemetry':
					self::tool_row(
						'telemetry',
						__( 'Help Us Improve', 'contact-form-7-mailchimp-extension' ),
						$site . ' ' . __( 'Anonymous usage signals: which features are on, never form data, keys or addresses.', 'contact-form-7-mailchimp-extension' ),
						self::check_html( 'cmatic-telemetry-enabled', 'telemetry', 'enabled' === Cmatic_Lite_Signls_Privacy::consent_status(), __( 'Help us improve the plugin', 'contact-form-7-mailchimp-extension' ) )
						. '<p class="description"><a href="https://chimpmatic.com/privacy" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Learn about it', 'contact-form-7-mailchimp-extension' ) . '</a></p>'
					);
					break;
				case 'license':
					self::tool_row(
						'license',
						__( 'License Reset', 'contact-form-7-mailchimp-extension' ),
						$site . ' ' . __( 'Clears cached license data. Use it when a deactivated license still shows as active.', 'contact-form-7-mailchimp-extension' ),
						'<div class="cmatic-provider-actions"><button type="button" id="cmatic-license-reset-btn" class="button">' . esc_html__( 'Reset License Data', 'contact-form-7-mailchimp-extension' ) . '</button></div><div id="cmatic-license-reset-message"></div>'
					);
					break;
				case 'auto_update':
					self::tool_row(
						'auto-update',
						__( 'Auto Update', 'contact-form-7-mailchimp-extension' ),
						__( 'Chimpmatic Lite. Installs each new release as WordPress finds it.', 'contact-form-7-mailchimp-extension' ),
						self::check_html( 'chimpmatic-update', 'auto_update', (bool) Cmatic_Options_Repository::get_option( 'auto_update', true ), __( 'Auto Update Chimpmatic Lite', 'contact-form-7-mailchimp-extension' ) )
					);
					break;
				case 'backlink':
					self::tool_row(
						'backlink',
						__( 'Developer', 'contact-form-7-mailchimp-extension' ),
						__( 'Chimpmatic Lite. A small credit line under the form.', 'contact-form-7-mailchimp-extension' ),
						self::check_html( 'wpcf7-mailchimp-cf-support', 'backlink', (bool) Cmatic_Options_Repository::get_option( 'backlink', false ), __( 'A backlink to my site, not compulsory, but appreciated', 'contact-form-7-mailchimp-extension' ) )
					);
					break;
				case 'unsubscribed':
					self::tool_row(
						'unsubscribed',
						__( 'Unsubscribed', 'contact-form-7-mailchimp-extension' ),
						$form . ' ' . __( 'Ignored when the consent field is required.', 'contact-form-7-mailchimp-extension' ),
						self::check_html( 'wpcf7-mailchimp-addunsubscr', 'unsubscribed', (bool) Cmatic_Options_Repository::get_option( 'unsubscribed', false ), __( 'Marks submitted contacts as unsubscribed', 'contact-form-7-mailchimp-extension' ), 'wpcf7-mailchimp[addunsubscr]' )
						. '<p class="description"><a href="' . esc_url( Cmatic_Pursuit::docs( 'mailchimp-integration-faq', 'unsubscribed_help' ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Learn More', 'contact-form-7-mailchimp-extension' ) . '</a></p>',
						' data-cmatic-provider-only="mailchimp"'
					);
					break;
			}
		}
	}

	private static function check_html( string $id, string $field, bool $checked, string $label, string $name = '' ): string {
		return '<label class="cmatic-adv-toggle" for="' . esc_attr( $id ) . '"><input type="checkbox" id="' . esc_attr( $id ) . '"' . ( '' === $name ? '' : ' name="' . esc_attr( $name ) . '"' ) . ' data-field="' . esc_attr( $field ) . '" value="1"' . checked( $checked, true, false ) . ' /> ' . esc_html( $label ) . '</label>';
	}

	private static function tool_row( string $key, string $title, string $copy, string $control_html, string $attributes = '' ): void {
		echo '<div class="cmatic-provider-tool-row cmatic-adv-row cmatic-adv-row--' . esc_attr( $key ) . '"' . $attributes . '>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attribute strings are literals from this class.
			. '<div class="cmatic-provider-tool-copy"><strong data-cmatic-row-title>' . esc_html( $title ) . '</strong><p>' . esc_html( $copy ) . '</p></div>'
			. '<div class="cmatic-provider-tool-control cmatic-adv-control">' . $control_html . '</div>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts above.
			. '</div>';
	}

	/**
	 * @param mixed $value
	 */
	private static function text( $value, string $fallback ): string {
		return is_scalar( $value ) && '' !== (string) $value ? (string) $value : $fallback;
	}

}
