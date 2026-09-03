<?php
/**
 * Settings page header component.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing -- This legacy global class follows the plugin's existing concise component style.
class Cmatic_Header {
	const CMATIC_FB_B = '@gmail';

	private $version;
	private $is_pro;
	private $api_status;
	private $review_url;
	private $review_phrases;
	private $provider;
	private $provider_options;

	public function __construct( array $args = array() ) {
		$this->version          = $this->resolve_version( $args );
		$this->is_pro           = $this->resolve_pro_status( $args );
		$this->api_status       = isset( $args['api_status'] ) && is_string( $args['api_status'] ) ? $args['api_status'] : null;
		$this->review_url       = isset( $args['review_url'] ) && is_string( $args['review_url'] ) ? $args['review_url'] : $this->get_default_review_url();
		$this->provider         = isset( $args['provider'] ) && is_string( $args['provider'] ) ? sanitize_key( $args['provider'] ) : 'mailchimp';
		$this->provider_options = isset( $args['provider_options'] ) && is_array( $args['provider_options'] ) ? $args['provider_options'] : array();
		$this->review_phrases   = array(
			__( 'Loving Chimpmatic? Leave a review', 'contact-form-7-mailchimp-extension' ),
		);
	}

	private function resolve_version( array $args ): string {
		if ( isset( $args['version'] ) && is_string( $args['version'] ) ) {
			return $args['version'];
		}
		if ( defined( 'CMATIC_VERSION' ) ) {
			return (string) CMATIC_VERSION;
		}
		if ( defined( 'SPARTAN_MCE_VERSION' ) ) {
			return (string) SPARTAN_MCE_VERSION;
		}
		return '0.0.0';
	}

	private function resolve_pro_status( array $args ): bool {
		if ( isset( $args['is_pro'] ) ) {
			return (bool) $args['is_pro'];
		}
		if ( function_exists( 'cmatic_is_blessed' ) ) {
			return (bool) cmatic_is_blessed();
		}
		return false;
	}

	private function get_default_review_url(): string {
		return 'https://wordpress.org/support/plugin/contact-form-7-mailchimp-extension/reviews/';
	}

	private function get_review_phrase(): string {
		return $this->review_phrases[0];
	}

	private function get_provider_label(): string {
		if ( isset( $this->provider_options[ $this->provider ] ) ) {
			return (string) $this->provider_options[ $this->provider ];
		}
		return __( 'Email provider', 'contact-form-7-mailchimp-extension' );
	}

	public function render(): void {
		$badge_class = $this->is_pro ? 'cmatic-header__badge--pro' : 'cmatic-header__badge--lite';
		$badge_text  = $this->is_pro ? __( 'PRO', 'contact-form-7-mailchimp-extension' ) : __( 'Lite', 'contact-form-7-mailchimp-extension' );
		?>
		<header class="cmatic-header">
			<div class="cmatic-header__inner">
				<div class="cmatic-header__brand">
					<span class="cmatic-header__title"><?php esc_html_e( 'Chimpmatic', 'contact-form-7-mailchimp-extension' ); ?></span>
					<span class="cmatic-header__badge <?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $badge_text ); ?></span>
					<span class="cmatic-header__version">v<?php echo esc_html( $this->version ); ?></span>
				</div>
				<div class="cmatic-header__context" id="cmatic-header-provider-context" <?php echo '' === $this->provider ? 'hidden' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Constant HTML attribute. ?>>
					<?php $this->render_provider_selector(); ?>
					<?php $this->render_api_status(); ?>
					<div class="cmatic-header__actions">
						<?php if ( $this->is_pro ) : ?>
							<a href="<?php echo esc_url( Cmatic_Pursuit::url( 'https://chimpmatic.com/my-account', 'plugin', 'header_account', 'account' ) ); ?>" target="_blank" rel="noopener noreferrer" class="cmatic-header__review">
								<?php esc_html_e( 'My Account', 'contact-form-7-mailchimp-extension' ); ?>
							</a>
						<?php else : ?>
							<a href="<?php echo esc_url( $this->review_url ); ?>" target="_blank" rel="noopener noreferrer" class="cmatic-header__review">
								<?php echo esc_html( $this->get_review_phrase() ); ?>
								<span class="cmatic-sparkles" aria-label="<?php esc_attr_e( 'Five stars', 'contact-form-7-mailchimp-extension' ); ?>"></span>
							</a>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</header>
		<?php
	}

	private function render_provider_selector(): void {
		if ( empty( $this->provider_options ) ) {
			return;
		}
		?>
		<div class="cmatic-header__provider-control">
			<label for="cmatic-provider"><?php esc_html_e( 'Email provider', 'contact-form-7-mailchimp-extension' ); ?></label>
			<select id="cmatic-provider" name="wpcf7-cmatic-provider[provider]">
				<option value="" disabled <?php selected( '', $this->provider ); ?>><?php esc_html_e( 'Select a provider...', 'contact-form-7-mailchimp-extension' ); ?></option>
				<?php foreach ( $this->provider_options as $slug => $label ) : ?>
					<option value="<?php echo esc_attr( (string) $slug ); ?>" <?php selected( $this->provider, (string) $slug ); ?>>
						<?php echo esc_html( (string) $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>
		<?php
	}

	private function render_api_status(): void {
		if ( null === $this->api_status ) {
			?>
			<div class="cmatic-header__status" role="status" aria-live="polite">
				<span class="cmatic-header__status-dot cmatic-header__status-dot--neutral"></span>
				<span class="cmatic-header__status-text"></span>
			</div>
			<?php
			return;
		}
		$provider_label = $this->get_provider_label();
		if ( 'connected' === $this->api_status ) {
			$dot_class = 'cmatic-header__status-dot--connected';
			/* translators: %s: email provider name */
			$status_text = sprintf( __( '%s connected', 'contact-form-7-mailchimp-extension' ), $provider_label );
		} elseif ( 'fresh' === $this->api_status ) {
			$dot_class = 'cmatic-header__status-dot--neutral';
			/* translators: %s: email provider name */
			$status_text = sprintf( __( '%s not connected', 'contact-form-7-mailchimp-extension' ), $provider_label );
		} else {
			$dot_class = 'cmatic-header__status-dot--disconnected';
			/* translators: %s: email provider name */
			$status_text = sprintf( __( '%s connection inactive', 'contact-form-7-mailchimp-extension' ), $provider_label );
		}
		?>
		<div class="cmatic-header__status" role="status" aria-live="polite">
			<span class="cmatic-header__status-dot <?php echo esc_attr( $dot_class ); ?>"></span>
			<span class="cmatic-header__status-text"><?php echo esc_html( $status_text ); ?></span>
		</div>
		<?php
	}

	public function set_api_status( ?string $status ): self {
		$this->api_status = $status;
		return $this;
	}

	public static function output( array $args = array() ): void {
		$header = new self( $args );
		$header->render();
	}
}
