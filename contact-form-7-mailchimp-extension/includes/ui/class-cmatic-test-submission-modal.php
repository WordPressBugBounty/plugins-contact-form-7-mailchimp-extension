<?php
/**
 * Test submission modal component.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Cmatic_Test_Submission_Modal' ) ) {
	class Cmatic_Test_Submission_Modal extends Cmatic_Modal {
		private $contact_form = null;

		public function __construct() {
			parent::__construct( 'cmatic-test-modal' );
		}

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

			parent::render_modal();
		}

		protected function get_title() {
			return __( 'Test Current Form Submission', 'chimpmatic-lite' );
		}

		protected function render_header_actions() {
			?>
			<button type="button" class="cmatic-modal__submit button button-primary">
				<?php esc_html_e( 'Submit', 'chimpmatic-lite' ); ?>
			</button>
			<?php
		}

		protected function get_body() {
			if ( ! $this->contact_form ) {
				return '<p>' . esc_html__( 'No form available.', 'chimpmatic-lite' ) . '</p>';
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

		protected function get_strings() {
			return array_merge(
				parent::get_strings(),
				array(
					'submit'     => __( 'Submit', 'chimpmatic-lite' ),
					'submitting' => __( 'Submitting...', 'chimpmatic-lite' ),
					'success'    => __( 'Success!', 'chimpmatic-lite' ),
					'error'      => __( 'Error', 'chimpmatic-lite' ),
				)
			);
		}
	}
}
