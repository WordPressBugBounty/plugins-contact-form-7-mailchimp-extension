<?php
/**
 * Admin UI banners and frontend credit.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

class Cmatic_Banners {
	public static function init(): void {
		add_filter( 'wpcf7_form_response_output', array( __CLASS__, 'maybe_append_backlink' ), 10, 4 );
	}

	public static function maybe_append_backlink( string $output, string $class, string $content, $contact_form ): string {
		// Check if backlink setting is enabled.
		if ( ! Cmatic_Options_Repository::get_option( 'backlink', false ) ) {
			return $output;
		}

		// Check if this form has Chimpmatic configured.
		$form_id = $contact_form->id();
		$cf7_mch = get_option( 'cf7_mch_' . $form_id, array() );

		if ( empty( $cf7_mch ) || empty( $cf7_mch['api-validation'] ) ) {
			return $output;
		}

		// Append the author credit.
		return $output . self::get_author_credit();
	}

	private const DEFAULT_WELCOME = '<p class="about-description">Hello. My name is Renzo, I <span alt="f487" class="dashicons dashicons-heart red-icon"> </span> WordPress and I develop this free plugin to help users like you. I drink copious amounts of coffee to keep me running longer <span alt="f487" class="dashicons dashicons-smiley red-icon"> </span>. If you\'ve found this plugin useful, please consider making a donation.</p><br>
      <p class="about-description">Would you like to <a class="button-primary" href="https://bit.ly/cafe4renzo" target="_blank">buy me a coffee?</a> or <a class="button-primary" href="https://bit.ly/cafe4renzo" target="_blank">Donate with Paypal</a></p>';

	public static function get_welcome(): string {
		$banner = get_site_option( 'mce_conten_panel_welcome', self::DEFAULT_WELCOME );

		return empty( trim( $banner ) ) ? self::DEFAULT_WELCOME : $banner;
	}

	public static function render_lateral(): void {
		?>
		<div id="informationdiv_aux" class="postbox mce-move mce-hidden mc-lateral">
			<?php echo wp_kses_post( self::get_lateral_content() ); ?>
		</div>
		<?php
	}

	public static function get_lateral_content(): string {
		return '
		<div class="inside bg-f2"><h3>Upgrade to PRO</h3>
			<p>We have the the best tool to integrate <b>Contact Form 7</b> & <b>Mailchimp.com</b> mailing lists. We have new nifty features:</p>
			<ul>
				<li>Tag Existing Subscribers</li>
				<li>Group Existing Subscribers</li>
				<li>Email Verification</li>
				<li>AWESOME Support And more!</li>
			</ul>
		</div>
		<div class="promo-2022">
			<h1>40<span>%</span> Off!</h1>
			<p class="interesting">Submit your name and email and we\'ll send you a coupon for <b>40% off</b> your upgrade to the pro version.</p>
			<div class="cm-form" id="promo-form-container">
				<!-- Form will be injected by JavaScript after page load to prevent CF7 from stripping it -->
			</div>
		</div>';
	}

	public static function render_new_user_help(): void {
		$help_url = Cmatic_Pursuit::docs( 'how-to-get-your-mailchimp-api-key', 'new_user_help' );
		?>
		<h2>
			<a href="<?php echo esc_url( $help_url ); ?>" class="helping-field" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'How to get your Mailchimp API key.', 'chimpmatic-lite' ); ?>
			</a>
		</h2>
		<?php
	}

	public static function get_author_credit(): string {
		$url = 'https://chimpmatic.com/?utm_source=client_site&utm_medium=backlink&utm_campaign=powered_by';

		$html  = '<p style="display: none !important">';
		$html .= '<a href="' . esc_url( $url ) . '" rel="noopener" target="_blank">ChimpMatic</a>';
		$html .= ' – CF7 Mailchimp Integration';
		$html .= '</p>' . "\n";

		return $html;
	}
}
