<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

final class Cmatic_Admin_Bar_Menu {
	private const MENU        = 'chimpmatic-menu';
	private const FORMS_CACHE = 'cmatic_ab_forms';
	private const CACHE_TTL   = 300;
	private const MAX_FORMS   = 30;

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_bar_menu', array( $this, 'add_menu' ), 95 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_footer', array( $this, 'render_upgrade_click_script' ) );
		add_action( 'wp_footer', array( $this, 'render_upgrade_click_script' ) );
		add_action( 'save_post_wpcf7_contact_form', array( __CLASS__, 'forget_forms' ) );
		add_action( 'deleted_post', array( __CLASS__, 'forget_forms' ) );
	}

	public static function forget_forms(): void {
		delete_transient( self::FORMS_CACHE );
	}

	private function can_show_menu() {
		return current_user_can( 'manage_options' ) && is_admin_bar_showing();
	}

	private function is_pro_active() {
		return function_exists( 'cmatic_is_blessed' ) && cmatic_is_blessed();
	}

	private function is_pro_installed_not_licensed() {
		return defined( 'CMATIC_VERSION' ) && ! $this->is_pro_active();
	}

	/**
	 * @return array<string,string>
	 */
	private function updates(): array {
		$updates = get_site_transient( 'update_plugins' );
		if ( ! is_object( $updates ) || ! isset( $updates->response ) || ! is_array( $updates->response ) ) {
			return array();
		}
		$found = array();
		foreach ( array( 'contact-form-7-mailchimp-extension/chimpmatic-lite.php' => __( 'Chimpmatic Lite', 'contact-form-7-mailchimp-extension' ), 'chimpmatic/chimpmatic.php' => __( 'Chimpmatic Pro', 'contact-form-7-mailchimp-extension' ) ) as $file => $name ) {
			if ( isset( $updates->response[ $file ] ) ) {
				$item           = $updates->response[ $file ];
				$version        = is_object( $item ) && isset( $item->new_version ) && is_scalar( $item->new_version ) ? (string) $item->new_version : '';
				$found[ $file ] = trim( $name . ' ' . $version );
			}
		}
		return $found;
	}

	private function should_show_upgrade_badge() {
		return ! Cmatic_Options_Repository::get_option( 'ui.upgrade_clicked', false );
	}

	public function add_menu( WP_Admin_Bar $wp_admin_bar ) {
		if ( ! $this->can_show_menu() ) {
			return;
		}
		$stats   = class_exists( 'Cmatic_Sync_Stats' ) ? Cmatic_Sync_Stats::all() : array();
		$forms   = self::forms();
		$updates = $this->updates();
		$failing = 0;
		foreach ( array_keys( $forms ) as $form_id ) {
			if ( isset( $stats[ $form_id ] ) && Cmatic_Sync_Stats::is_failing( $stats[ $form_id ] ) ) {
				++$failing;
			}
		}

		$wp_admin_bar->add_menu(
			array(
				'id'    => self::MENU,
				'title' => '<span class="cmatic-ab-icon"></span>'
					. '<span class="screen-reader-text">' . esc_html__( 'Chimpmatic', 'contact-form-7-mailchimp-extension' ) . '</span>'
					. self::failing_counter( $failing )
					. self::update_counter( count( $updates ) ),
				'href'  => false,
				'meta'  => array( 'title' => esc_attr__( 'Chimpmatic', 'contact-form-7-mailchimp-extension' ) ),
			)
		);

		$edition = defined( 'CMATIC_VERSION' )
			/* translators: %s: version number */
			? sprintf( __( 'Chimpmatic Pro %s', 'contact-form-7-mailchimp-extension' ), CMATIC_VERSION )
			/* translators: %s: version number */
			: sprintf( __( 'Chimpmatic Lite %s', 'contact-form-7-mailchimp-extension' ), SPARTAN_MCE_VERSION );
		$wp_admin_bar->add_menu(
			array(
				'parent' => self::MENU,
				'id'     => 'chimpmatic-brand',
				'title'  => '<span class="cmatic-ab-brand">' . esc_html__( 'Chimpmatic', 'contact-form-7-mailchimp-extension' ) . '</span>'
					. '<span class="cmatic-ab-plugin">' . esc_html( $edition ) . '</span>',
				'href'   => false,
				'meta'   => array( 'class' => 'cmatic-ab-header' ),
			)
		);

		$this->add_updates( $wp_admin_bar, $updates );
		$this->add_forms( $wp_admin_bar, $forms, $stats );
		$this->add_links( $wp_admin_bar );
	}

	/**
	 * @param array<string,string> $updates
	 */
	private function add_updates( WP_Admin_Bar $wp_admin_bar, array $updates ) {
		foreach ( $updates as $file => $label ) {
			$wp_admin_bar->add_menu(
				array(
					'parent' => self::MENU,
					'id'     => 'chimpmatic-update-' . sanitize_key( basename( dirname( $file ) ) ),
					/* translators: %s: plugin name and version */
					'title'  => esc_html( sprintf( __( 'Update available: %s', 'contact-form-7-mailchimp-extension' ), $label ) ),
					'href'   => admin_url( 'plugins.php?plugin_status=upgrade' ),
					'meta'   => array(
						'class' => 'cmatic-ab-update',
						'title' => esc_attr__( 'Open the plugin updates', 'contact-form-7-mailchimp-extension' ),
					),
				)
			);
		}
		if ( $this->is_pro_installed_not_licensed() ) {
			$wp_admin_bar->add_menu(
				array(
					'parent' => self::MENU,
					'id'     => 'chimpmatic-activate-license',
					'title'  => esc_html__( 'Activate your Pro license', 'contact-form-7-mailchimp-extension' ),
					'href'   => admin_url( 'admin.php?page=wpcf7-integration&service=0_chimpmatic&action=setup' ),
					'meta'   => array( 'class' => 'cmatic-ab-update' ),
				)
			);
		}
	}

	/**
	 * @param array<int,string>                $forms
	 * @param array<int,array<string,mixed>>   $stats
	 */
	private function add_forms( WP_Admin_Bar $wp_admin_bar, array $forms, array $stats ) {
		if ( array() === $forms ) {
			$wp_admin_bar->add_menu(
				array(
					'parent' => self::MENU,
					'id'     => 'chimpmatic-noforms',
					'title'  => esc_html__( 'No forms found', 'contact-form-7-mailchimp-extension' ),
					'href'   => false,
				)
			);
			return;
		}
		$wp_admin_bar->add_menu(
			array(
				'parent' => self::MENU,
				'id'     => 'chimpmatic-group-cf7',
				'title'  => esc_html__( 'Contact Form 7', 'contact-form-7-mailchimp-extension' ),
				'href'   => false,
				'meta'   => array( 'class' => 'cmatic-ab-group' ),
			)
		);
		foreach ( $forms as $form_id => $title ) {
			$entry = isset( $stats[ $form_id ] ) ? $stats[ $form_id ] : null;
			$wp_admin_bar->add_menu(
				array(
					'parent' => self::MENU,
					'id'     => 'chimpmatic-form-' . $form_id,
					'title'  => '<span class="cmatic-ab-name">' . esc_html( $title ) . '</span>' . self::pill( $entry ),
					'href'   => admin_url( 'admin.php?page=wpcf7&post=' . $form_id . '&action=edit&active-tab=Chimpmatic' ),
					'meta'   => array(
						'class' => 'cmatic-ab-form',
						'title' => self::tooltip( $entry ),
					),
				)
			);
		}
	}

	private function add_links( WP_Admin_Bar $wp_admin_bar ) {
		$links = array(
			'docs'    => array( __( 'Documentation', 'contact-form-7-mailchimp-extension' ), Cmatic_Pursuit::adminbar( 'help', 'menu_docs' ) ),
			'support' => array( __( 'Support', 'contact-form-7-mailchimp-extension' ), Cmatic_Pursuit::adminbar( 'support', 'menu_support' ) ),
			'reviews' => array( __( 'Review Chimpmatic', 'contact-form-7-mailchimp-extension' ), 'https://wordpress.org/support/plugin/contact-form-7-mailchimp-extension/reviews/' ),
		);
		foreach ( $links as $id => $link ) {
			$wp_admin_bar->add_menu(
				array(
					'parent' => self::MENU,
					'id'     => 'chimpmatic-' . $id,
					'title'  => esc_html( $link[0] ),
					'href'   => $link[1],
					'meta'   => array(
						'class'  => 'cmatic-ab-link',
						'target' => '_blank',
						'rel'    => 'noopener noreferrer',
					),
				)
			);
		}
		if ( ! $this->is_pro_active() ) {
			$title = esc_html__( 'Upgrade to Pro', 'contact-form-7-mailchimp-extension' );
			if ( $this->should_show_upgrade_badge() ) {
				$title .= ' ' . self::counter( 1, __( '1 notification', 'contact-form-7-mailchimp-extension' ), 'cmatic-ab-count--update' );
			}
			$wp_admin_bar->add_menu(
				array(
					'parent' => self::MENU,
					'id'     => 'chimpmatic-upgrade',
					'title'  => $title,
					'href'   => Cmatic_Pursuit::adminbar( 'pricing', 'menu_upgrade' ),
					'meta'   => array(
						'class'  => 'cmatic-ab-upgrade',
						'target' => '_blank',
						'rel'    => 'noopener noreferrer',
					),
				)
			);
		}
	}

	/**
	 * @return array<int,string>
	 */
	private static function forms(): array {
		$cached = get_transient( self::FORMS_CACHE );
		if ( is_array( $cached ) ) {
			$forms = array();
			foreach ( $cached as $id => $title ) {
				if ( is_numeric( $id ) && is_scalar( $title ) ) {
					$forms[ (int) $id ] = (string) $title;
				}
			}
			return $forms;
		}
		$forms = array();
		if ( class_exists( 'WPCF7_ContactForm' ) ) {
			foreach ( (array) WPCF7_ContactForm::find( array( 'posts_per_page' => self::MAX_FORMS ) ) as $form ) {
				if ( is_object( $form ) && method_exists( $form, 'id' ) && method_exists( $form, 'title' ) ) {
					$id = Cmatic_Sync_Stats::int( $form->id() );
					if ( $id > 0 ) {
						$title        = Cmatic_Sync_Stats::str( $form->title() );
						$forms[ $id ] = '' !== $title ? $title : sprintf( /* translators: %d: form id */ __( 'Form %d', 'contact-form-7-mailchimp-extension' ), $id );
					}
				}
			}
		}
		set_transient( self::FORMS_CACHE, $forms, self::CACHE_TTL );
		return $forms;
	}

	/**
	 * @param array<string,mixed>|null $entry
	 */
	private static function pill( $entry ): string {
		if ( null === $entry || ! Cmatic_Sync_Stats::has_counts( $entry ) ) {
			return '<span class="cmatic-ab-pill cmatic-ab-idle">' . esc_html__( 'no syncs yet', 'contact-form-7-mailchimp-extension' ) . '</span>';
		}
		$parts = array();
		$ok     = Cmatic_Sync_Stats::int( $entry['ok'] ?? 0 );
		$failed = Cmatic_Sync_Stats::int( $entry['failed'] ?? 0 );
		if ( $ok > 0 ) {
			$parts[] = '<span class="cmatic-ab-ok">' . esc_html( number_format_i18n( $ok ) ) . '&nbsp;&#10003;</span>';
		}
		if ( Cmatic_Sync_Stats::is_failing( $entry ) ) {
			$parts[] = '<span class="cmatic-ab-fail">' . esc_html( number_format_i18n( $failed ) ) . '&nbsp;&#10007;</span>';
		}
		return '<span class="cmatic-ab-pill">' . implode( ' ', $parts ) . '</span>';
	}

	/**
	 * @param array<string,mixed>|null $entry
	 */
	private static function tooltip( $entry ): string {
		if ( null === $entry || ! Cmatic_Sync_Stats::has_counts( $entry ) ) {
			return esc_attr__( 'No submissions have synced yet.', 'contact-form-7-mailchimp-extension' );
		}
		if ( Cmatic_Sync_Stats::is_failing( $entry ) ) {
			$error = Cmatic_Sync_Stats::str( $entry['last_error'] ?? '' );
			$error = '' !== $error ? $error : __( 'unknown', 'contact-form-7-mailchimp-extension' );
			/* translators: %s: the error the provider returned */
			return esc_attr( sprintf( __( 'Last error: %s', 'contact-form-7-mailchimp-extension' ), $error ) );
		}
		return esc_attr__( 'Syncing normally.', 'contact-form-7-mailchimp-extension' );
	}

	private static function failing_counter( int $count ): string {
		if ( $count < 1 ) {
			return '';
		}
		/* translators: %s: number of forms failing to sync */
		return self::counter( $count, sprintf( _n( '%s form is failing to sync', '%s forms are failing to sync', $count, 'contact-form-7-mailchimp-extension' ), number_format_i18n( $count ) ), '' );
	}

	private static function update_counter( int $count ): string {
		if ( $count < 1 ) {
			return '';
		}
		/* translators: %s: number of plugin updates */
		return self::counter( $count, sprintf( _n( '%s plugin update', '%s plugin updates', $count, 'contact-form-7-mailchimp-extension' ), number_format_i18n( $count ) ), 'cmatic-ab-count--update' );
	}

	private static function counter( int $count, string $label, string $extra_class ): string {
		return sprintf(
			'<span class="wp-core-ui wp-ui-notification cmatic-ab-count %3$s"><span aria-hidden="true">%1$d</span><span class="screen-reader-text">%2$s</span></span>',
			$count,
			esc_html( $label ),
			esc_attr( $extra_class )
		);
	}

	public function enqueue_assets() {
		if ( ! $this->can_show_menu() ) {
			return;
		}
		wp_add_inline_style( 'admin-bar', $this->css() );
	}

	private function css(): string {
		$icon = 'data:image/svg+xml;base64,' . base64_encode( // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Inline icon, not code.
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#ffffff">'
			. '<path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>'
		);
		$root = '#wpadminbar #wp-admin-bar-' . self::MENU;
		return '
		' . $root . ' .cmatic-ab-icon{background:url("' . $icon . '") center/18px no-repeat;width:26px;height:30px;float:left;margin-top:2px}
		' . $root . ' .cmatic-ab-count{background-color:#d63638;border-radius:9px;color:#fff;display:inline;margin-left:7px;padding:1px 7px 1px 6px!important}
		' . $root . ' .cmatic-ab-count--update{background-color:#2271b1}
		' . $root . ' .cmatic-ab-header>.ab-item{height:auto;line-height:1.5;padding:11px 12px 10px!important;border-bottom:1px solid rgba(255,255,255,.14);pointer-events:none}
		' . $root . ' .cmatic-ab-brand{color:#fff;font-weight:600;display:block}
		' . $root . ' .cmatic-ab-plugin{display:block;font-size:11px;color:#a7aaad;line-height:1.3}
		' . $root . ' .cmatic-ab-group>.ab-item{height:auto;line-height:1.4;padding:13px 12px 2px!important;font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#8f9296!important;pointer-events:none}
		' . $root . ' .cmatic-ab-form>.ab-item{display:flex;justify-content:space-between;align-items:center;gap:18px;padding-left:20px!important;background:rgba(255,255,255,.04)}
		' . $root . ' .cmatic-ab-form>.ab-item:hover{background:rgba(255,255,255,.1)}
		' . $root . ' .cmatic-ab-name{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:230px}
		' . $root . ' .cmatic-ab-pill{font-size:11px;font-weight:600;flex-shrink:0;white-space:nowrap}
		' . $root . ' .cmatic-ab-ok{color:#00ba37}
		' . $root . ' .cmatic-ab-fail{color:#ff6b6b}
		' . $root . ' .cmatic-ab-idle{color:#787c82;font-weight:400}
		' . $root . ' .cmatic-ab-update>.ab-item{color:#fff!important;font-weight:600}
		' . $root . ' .cmatic-ab-update>.ab-item:hover{background:rgba(255,255,255,.1)}
		' . $root . ' .cmatic-ab-link>.ab-item{border-top:1px solid rgba(255,255,255,.1)}
		' . $root . ' li.cmatic-ab-upgrade{display:flex}
		' . $root . ' li.cmatic-ab-upgrade>.ab-item{display:inline-flex;align-items:center;justify-content:center;width:100%;margin:8px 12px;padding:6px 10px;border-radius:6px;background-color:#00be28;color:#fff!important;font-size:13px;font-weight:500;text-align:center;text-decoration:none;cursor:pointer;height:auto;line-height:1.4}
		' . $root . ' li.cmatic-ab-upgrade>.ab-item:hover{background-color:#00a522;color:#fff!important}
		@media screen and (max-width:782px){
			' . $root . ' .cmatic-ab-icon{background-size:24px;width:52px;height:46px;margin-top:0}
		}
		';
	}

	public function render_upgrade_click_script() {
		if ( ! $this->can_show_menu() || $this->is_pro_active() || ! $this->should_show_upgrade_badge() ) {
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
