<?php
/**
 * Admin asset loader.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Cmatic_Asset_Loader' ) ) {

	class Cmatic_Asset_Loader {

		private static array $scripts = array();

		private static array $styles = array();

		public static function init(): void {
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_notices_script' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_cf7_frontend_styles' ) );
			add_filter( 'admin_body_class', array( __CLASS__, 'add_body_class' ) );
		}

		public static function enqueue_admin_assets( ?string $hook_suffix ): void {
			if ( null === $hook_suffix || false === strpos( $hook_suffix, 'wpcf7' ) ) {
				return;
			}

			self::enqueue_styles();
			self::enqueue_lite_js();

			$is_pro_installed = defined( 'CMATIC_VERSION' );
			$is_pro_blessed   = function_exists( 'cmatic_is_blessed' ) && cmatic_is_blessed();

			if ( $is_pro_installed ) {
				self::enqueue_pro_js( $is_pro_blessed );
			}
		}

		private static function enqueue_styles(): void {
			$css_file_path = SPARTAN_MCE_PLUGIN_DIR . 'assets/css/chimpmatic-lite.css';
			wp_enqueue_style(
				'chimpmatic-lite-css',
				SPARTAN_MCE_PLUGIN_URL . 'assets/css/chimpmatic-lite.css',
				array(),
				Cmatic_Buster::instance()->get_version( $css_file_path )
			);

			$modal_css_path = SPARTAN_MCE_PLUGIN_DIR . 'assets/css/chimpmatic-lite-deactivate.css';
			wp_enqueue_style(
				'cmatic-modal-css',
				SPARTAN_MCE_PLUGIN_URL . 'assets/css/chimpmatic-lite-deactivate.css',
				array(),
				Cmatic_Buster::instance()->get_version( $modal_css_path )
			);

			wp_enqueue_style( 'site-health' );

			self::$styles['chimpmatic-lite-css'] = $css_file_path;
			self::$styles['cmatic-modal-css']    = $modal_css_path;
		}

		private static function enqueue_lite_js(): void {
			$js_file_path = SPARTAN_MCE_PLUGIN_DIR . 'assets/js/chimpmatic-lite.js';
			wp_enqueue_script(
				'chimpmatic-lite-js',
				SPARTAN_MCE_PLUGIN_URL . 'assets/js/chimpmatic-lite.js',
				array(),
				Cmatic_Buster::instance()->get_version( $js_file_path ),
				true
			);

			$form_settings = self::get_form_settings();

			wp_localize_script(
				'chimpmatic-lite-js',
				'chimpmaticLite',
				array(
					'restUrl'         => esc_url_raw( rest_url( 'chimpmatic-lite/v1/' ) ),
					'restNonce'       => wp_create_nonce( 'wp_rest' ),
					'licenseResetUrl' => esc_url_raw( rest_url( 'chimpmatic-lite/v1/settings/reset' ) ),
					'nonce'           => wp_create_nonce( 'wp_rest' ),
					'pluginUrl'       => SPARTAN_MCE_PLUGIN_URL,
					'formId'          => $form_settings['form_id'],
					'mergeFields'     => $form_settings['merge_fields'],
					'loggingEnabled'  => $form_settings['logging_enabled'],
					'i18n'            => self::get_i18n_strings(),
				)
			);

			self::$scripts['chimpmatic-lite-js'] = $js_file_path;
		}

		private static function enqueue_pro_js( bool $is_pro_blessed ): void {
			$pro_js_path = SPARTAN_MCE_PLUGIN_DIR . 'assets/js/chimpmatic.js';

			wp_enqueue_script(
				'chimpmatic-pro',
				SPARTAN_MCE_PLUGIN_URL . 'assets/js/chimpmatic.js',
				array(),
				Cmatic_Buster::instance()->get_version( $pro_js_path ),
				true
			);

			wp_localize_script(
				'chimpmatic-pro',
				'chmConfig',
				array(
					'restUrl'   => rest_url( 'chimpmatic/v1/' ),
					'nonce'     => wp_create_nonce( 'wp_rest' ),
					'isBlessed' => $is_pro_blessed,
				)
			);

			wp_localize_script(
				'chimpmatic-pro',
				'wpApiSettings',
				array(
					'root'  => esc_url_raw( rest_url() ),
					'nonce' => wp_create_nonce( 'wp_rest' ),
				)
			);

			self::$scripts['chimpmatic-pro'] = $pro_js_path;
		}

		public static function enqueue_notices_script( ?string $hook_suffix ): void {
			if ( null === $hook_suffix || false === strpos( $hook_suffix, 'wpcf7' ) ) {
				return;
			}

			$notices_js_path = SPARTAN_MCE_PLUGIN_DIR . 'assets/js/chimpmatic-lite-notices.js';
			wp_enqueue_script(
				'chimpmatic-lite-notices',
				SPARTAN_MCE_PLUGIN_URL . 'assets/js/chimpmatic-lite-notices.js',
				array(),
				Cmatic_Buster::instance()->get_version( $notices_js_path ),
				true
			);

			wp_localize_script(
				'chimpmatic-lite-notices',
				'chimpmaticNotices',
				array(
					'restUrl'   => esc_url_raw( rest_url( 'chimpmatic-lite/v1' ) ),
					'restNonce' => wp_create_nonce( 'wp_rest' ),
				)
			);

			self::$scripts['chimpmatic-lite-notices'] = $notices_js_path;
		}

		public static function enqueue_cf7_frontend_styles( ?string $hook_suffix ): void {
			if ( null === $hook_suffix || 'toplevel_page_wpcf7' !== $hook_suffix ) {
				return;
			}

			$form_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
			if ( ! $form_id ) {
				return;
			}

			$cf7_path = WP_PLUGIN_DIR . '/contact-form-7/';
			$cf7_url  = plugins_url( '/', $cf7_path . 'wp-contact-form-7.php' );

			if ( ! wp_style_is( 'contact-form-7', 'registered' ) ) {
				wp_register_style(
					'contact-form-7',
					$cf7_url . 'includes/css/styles.css',
					array(),
					defined( 'WPCF7_VERSION' ) ? WPCF7_VERSION : '5.0',
					'all'
				);
			}

			wp_enqueue_style( 'contact-form-7' );
		}

		public static function add_body_class( ?string $classes ): string {
			$classes = $classes ?? '';
			$screen  = get_current_screen();

			if ( $screen && strpos( $screen->id, 'wpcf7' ) !== false ) {
				$classes .= ' chimpmatic-lite';

				if ( function_exists( 'cmatic_is_blessed' ) && cmatic_is_blessed() ) {
					$classes .= ' chimpmatic';
				}
			}

			return $classes;
		}

		private static function get_form_settings(): array {
			$form_id         = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
			$merge_fields    = array();
			$logging_enabled = false;

			if ( $form_id > 0 ) {
				$option_name = 'cf7_mch_' . $form_id;
				$cf7_mch     = get_option( $option_name, array() );

				if ( isset( $cf7_mch['merge_fields'] ) && is_array( $cf7_mch['merge_fields'] ) ) {
					$merge_fields = $cf7_mch['merge_fields'];
				}

				$logging_enabled = ! empty( $cf7_mch['logfileEnabled'] );
			}

			return array(
				'form_id'         => $form_id,
				'merge_fields'    => $merge_fields,
				'logging_enabled' => $logging_enabled,
			);
		}

		private static function get_i18n_strings(): array {
			return array(
				'loading'       => __( 'Loading...', 'chimpmatic-lite' ),
				'error'         => __( 'An error occurred. Check the browser console for details.', 'chimpmatic-lite' ),
				'apiKeyValid'   => __( 'API Connected', 'chimpmatic-lite' ),
				'apiKeyInvalid' => __( 'API Inactive', 'chimpmatic-lite' ),
			);
		}

		public static function get_registered_scripts(): array {
			return self::$scripts;
		}

		public static function get_registered_styles(): array {
			return self::$styles;
		}
	}
}
