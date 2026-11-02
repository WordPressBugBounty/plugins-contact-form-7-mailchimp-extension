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
		$label    = __( 'Contact Form 7 Extension', 'contact-form-7-mailchimp-extension' );
		if ( '' !== $provider && class_exists( 'Cmatic_Lite_Esp_Manifest' ) ) {
			$definition = Cmatic_Lite_Esp_Manifest::get( $provider );
			if ( isset( $definition['label'] ) && is_scalar( $definition['label'] ) && '' !== (string) $definition['label'] ) {
				$label = (string) $definition['label'];
			}
		}
		$entry      = \Cmatic\Lite\Submission\Stats::get( $post_id );
		$failing    = array() !== $entry && \Cmatic\Lite\Submission\Stats::is_failing( $entry );
		$last_error = array() !== $entry ? \Cmatic\Lite\Submission\Stats::str( $entry['last_error'] ?? '' ) : '';
		$facts      = array() !== $entry ? \Cmatic\Lite\Submission\Stats::summary( $entry ) : __( 'no syncs yet', 'contact-form-7-mailchimp-extension' );
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
				<h3>Explore our Pro version</h3>
				<p>Our Pro version connects Contact Form 7 to eight email marketing platforms, with:</p>
				<ul>
					<li>Tags from form answers</li>
					<li>Multiple destinations and routing</li>
					<li>Unlimited field mapping</li>
					<li>Priority support</li>
				</ul>
			</div>
			<div class="promo-2022">
				<h1><?php echo (int) $discount; ?><span>%</span> Off!</h1>
				<p class="interesting">Bring advanced tagging, routing, unlimited field mapping and priority support to your forms.</p>
				<div class="cm-form">
					<a href="<?php echo esc_url( $promo_url ); ?>" target="_blank" class="button cm-submit">Get our Pro version</a>
					<span class="cm-pricing"><?php echo esc_html( $text ); ?></span>
				</div>
			</div>
		</div>
		<?php
	}


	private function __construct() {}
}
