<?php
/**
 * Sidebar panel components.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

final class Cmatic_Sidebar_Panel {
	public static function render_submit_info( int $post_id ): void {
		$cf7_mch   = get_option( 'cf7_mch_' . $post_id, array() );
		$api_valid = (int) ( $cf7_mch['api-validation'] ?? 0 );
		$sent      = Cmatic_Options_Repository::get_option( 'stats.sent', 0 );

		$has_credentials = ( is_array( $cf7_mch ) && ! empty( $cf7_mch['api'] ) )
			|| ( isset( $cf7_mch['auth_type'] ) && 'oauth' === $cf7_mch['auth_type'] );
		if ( 1 === $api_valid ) {
			$status_text = '<span class="chmm valid">API Connected</span>';
		} elseif ( ! $has_credentials ) {
			$status_text = '<span class="chmm neutral">Not Connected</span>';
		} else {
			$status_text = '<span class="chmm invalid">API Inactive</span>';
		}
		?>
		<div class="misc-pub-section chimpmatic-info" id="chimpmatic-version-info">
			<div style="margin-bottom: 3px;">
				<?php if ( defined( 'CMATIC_VERSION' ) ) : ?>
					<strong><?php echo esc_html__( 'Chimpmatic Pro', 'contact-form-7-mailchimp-extension' ) . ' ' . esc_html( CMATIC_VERSION ); ?></strong>
					<div style="color: #646970; font-size: 11px;"><?php echo esc_html__( 'Lite base', 'contact-form-7-mailchimp-extension' ) . ' ' . esc_html( SPARTAN_MCE_VERSION ); ?></div>
				<?php else : ?>
					<strong><?php echo esc_html__( 'Chimpmatic Lite', 'contact-form-7-mailchimp-extension' ) . ' ' . esc_html( SPARTAN_MCE_VERSION ); ?></strong>
				<?php endif; ?>
			</div>
			<div style="margin-top: 5px;">
				<div class="mc-stats" style="color: #646970; font-size: 12px; margin-bottom: 3px;">
					<?php
					echo esc_html( $sent ) . ' synced contacts in ' .
						esc_html( Cmatic_Utils::get_days_since( (int) Cmatic_Options_Repository::get_option( 'install.quest', time() ) ) ) . ' days';
					?>
				</div>
				<div style="margin-bottom: 3px;">
					<?php echo wp_kses_post( $status_text ); ?>
				</div>
			</div>
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
