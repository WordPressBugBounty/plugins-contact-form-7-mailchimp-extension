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
			<th scope="row"><?php esc_html_e( 'Unsubscribed', 'chimpmatic-lite' ); ?></th>
			<td>
				<fieldset><legend class="screen-reader-text"><span><?php esc_html_e( 'Unsubscribed', 'chimpmatic-lite' ); ?></span></legend>
				<label class="cmatic-toggle">
					<input type="checkbox" id="wpcf7-mailchimp-addunsubscr" name="wpcf7-mailchimp[addunsubscr]" data-field="unsubscribed" value="1" <?php checked( Cmatic_Options_Repository::get_option( 'unsubscribed', false ), true ); ?> />
					<span class="cmatic-toggle-slider"></span>
				</label>
				<span class="cmatic-toggle-label"><?php esc_html_e( 'Marks submitted contacts as unsubscribed.', 'chimpmatic-lite' ); ?></span>
				<a href="<?php echo esc_url( Cmatic_Pursuit::docs( 'mailchimp-opt-in-addunsubscr', 'unsubscribed_help' ) ); ?>" class="helping-field" target="_blank" title="<?php esc_attr_e( 'Get help with Custom Fields', 'chimpmatic-lite' ); ?>"> <?php esc_html_e( 'Learn More', 'chimpmatic-lite' ); ?> </a>
				</fieldset>
			</td>
			</tr>

			<tr>
			<th scope="row"><?php esc_html_e( 'Debug Logger', 'chimpmatic-lite' ); ?></th>
			<td>
				<fieldset><legend class="screen-reader-text"><span><?php esc_html_e( 'Debug Logger', 'chimpmatic-lite' ); ?></span></legend>
				<label class="cmatic-toggle">
					<input type="checkbox" id="wpcf7-mailchimp-logfileEnabled" data-field="debug" value="1" <?php checked( (bool) Cmatic_Options_Repository::get_option( 'debug', false ), true ); ?> />
					<span class="cmatic-toggle-slider"></span>
				</label>
				<span class="cmatic-toggle-label"><?php esc_html_e( 'Enables activity logging to help troubleshoot form issues.', 'chimpmatic-lite' ); ?></span>
				</fieldset>
			</td>
			</tr>

			<tr>
			<th scope="row"><?php esc_html_e( 'Developer', 'chimpmatic-lite' ); ?></th>
			<td>
				<fieldset><legend class="screen-reader-text"><span><?php esc_html_e( 'Developer', 'chimpmatic-lite' ); ?></span></legend>
				<label class="cmatic-toggle">
					<input type="checkbox" id="wpcf7-mailchimp-cf-support" data-field="backlink" value="1" <?php checked( Cmatic_Options_Repository::get_option( 'backlink', false ), true ); ?> />
					<span class="cmatic-toggle-slider"></span>
				</label>
				<span class="cmatic-toggle-label"><?php esc_html_e( 'A backlink to my site, not compulsory, but appreciated', 'chimpmatic-lite' ); ?></span>
				</fieldset>
			</td>
			</tr>

			<tr>
			<th scope="row"><?php esc_html_e( 'Auto Update', 'chimpmatic-lite' ); ?></th>
			<td>
				<fieldset><legend class="screen-reader-text"><span><?php esc_html_e( 'Auto Update', 'chimpmatic-lite' ); ?></span></legend>
				<label class="cmatic-toggle">
					<input type="checkbox" id="chimpmatic-update" data-field="auto_update" value="1" <?php checked( (bool) Cmatic_Options_Repository::get_option( 'auto_update', true ), true ); ?> />
					<span class="cmatic-toggle-slider"></span>
				</label>
				<span class="cmatic-toggle-label"><?php esc_html_e( 'Auto Update Chimpmatic Lite', 'chimpmatic-lite' ); ?></span>
				</fieldset>
			</td>
			</tr>

			<tr>
			<th scope="row"><?php esc_html_e( 'Help Us Improve', 'chimpmatic-lite' ); ?></th>
			<td>
				<fieldset><legend class="screen-reader-text"><span><?php esc_html_e( 'Help Us Improve Chimpmatic', 'chimpmatic-lite' ); ?></span></legend>
				<label class="cmatic-toggle">
					<input type="checkbox" id="cmatic-telemetry-enabled" data-field="telemetry" value="1" <?php checked( (bool) Cmatic_Options_Repository::get_option( 'telemetry.enabled', true ), true ); ?> />
					<span class="cmatic-toggle-slider"></span>
				</label>
				<span class="cmatic-toggle-label"><?php esc_html_e( 'Help us improve Chimpmatic by sharing anonymous usage data', 'chimpmatic-lite' ); ?></span>
				</fieldset>
			</td>
			</tr>

			<tr>
			<th scope="row"><?php esc_html_e( 'License Reset', 'chimpmatic-lite' ); ?></th>
			<td>
				<fieldset><legend class="screen-reader-text"><span><?php esc_html_e( 'License Reset', 'chimpmatic-lite' ); ?></span></legend>
				<button type="button" id="cmatic-license-reset-btn" class="button"><?php esc_html_e( 'Reset License Data', 'chimpmatic-lite' ); ?></button>
				<div id="cmatic-license-reset-message" style="margin-top: 10px;"></div>
				<small class="description"><?php esc_html_e( 'Clears all cached license data. Use this if you see "zombie activation" issues after deactivating your license.', 'chimpmatic-lite' ); ?></small>
				</fieldset>
			</td>
			</tr>

		</tbody>
		</table>
		<?php
	}
}
