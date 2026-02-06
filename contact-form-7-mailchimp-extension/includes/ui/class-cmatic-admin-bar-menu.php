<?php
/**
 * Admin bar menu integration.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

class Cmatic_Admin_Bar_Menu {
	const MENU_IDENTIFIER = 'chimpmatic-menu';
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->register_hooks();
	}

	private function register_hooks() {
		add_action( 'admin_bar_menu', array( $this, 'add_menu' ), 95 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_footer', array( $this, 'render_upgrade_click_script' ) );
		add_action( 'wp_footer', array( $this, 'render_upgrade_click_script' ) );
	}

	private function can_show_menu() {
		return current_user_can( 'manage_options' ) && is_admin_bar_showing();
	}

	private function is_pro_active() {
		return function_exists( 'cmatic_is_blessed' ) && cmatic_is_blessed();
	}

	private function is_pro_installed_not_licensed() {
		if ( ! defined( 'CMATIC_VERSION' ) ) {
			return false;
		}
		return ! $this->is_pro_active();
	}

	private function has_plugin_update() {
		$updates = get_site_transient( 'update_plugins' );
		if ( ! $updates || ! isset( $updates->response ) ) {
			return false;
		}
		return isset( $updates->response['contact-form-7-mailchimp-extension/chimpmatic-lite.php'] )
			|| isset( $updates->response['chimpmatic/chimpmatic.php'] );
	}

	private function should_show_upgrade_badge() {
		return ! Cmatic_Options_Repository::get_option( 'ui.upgrade_clicked', false );
	}

	private function get_license_activation_url() {
		return admin_url( 'admin.php?page=wpcf7-integration&service=0_chimpmatic&action=setup' );
	}

	private function get_update_url() {
		return admin_url( 'plugins.php?plugin_status=upgrade' );
	}

	public function add_menu( WP_Admin_Bar $wp_admin_bar ) {
		if ( ! $this->can_show_menu() ) {
			return;
		}

		$this->add_root_menu( $wp_admin_bar );
		$this->add_submenu_items( $wp_admin_bar );
	}

	private function add_root_menu( WP_Admin_Bar $wp_admin_bar ) {
		$badge_count = 0;

		if ( $this->has_plugin_update() ) {
			++$badge_count;
		}

		if ( ! $this->is_pro_active() && $this->should_show_upgrade_badge() ) {
			++$badge_count;
		}

		$icon_svg    = 'data:image/svg+xml;base64,' . $this->get_icon_base64();
		$icon_styles = 'width:26px;height:30px;float:left;background:url(\'' . esc_attr( $icon_svg ) . '\') center/20px no-repeat;';

		$title  = '<div id="cmatic-ab-icon" class="ab-item cmatic-logo svg" style="' . esc_attr( $icon_styles ) . '">';
		$title .= '<span class="screen-reader-text">' . esc_html__( 'Chimpmatic Lite', 'chimpmatic-lite' ) . '</span>';
		$title .= '</div>';

		if ( $badge_count > 0 ) {
			$title .= $this->get_notification_counter( $badge_count );
		}

		$wp_admin_bar->add_menu(
			array(
				'id'    => self::MENU_IDENTIFIER,
				'title' => $title,
				'href'  => false,
			)
		);
	}

	private function add_submenu_items( WP_Admin_Bar $wp_admin_bar ) {
		if ( $this->has_plugin_update() ) {
			$wp_admin_bar->add_menu(
				array(
					'parent' => self::MENU_IDENTIFIER,
					'id'     => 'chimpmatic-update',
					'title'  => esc_html__( 'Update Available', 'chimpmatic-lite' ) . ' ' . $this->get_notification_counter( 1 ),
					'href'   => $this->get_update_url(),
					'meta'   => array(
						'title' => esc_attr__( 'Update strongly recommended', 'chimpmatic-lite' ),
					),
				)
			);
		}

		if ( $this->is_pro_installed_not_licensed() ) {
			$wp_admin_bar->add_menu(
				array(
					'parent' => self::MENU_IDENTIFIER,
					'id'     => 'chimpmatic-activate-license',
					'title'  => esc_html__( 'Activate License', 'chimpmatic-lite' ),
					'href'   => $this->get_license_activation_url(),
				)
			);
		}

		// Add Forms submenu with all CF7 forms.
		$this->add_forms_submenu( $wp_admin_bar );

		$wp_admin_bar->add_menu(
			array(
				'parent' => self::MENU_IDENTIFIER,
				'id'     => 'chimpmatic-docs',
				'title'  => esc_html__( 'Documentation', 'chimpmatic-lite' ),
				'href'   => Cmatic_Pursuit::adminbar( 'docs', 'menu_docs' ),
				'meta'   => array(
					'target' => '_blank',
					'rel'    => 'noopener noreferrer',
				),
			)
		);

		$wp_admin_bar->add_menu(
			array(
				'parent' => self::MENU_IDENTIFIER,
				'id'     => 'chimpmatic-support',
				'title'  => esc_html__( 'Support', 'chimpmatic-lite' ),
				'href'   => Cmatic_Pursuit::adminbar( 'support', 'menu_support' ),
				'meta'   => array(
					'target' => '_blank',
					'rel'    => 'noopener noreferrer',
				),
			)
		);

		$wp_admin_bar->add_menu(
			array(
				'parent' => self::MENU_IDENTIFIER,
				'id'     => 'chimpmatic-reviews',
				'title'  => esc_html__( 'Reviews', 'chimpmatic-lite' ),
				'href'   => 'https://wordpress.org/support/plugin/contact-form-7-mailchimp-extension/reviews/?filter=5',
				'meta'   => array(
					'target' => '_blank',
					'rel'    => 'noopener noreferrer',
				),
			)
		);

		if ( ! $this->is_pro_active() ) {
			$upgrade_title = esc_html__( 'Upgrade to Pro', 'chimpmatic-lite' );

			if ( $this->should_show_upgrade_badge() ) {
				$upgrade_title .= ' ' . $this->get_notification_counter( 1 );
			}

			$wp_admin_bar->add_menu(
				array(
					'parent' => self::MENU_IDENTIFIER,
					'id'     => 'chimpmatic-upgrade',
					'title'  => $upgrade_title,
					'href'   => Cmatic_Pursuit::adminbar( 'pricing', 'menu_upgrade' ),
					'meta'   => array(
						'target' => '_blank',
						'rel'    => 'noopener noreferrer',
					),
				)
			);
		}
	}

	private function add_forms_submenu( WP_Admin_Bar $wp_admin_bar ) {
		// Check if CF7 is active.
		if ( ! class_exists( 'WPCF7_ContactForm' ) ) {
			return;
		}

		// Get all CF7 forms.
		$forms = WPCF7_ContactForm::find( array( 'posts_per_page' => -1 ) );

		if ( empty( $forms ) ) {
			return;
		}

		// Add "Form Settings" section header (non-clickable label).
		$wp_admin_bar->add_menu(
			array(
				'parent' => self::MENU_IDENTIFIER,
				'id'     => 'chimpmatic-forms-header',
				'title'  => esc_html__( 'Form Settings', 'chimpmatic-lite' ),
				'href'   => false,
			)
		);

		// Add each form directly to main menu (flat, not nested).
		foreach ( $forms as $form ) {
			$form_url = admin_url(
				sprintf(
					'admin.php?page=wpcf7&post=%d&action=edit&active-tab=Chimpmatic',
					$form->id()
				)
			);

			// Check API connection status for this form.
			$api_status = $this->get_form_api_status( $form->id() );

			$wp_admin_bar->add_menu(
				array(
					'parent' => self::MENU_IDENTIFIER,
					'id'     => 'chimpmatic-form-' . $form->id(),
					'title'  => '&nbsp;&nbsp;' . esc_html( $form->title() ) . $api_status,
					'href'   => $form_url,
					'meta'   => array(
						'class' => 'cmatic-form-item',
					),
				)
			);
		}
	}

	private function get_form_api_status( $form_id ) {
		$cf7_mch = get_option( 'cf7_mch_' . $form_id, array() );

		// Check if API is validated and a list/audience is selected.
		$is_connected = ! empty( $cf7_mch['api-validation'] )
			&& 1 == $cf7_mch['api-validation']
			&& ! empty( $cf7_mch['list'] );

		if ( $is_connected ) {
			return '<span class="cmatic-api-status cmatic-api-connected" title="' . esc_attr__( 'Connected to Mailchimp API', 'chimpmatic-lite' ) . '">' . esc_html__( 'API', 'chimpmatic-lite' ) . '</span>';
		}

		return '<span class="cmatic-api-status cmatic-api-disconnected" title="' . esc_attr__( 'Not connected to Mailchimp API', 'chimpmatic-lite' ) . '">' . esc_html__( 'API', 'chimpmatic-lite' ) . '</span>';
	}

	private function get_notification_counter( $count ) {
		if ( $count < 1 ) {
			return '';
		}

		$screen_reader_text = sprintf(
			/* translators: %s: number of notifications */
			_n( '%s notification', '%s notifications', $count, 'chimpmatic-lite' ),
			number_format_i18n( $count )
		);

		return sprintf(
			'<div class="wp-core-ui wp-ui-notification cmatic-issue-counter"><span aria-hidden="true">%1$d</span><span class="screen-reader-text">%2$s</span></div>',
			(int) $count,
			esc_html( $screen_reader_text )
		);
	}

	private function get_settings_url() {
		if ( class_exists( 'Cmatic_Plugin_Links' ) ) {
			$url = Cmatic_Plugin_Links::get_settings_url();
			if ( ! empty( $url ) ) {
				return $url;
			}
		}

		return admin_url( 'admin.php?page=wpcf7' );
	}

	public function enqueue_assets() {
		if ( ! $this->can_show_menu() ) {
			return;
		}

		$css = $this->get_inline_css();
		wp_add_inline_style( 'admin-bar', $css );
	}

	private function get_inline_css() {
		$icon_base64 = $this->get_icon_base64();

		$css = '
			#wpadminbar .cmatic-logo.svg {
				background-image: url("data:image/svg+xml;base64,' . $icon_base64 . '");
				background-position: center;
				background-repeat: no-repeat;
				background-size: 20px;
				float: left;
				height: 30px;
				width: 26px;
				margin-top: 2px;
			}
			#wpadminbar #wp-admin-bar-chimpmatic-menu .cmatic-form-item .ab-item {
				background-color: rgba(255,255,255,0.04) !important;
				padding-left: 20px !important;
				display: flex;
				justify-content: space-between;
				align-items: center;
			}
			#wpadminbar #wp-admin-bar-chimpmatic-menu .cmatic-form-item .ab-item:hover {
				background-color: rgba(255,255,255,0.1) !important;
			}
			#wpadminbar .cmatic-api-status {
				font-size: 10px;
				font-weight: 600;
				text-transform: uppercase;
				letter-spacing: 0.5px;
				margin-left: 15px;
				flex-shrink: 0;
			}
			#wpadminbar .cmatic-api-connected {
				color: #00ba37;
			}
			#wpadminbar .cmatic-api-disconnected {
				color: #787c82;
			}
			#wpadminbar .cmatic-issue-counter {
				background-color: #d63638;
				border-radius: 9px;
				color: #fff;
				display: inline;
				padding: 1px 7px 1px 6px !important;
			}
			#wpadminbar .quicklinks #wp-admin-bar-chimpmatic-menu #wp-admin-bar-chimpmatic-menu-default li#wp-admin-bar-chimpmatic-upgrade {
				display: flex;
			}
			#wpadminbar .quicklinks #wp-admin-bar-chimpmatic-menu #wp-admin-bar-chimpmatic-menu-default li#wp-admin-bar-chimpmatic-upgrade .ab-item {
				align-items: center;
				border-color: transparent;
				border-radius: 6px;
				cursor: pointer;
				display: inline-flex;
				justify-content: center;
				margin: 8px 12px;
				background-color: #00be28;
				font-size: 13px;
				font-weight: 500;
				padding: 6px 10px;
				text-align: center;
				text-decoration: none;
				color: #fff !important;
			}
			#wpadminbar .quicklinks #wp-admin-bar-chimpmatic-menu #wp-admin-bar-chimpmatic-menu-default li#wp-admin-bar-chimpmatic-upgrade .ab-item:hover {
				background-color: #00a522;
				color: #fff !important;
			}
			#wpadminbar #wp-admin-bar-chimpmatic-upgrade .cmatic-issue-counter {
				width: 18px;
				height: 18px;
				min-width: 18px;
				border-radius: 50%;
				padding: 0 !important;
				display: inline-flex;
				align-items: center;
				justify-content: center;
				margin-left: 6px;
				font-size: 11px;
				line-height: 1;
			}
			@media screen and (max-width: 782px) {
				#wpadminbar .cmatic-logo.svg {
					background-position: center 8px;
					background-size: 30px;
					height: 46px;
					width: 52px;
				}
				#wpadminbar .cmatic-logo + .cmatic-issue-counter {
					margin-left: -5px;
					margin-right: 10px;
				}
			}
		';

		return $css;
	}

	private function get_icon_base64() {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%2382878c">'
			. '<path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>'
			. '</svg>';

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		return base64_encode( $svg );
	}

	public function render_upgrade_click_script() {
		if ( ! $this->can_show_menu() ) {
			return;
		}

		if ( $this->is_pro_active() || ! $this->should_show_upgrade_badge() ) {
			return;
		}

		?>
		<script>
		(function() {
			var upgradeLink = document.querySelector('#wp-admin-bar-chimpmatic-upgrade > a');
			if (upgradeLink) {
				upgradeLink.addEventListener('click', function() {
					fetch('<?php echo esc_url( rest_url( 'chimpmatic-lite/v1/notices/dismiss' ) ); ?>', {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>'
						},
						body: JSON.stringify({ notice_id: 'upgrade' })
					});
				});
			}
		})();
		</script>
		<?php
	}
}
