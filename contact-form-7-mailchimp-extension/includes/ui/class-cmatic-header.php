<?php
/**
 * Settings page header component.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

class Cmatic_Header {
	const CMATIC_FB_B = '@gmail';

	private $version;
	private $is_pro;
	private $api_status;
	private $review_url;
	private $review_phrases;


	public function __construct( array $args = array() ) {
		$this->version    = $this->resolve_version( $args );
		$this->is_pro     = $this->resolve_pro_status( $args );
		$this->api_status = isset( $args['api_status'] ) && is_string( $args['api_status'] ) ? $args['api_status'] : null;
		$this->review_url = isset( $args['review_url'] ) && is_string( $args['review_url'] ) ? $args['review_url'] : $this->get_default_review_url();

		$this->review_phrases = array(
			__( 'Loving Chimpmatic? Leave a review', 'chimpmatic-lite' ),
			__( 'We run on coffee & 5-star reviews', 'chimpmatic-lite' ),
			__( 'Make a developer smile today', 'chimpmatic-lite' ),
			__( 'Got 10 seconds? Rate us!', 'chimpmatic-lite' ),
			__( 'Fuel our plugin addiction', 'chimpmatic-lite' ),
			__( 'Stars make us code faster', 'chimpmatic-lite' ),
			__( 'Help us stay free & caffeinated', 'chimpmatic-lite' ),
			__( "Love us? Don't keep it a secret", 'chimpmatic-lite' ),
			__( 'Your review = our dopamine', 'chimpmatic-lite' ),
			__( 'Be our hero on WordPress.org', 'chimpmatic-lite' ),
			__( 'Psst... we love 5 stars', 'chimpmatic-lite' ),
			__( 'Worth 5 stars? Let us know', 'chimpmatic-lite' ),
			__( 'Support indie plugins', 'chimpmatic-lite' ),
			__( 'Reviews keep the lights on', 'chimpmatic-lite' ),
			__( 'Spread the Chimpmatic love', 'chimpmatic-lite' ),
			__( 'Got love? Leave stars', 'chimpmatic-lite' ),
			__( 'One click, endless gratitude', 'chimpmatic-lite' ),
			__( 'Help other WP folks find us', 'chimpmatic-lite' ),
			__( 'Like us? Rate us!', 'chimpmatic-lite' ),
			__( 'Your stars = our motivation', 'chimpmatic-lite' ),
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
		return 'https://wordpress.org/support/plugin/contact-form-7-mailchimp-extension/reviews/?filter=5';
	}


	private function get_review_phrase(): string {
		$index = wp_rand( 0, count( $this->review_phrases ) - 1 );
		return $this->review_phrases[ $index ];
	}


	public function render(): void {
		$badge_class = $this->is_pro ? 'cmatic-header__badge--pro' : 'cmatic-header__badge--lite';
		$badge_text  = $this->is_pro ? __( 'PRO', 'chimpmatic-lite' ) : __( 'Lite', 'chimpmatic-lite' );
		?>
		<header class="cmatic-header">
			<div class="cmatic-header__inner">
				<div class="cmatic-header__brand">
					<span class="cmatic-header__title"><?php esc_html_e( 'Chimpmatic', 'chimpmatic-lite' ); ?></span>
					<span class="cmatic-header__badge <?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $badge_text ); ?></span>
					<span class="cmatic-header__version">v<?php echo esc_html( $this->version ); ?></span>
					<?php $this->render_api_status(); ?>
				</div>
				<div class="cmatic-header__actions">
					<a href="<?php echo esc_url( $this->review_url ); ?>" target="_blank" rel="noopener noreferrer" class="cmatic-header__review">
						<?php echo esc_html( $this->get_review_phrase() ); ?>
						<span class="cmatic-sparkles" aria-label="5 sparkles"></span>
					</a>
				</div>
			</div>
		</header>
		<?php
	}


	private function render_api_status(): void {
		if ( null === $this->api_status ) {
			return;
		}

		$is_connected = ( 'connected' === $this->api_status );
		$dot_class    = $is_connected ? 'cmatic-header__status-dot--connected' : 'cmatic-header__status-dot--disconnected';
		$status_text  = $is_connected
			? __( 'API Connected', 'chimpmatic-lite' )
			: __( 'API Inactive', 'chimpmatic-lite' );
		?>
		<div class="cmatic-header__status">
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
