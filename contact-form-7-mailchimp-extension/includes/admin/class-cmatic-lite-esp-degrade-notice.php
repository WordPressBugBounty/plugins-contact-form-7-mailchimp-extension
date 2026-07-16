<?php
/**
 * MailerLite license-degradation admin warning.
 *
 * @package contact-form-7-mailchimp-extension
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

final class Cmatic_Lite_Esp_Degrade_Notice {
	public static function init(): void {
		add_action( 'admin_notices', array( __CLASS__, 'render' ) );
	}

	public static function render(): void {
		$forms = get_posts(
			array(
				'post_type'              => 'wpcf7_contact_form',
				'post_status'            => 'any',
				'posts_per_page'         => 20,
				'fields'                 => 'ids',
				'meta_key'               => Cmatic_Mailerlite_Degradation_Reporter::META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Bounded operational query for a dedicated indexed key.
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			)
		);
		foreach ( $forms as $form_id ) {
			$form_id = (int) $form_id;
			if ( ! current_user_can( 'wpcf7_edit_contact_form', $form_id ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- Contact Form 7 meta capability.
				continue;
			}
			$config    = get_option( 'cf7_mch_' . $form_id, array() );
			$config    = is_array( $config ) ? $config : array();
			$providers = isset( $config['providers'] ) && is_array( $config['providers'] ) ? $config['providers'] : array();
			$settings  = isset( $providers['mailerlite'] ) && is_array( $providers['mailerlite'] ) ? $providers['mailerlite'] : array();
			$policy    = Cmatic_Mailerlite_Runtime_Policy::apply( $settings, self::entitlements( $form_id ) );
			if ( ! $policy['degraded'] ) {
				Cmatic_Mailerlite_Degradation_Reporter::clear( $form_id );
				continue;
			}
			$url = add_query_arg(
				array(
					'page'   => 'wpcf7',
					'post'   => $form_id,
					'action' => 'edit',
				),
				admin_url( 'admin.php' )
			);
			printf(
				'<div class="notice notice-warning is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
				/* translators: %d: Contact Form 7 form ID. */
				esc_html( sprintf( __( 'MailerLite Pro features are saved but inactive for form %d. Capture continues using the first group and legacy status.', 'chimpmatic-lite' ), $form_id ) ),
				esc_url( $url ),
				esc_html__( 'Review and renew', 'chimpmatic-lite' )
			);
		}
	}

	private static function entitlements( int $form_id ): array {
		$result = array();
		foreach ( array( 'mailerlite_routing', 'mailerlite_status', 'mailerlite_resubscribe', 'mailerlite_consent_metadata' ) as $feature ) {
			$result[ $feature ] = Cmatic_Lite_Esp_Capabilities::feature_enabled( $feature, 'mailerlite', $form_id );
		}
		return $result;
	}

	private function __construct() {}
}
