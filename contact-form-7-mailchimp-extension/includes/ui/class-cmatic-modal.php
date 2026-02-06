<?php
/**
 * Modal base class.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Cmatic_Modal' ) ) {
	abstract class Cmatic_Modal {
		protected $modal_id;
		protected $admin_hooks = array();
		protected $initialized = false;

		public function __construct( $modal_id, $admin_hooks = array() ) {
			$this->modal_id    = sanitize_key( $modal_id );
			$this->admin_hooks = is_array( $admin_hooks ) ? $admin_hooks : array( $admin_hooks );
		}

		public function init() {
			if ( $this->initialized ) {
				return;
			}

			add_action( 'admin_enqueue_scripts', array( $this, 'maybe_enqueue_assets' ) );
			add_action( $this->get_render_hook(), array( $this, 'maybe_render_modal' ), $this->get_render_priority(), $this->get_render_args() );

			$this->initialized = true;
		}

		protected function get_render_hook() {
			return 'admin_footer';
		}

		protected function get_render_priority() {
			return 20;
		}

		protected function get_render_args() {
			return 0;
		}

		protected function is_valid_admin_page( $hook ) {
			if ( empty( $this->admin_hooks ) ) {
				return true;
			}
			return in_array( $hook, $this->admin_hooks, true );
		}

		public function maybe_enqueue_assets( $hook ) {
			if ( ! $this->is_valid_admin_page( $hook ) ) {
				return;
			}
			$this->enqueue_assets( $hook );
		}

		public function maybe_render_modal() {
			$screen = get_current_screen();
			if ( ! $screen ) {
				return;
			}

			$current_hook = $screen->id;
			if ( ! empty( $this->admin_hooks ) && ! in_array( $current_hook, $this->admin_hooks, true ) ) {
				return;
			}

			$this->render_modal();
		}

		protected function enqueue_assets( $hook ) {
		}

		protected function render_modal() {
			$title       = $this->get_title();
			$body        = $this->get_body();
			$footer      = $this->get_footer();
			$extra_class = $this->get_extra_class();
			$description = $this->get_description();

			?>
			<div id="<?php echo esc_attr( $this->modal_id ); ?>"
				class="cmatic-modal <?php echo esc_attr( $extra_class ); ?>"
				role="dialog"
				aria-modal="true"
				aria-labelledby="<?php echo esc_attr( $this->modal_id ); ?>-title"
				<?php if ( $description ) : ?>
					aria-describedby="<?php echo esc_attr( $this->modal_id ); ?>-description"
				<?php endif; ?>
			>
				<div class="cmatic-modal__overlay"></div>
				<div class="cmatic-modal__dialog">
					<div class="cmatic-modal__header">
						<h2 id="<?php echo esc_attr( $this->modal_id ); ?>-title"><?php echo esc_html( $title ); ?></h2>
						<?php $this->render_header_actions(); ?>
						<button type="button" class="cmatic-modal__close" aria-label="<?php esc_attr_e( 'Close dialog', 'chimpmatic-lite' ); ?>">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<div class="cmatic-modal__body">
						<?php if ( $description ) : ?>
							<p id="<?php echo esc_attr( $this->modal_id ); ?>-description" class="cmatic-modal__description">
								<?php echo esc_html( $description ); ?>
							</p>
						<?php endif; ?>
						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						echo $body;
						?>
					</div>
					<?php if ( $footer ) : ?>
						<div class="cmatic-modal__footer">
							<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo $footer;
							?>
						</div>
					<?php endif; ?>
				</div>
			</div>
			<?php
		}

		protected function render_header_actions() {
		}

		abstract protected function get_title();

		abstract protected function get_body();

		protected function get_footer() {
			return '';
		}

		protected function get_description() {
			return '';
		}

		protected function get_extra_class() {
			return '';
		}

		public function get_modal_id() {
			return $this->modal_id;
		}

		protected function get_strings() {
			return array(
				'closeLabel' => __( 'Close dialog', 'chimpmatic-lite' ),
			);
		}

		protected function get_js_data() {
			return array(
				'modalId' => $this->modal_id,
				'strings' => $this->get_strings(),
			);
		}
	}
}
