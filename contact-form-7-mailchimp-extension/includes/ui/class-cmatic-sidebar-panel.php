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

		$status_text = ( 1 === $api_valid )
			? '<span class="chmm valid">API Connected</span>'
			: '<span class="chmm invalid">API Inactive</span>';
		?>
		<div class="misc-pub-section chimpmatic-info" id="chimpmatic-version-info">
			<div style="margin-bottom: 3px;">
				<strong><?php echo esc_html__( 'ChimpMatic Lite', 'chimpmatic-lite' ) . ' ' . esc_html( SPARTAN_MCE_VERSION ); ?></strong>
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
		$pricing  = self::get_pricing_data();
		$text     = $pricing['formatted'] ?? '$39 → $29.25 • Save 40%';
		$discount = (int) ( $pricing['discount_percent'] ?? 40 );

		$install_id = Cmatic_Options_Repository::get_option( 'install.id' );
		if ( ! $install_id ) {
			$install_data = new Cmatic_Install_Data( new Cmatic_Options_Repository() );
			$install_id   = $install_data->get_install_id();
		}

		$promo_url = add_query_arg(
			array(
				'source' => $install_id,
				'promo'  => 'pro' . $discount,
			),
			Cmatic_Pursuit::promo( 'footer_banner', $discount )
		);
		?>
		<div id="informationdiv_aux" class="postbox mce-move mc-lateral" style="display:none">
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

	private static function get_pricing_data(): array {
		$fetcher = new CMatic_Remote_Fetcher(
			array(
				'url'            => 'https://api.chimpmatic.com/promo',
				'cache_key'      => 'cmatic_pricing_data',
				'cache_duration' => DAY_IN_SECONDS,
				'fallback_data'  => array(
					'regular_price'    => 39,
					'sale_price'       => 29.25,
					'discount_percent' => 40,
					'coupon_code'      => 'NOW40',
					'formatted'        => '$39 → $29.25 • Save 40%',
				),
			)
		);

		return $fetcher->get_data();
	}

	private function __construct() {}
}
