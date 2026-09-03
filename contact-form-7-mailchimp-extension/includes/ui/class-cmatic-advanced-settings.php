<?php
/**
 * Advanced settings panel renderer.
 *
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
}
