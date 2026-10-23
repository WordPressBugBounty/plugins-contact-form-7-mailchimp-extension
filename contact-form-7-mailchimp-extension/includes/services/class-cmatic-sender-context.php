<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

final class Cmatic_Sender_Context {
	public const GEO_BASE = 'https://iplokus.com/?ip=';
	private const COOKIE  = 'cmatic_ref';
	private const MARK    = 'cmatic-sender-context';

	public static function init(): void {
		add_action( 'template_redirect', array( __CLASS__, 'remember_referrer' ), 1 );
		add_filter( 'wpcf7_mail_components', array( __CLASS__, 'append' ), 20, 3 );
	}

	public static function enabled(): bool {
		return (bool) Cmatic_Options_Repository::get_option( 'sender_context', false );
	}

	public static function remember_referrer(): void {
		if ( ! self::enabled() || is_admin() || headers_sent() || isset( $_COOKIE[ self::COOKIE ] ) ) {
			return;
		}
		$referer = self::server( 'HTTP_REFERER' );
		$referer = '' === $referer ? '' : esc_url_raw( $referer );
		$home    = wp_parse_url( home_url(), PHP_URL_HOST );
		$from    = '' === $referer ? '' : wp_parse_url( $referer, PHP_URL_HOST );
		$value   = is_string( $from ) && '' !== $from && $from !== $home ? $referer : 'direct';
		setcookie(
			self::COOKIE,
			$value,
			array(
				'expires'  => 0,
				'path'     => '/',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	/**
	 * @param mixed $components
	 * @param mixed $form
	 * @param mixed $mail
	 * @return mixed
	 */
	public static function append( $components, $form = null, $mail = null ) {
		if ( ! self::enabled() || ! is_array( $components ) || ! is_object( $form ) || ! method_exists( $form, 'id' ) ) {
			return $components;
		}
		if ( is_object( $mail ) && method_exists( $mail, 'name' ) && 'mail' !== $mail->name() ) {
			return $components;
		}
		$body = isset( $components['body'] ) && is_string( $components['body'] ) ? $components['body'] : '';
		if ( false !== strpos( $body, self::MARK ) || false !== strpos( $body, __( 'Sender geo location', 'contact-form-7-mailchimp-extension' ) . ':' ) ) {
			return $components;
		}
		$template           = method_exists( $form, 'prop' ) ? $form->prop( 'mail' ) : array();
		$html               = is_array( $template ) && ! empty( $template['use_html'] );
		$components['body'] = $body . self::block( $form, $html );

		return $components;
	}

	/**
	 * @param object $form
	 */
	public static function block( $form, bool $html ): string {
		$ip    = Cmatic_Request_Ip::get();
		$geo   = '' === $ip ? '' : self::GEO_BASE . rawurlencode( $ip );
		$page  = self::server( 'HTTP_REFERER' );
		$page  = '' === $page ? '' : esc_url_raw( $page );
		$title = method_exists( $form, 'title' ) ? $form->title() : '';
		$title = is_scalar( $title ) ? (string) $title : '';
		$id    = method_exists( $form, 'id' ) ? $form->id() : 0;
		$id    = is_scalar( $id ) ? (int) $id : 0;
		$time  = wp_date( self::time_format() );
		$where = trim( $page . ' : ' . $title . ' (#' . $id . ') : ' . $time, ' :' );
		$ref   = isset( $_COOKIE[ self::COOKIE ] ) && is_string( $_COOKIE[ self::COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '';
		$ref   = '' === $ref || 'direct' === $ref ? __( 'Direct visit', 'contact-form-7-mailchimp-extension' ) : esc_url_raw( $ref );
		$ua    = sanitize_text_field( self::server( 'HTTP_USER_AGENT' ) );
		$lines = array(
			array( __( 'Sender geo location', 'contact-form-7-mailchimp-extension' ), $geo, true ),
			array( __( 'URL', 'contact-form-7-mailchimp-extension' ), $where, false ),
			array( __( 'Referrer', 'contact-form-7-mailchimp-extension' ), $ref, false ),
			array( __( 'User agent', 'contact-form-7-mailchimp-extension' ), $ua, false ),
		);
		if ( $html ) {
			$out = "\n<!-- " . self::MARK . " -->\n<p style=\"margin-top:1.5em;font-size:12px;color:#555\">";
			foreach ( $lines as $line ) {
				if ( '' === $line[1] ) {
					continue;
				}
				$shown = $line[2] ? '<a href="' . esc_url( $line[1] ) . '">' . esc_html( $line[1] ) . '</a>' : esc_html( $line[1] );
				$out  .= '<strong>' . esc_html( $line[0] ) . ':</strong> ' . $shown . '<br>';
			}

			return $out . '</p>';
		}
		$out = "\n\n-- \n";
		foreach ( $lines as $line ) {
			if ( '' !== $line[1] ) {
				$out .= $line[0] . ': ' . $line[1] . "\n";
			}
		}

		return $out;
	}

	public static function sample(): string {
		return __( 'Sender geo location', 'contact-form-7-mailchimp-extension' ) . ': ' . self::GEO_BASE . "203.0.113.9\n"
			. __( 'URL', 'contact-form-7-mailchimp-extension' ) . ': https://example.com/contact : Contact (#12) : 7:35 am' . "\n"
			. __( 'Referrer', 'contact-form-7-mailchimp-extension' ) . ': https://www.google.com/' . "\n"
			. __( 'User agent', 'contact-form-7-mailchimp-extension' ) . ': Mozilla/5.0 ...';
	}

	private static function time_format(): string {
		$format = get_option( 'time_format', 'H:i' );

		return is_string( $format ) && '' !== $format ? $format : 'H:i';
	}

	private static function server( string $key ): string {
		return isset( $_SERVER[ $key ] ) && is_string( $_SERVER[ $key ] ) ? wp_unslash( $_SERVER[ $key ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Every caller escapes or sanitizes the value for its own use.
	}

	private function __construct() {}
}
