<?php
/**
 * URL tracking and link builder.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

final class Cmatic_Pursuit {

	const PLUGIN_ID = 'chimpmatic_lite';
	const BASE_URLS = array(
		'docs'    => 'https://chimpmatic.com',
		'help'    => 'https://chimpmatic.com/help',
		'pricing' => 'https://chimpmatic.com/pricing',
		'support' => 'https://chimpmatic.com/contact',
		'promo'   => 'https://chimpmatic.com/almost-there',
		'home'    => 'https://chimpmatic.com',
		'author'  => 'https://renzojohnson.com',
	);

	private function __construct() {}

	public static function url( string $base_url, string $medium, string $content = '', string $campaign = '' ): string {
		if ( empty( $base_url ) ) {
			return '';
		}
		$params = array(
			'utm_source'   => self::PLUGIN_ID,
			'utm_medium'   => self::sanitize( $medium ),
			'utm_campaign' => $campaign ? self::sanitize( $campaign ) : 'plugin_' . gmdate( 'Y' ),
		);
		if ( $content ) {
			$params['utm_content'] = self::sanitize( $content );
		}
		return add_query_arg( $params, $base_url );
	}

	public static function docs( string $slug = '', string $content = '' ): string {
		$base = self::BASE_URLS['docs'];
		if ( $slug ) {
			$base = trailingslashit( $base ) . ltrim( $slug, '/' );
		}
		return self::url( $base, 'plugin', $content, 'docs' );
	}

	public static function upgrade( string $content = '' ): string {
		return self::url( self::BASE_URLS['pricing'], 'plugin', $content, 'upgrade' );
	}

	public static function support( string $content = '' ): string {
		return self::url( self::BASE_URLS['support'], 'plugin', $content, 'support' );
	}

	public static function pricing(): array {
		$fetcher = new CMatic_Remote_Fetcher(
			array(
				'url'            => 'https://api.chimpmatic.com/promo',
				'cache_key'      => 'cmatic_pricing_data',
				'cache_duration' => DAY_IN_SECONDS,
				'headers'        => Cmatic_Lite_Service_Context::headers( 'promo_pricing_refresh' ),
				'fallback_data'  => array(
					'regular_price'    => 39,
					'sale_price'       => 29.25,
					'discount_percent' => 40,
					'coupon_code'      => 'NOW40',
					'formatted'        => '$39 → $29.25 • Save 40%',
				),
			)
		);
		return $fetcher->get_data();
	}

	public static function discount(): int {
		$pricing = self::pricing();
		return max( 0, (int) ( $pricing['discount_percent'] ?? 0 ) );
	}

	public static function promo_checkout( string $content = '' ): string {
		$discount = self::discount();
		$source   = class_exists( 'Cmatic_Options_Repository' )
		? Cmatic_Options_Repository::get_option( 'install.id', '' )
		: '';
		return add_query_arg(
			array(
				'source' => $source,
				'promo'  => 'pro' . $discount,
			),
			self::promo( $content, $discount )
		);
	}

	public static function promo( string $content = '', ?int $discount = null ): string {
		if ( null === $discount ) { // Default follows the live promo, not a constant.
			$pricing  = self::pricing();
			$discount = (int) ( $pricing['discount_percent'] ?? 0 );
		}
		$params = array(
			'u_source'   => self::PLUGIN_ID,
			'u_medium'   => 'banner',
			'u_campaign' => 'promo_' . $discount . 'off',
		);
		if ( $content ) {
			$params['u_content'] = self::sanitize( $content );
		}
		return add_query_arg( $params, self::BASE_URLS['promo'] );
	}

	public static function home( string $content = '' ): string {
		return self::url( self::BASE_URLS['home'], 'plugin', $content, 'brand' );
	}

	public static function author( string $content = '' ): string {
		return self::url( self::BASE_URLS['author'], 'plugin', $content, 'author' );
	}

	public static function adminbar( string $destination, string $content = '' ): string {
		$base = self::BASE_URLS[ $destination ] ?? self::BASE_URLS['home'];
		return self::url( $base, 'adminbar', $content, $destination );
	}

	private static function sanitize( string $value ): string {
		return preg_replace( '/[^a-z0-9_]/', '', str_replace( array( ' ', '-' ), '_', strtolower( trim( $value ) ) ) );
	}
}
