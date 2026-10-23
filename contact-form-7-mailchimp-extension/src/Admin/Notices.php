<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    hello@chimpmatic.com
 * @copyright 2014-2026 Contact Form 7 Extension
 * @license   GPL-3.0+
 */

namespace Cmatic\Lite\Admin;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing -- Typed signatures follow the existing Lite class convention.
final class Notices {
	private const META          = 'cmatic_lite_notice_';
	private const FIRST_SEEN    = 'cmatic_lite_first_seen';
	private const LAST          = 'cmatic_lite_notice_last';
	private const HANDLE        = 'cmatic-lite-notices';
	private const SNOOZE        = 30 * DAY_IN_SECONDS;
	private const GROUPS        = array( 'failing', 'onboarding', 'review', 'upgrade' );
	private static bool $booted = false;
	/** @var array<string,string> */
	private static array $seen = array();

	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;
		add_action( 'admin_notices', array( self::class, 'render' ) );
		add_action( 'network_admin_notices', array( self::class, 'render' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	public static function mode(): string {
		if ( class_exists( '\\Cmatic\\Pro\\Kernel\\Engine', false ) && \Cmatic\Pro\Kernel\Engine::ready() ) {
			return 'new';
		}
		return self::plugin_state( 'chimpmatic/chimpmatic.php' )['active'] ? 'old' : 'free';
	}

	private static function allowed(): bool {
		return current_user_can( 'manage_options' ) && 'new' !== self::mode();
	}

	private static function on_our_screen(): bool {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen instanceof \WP_Screen && ( in_array( $screen->id, array( 'dashboard', 'plugins', 'plugins-network' ), true ) || false !== strpos( $screen->id, 'wpcf7' ) );
	}

	/** @return list<array<string,mixed>> */
	public static function entries(): array {
		if ( ! self::allowed() ) {
			return array();
		}
		if ( 'old' === self::mode() ) {
			return array(
				self::entry(
					'pro-update',
					'failing',
					100,
					__( 'Update your Pro plugin', 'contact-form-7-mailchimp-extension' ),
					__( 'A newer version of your Pro plugin is ready for your forms. Update to keep every Pro feature working with this plugin. Your forms and saved settings stay in place.', 'contact-form-7-mailchimp-extension' ),
					array( self::action( __( 'Update your Pro plugin', 'contact-form-7-mailchimp-extension' ), admin_url( 'plugins.php' ), 'primary' ) )
				),
			);
		}
		$syncs   = self::syncs();
		$entries = array(
			self::cf7_entry( self::plugin_state( 'contact-form-7/wp-contact-form-7.php' ) ),
			self::entry(
				'welcome',
				'onboarding',
				0,
				__( 'Welcome to Contact Form 7 Extension', 'contact-form-7-mailchimp-extension' ),
				__( "Turn every form submission into a new subscriber on Mailchimp, Brevo, MailerLite, Klaviyo, GetResponse, EmailOctopus, Campaign Monitor or Moosend. Open your form's Contact Form 7 Extension tab, connect your email service, and choose a list. You can use a different service on each form.", 'contact-form-7-mailchimp-extension' ),
				array( self::action( __( 'Connect your first form', 'contact-form-7-mailchimp-extension' ), self::panel_url(), 'primary' ), self::action( __( 'Read the setup guide', 'contact-form-7-mailchimp-extension' ), \Cmatic_Pursuit::url( 'https://chimpmatic.com/integrations', 'banner', 'setup_guide', 'welcome' ) ), self::action( __( 'Maybe later', 'contact-form-7-mailchimp-extension' ), '', 'link', 'snooze' ) ),
				self::days_installed() <= 30 && 0 === $syncs
			),
			self::entry(
				'review',
				'review',
				$syncs,
				__( 'Enjoying Contact Form 7 Extension?', 'contact-form-7-mailchimp-extension' ),
				/* translators: %s: number of successful signups. */
				sprintf( __( 'This plugin has sent %s subscribers from your forms. If it has saved you some work, a review helps other people find it. If it has not, tell us instead and we will fix it.', 'contact-form-7-mailchimp-extension' ), number_format_i18n( $syncs ) ),
				array( self::action( __( 'Leave a review', 'contact-form-7-mailchimp-extension' ), 'https://wordpress.org/support/plugin/contact-form-7-mailchimp-extension/reviews', 'primary', 'dismiss' ), self::action( __( 'Maybe later', 'contact-form-7-mailchimp-extension' ), '', 'link', 'snooze' ), self::action( __( 'I already did', 'contact-form-7-mailchimp-extension' ), '', 'link', 'dismiss' ) ),
				self::days_installed() >= 14 && $syncs >= 25
			),
			self::entry(
				'upgrade',
				'upgrade',
				0,
				__( 'Give every signup a more personal welcome', 'contact-form-7-mailchimp-extension' ),
				__( 'Send each signup to several lists, route people based on their answers, add tags automatically on all 8 platforms, and bring along every field with ChimpMatic Pro. Your forms and saved settings stay with you.', 'contact-form-7-mailchimp-extension' ),
				array( self::action( __( 'Explore ChimpMatic Pro', 'contact-form-7-mailchimp-extension' ), \Cmatic_Pursuit::url( 'https://chimpmatic.com/pro', 'banner', 'lite_upgrade', 'upgrade' ), 'primary', 'snooze' ), self::action( __( 'Maybe later', 'contact-form-7-mailchimp-extension' ), '', 'link', 'snooze' ) ),
				self::days_installed() >= 7
			),
		);
		foreach ( \Cmatic\Lite\Submission\Stats::all() as $form_id => $stat ) {
			if ( $form_id > 0 && \Cmatic\Lite\Submission\Stats::is_failing( $stat ) && current_user_can( 'wpcf7_edit_contact_form', $form_id ) ) {
				$entries[] = self::failing_entry( $form_id, $stat );
			}
		}
		return array_merge( $entries, \Cmatic_Lite_Esp_Degrade_Notice::entries() );
	}

	/**
	 * @param list<array<string,string>> $actions
	 * @return array<string,mixed>
	 */
	public static function entry( string $id, string $group, int $weight, string $title, string $message, array $actions, bool $when = true ): array {
		return array(
			'id'       => $id,
			'group'    => $group,
			'weight'   => $weight,
			'title'    => $title,
			'message'  => $message,
			'actions'  => $actions,
			'when'     => $when,
			'modifier' => 'failing' === $group ? 'failing' : ( 'onboarding' === $group ? 'onboarding' : '' ),
			'top'      => true,
		);
	}

	/** @return array<string,mixed>|null */
	public static function due(): ?array {
		if ( ! self::allowed() ) {
			return null;
		}
		if ( 'free' === self::mode() ) {
			add_option( self::FIRST_SEEN, time(), '', false );
		}
		$best = array();
		foreach ( self::entries() as $entry ) {
			$id    = self::str( $entry['id'] );
			$state = self::state( $id );
			if ( ! $entry['when'] || ! empty( $state['dismissed'] ) || self::int( $state['until'] ?? 0 ) > time() || ( 'upgrade' === $id && self::int( $state['count'] ?? 0 ) >= 3 ) ) {
				continue;
			}
			$group = self::str( $entry['group'] );
			if ( ! isset( $best[ $group ] ) || $entry['weight'] > $best[ $group ]['weight'] ) {
				$best[ $group ] = $entry;
			}
		}
		foreach ( self::GROUPS as $group ) {
			if ( isset( $best[ $group ] ) ) {
				$last = self::arr( get_user_meta( get_current_user_id(), self::LAST, true ) );
				if ( ( $last['id'] ?? '' ) !== $best[ $group ]['id'] && time() - self::int( $last['at'] ?? 0 ) < DAY_IN_SECONDS ) {
					return null;
				}
				return $best[ $group ];
			}
		}
		return null;
	}

	public static function render(): void {
		if ( ! self::allowed() || ! self::on_our_screen() ) {
			return;
		}
		$preview = self::preview();
		if ( array() !== $preview ) {
			foreach ( $preview as $entry ) {
				echo self::html( $entry ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			return;
		}
		$entry = self::due();
		if ( null !== $entry && ( 'upgrade' !== $entry['id'] || self::claim_upgrade() ) ) {
			echo self::html( $entry ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	private static function claim_upgrade(): bool {
		$state = self::state( 'upgrade' );
		if ( self::int( $state['count'] ?? 0 ) >= 3 || self::int( $state['until'] ?? 0 ) > time() ) {
			return false;
		}
		$claim = self::META . 'upgrade_claim_' . get_current_user_id() . '_' . ( self::int( $state['count'] ?? 0 ) + 1 );
		if ( ! add_option( $claim, time(), '', false ) ) {
			return false;
		}
		return self::save(
			'upgrade',
			array(
				'count' => self::int( $state['count'] ?? 0 ) + 1,
				'until' => time() + self::SNOOZE,
			),
			$state
		);
	}

	/**
	 * @param array<string,mixed> $state
	 * @param array<string,mixed> $previous
	 */
	private static function save( string $id, array $state, array $previous ): bool {
		$key = self::META . $id;
		if ( ! metadata_exists( 'user', get_current_user_id(), $key ) ) {
			return false !== add_user_meta( get_current_user_id(), $key, $state, true );
		}
		return false !== update_user_meta( get_current_user_id(), $key, $state, $previous );
	}

	/** @return array<string,mixed> */
	private static function state( string $id ): array {
		return self::arr( get_user_meta( get_current_user_id(), self::META . $id, true ) );
	}

	public static function act( string $id, string $act ): bool {
		if ( ! self::allowed() || ! in_array( $act, array( 'dismiss', 'snooze', 'snooze-day' ), true ) ) {
			return false;
		}
		$entries = array_column( self::entries(), null, 'id' );
		if ( ! isset( $entries[ $id ] ) ) {
			return false;
		}
		$old   = self::state( $id );
		$state = 'dismiss' === $act ? array( 'dismissed' => true ) : array( 'until' => time() + ( 'snooze-day' === $act ? DAY_IN_SECONDS : self::SNOOZE ) );
		if ( 'upgrade' === $id ) {
			$state = array(
				'count' => self::int( $old['count'] ?? 0 ),
				'until' => max( time() + self::SNOOZE, self::int( $old['until'] ?? 0 ) ),
			);
		}
		if ( $state !== $old && ! self::save( $id, $state, $old ) ) {
			return false;
		}
		update_user_meta(
			get_current_user_id(),
			self::LAST,
			array(
				'id' => $id,
				'at' => time(),
			)
		);
		return true;
	}

	public static function routes(): void {
		if ( 'new' === self::mode() ) {
			return;
		}
		register_rest_route(
			'chimpmatic-lite/v1',
			'/notices/act',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'rest_act' ),
				'permission_callback' => array( '\\Cmatic_Rest_Permissions', 'admin' ),
			)
		);
	}

	public static function rest_act( \WP_REST_Request $request ): \WP_REST_Response {
		$done = self::act( self::str( $request->get_param( 'id' ) ), self::str( $request->get_param( 'act' ) ) );
		return new \WP_REST_Response( array( 'success' => $done ), $done ? 200 : 400 );
	}

	public static function enqueue(): void {
		if ( ! self::allowed() || ! self::on_our_screen() || ( null === self::due() && array() === self::preview() ) ) {
			return;
		}
		wp_enqueue_style( self::HANDLE, SPARTAN_MCE_PLUGIN_URL . 'assets/css/cmatic-lite-notices.css', array(), self::version( SPARTAN_MCE_PLUGIN_DIR . 'assets/css/cmatic-lite-notices.css' ) );
		wp_enqueue_script( self::HANDLE, SPARTAN_MCE_PLUGIN_URL . 'assets/js/cmatic-lite-notices.js', array(), self::version( SPARTAN_MCE_PLUGIN_DIR . 'assets/js/cmatic-lite-notices.js' ), true );
		wp_localize_script(
			self::HANDLE,
			'cmaticLiteNotices',
			array(
				'url'     => esc_url_raw( rest_url( 'chimpmatic-lite/v1/notices/act' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'preview' => array() !== self::preview(),
			)
		);
	}

	/** @return list<array<string,mixed>> */
	public static function preview(): array {
		if ( ! self::allowed() || 'all' !== self::get( 'cmatic_lite_notice' ) ) {
			return array();
		}
		if ( 'old' === self::mode() ) {
			return self::entries();
		}
		$entries   = array_values(
			array_filter(
				self::entries(),
				static function ( array $entry ): bool {
					return 0 !== strpos( self::str( $entry['id'] ), 'cf7-' );
				}
			)
		);
		$entries[] = self::failing_entry(
			self::first_form_id(),
			array(
				'provider'   => 'mailchimp',
				'last_error' => __( 'Check the API key and selected list, then try again.', 'contact-form-7-mailchimp-extension' ),
			)
		);
		foreach ( array( array( false, false, '' ), array( true, false, '6.1.7' ), array( true, true, '5.7' ) ) as $state ) {
			$entries[] = self::cf7_entry(
				array(
					'installed' => $state[0],
					'active'    => $state[1],
					'version'   => $state[2],
				)
			);
		}
		return $entries;
	}

	private static function dismiss_action( array $entry ): string {
		return 'upgrade' === $entry['id'] ? 'snooze' : ( 'failing' === $entry['group'] ? 'snooze-day' : 'dismiss' );
	}

	public static function days_installed(): int {
		$since = self::int( get_option( self::FIRST_SEEN, 0 ) );
		return $since > 0 ? (int) floor( ( time() - $since ) / DAY_IN_SECONDS ) : 0;
	}

	public static function syncs(): int {
		return array_sum(
			array_map(
				static function ( array $stat ): int {
					return self::int( $stat['ok'] ?? 0 );
				},
				\Cmatic\Lite\Submission\Stats::all()
			)
		);
	}

	/** @param array<string,mixed> $stat
	 * @return array<string,mixed>
	 */
	private static function failing_entry( int $form_id, array $stat ): array {
		$provider   = self::str( $stat['provider'] ?? '' );
		$definition = \Cmatic_Lite_Esp_Manifest::get( $provider );
		$label      = self::str( $definition['label'] ?? $provider );
		$title      = get_the_title( $form_id ) ?: __( 'Your contact form', 'contact-form-7-mailchimp-extension' );
		$error      = \Cmatic\Lite\Submission\Stats::redact( self::str( $stat['last_error'] ?? '' ) );
		/* translators: 1: provider name, 2: redacted provider error. */
		$message = sprintf( __( 'Recent submissions from this form are not reaching %1$s. %1$s said: "%2$s" Open the form\'s Contact Form 7 Extension tab to fix the connection.', 'contact-form-7-mailchimp-extension' ), $label, $error ?: __( 'Check the connection and selected list.', 'contact-form-7-mailchimp-extension' ) );
		/* translators: 1: form title, 2: provider name. */
		return self::entry( 'sync-failing:' . $form_id, 'failing', 40, sprintf( __( '"%1$s" is not reaching %2$s', 'contact-form-7-mailchimp-extension' ), $title, $label ), $message, array( self::action( __( "Open the form's Contact Form 7 Extension tab", 'contact-form-7-mailchimp-extension' ), self::form_url( $form_id ), 'primary' ), self::action( __( 'Remind me later', 'contact-form-7-mailchimp-extension' ), '', 'link', 'snooze-day' ) ) );
	}

	/** @param array{installed:bool,active:bool,version:string} $plugin
	 * @return array<string,mixed>
	 */
	private static function cf7_entry( array $plugin ): array {
		$id           = ! $plugin['installed'] ? 'missing' : ( ! $plugin['active'] ? 'inactive' : 'outdated' );
		$labels       = array(
			'missing'  => __( 'Install Contact Form 7', 'contact-form-7-mailchimp-extension' ),
			'inactive' => __( 'Activate Contact Form 7', 'contact-form-7-mailchimp-extension' ),
			'outdated' => __( 'Update Contact Form 7', 'contact-form-7-mailchimp-extension' ),
		);
		$messages     = array(
			'missing'  => __( 'This plugin connects Contact Form 7 forms to your email service. Install Contact Form 7 to start building forms.', 'contact-form-7-mailchimp-extension' ),
			'inactive' => __( 'Your forms and plugin settings are saved. Activate Contact Form 7 to start welcoming subscribers again.', 'contact-form-7-mailchimp-extension' ),
			/* translators: %s: installed Contact Form 7 version. */
			'outdated' => sprintf( __( 'This plugin needs Contact Form 7 version 5.8 or newer. This site has version %s. Update Contact Form 7 to keep your forms working smoothly.', 'contact-form-7-mailchimp-extension' ), $plugin['version'] ),
		);
		$file         = 'contact-form-7/wp-contact-form-7.php';
		$capabilities = array(
			'missing'  => 'install_plugins',
			'inactive' => 'activate_plugins',
			'outdated' => 'update_plugins',
		);
		$operations   = array(
			'missing'  => 'install-plugin',
			'inactive' => 'activate',
			'outdated' => 'upgrade-plugin',
		);
		$actions      = array();
		if ( current_user_can( $capabilities[ $id ] ) ) {
			$target    = 'missing' === $id ? 'contact-form-7' : $file;
			$page      = 'inactive' === $id ? 'plugins.php' : 'update.php';
			$nonce     = 'inactive' === $id ? 'activate-plugin' : $operations[ $id ];
			$url       = wp_nonce_url( admin_url( $page . '?action=' . $operations[ $id ] . '&plugin=' . rawurlencode( $target ) ), $nonce . '_' . $target );
			$actions[] = self::action( $labels[ $id ], $url, 'primary' );
		}
		return self::entry( 'cf7-' . $id, 'failing', 50, $labels[ $id ], $messages[ $id ], $actions, ! $plugin['installed'] || ! $plugin['active'] || version_compare( $plugin['version'], '5.8', '<' ) );
	}

	/**
	 * @return array{label:string,url:string,style:string,act:string}
	 */
	public static function action( string $label, string $url = '', string $style = 'secondary', string $act = '' ): array {
		return array(
			'label' => $label,
			'url'   => $url,
			'style' => $style,
			'act'   => $act,
		);
	}


	/**
	 * @param array<string,mixed> $entry
	 */
	public static function html( array $entry ): string {
		$modifier = self::str( $entry['modifier'] ?? '' );
		$out      = sprintf(
			'<div class="notice cmatic-lite-notice%s%s" role="status" data-cmatic-lite-notice="%s">',
			! empty( $entry['top'] ) ? ' inline' : '',
			'' === $modifier ? '' : ' cmatic-lite-notice--' . esc_attr( $modifier ),
			esc_attr( self::str( $entry['id'] ?? '' ) )
		);
		$title    = self::str( $entry['title'] ?? '' );
		if ( '' !== $title ) {
			$out .= '<p class="cmatic-lite-notice__title">' . esc_html( $title ) . '</p>';
		}
		$out  .= '<p class="cmatic-lite-notice__body">' . esc_html( self::str( $entry['message'] ?? '' ) ) . '</p>';
		$nudge = self::str( $entry['nudge'] ?? '' );
		if ( '' !== $nudge ) {
			$out .= '<p class="cmatic-lite-notice__body">' . esc_html( $nudge ) . '</p>';
		}
		$actions = self::arr( $entry['actions'] ?? null );
		if ( array() !== $actions ) {
			$out .= '<p class="cmatic-lite-notice__actions">';
			foreach ( $actions as $action ) {
				$action = self::arr( $action );
				$label  = self::str( $action['label'] ?? '' );
				$url    = self::str( $action['url'] ?? '' );
				$act    = self::str( $action['act'] ?? '' );
				if ( '' === $label ) {
					continue;
				}
				$class    = 'cmatic-lite-notice__action cmatic-lite-notice__action--' . sanitize_html_class( self::str( $action['style'] ?? 'secondary' ) );
				$data     = '' === $act ? '' : ' data-cmatic-lite-act="' . esc_attr( $act ) . '"';
				$external = self::external_url( $url ) ? ' target="_blank" rel="noopener noreferrer"' : '';
				$out     .= '' !== $url
					? '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '"' . $data . $external . '>' . esc_html( $label ) . '</a>'
					: '<button type="button" class="' . esc_attr( $class ) . '"' . $data . '>' . esc_html( $label ) . '</button>';
			}
			$out .= '</p>';
		}

		$out .= '<button type="button" class="notice-dismiss" data-cmatic-lite-act="' . esc_attr( self::dismiss_action( $entry ) ) . '"><span class="screen-reader-text">' . esc_html__( 'Dismiss this notice.', 'contact-form-7-mailchimp-extension' ) . '</span></button>';

		return $out . '</div>';
	}


	private static function form_url( int $form_id ): string {
		return admin_url( 'admin.php?page=wpcf7&post=' . $form_id . '&action=edit&active-tab=Chimpmatic' );
	}


	private static function panel_url(): string {
		$form_id = self::int( self::get( 'post' ) );
		if ( 'wpcf7' === self::get( 'page' ) && $form_id > 0 ) {
			return self::form_url( $form_id );
		}
		$form_id = self::first_form_id();
		return $form_id ? self::form_url( $form_id ) : admin_url( 'admin.php?page=wpcf7-new&active-tab=Chimpmatic' );
	}


	private static function first_form_id(): int {
		$forms = get_posts(
			array(
				'post_type'      => 'wpcf7_contact_form',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);
		return $forms ? (int) $forms[0] : 0;
	}


	private static function external_url( string $url ): bool {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		return is_string( $host ) && ! in_array( strtolower( $host ), array( strtolower( (string) wp_parse_url( admin_url(), PHP_URL_HOST ) ), strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) ), true );
	}


	/** @return array{installed:bool,active:bool,version:string} */
	private static function plugin_state( string $file ): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugins = get_plugins();
		return array(
			'installed' => isset( $plugins[ $file ] ),
			'active'    => is_plugin_active( $file ),
			'version'   => self::str( $plugins[ $file ]['Version'] ?? '' ),
		);
	}


	private static function version( string $path, ?bool $debug = null ): string {
		$debug = $debug ?? ( defined( 'WP_DEBUG' ) && WP_DEBUG );
		if ( ! $debug && isset( self::$seen[ $path ] ) ) {
			return self::$seen[ $path ];
		}
		$parts = array( SPARTAN_MCE_VERSION );
		if ( is_readable( $path ) ) {
			$parts[] = (string) filemtime( $path );
			$parts[] = substr( (string) hash_file( 'sha256', $path ), 0, 8 );
		}
		if ( $debug ) {
			$parts[] = (string) time();
		}
		$version = implode( '-', $parts );
		if ( ! $debug ) {
			self::$seen[ $path ] = $version;
		}

		return $version;
	}


	/**
	 * @param mixed $value
	 */
	private static function str( $value ): string {
		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}


	/**
	 * @param mixed $value
	 */
	private static function int( $value ): int {
		return is_numeric( $value ) ? (int) $value : 0;
	}


	/**
	 * @param mixed $value
	 * @return array<array-key, mixed>
	 */
	private static function arr( $value ): array {
		return is_array( $value ) ? $value : array();
	}


	private static function get( string $key ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- single audited input gate; unslashed, narrowed and sanitized here.
		return sanitize_text_field( self::str( wp_unslash( $_GET[ $key ] ?? '' ) ) );
	}
}
