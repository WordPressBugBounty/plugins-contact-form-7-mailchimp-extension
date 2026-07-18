<?php
/**
 * One-time administrator pointer to the existing Signls sharing setting.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

final class Cmatic_Lite_Signls_Consent_Notice {

	public const NOTICE_VERSION = 1;

	private const USER_META = 'cmatic_signls_consent_notice_dismissed';

	public static function init(): void {
		add_action( 'admin_notices', array( self::class, 'render' ) );
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) || 'unset' !== Cmatic_Lite_Signls_Privacy::consent_status() ) {
			return;
		}
		if ( (int) get_user_meta( get_current_user_id(), self::USER_META, true ) >= self::NOTICE_VERSION ) {
			return;
		}
		$settings_url = Cmatic_Plugin_Links::get_settings_url();
		if ( '' === $settings_url ) {
			$settings_url = admin_url( 'admin.php?page=wpcf7' );
		}
		$endpoint = rest_url( 'chimpmatic-lite/v1/notices/dismiss' );
		$nonce    = wp_create_nonce( 'wp_rest' );
		?>
		<div class="notice notice-info is-dismissible cmatic-signls-consent-notice" data-notice-version="<?php echo esc_attr( (string) self::NOTICE_VERSION ); ?>">
			<p><strong><?php esc_html_e( 'Choose whether to share aggregate ChimpMatic product insights.', 'chimpmatic-lite' ); ?></strong></p>
			<p><?php esc_html_e( 'Sharing is currently off. Review the pseudonymous data categories and enable the existing Help Us Improve setting only if you agree.', 'chimpmatic-lite' ); ?> <a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Review sharing settings', 'chimpmatic-lite' ); ?></a></p>
		</div>
		<script>
		(() => {
			const notice = document.querySelector('.cmatic-signls-consent-notice[data-notice-version="<?php echo esc_js( (string) self::NOTICE_VERSION ); ?>"]');
			if (!notice) return;
			notice.addEventListener('click', (event) => {
				if (!event.target.closest('.notice-dismiss')) return;
				fetch(<?php echo wp_json_encode( $endpoint ); ?>, {
					method: 'POST',
					headers: {'Content-Type': 'application/json', 'X-WP-Nonce': <?php echo wp_json_encode( $nonce ); ?>},
					body: JSON.stringify({notice_id: 'signls_consent', state: '<?php echo esc_js( (string) self::NOTICE_VERSION ); ?>'})
				}).catch(() => {});
			});
		})();
		</script>
		<?php
	}

	public static function dismiss( string $state ): bool {
		if ( (string) self::NOTICE_VERSION !== $state ) {
			return false;
		}
		if ( (int) get_user_meta( get_current_user_id(), self::USER_META, true ) >= self::NOTICE_VERSION ) {
			return true;
		}
		return false !== update_user_meta( get_current_user_id(), self::USER_META, self::NOTICE_VERSION );
	}

	private function __construct() {}
}
