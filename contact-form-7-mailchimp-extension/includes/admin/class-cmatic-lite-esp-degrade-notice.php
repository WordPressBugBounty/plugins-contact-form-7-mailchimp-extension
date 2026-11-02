<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

final class Cmatic_Lite_Esp_Degrade_Notice {
	public static function init(): void {
		\Cmatic\Lite\Admin\Notices::boot();
	}

	public static function render(): void {}

	/** @return list<array<string,mixed>> */
	public static function entries(): array {
		$entries = array();
		$forms   = get_posts(
			array(
				'post_type'              => 'wpcf7_contact_form',
				'post_status'            => 'any',
				'posts_per_page'         => 20,
				'fields'                 => 'ids',
				'meta_key'               => \Cmatic\Lite\Submission\Degradation::META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Bounded operational query for a dedicated indexed key.
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			)
		);
		foreach ( $forms as $form_id ) {
			$form_id = (int) $form_id;
			if ( ! current_user_can( 'wpcf7_edit_contact_form', $form_id ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- Contact Form 7 meta capability.
				continue;
			}
			$settings = \Cmatic\Lite\Settings\FormSettingsRepository::load( $form_id )->section( 'mailerlite' );
			$policy   = \Cmatic\Lite\Settings\Entitlements::apply( 'mailerlite', $settings, $form_id );
			if ( ! $policy['degraded'] ) {
				\Cmatic\Lite\Submission\Degradation::clear( $form_id );
				continue;
			}
			/* translators: %d: Contact Form 7 form ID. */
			$message   = sprintf( __( 'Your MailerLite Pro settings for form %d are saved. Capture continues using the first group and legacy status. Activate Pro to use your saved settings again.', 'contact-form-7-mailchimp-extension' ), $form_id );
			$entries[] = \Cmatic\Lite\Admin\Notices::entry( 'saved-pro:' . $form_id, 'failing', 35, __( 'Your saved Pro settings are safe', 'contact-form-7-mailchimp-extension' ), $message, array( \Cmatic\Lite\Admin\Notices::action( __( 'Review this form', 'contact-form-7-mailchimp-extension' ), admin_url( 'admin.php?page=wpcf7&post=' . $form_id . '&action=edit&active-tab=Chimpmatic' ), 'primary' ) ) );
		}
		return $entries;
	}

	private function __construct() {}
}
