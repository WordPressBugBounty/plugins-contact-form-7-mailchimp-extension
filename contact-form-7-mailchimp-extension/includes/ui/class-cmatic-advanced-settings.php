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
	public static function render_signls_section(): void {
		?>
		<div id="cmatic-lite-signls-settings" class="mce-custom-fields cmatic-lite-product-insights">
			<h3 class="title"><?php esc_html_e( 'Product Insights', 'chimpmatic-lite' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Choose whether ChimpMatic Lite may share pseudonymous aggregate product insights with Signls.', 'chimpmatic-lite' ); ?></p>
			<table class="form-table mt0 description">
			<tbody>
				<?php self::render_signls_row(); ?>
			</tbody>
			</table>
		</div>
		<?php
	}

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
				<a href="<?php echo esc_url( Cmatic_Pursuit::docs( 'mailchimp-integration-faq', 'unsubscribed_help' ) ); ?>" class="helping-field" target="_blank" title="<?php esc_attr_e( 'Get help with Custom Fields', 'chimpmatic-lite' ); ?>"> <?php esc_html_e( 'Learn More', 'chimpmatic-lite' ); ?> </a>
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

			<?php self::render_signls_row(); ?>

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

	private static function render_signls_row(): void {
		?>
		<tr>
		<th scope="row"><?php esc_html_e( 'Help Us Improve', 'chimpmatic-lite' ); ?></th>
		<td>
			<fieldset><legend class="screen-reader-text"><span><?php esc_html_e( 'Help Us Improve Chimpmatic', 'chimpmatic-lite' ); ?></span></legend>
			<label class="cmatic-toggle">
				<input type="checkbox" id="cmatic-signls-sharing" data-field="signls_sharing" value="1" <?php checked( Cmatic_Lite_Signls_Privacy::consent_status(), 'enabled' ); ?> />
				<span class="cmatic-toggle-slider"></span>
			</label>
			<span class="cmatic-toggle-label"><?php esc_html_e( 'Send pseudonymous aggregate product usage data to Signls after you enable this setting.', 'chimpmatic-lite' ); ?></span>
			<a href="https://chimpmatic.com/privacy" class="helping-field" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Privacy details', 'chimpmatic-lite' ); ?></a>
			<p class="description"><?php esc_html_e( 'Includes plugin/runtime versions, configured provider and feature counts, aggregate operation outcomes, and whether the Pro add-on is present. It never includes contact data, credentials, form IDs, names, destination IDs, error text, or your site URL. Active installations send at most daily; quiet installations send weekly.', 'chimpmatic-lite' ); ?></p>
			</fieldset>
		</td>
		</tr>
		<?php
	}
}
