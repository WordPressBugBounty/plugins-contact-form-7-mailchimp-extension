<?php
/**
 * Groups + GDPR native previews (Pro feature showcase).
 *
 * Mirrors the real Pro sections captured from a live audience so free users
 * see the exact UI an upgrade turns on. Example data on purpose: it renders
 * for every audience with zero extra Mailchimp API calls in Lite, and the
 * footnote says plainly that Pro shows their real categories/permissions.
 * Renders only when Pro is absent (same rule as the Tags showcase).
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

class Cmatic_Pro_Showcase {

	public static function render( int $api_valid ): void {
		$disclosure_class = ( 1 === $api_valid ) ? 'spt-response-out spt-valid' : 'spt-response-out chmp-inactive';

		$pricing  = Cmatic_Pursuit::pricing();
		$discount = (int) ( $pricing['discount_percent'] ?? 0 );

		self::render_gdpr( $disclosure_class, $discount );
		self::render_groups( $disclosure_class, $discount );
	}

	private static function render_gdpr( string $disclosure_class, int $discount ): void {
		?>
		<div class="<?php echo esc_attr( $disclosure_class ); ?>">
			<div class="mce-custom-fields holder-img">
				<h3 class="title cmatic-title-with-toggle">
					<span><?php esc_html_e( 'GDPR Marketing Preferences', 'contact-form-7-mailchimp-extension' ); ?></span>
				</h3>
				<p><?php esc_html_e( 'Collect lawful consent on the form and sync it to your audience\'s Mailchimp marketing permissions. Match each permission to a checkbox or acceptance field:', 'contact-form-7-mailchimp-extension' ); ?></p>
				<div class="cmatic-showcase-grid">
					<label class="cmatic-showcase-row">
						<span class="cmatic-showcase-label"><?php esc_html_e( 'Consent to email communications', 'contact-form-7-mailchimp-extension' ); ?></span>
						<select disabled="disabled" title="<?php esc_attr_e( 'GDPR fields are a Chimpmatic Pro feature', 'contact-form-7-mailchimp-extension' ); ?>">
							<option><?php esc_html_e( 'Available in Chimpmatic Pro', 'contact-form-7-mailchimp-extension' ); ?></option>
						</select>
					</label>
					<label class="cmatic-showcase-row">
						<span class="cmatic-showcase-label"><?php esc_html_e( 'Consent to data processing', 'contact-form-7-mailchimp-extension' ); ?></span>
						<select disabled="disabled" title="<?php esc_attr_e( 'GDPR fields are a Chimpmatic Pro feature', 'contact-form-7-mailchimp-extension' ); ?>">
							<option><?php esc_html_e( 'Available in Chimpmatic Pro', 'contact-form-7-mailchimp-extension' ); ?></option>
						</select>
					</label>
				</div>
				<?php self::render_unlock_line( 'gdpr_showcase', __( 'Unlock GDPR with Pro', 'contact-form-7-mailchimp-extension' ), $discount ); ?>
				<p class="cmatic-showcase-note"><?php esc_html_e( 'Example permissions. With Pro, the permissions defined on your Mailchimp audience appear here automatically.', 'contact-form-7-mailchimp-extension' ); ?></p>
				<a class="lin-to-pro" href="<?php echo esc_url( Cmatic_Pursuit::promo_checkout( 'gdpr_overlay' ) ); ?>" target="_blank" title="<?php esc_attr_e( 'Chimpmatic Pro Options', 'contact-form-7-mailchimp-extension' ); ?>"><span><?php esc_html_e( 'PRO Feature', 'contact-form-7-mailchimp-extension' ); ?> <span><?php esc_html_e( 'Learn More...', 'contact-form-7-mailchimp-extension' ); ?></span></span></a>
			</div>
		</div>
		<?php
	}

	private static function render_groups( string $disclosure_class, int $discount ): void {
		?>
		<div class="<?php echo esc_attr( $disclosure_class ); ?>">
			<div class="mce-custom-fields holder-img">
				<h3 class="title cmatic-title-with-toggle">
					<span><?php esc_html_e( 'Groups & Interests', 'contact-form-7-mailchimp-extension' ); ?></span>
					<label class="cmatic-toggle-row cmatic-showcase-locked" title="<?php esc_attr_e( 'Groups are a Chimpmatic Pro feature', 'contact-form-7-mailchimp-extension' ); ?>">
						<span class="cmatic-toggle-label"><?php esc_html_e( 'Replace Groups', 'contact-form-7-mailchimp-extension' ); ?></span>
						<span class="cmatic-toggle">
							<input type="checkbox" disabled="disabled">
							<span class="cmatic-toggle-slider"></span>
						</span>
					</label>
				</h3>
				<p><?php esc_html_e( 'Send subscribers into your Mailchimp interest groups straight from the form. Match each category to your checkboxes or radio buttons:', 'contact-form-7-mailchimp-extension' ); ?></p>
				<div class="cmatic-showcase-grid">
					<label class="cmatic-showcase-row">
						<span class="cmatic-showcase-label"><?php esc_html_e( 'Newsletter Topics', 'contact-form-7-mailchimp-extension' ); ?> <span class="cmatic-showcase-meta"><?php esc_html_e( 'type: checkboxes', 'contact-form-7-mailchimp-extension' ); ?></span></span>
						<select disabled="disabled" title="<?php esc_attr_e( 'Groups are a Chimpmatic Pro feature', 'contact-form-7-mailchimp-extension' ); ?>">
							<option><?php esc_html_e( 'Available in Chimpmatic Pro', 'contact-form-7-mailchimp-extension' ); ?></option>
						</select>
					</label>
					<label class="cmatic-showcase-row">
						<span class="cmatic-showcase-label"><?php esc_html_e( 'Email Frequency', 'contact-form-7-mailchimp-extension' ); ?> <span class="cmatic-showcase-meta"><?php esc_html_e( 'type: radio', 'contact-form-7-mailchimp-extension' ); ?></span></span>
						<select disabled="disabled" title="<?php esc_attr_e( 'Groups are a Chimpmatic Pro feature', 'contact-form-7-mailchimp-extension' ); ?>">
							<option><?php esc_html_e( 'Available in Chimpmatic Pro', 'contact-form-7-mailchimp-extension' ); ?></option>
						</select>
					</label>
				</div>
				<?php self::render_unlock_line( 'groups_showcase', __( 'Unlock Groups with Pro', 'contact-form-7-mailchimp-extension' ), $discount ); ?>
				<p class="cmatic-showcase-note"><?php esc_html_e( 'Example categories. With Pro, your audience\'s real interest groups appear here automatically.', 'contact-form-7-mailchimp-extension' ); ?></p>
				<a class="lin-to-pro" href="<?php echo esc_url( Cmatic_Pursuit::promo_checkout( 'groups_overlay' ) ); ?>" target="_blank" title="<?php esc_attr_e( 'Chimpmatic Pro Options', 'contact-form-7-mailchimp-extension' ); ?>"><span><?php esc_html_e( 'PRO Feature', 'contact-form-7-mailchimp-extension' ); ?> <span><?php esc_html_e( 'Learn More...', 'contact-form-7-mailchimp-extension' ); ?></span></span></a>
			</div>
		</div>
		<?php
	}

	public static function render_unlock_line( string $content, string $label, int $discount ): void {
		$url = Cmatic_Pursuit::promo_checkout( $content );
		?>
		<p class="cmatic-showcase-unlock">
			<a href="<?php echo esc_url( $url ); ?>" class="helping-field cmatic-unlock-fields" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $label ); ?></a>
			<?php if ( $discount > 0 ) : ?>
				<span class="cmatic-showcase-off">
					<?php
					/* translators: %d: current discount percentage from the live promo */
					printf( esc_html__( '%d%% off', 'contact-form-7-mailchimp-extension' ), (int) $discount );
					?>
				</span>
			<?php endif; ?>
		</p>
		<?php
	}

	private function __construct() {}
}
