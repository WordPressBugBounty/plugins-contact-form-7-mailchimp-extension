<?php
/**
 * Admin-wide license state banner.
 *
 * Renders only when ChimpMatic Pro is installed.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

final class Cmatic_License_Banner {

	private const AMNESTY_DEFAULT_UNTIL = 1788235199;

	private const CAMPAIGNS = array( 'activate', 'winback', 'renew' );

	private const META_KEY = 'cmatic_banner_dismissals';

	private const SNOOZE = array(
		'unlicensed'        => 7 * DAY_IN_SECONDS,
		'unlicensed_urgent' => DAY_IN_SECONDS,
		'unlicensed_over'   => DAY_IN_SECONDS,
		'expired'           => 30 * DAY_IN_SECONDS,
		'invalid'           => 7 * DAY_IN_SECONDS,
		'expiring'          => 30 * DAY_IN_SECONDS,
	);

	public static function init(): void {
		add_action( 'admin_notices', array( __CLASS__, 'render' ) );
	}

	/** Resolve the banner state, or an empty string when no banner applies. */
	public static function resolve_state(): string {
		if ( ! defined( 'CMATIC_VERSION' ) ) {
			return '';
		}

		$license_state = 'none';
		if ( class_exists( 'Cmatic_License_Validator' ) && method_exists( 'Cmatic_License_Validator', 'get_license_state' ) ) {
			$license_state = (string) Cmatic_License_Validator::get_license_state();
		}

		if ( 'active' === $license_state ) {
			$expires = self::license_expires_at();
			if ( $expires > 0 && ( $expires - time() ) <= 30 * DAY_IN_SECONDS && $expires > time() ) {
				return 'expiring';
			}
			return '';
		}

		if ( 'expired' === $license_state ) {
			return 'expired';
		}

		if ( 'invalid' === $license_state ) {
			return 'invalid';
		}

		$deadline = self::amnesty_until();
		if ( time() > $deadline ) {
			return 'unlicensed_over';
		}
		if ( ( $deadline - time() ) <= 14 * DAY_IN_SECONDS ) {
			return 'unlicensed_urgent';
		}
		return 'unlicensed';
	}

	private static function amnesty_until(): int {
		$cached = (int) get_option( 'chimpmatic_amnesty_until', 0 );
		return $cached > 0 ? $cached : self::AMNESTY_DEFAULT_UNTIL;
	}

	private static function license_expires_at(): int {
		if ( ! function_exists( 'chimpmatic_license' ) ) {
			return 0;
		}
		try {
			$activation = chimpmatic_license()->get_activation();
			if ( $activation && method_exists( $activation, 'get_expires_at' ) ) {
				return (int) $activation->get_expires_at();
			}
		} catch ( \Throwable $e ) {
			return 0;
		}
		return 0;
	}

	/** Resolve the current server-provided promotion. */
	private static function offer( string $kind ): array {
		$percent = 0;
		if ( class_exists( 'Cmatic_Pursuit' ) ) {
			$pricing = Cmatic_Pursuit::pricing();
			$percent = 'activate' === $kind
				? (int) ( $pricing['discount_percent'] ?? 0 )
				: (int) ( $pricing[ $kind . '_discount' ] ?? 0 );
		}
		return array(
			'kind'    => $kind,
			'percent' => $percent,
		);
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && ( $screen->is_block_editor() || false !== strpos( (string) $screen->id, 'wpcf7' ) ) ) {
			return;
		}

		$state = self::resolve_state();
		if ( '' === $state ) {
			return;
		}

		$dismissals = get_user_meta( get_current_user_id(), self::META_KEY, true );
		if ( is_array( $dismissals ) && isset( $dismissals[ $state ] ) && (int) $dismissals[ $state ] > time() ) {
			return;
		}

		$view = self::view_for( $state );
		if ( null === $view ) {
			return;
		}
		self::print_banner( $state, $view );
	}

	/** @return array{tone:string,message:string,coupon:?array,cta:string,cta_url:string,secondary:?string,secondary_url:?string,dismissible:bool}|null */
	private static function view_for( string $state ): ?array {
		$days_left = (int) max( 0, ceil( ( self::amnesty_until() - time() ) / DAY_IN_SECONDS ) );

		switch ( $state ) {
			case 'unlicensed':
			case 'unlicensed_urgent':
				$offer = self::offer( 'activate' );
				return array(
					'tone'          => 'unlicensed_urgent' === $state ? 'amber' : 'blue',
					'message'       => sprintf(
						/* translators: %d: days until the activation deadline */
						__( 'You are using Chimpmatic Pro without a license on file. Everything keeps working for %d more days. Make it official today and September 1st is just another day.', 'chimpmatic-lite' ),
						$days_left
					),
					'offer'         => $offer,
					'cta'           => __( 'Make it official', 'chimpmatic-lite' ),
					'cta_url'       => self::offer_url(
						'https://chimpmatic.com/pricing',
						'activate',
						'unlicensed_urgent' === $state ? 'banner_lastcall' : 'banner_hello'
					),
					'secondary'     => __( 'I already have a key', 'chimpmatic-lite' ),
					'secondary_url' => admin_url( 'admin.php?page=wpcf7' ),
					'dismissible'   => 'unlicensed' === $state,
				);

			case 'unlicensed_over':
				return array(
					'tone'          => 'red',
					'message'       => __( 'Chimpmatic Pro is paused on this site. All your settings are saved and nothing was lost. Add a license and everything picks up exactly where it left off.', 'chimpmatic-lite' ),
					'offer'         => self::offer( 'activate' ),
					'cta'           => __( 'Bring Pro back', 'chimpmatic-lite' ),
					'cta_url'       => self::offer_url( 'https://chimpmatic.com/pricing', 'activate', 'banner_freshstart' ),
					'secondary'     => __( 'Enter my license key', 'chimpmatic-lite' ),
					'secondary_url' => admin_url( 'admin.php?page=wpcf7' ),
					'dismissible'   => false,
				);

			case 'expired':
				$offer = self::offer( 'winback' );
				$msg   = $offer['percent'] > 0
					? sprintf(
						/* translators: %d: win-back discount percentage */
						__( 'Good to see you again! Your Chimpmatic Pro license ended a while ago, and we would love to have you back. Renew today for %d%% off, applied automatically at checkout. Everything is just as you left it.', 'chimpmatic-lite' ),
						$offer['percent']
					)
					: __( 'Good to see you again! Your Chimpmatic Pro license ended a while ago, and we would love to have you back. Renew today with a special welcome-back discount, applied automatically at checkout. Everything is just as you left it.', 'chimpmatic-lite' );
				return array(
					'tone'          => 'green',
					'message'       => $msg,
					'offer'         => $offer,
					'cta'           => __( 'Claim my welcome-back offer', 'chimpmatic-lite' ),
					'cta_url'       => self::offer_url( 'https://chimpmatic.com/my-account', 'winback', 'banner_welcomeback' ),
					'secondary'     => null,
					'secondary_url' => null,
					'dismissible'   => true,
				);

			case 'invalid':
				return array(
					'tone'          => 'amber',
					'message'       => __( 'Your Chimpmatic Pro license key is not connecting to your account. It may have been mistyped or used on another site. A quick fix keeps Pro features, updates, and support flowing.', 'chimpmatic-lite' ),
					'offer'         => self::offer( 'activate' ),
					'cta'           => __( 'Fix my license', 'chimpmatic-lite' ),
					'cta_url'       => self::offer_url( 'https://chimpmatic.com/pricing', 'activate', 'banner_keyhelp' ),
					'secondary'     => __( 'Re-enter my key', 'chimpmatic-lite' ),
					'secondary_url' => admin_url( 'admin.php?page=wpcf7' ),
					'dismissible'   => true,
				);

			case 'expiring':
				$offer   = self::offer( 'renew' );
				$expires = self::license_expires_at();
				$date    = esc_html( date_i18n( get_option( 'date_format' ), $expires ) );
				$msg     = $offer['percent'] > 0
					? sprintf(
						/* translators: 1: expiry date, 2: renewal discount percentage */
						__( 'Your Chimpmatic Pro license runs through %1$s. Renew early with %2$d%% off, applied automatically at checkout, and never miss an update.', 'chimpmatic-lite' ),
						$date,
						$offer['percent']
					)
					: sprintf(
						/* translators: %s: expiry date */
						__( 'Your Chimpmatic Pro license runs through %s. Renew early and never miss an update.', 'chimpmatic-lite' ),
						$date
					);
				return array(
					'tone'          => 'blue',
					'message'       => $msg,
					'offer'         => $offer,
					'cta'           => __( 'Lock in my renewal', 'chimpmatic-lite' ),
					'cta_url'       => self::offer_url( 'https://chimpmatic.com/my-account', 'renew', 'banner_earlybird' ),
					'secondary'     => null,
					'secondary_url' => null,
					'dismissible'   => true,
				);
		}

		return null;
	}

	private static function offer_url( string $base, string $campaign, string $content ): string {
		if ( ! class_exists( 'Cmatic_Pursuit' ) ) {
			return $base;
		}
		if ( 'activate' === $campaign ) {
			return add_query_arg(
				array( 'from' => $content ),
				Cmatic_Pursuit::promo_checkout( $content )
			);
		}
		$source = class_exists( 'Cmatic_Options_Repository' )
			? Cmatic_Options_Repository::get_option( 'install.id', '' )
			: '';
		$url    = Cmatic_Pursuit::url( $base, 'banner', $content, 'license_banner' );
		return add_query_arg(
			array(
				'source' => $source ? $source : 'lite-banner',
				'promo'  => $campaign,
			),
			$url
		);
	}

	private static function print_banner( string $state, array $view ): void {
		$tones               = array(
			'blue'  => array( '#2271b1', '#f0f6fb' ),
			'amber' => array( '#996800', '#fcf9e8' ),
			'red'   => array( '#b32d2e', '#fcf0f1' ),
			'green' => array( '#00753e', '#edfaef' ),
		);
		list( $accent, $bg ) = $tones[ $view['tone'] ] ?? $tones['blue'];
		$nonce               = wp_create_nonce( 'wp_rest' );
		?>
		<div class="cmatic-license-banner notice" id="cmatic-license-banner" data-state="<?php echo esc_attr( $state ); ?>"
			style="display:none;position:relative;border:1px solid <?php echo esc_attr( $accent ); ?>33;border-left:4px solid <?php echo esc_attr( $accent ); ?>;background:<?php echo esc_attr( $bg ); ?>;padding:14px 44px 14px 16px;margin:12px 20px 12px 2px;border-radius:4px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
			<span style="font-weight:700;color:<?php echo esc_attr( $accent ); ?>;white-space:nowrap;">Chimpmatic</span>
			<span style="flex:1 1 320px;min-width:240px;color:#1d2327;line-height:1.5;">
				<?php echo esc_html( $view['message'] ); ?>
				<?php if ( ! empty( $view['offer']['percent'] ) ) : ?>
					<span style="background:#fff;border:1px dashed <?php echo esc_attr( $accent ); ?>;border-radius:3px;padding:2px 8px;font-weight:700;color:<?php echo esc_attr( $accent ); ?>;margin-left:4px;white-space:nowrap;">
						<?php
						printf(
							/* translators: %d: live discount percentage */
							esc_html__( '%d%% off', 'chimpmatic-lite' ),
							(int) $view['offer']['percent']
						);
						?>
					</span>
				<?php endif; ?>
			</span>
			<span style="display:flex;align-items:center;gap:12px;white-space:nowrap;">
				<a href="<?php echo esc_url( $view['cta_url'] ); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary" style="background:<?php echo esc_attr( $accent ); ?>;border-color:<?php echo esc_attr( $accent ); ?>;">
					<?php echo esc_html( $view['cta'] ); ?>
				</a>
				<?php if ( ! empty( $view['secondary'] ) ) : ?>
					<a href="<?php echo esc_url( $view['secondary_url'] ); ?>" style="color:<?php echo esc_attr( $accent ); ?>;"><?php echo esc_html( $view['secondary'] ); ?></a>
				<?php endif; ?>
			</span>
			<?php if ( $view['dismissible'] ) : ?>
				<button type="button" class="cmatic-license-banner-dismiss" aria-label="<?php esc_attr_e( 'Dismiss this notice', 'chimpmatic-lite' ); ?>"
					style="position:absolute;top:8px;right:10px;background:none;border:none;cursor:pointer;color:#787c82;font-size:16px;line-height:1;padding:4px;">&#10005;</button>
			<?php endif; ?>
		</div>
		<script>
		(function () {
			var banner = document.getElementById('cmatic-license-banner');
			if (!banner) { return; }
			var state = banner.getAttribute('data-state');
			var lsKey = 'cmaticBannerDismiss:' + state;
			// Hide cached markup immediately, then persist the dismissal server-side.
			try {
				var until = parseInt(window.localStorage.getItem(lsKey) || '0', 10);
				if (until > Date.now()) {
					banner.remove();
					return;
				}
			} catch (e) {}
			banner.style.display = 'flex';
			var btn = banner.querySelector('.cmatic-license-banner-dismiss');
			if (!btn) { return; }
			btn.addEventListener('click', function () {
				banner.remove();
				var snoozeMs = <?php echo (int) ( self::SNOOZE[ $state ] ?? WEEK_IN_SECONDS ) * 1000; ?>;
				try { window.localStorage.setItem(lsKey, String(Date.now() + snoozeMs)); } catch (e) {}
				fetch('<?php echo esc_url_raw( rest_url( 'chimpmatic-lite/v1/notices/dismiss' ) ); ?>', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': '<?php echo esc_js( $nonce ); ?>' },
					body: JSON.stringify({ notice_id: 'license_banner', state: state })
				}).catch(function () {});
			});
		})();
		</script>
		<?php
	}

	/** Snooze a banner state for the current administrator. */
	public static function handle_dismiss( string $state ) {
		if ( ! isset( self::SNOOZE[ $state ] ) ) {
			return false;
		}
		$user_id    = get_current_user_id();
		$dismissals = get_user_meta( $user_id, self::META_KEY, true );
		if ( ! is_array( $dismissals ) ) {
			$dismissals = array();
		}
		$dismissals[ $state ] = time() + self::SNOOZE[ $state ];
		update_user_meta( $user_id, self::META_KEY, $dismissals );
		return $dismissals[ $state ];
	}
}
