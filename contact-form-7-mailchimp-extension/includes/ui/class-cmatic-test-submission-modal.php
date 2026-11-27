<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Cmatic_Test_Submission_Modal' ) ) {
	class Cmatic_Test_Submission_Modal {
		private const MODAL_ID = 'cmatic-test-modal';

		private $contact_form = null;
		private $initialized  = false;

		public function init() {
			if ( $this->initialized ) {
				return;
			}

			add_action( 'wpcf7_admin_footer', array( $this, 'render_modal_with_form' ), 20, 1 );

			$this->initialized = true;
		}

		public function render_modal_with_form( $post ) {
			if ( ! $post || ! method_exists( $post, 'id' ) ) {
				return;
			}

			$form_id = $post->id();
			if ( ! $form_id ) {
				return;
			}

			$this->contact_form = wpcf7_contact_form( $form_id );
			if ( ! $this->contact_form ) {
				return;
			}

			$this->render_modal();
		}

		private function render_modal() {
			$body = $this->get_body();
			?>
			<div id="<?php echo esc_attr( self::MODAL_ID ); ?>"
				class="cmatic-modal "
				role="dialog"
				aria-modal="true"
				aria-labelledby="<?php echo esc_attr( self::MODAL_ID ); ?>-title"
			>
				<div class="cmatic-modal__overlay"></div>
				<div class="cmatic-modal__dialog">
					<div class="cmatic-modal__header">
						<h2 id="<?php echo esc_attr( self::MODAL_ID ); ?>-title"><?php esc_html_e( 'Test Current Form Submission', 'contact-form-7-mailchimp-extension' ); ?></h2>
						<button type="button" class="cmatic-modal__submit button button-primary">
							<?php esc_html_e( 'Submit', 'contact-form-7-mailchimp-extension' ); ?>
						</button>
						<button type="button" class="cmatic-modal__close" aria-label="<?php esc_attr_e( 'Close dialog', 'contact-form-7-mailchimp-extension' ); ?>">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<div class="cmatic-modal__body">
						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						echo $body;
						?>
					</div>
				</div>
			</div>
			<?php
		}

		private function get_body() {
			if ( ! $this->contact_form ) {
				return '<p>' . esc_html__( 'No form available.', 'contact-form-7-mailchimp-extension' ) . '</p>';
			}

			ob_start();
			?>
			<div class="cmatic-modal__feedback" style="display: none;">
				<div class="cmatic-modal__feedback-icon"></div>
				<div class="cmatic-modal__feedback-content">
					<div class="cmatic-modal__feedback-title"></div>
					<div class="cmatic-modal__feedback-details"></div>
				</div>
			</div>
			<div class="cmatic-test-form-wrap">
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $this->contact_form->form_html( array( 'html_class' => 'cmatic-test-form' ) );
				?>
			</div>
			<?php
			return ob_get_clean();
		}
	}
}
