<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    hello@chimpmatic.com
 * @copyright 2014-2026 ChimpMatic
 * @license   GPL-3.0+
 */

namespace Cmatic\Lite\Core;

defined( 'ABSPATH' ) || exit;

final class StayCurrent {

	public const BASENAME = 'contact-form-7-mailchimp-extension/chimpmatic-lite.php';

	private const SLUG = 'contact-form-7-mailchimp-extension';

	private const PRO_OWNER = '\\Chimpmatic\\License\\Integration\\Current';

	public static function init(): void {
		add_filter( 'auto_update_plugin', array( self::class, 'decide' ), 10, 2 );
		add_action( 'set_site_transient_update_plugins', array( self::class, 'schedule' ) );
	}

	public static function enabled(): bool {
		if ( class_exists( self::PRO_OWNER ) ) {
			return false;
		}
		return (bool) \Cmatic_Options_Repository::get_option( 'auto_update', true );
	}

	/**
	 * @param mixed $update Decision reached so far.
	 * @param mixed $item   Plugin row from the update list.
	 * @return mixed
	 */
	public static function decide( $update, $item ) {
		if ( ! is_object( $item ) ) {
			return $update;
		}
		$plugin = isset( $item->plugin ) ? (string) $item->plugin : '';
		$slug   = isset( $item->slug ) ? (string) $item->slug : '';
		if ( self::BASENAME !== $plugin && self::SLUG !== $slug ) {
			return $update;
		}
		return self::enabled() ? true : $update;
	}

	/**
	 * @param mixed $transient Update list WordPress just saved.
	 */
	public static function schedule( $transient ): void {
		if ( ! is_object( $transient ) || ! isset( $transient->response ) || ! is_array( $transient->response ) || empty( $transient->response[ self::BASENAME ] ) ) {
			return;
		}
		if ( ! self::enabled() ) {
			return;
		}
		$next = wp_next_scheduled( 'wp_maybe_auto_update' );
		if ( false === $next || $next > time() + MINUTE_IN_SECONDS ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'wp_maybe_auto_update' );
		}
	}
}
