<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

final class Cmatic_Sidebar_Panel {
	public static function render_submit_info( int $post_id ): void {
		$cf7_mch  = get_option( 'cf7_mch_' . $post_id, array() );
		$cf7_mch  = is_array( $cf7_mch ) ? $cf7_mch : array();
		$provider = class_exists( 'Cmatic_Lite_Esp_Registry' ) ? (string) Cmatic_Lite_Esp_Registry::get_selected( $cf7_mch ) : '';
		$label    = __( 'Chimpmatic', 'contact-form-7-mailchimp-extension' );
		if ( '' !== $provider && class_exists( 'Cmatic_Lite_Esp_Manifest' ) ) {
			$definition = Cmatic_Lite_Esp_Manifest::get( $provider );
			if ( isset( $definition['label'] ) && is_scalar( $definition['label'] ) && '' !== (string) $definition['label'] ) {
				$label = (string) $definition['label'];
			}
		}
		$entry      = class_exists( 'Cmatic_Sync_Stats' ) ? Cmatic_Sync_Stats::get( $post_id ) : array();
		$failing    = array() !== $entry && Cmatic_Sync_Stats::is_failing( $entry );
		$last_error = array() !== $entry ? Cmatic_Sync_Stats::str( $entry['last_error'] ?? '' ) : '';
		$facts      = array() !== $entry ? Cmatic_Sync_Stats::summary( $entry ) : __( 'no syncs yet', 'contact-form-7-mailchimp-extension' );
		?>
		<div class="misc-pub-section cmatic-status<?php echo $failing ? ' cmatic-status--failing' : ''; ?>">
			<a href="#Chimpmatic" data-cmatic-tab="Chimpmatic-tab" class="cmatic-status__name"><?php echo esc_html( $label ); ?></a>
			<span class="cmatic-status__facts"><?php echo esc_html( $facts ); ?></span>
			<?php if ( $failing && '' !== $last_error ) : ?>
				<span class="cmatic-status__error"><?php echo esc_html( $last_error ); ?></span>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function render_footer_promo(): void {
		if ( function_exists( 'cmatic_is_blessed' ) && cmatic_is_blessed() ) {
			return;
		}

		$pricing   = Cmatic_Pursuit::pricing();
		$text      = $pricing['formatted'] ?? '';
		$discount  = (int) ( $pricing['discount_percent'] ?? 0 );
		$promo_url = Cmatic_Pursuit::promo_checkout( 'footer_banner' );
		?>
		<div id="informationdiv_aux" class="postbox mce-move mc-lateral">
			<div class="inside bg-f2">
				<h3>Upgrade to PRO</h3>
				<p>Get the best Contact Form 7 and Mailchimp integration tool available. Now with these new features:</p>
				<ul>
					<li>Tag Existing Subscribers</li>
					<li>Group Existing Subscribers</li>
					<li>Email Verification</li>
					<li>AWESOME Support And more!</li>
				</ul>
			</div>
			<div class="promo-2022">
				<h1><?php echo (int) $discount; ?><span>%</span> Off!</h1>
				<p class="interesting">Unlock advanced tagging, subscriber groups, email verification, and priority support for your Mailchimp campaigns.</p>
				<div class="cm-form">
					<a href="<?php echo esc_url( $promo_url ); ?>" target="_blank" class="button cm-submit">Get PRO Now</a>
					<span class="cm-pricing"><?php echo esc_html( $text ); ?></span>
				</div>
			</div>
		</div>
		<?php
	}


	private function __construct() {}
}
