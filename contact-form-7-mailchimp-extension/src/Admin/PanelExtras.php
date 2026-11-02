<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    hello@chimpmatic.com
 * @copyright 2014-2026 ChimpMatic
 * @license   GPL-3.0+
 */

namespace Cmatic\Lite\Admin;

use Cmatic\Lite\Settings\MappingSchema;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing -- Typed signatures follow the existing Lite class convention.
final class PanelExtras {
	public static function paid_keys(): array {
		return array( 'force_status', 'noupdateresend', 'checkemail', 'email_msgerr', 'selectlanguage', 'emailtype', 'selectunsubscribe', 'status_mode', 'resubscribe_force', 'consent_metadata_enabled' );
	}

	public static function chip(): void {
		$hint = __( 'Available with our Pro version', 'contact-form-7-mailchimp-extension' );
		printf( '<span class="cmatic-pro-chip" tabindex="0" title="%1$s" data-tooltip="%1$s">%2$s</span>', esc_attr( $hint ), esc_html__( 'PRO', 'contact-form-7-mailchimp-extension' ) );
	}

	public static function offer( string $copy, string $extra_class = '' ): void {
		printf( '<p class="%1$s"><a href="https://chimpmatic.com/pro" target="_blank" rel="noopener noreferrer">%2$s</a></p>', esc_attr( trim( 'description cmatic-lite-preview__offer ' . $extra_class ) ), esc_html( $copy ) );
	}

	public static function cards( string $slug, array $config, array $form_tags, int $form_id ): void {
		?>
		<section id="cmatic-tags" class="cmatic-provider-section cmatic-pro-card" data-lite-preview>
			<h3 class="cmatic-title-with-toggle">
				<span><?php esc_html_e( 'Tags for', 'contact-form-7-mailchimp-extension' ); ?> <span class="audience-name"></span></span>
				<label class="cmatic-toggle-row" data-cmatic-provider-only="mailchimp"><span class="cmatic-toggle-label"><?php esc_html_e( 'Sync Tags', 'contact-form-7-mailchimp-extension' ); ?></span><span class="cmatic-toggle"><input type="checkbox" data-field="sync_tags" disabled><span class="cmatic-toggle-slider"></span></span></label>
			</h3>
			<p class="description" data-tags-description></p>
			<div id="chm_panel_camposformatags">
				<div class="cmatic-tags-grid">
				<?php foreach ( $form_tags as $tag ) : ?>
					<?php
					if ( ! is_array( $tag ) || empty( $tag['name'] ) || ! in_array( $tag['basetype'] ?? '', array( 'checkbox', 'radio', 'select', 'acceptance' ), true ) ) {
						continue; }
					?>
					<label class="cmatic-tag-chip" data-tags-answer="<?php echo esc_attr( MappingSchema::str( $tag['name'] ) ); ?>" data-tag-basetype="<?php echo esc_attr( MappingSchema::str( $tag['basetype'] ) ); ?>"><input type="checkbox" disabled><span class="cmatic-tag-name">[<?php echo esc_html( MappingSchema::str( $tag['name'] ) ); ?>]</span></label>
				<?php endforeach; ?>
				</div>
				<label class="atags" data-tags-fixed><b><?php esc_html_e( 'Fixed tags', 'contact-form-7-mailchimp-extension' ); ?></b><input type="text" disabled placeholder="<?php esc_attr_e( 'comma, separated, texts, or [mail-tags]', 'contact-form-7-mailchimp-extension' ); ?>"><span class="description"><?php esc_html_e( 'Separate tags with commas. You can include form mail-tags.', 'contact-form-7-mailchimp-extension' ); ?></span></label>
			</div>
			<div id="cmatic-tags-property-row" class="cmatic-provider-mapping-row" hidden><label for="cmatic-tags-property"><?php esc_html_e( 'Save tags in', 'contact-form-7-mailchimp-extension' ); ?></label><select id="cmatic-tags-property" disabled></select><input type="text" id="cmatic-tags-property-name" aria-label="<?php esc_attr_e( 'Profile property name', 'contact-form-7-mailchimp-extension' ); ?>" disabled hidden></div>
			<p class="cmatic-consent-policy-explanation" data-tags-managed hidden></p>
			<div class="cmatic-routing-table"><h4><?php esc_html_e( 'Tags based on form answers', 'contact-form-7-mailchimp-extension' ); ?></h4><div id="cmatic-tag-rule-list" class="cmatic-routing-table-list"></div><button type="button" class="button" id="cmatic-tag-add-rule" disabled><?php esc_html_e( 'Add rule', 'contact-form-7-mailchimp-extension' ); ?></button></div>
			<?php self::offer( __( 'Our Pro version adds fixed tags, tags from answers, and tag rules.', 'contact-form-7-mailchimp-extension' ) ); ?>
		</section>
		<div data-cmatic-provider-only="mailchimp" data-lite-preview>
			<section class="cmatic-provider-section cmatic-pro-card"><h3><?php esc_html_e( 'GDPR permissions', 'contact-form-7-mailchimp-extension' ); ?> <?php self::chip(); ?></h3><p class="description"><?php esc_html_e( 'Match each marketing permission to a checkbox or acceptance field. A ticked field grants the permission.', 'contact-form-7-mailchimp-extension' ); ?></p><label><?php esc_html_e( 'Marketing permission', 'contact-form-7-mailchimp-extension' ); ?> <select disabled><option><?php esc_html_e( 'Choose a checkbox or acceptance field', 'contact-form-7-mailchimp-extension' ); ?></option></select></label><?php self::offer( __( 'Our Pro version maps Mailchimp marketing permissions.', 'contact-form-7-mailchimp-extension' ) ); ?></section>
			<section class="cmatic-provider-section cmatic-pro-card"><h3><?php esc_html_e( 'Groups', 'contact-form-7-mailchimp-extension' ); ?> <?php self::chip(); ?></h3><p class="description"><?php esc_html_e( 'Match each group category to a checkbox, radio or select field, or lock a category to one interest for everyone. Replace Groups removes the contact\'s other interests.', 'contact-form-7-mailchimp-extension' ); ?></p><label><?php esc_html_e( 'Replace Groups', 'contact-form-7-mailchimp-extension' ); ?> <input type="checkbox" disabled></label><select disabled aria-label="<?php esc_attr_e( 'Group category', 'contact-form-7-mailchimp-extension' ); ?>"><option><?php esc_html_e( 'Choose a field', 'contact-form-7-mailchimp-extension' ); ?></option></select><?php self::offer( __( 'Our Pro version maps Mailchimp groups and interests.', 'contact-form-7-mailchimp-extension' ) ); ?></section>
		</div>
		<?php
	}

	public static function rows( string $slug, array $config, array $form_tags, int $form_id ): void {
		$rows     = array(
			'force_status'      => array( __( 'Contact status', 'contact-form-7-mailchimp-extension' ), __( 'Normal follows the confirmation choice above. Any other choice sets the status for every contact this form sends.', 'contact-form-7-mailchimp-extension' ), array( __( 'Use the confirmation choice above (Normal)', 'contact-form-7-mailchimp-extension' ), __( 'Add them right away (Subscribed)', 'contact-form-7-mailchimp-extension' ), __( 'Send a confirmation email first (Pending)', 'contact-form-7-mailchimp-extension' ), __( 'Store without campaigns (Unsubscribed)', 'contact-form-7-mailchimp-extension' ), __( 'Receipts and account emails (Transactional)', 'contact-form-7-mailchimp-extension' ), __( 'Mark as a hard bounce (Cleaned)', 'contact-form-7-mailchimp-extension' ) ) ),
			'noupdateresend'    => array( __( 'Already in Mailchimp', 'contact-form-7-mailchimp-extension' ), __( 'On, a contact who already exists in the audience is left exactly as they are. Off, their details are updated from the form.', 'contact-form-7-mailchimp-extension' ), array( __( 'Add new contacts and keep existing details', 'contact-form-7-mailchimp-extension' ) ) ),
			'checkemail'        => array( __( 'Verify contact', 'contact-form-7-mailchimp-extension' ), __( 'Our Pro version lets you check whether an address is already in the audience before the form sends.', 'contact-form-7-mailchimp-extension' ), array( __( 'Check the audience first', 'contact-form-7-mailchimp-extension' ) ) ),
			'selectlanguage'    => array( __( 'Language', 'contact-form-7-mailchimp-extension' ), __( 'Force one language for every contact from this form, or leave Auto-detect and let Mailchimp decide.', 'contact-form-7-mailchimp-extension' ), array( __( 'Auto-detect', 'contact-form-7-mailchimp-extension' ) ) ),
			'emailtype'         => array( __( 'Email format', 'contact-form-7-mailchimp-extension' ), __( 'HTML or plain text for the campaigns this contact receives.', 'contact-form-7-mailchimp-extension' ), array( __( 'HTML', 'contact-form-7-mailchimp-extension' ), __( 'Plain text', 'contact-form-7-mailchimp-extension' ) ) ),
			'selectunsubscribe' => array( __( 'Unsubscribe', 'contact-form-7-mailchimp-extension' ), __( 'What this form does when it is used to unsubscribe a contact.', 'contact-form-7-mailchimp-extension' ), array( __( 'Keep subscribed', 'contact-form-7-mailchimp-extension' ), __( 'Unsubscribe', 'contact-form-7-mailchimp-extension' ) ) ),
		);
		$contexts = array(
			'force_status'      => __( 'Status after submission', 'contact-form-7-mailchimp-extension' ),
			'noupdateresend'    => __( 'Update policy', 'contact-form-7-mailchimp-extension' ),
			'checkemail'        => __( 'Before the form sends', 'contact-form-7-mailchimp-extension' ),
			'selectlanguage'    => __( 'Contact language', 'contact-form-7-mailchimp-extension' ),
			'emailtype'         => __( 'Contact preference', 'contact-form-7-mailchimp-extension' ),
			'selectunsubscribe' => __( 'On submission', 'contact-form-7-mailchimp-extension' ),
		);
		?>
		<div class="cmatic-consent-policy-provider" data-cmatic-provider-only="mailchimp" data-lite-preview>
		<?php foreach ( $rows as $key => $row ) : ?>
			<div class="cmatic-consent-policy-row"><label class="cmatic-consent-policy-name" for="cmatic-lite-preview-<?php echo esc_attr( $key ); ?>"><strong><?php echo esc_html( $row[0] ); ?></strong><span><?php echo esc_html( $contexts[ $key ] ); ?></span></label><div class="cmatic-consent-policy-control">
			<?php if ( in_array( $key, array( 'noupdateresend', 'checkemail' ), true ) ) : ?>
				<label class="cmatic-toggle-row"><span class="cmatic-toggle"><input type="checkbox" id="cmatic-lite-preview-<?php echo esc_attr( $key ); ?>" data-field="<?php echo esc_attr( $key ); ?>" disabled><span class="cmatic-toggle-slider"></span></span><span><?php echo esc_html( $row[2][0] ); ?></span></label>
			<?php else : ?>
			<select id="cmatic-lite-preview-<?php echo esc_attr( $key ); ?>" data-field="<?php echo esc_attr( $key ); ?>" disabled>
				<?php
				foreach ( $row[2] as $label ) :
					?>
				<option><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select>
			<?php endif; ?>
			</div><p class="cmatic-consent-policy-explanation"><?php echo esc_html( $row[1] ); ?></p></div>
		<?php endforeach; ?>
		<?php self::offer( __( 'Our Pro version adds these Mailchimp contact controls.', 'contact-form-7-mailchimp-extension' ), 'cmatic-consent-policy-offer' ); ?>
		</div>
		<?php
	}

	public static function tool_rows( string $slug, array $config, array $form_tags, int $form_id ): void {
		?>
		<div class="cmatic-provider-tool-row cmatic-provider-tool-row--wide" data-cmatic-provider-only="mailchimp" data-lite-preview><div class="cmatic-provider-tool-copy"><strong><?php esc_html_e( 'Export WordPress users', 'contact-form-7-mailchimp-extension' ); ?></strong><p><?php esc_html_e( 'Send the users of this site to a Mailchimp audience, one audience per role.', 'contact-form-7-mailchimp-extension' ); ?></p></div><div class="cmatic-provider-tool-control"><button type="button" class="button" disabled><?php esc_html_e( 'Export WordPress users', 'contact-form-7-mailchimp-extension' ); ?></button><?php self::offer( __( 'Our Pro version exports WordPress users to Mailchimp.', 'contact-form-7-mailchimp-extension' ) ); ?></div></div>
		<?php
	}

	public static function advanced_rows( string $slug, array $config, array $form_tags, int $form_id ): void {
		?>
		<div class="cmatic-provider-tool-row" data-cmatic-provider-only="mailchimp" data-lite-preview><div class="cmatic-provider-tool-copy"><strong><?php esc_html_e( 'Audiences', 'contact-form-7-mailchimp-extension' ); ?></strong></div><div class="cmatic-provider-tool-control"><button type="button" class="button" disabled><?php esc_html_e( 'Refresh audiences', 'contact-form-7-mailchimp-extension' ); ?></button><?php self::offer( __( 'Our Pro version manages additional Mailchimp audiences.', 'contact-form-7-mailchimp-extension' ) ); ?></div></div>
		<?php
	}

	private function __construct() {}
}
