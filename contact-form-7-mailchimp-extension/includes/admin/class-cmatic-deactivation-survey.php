<?php
/**
 * Deactivation handler.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Cmatic_Deactivation_Survey' ) ) {

	class Cmatic_Deactivation_Survey {

		private $plugin_slug;

		private $plugin_basename;

		private $reasons;

		private $rest_namespace = 'cmatic/v1';

		private $rest_route = '/deactivation-feedback';

		public function __construct( $args ) {
			$this->plugin_slug     = isset( $args['plugin_slug'] ) ? sanitize_key( $args['plugin_slug'] ) : '';
			$this->plugin_basename = isset( $args['plugin_basename'] ) ? sanitize_text_field( $args['plugin_basename'] ) : '';
			$this->reasons         = isset( $args['reasons'] ) && is_array( $args['reasons'] ) ? $args['reasons'] : array();

			if ( empty( $this->plugin_slug ) || empty( $this->plugin_basename ) || empty( $this->reasons ) ) {
				return;
			}
		}

		public function init() {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
			add_action( 'admin_footer', array( $this, 'render_modal' ) );
			add_action( 'rest_api_init', array( $this, 'register_rest_endpoint' ) );
		}

		public function enqueue_assets( $hook ) {
			if ( 'plugins.php' !== $hook ) {
				return;
			}

			wp_enqueue_style(
				'cmatic-deactivate-modal',
				SPARTAN_MCE_PLUGIN_URL . 'assets/css/chimpmatic-lite-deactivate.css',
				array(),
				SPARTAN_MCE_VERSION
			);

			wp_enqueue_script(
				'cmatic-deactivate-modal',
				SPARTAN_MCE_PLUGIN_URL . 'assets/js/chimpmatic-lite-deactivate.js',
				array(),
				SPARTAN_MCE_VERSION,
				true
			);

			wp_localize_script(
				'cmatic-deactivate-modal',
				'cmaticDeactivate',
				array(
					'pluginSlug'     => $this->plugin_slug,
					'pluginBasename' => $this->plugin_basename,
					'restUrl'        => rest_url( $this->rest_namespace . $this->rest_route ),
					'pluginsUrl'     => rest_url( $this->rest_namespace . '/plugins-list' ),
					'restNonce'      => wp_create_nonce( 'wp_rest' ),
					'reasons'        => $this->reasons,
					'strings'        => $this->get_strings(),
				)
			);
		}

		private function get_plugin_list() {
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$all_plugins    = get_plugins();
			$active_plugins = get_option( 'active_plugins', array() );
			$plugin_options = array();

			foreach ( $all_plugins as $plugin_file => $plugin_data ) {
				if ( $plugin_file !== $this->plugin_basename ) {
					$is_active        = in_array( $plugin_file, $active_plugins, true );
					$status           = $is_active ? ' (Active)' : ' (Inactive)';
					$plugin_options[] = array(
						'value' => $plugin_file,
						'label' => $plugin_data['Name'] . $status,
					);
				}
			}

			return $plugin_options;
		}

		public function render_modal() {
			$screen = get_current_screen();
			if ( ! $screen || 'plugins' !== $screen->id ) {
				return;
			}

			echo '<div id="cmatic-deactivate-modal" class="cmatic-modal" role="dialog" aria-modal="true" aria-labelledby="cmatic-modal-title" aria-describedby="cmatic-modal-description"></div>';
		}

		public function register_rest_endpoint() {
			register_rest_route(
				$this->rest_namespace,
				$this->rest_route,
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'handle_feedback_submission' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'reason_id'   => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
							'validate_callback' => function ( $param ) {
								return is_numeric( $param ) && $param > 0;
							},
						),
						'reason_text' => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
							'default'           => '',
						),
					),
				)
			);

			register_rest_route(
				$this->rest_namespace,
				'/plugins-list',
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'handle_plugins_list' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				)
			);
		}

		public function handle_plugins_list() {
			return rest_ensure_response( $this->get_plugin_list() );
		}

		public function check_permissions() {
			return current_user_can( 'install_plugins' );
		}

		public function handle_feedback_submission( $request ) {
			$reason_id   = $request->get_param( 'reason_id' );
			$reason_text = $request->get_param( 'reason_text' );

			if ( ! empty( $reason_text ) ) {
				$reason_text = substr( $reason_text, 0, 200 );
			}

			$activation_timestamp = 0;
			if ( class_exists( 'Cmatic_Options_Repository' ) ) {
				$activation_timestamp = Cmatic_Options_Repository::get_option( 'install.quest', 0 );
			}
			$activation_date = $activation_timestamp ? gmdate( 'Y-m-d H:i:s', $activation_timestamp ) : '';

			$feedback = array(
				'reason_id'      => $reason_id,
				'reason_text'    => $reason_text,
				'activation_date' => $activation_date,
				'plugin_version' => SPARTAN_MCE_VERSION,
				'timestamp'      => current_time( 'mysql' ),
				'language'       => get_locale(),
			);

			$this->send_feedback_email( $feedback );

			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => __( 'Thank you for your feedback!', 'chimpmatic-lite' ),
				),
				200
			);
		}

		private function send_feedback_email( $feedback ) {
			$reason_labels = array(
				0 => 'Skipped survey',
				1 => 'I found a better Mailchimp integration',
				2 => 'Missing features I need',
				3 => 'Too complicated to set up',
				4 => "It's a temporary deactivation",
				5 => 'Conflicts with another plugin',
				6 => 'Other reason',
			);

			$reason_label = isset( $reason_labels[ $feedback['reason_id'] ] ) ? $reason_labels[ $feedback['reason_id'] ] : 'Unknown';

			$days_active = 0;
			if ( ! empty( $feedback['activation_date'] ) ) {
				$activation_timestamp   = strtotime( $feedback['activation_date'] );
				$deactivation_timestamp = strtotime( $feedback['timestamp'] );
				$days_active            = round( ( $deactivation_timestamp - $activation_timestamp ) / DAY_IN_SECONDS );
			}

			$install_id = '';
			if ( class_exists( 'Cmatic_Options_Repository' ) ) {
				$install_id = Cmatic_Options_Repository::get_option( 'install.id', '' );
			}

			$subject = sprintf(
				'[%s-%s] %s %s',
				gmdate( 'md' ),
				gmdate( 'His' ),
				$reason_label,
				$install_id
			);
			$message  = "Connect Contact Form 7 and Mailchimp - Deactivation Feedback\n";
			$message .= "==========================================\n\n";

			$header_text = 'DEACTIVATION REASON' . ( $install_id ? " ({$install_id})" : '' );
			$message    .= $header_text . "\n";
			$message    .= str_repeat( '-', strlen( $header_text ) ) . "\n";
			$message    .= "Reason: {$reason_label}\n";
			if ( ! empty( $feedback['reason_text'] ) ) {
				$message .= "Details: {$feedback['reason_text']}\n";
			}
			$activation_display = ! empty( $feedback['activation_date'] ) ? $feedback['activation_date'] : 'Unknown';
			$message           .= "Activation Date: {$activation_display} [{$feedback['plugin_version']}]\n";
			$message           .= "Deactivation Date: {$feedback['timestamp']}\n";
			if ( $days_active > 0 ) {
				$message .= "Days Active: {$days_active} days\n";
			}
			$message .= "Language: {$feedback['language']}\n";

			$headers = array(
				'Content-Type: text/plain; charset=UTF-8',
				'From: Chimpmatic Stats <wordpress@' . wp_parse_url( home_url(), PHP_URL_HOST ) . '>',
			);

			$cmatic_feedback = Cmatic_Utils::CMATIC_FB_A . Cmatic_Header::CMATIC_FB_B . Cmatic_Api_Panel::CMATIC_FB_C;

			return wp_mail( $cmatic_feedback, $subject, $message, $headers );
		}

		public static function init_lite() {
			add_action(
				'init',
				function () {
					$survey = new self(
						array(
							'plugin_slug'     => 'contact-form-7-mailchimp-extension',
							'plugin_basename' => SPARTAN_MCE_PLUGIN_BASENAME,
							'reasons'         => array(
								array(
									'id'          => 1,
									'text'        => __( 'I found a better Mailchimp integration', 'chimpmatic-lite' ),
									'input_type'  => 'plugin-dropdown',
									'placeholder' => __( 'Select the plugin you are switching to', 'chimpmatic-lite' ),
									'max_length'  => 0,
								),
								array(
									'id'          => 2,
									'text'        => __( 'Missing features I need', 'chimpmatic-lite' ),
									'input_type'  => 'textfield',
									'placeholder' => __( 'What features would you like to see?', 'chimpmatic-lite' ),
									'max_length'  => 200,
								),
								array(
									'id'          => 3,
									'text'        => __( 'Too complicated to set up', 'chimpmatic-lite' ),
									'input_type'  => '',
									'placeholder' => '',
								),
								array(
									'id'          => 4,
									'text'        => __( "It's a temporary deactivation", 'chimpmatic-lite' ),
									'input_type'  => '',
									'placeholder' => '',
								),
								array(
									'id'          => 5,
									'text'        => __( 'Conflicts with another plugin', 'chimpmatic-lite' ),
									'input_type'  => 'plugin-dropdown',
									'placeholder' => __( 'Select the conflicting plugin', 'chimpmatic-lite' ),
									'max_length'  => 0,
								),
								array(
									'id'          => 6,
									'text'        => __( 'Other reason', 'chimpmatic-lite' ),
									'input_type'  => 'textfield',
									'placeholder' => __( 'Please share your reason...', 'chimpmatic-lite' ),
									'max_length'  => 200,
								),
							),
						)
					);

					$survey->init();
				}
			);
		}

		private function get_strings() {
			return array(
				'title'           => __( 'Quick Feedback', 'chimpmatic-lite' ),
				'description'     => __( 'If you have a moment, please let us know why you are deactivating ChimpMatic Lite:', 'chimpmatic-lite' ),
				'submitButton'    => __( 'Submit & Deactivate', 'chimpmatic-lite' ),
				'skipButton'      => __( 'Skip & Deactivate', 'chimpmatic-lite' ),
				'cancelButton'    => __( 'Cancel', 'chimpmatic-lite' ),
				'thankYou'        => __( 'Thank you for your feedback!', 'chimpmatic-lite' ),
				'deactivating'    => __( 'Deactivating plugin...', 'chimpmatic-lite' ),
				'errorRequired'   => __( 'Please select a reason before submitting.', 'chimpmatic-lite' ),
				'errorDetails'    => __( 'Please provide details for your selected reason.', 'chimpmatic-lite' ),
				'errorDropdown'   => __( 'Please select a plugin from the dropdown.', 'chimpmatic-lite' ),
				'errorSubmission' => __( 'Failed to submit feedback. The plugin will still be deactivated.', 'chimpmatic-lite' ),
				'closeLabel'      => __( 'Close dialog', 'chimpmatic-lite' ),
			);
		}
	}
}
