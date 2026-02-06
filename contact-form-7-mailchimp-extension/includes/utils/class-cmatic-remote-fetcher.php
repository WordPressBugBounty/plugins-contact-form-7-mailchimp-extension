<?php
/**
 * Remote data fetcher with caching.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

class CMatic_Remote_Fetcher {

	private $config;

	private $defaults = array(
		'url'             => '',
		'cache_key'       => 'cmatic_remote_data',
		'cache_duration'  => DAY_IN_SECONDS,
		'retry_interval'  => 600, // 10 minutes in seconds
		'max_retries'     => 3,
		'retry_count_key' => 'cmatic_retry_count',
		'cron_hook'       => 'cmatic_fetch_retry',
		'timeout'         => 15,
		'fallback_data'   => array(),
		'parser_callback' => null,
	);

	public function __construct( $config = array() ) {
		$this->config = wp_parse_args( $config, $this->defaults );
		add_action( $this->config['cron_hook'], array( $this, 'cron_retry_fetch' ) );
	}

	public function get_data() {
		$cached_data = $this->get_cache();

		if ( false !== $cached_data ) {
			return $cached_data;
		}

		$fresh_data = $this->fetch_fresh_data();

		if ( false !== $fresh_data ) {
			$this->set_cache( $fresh_data );
			$this->clear_retry_schedule();
			return $fresh_data;
		}

		$this->schedule_retry();
		return $this->get_fallback_data();
	}

	public function get_cache() {
		return get_transient( $this->config['cache_key'] );
	}

	public function set_cache( $data ) {
		return set_transient(
			$this->config['cache_key'],
			$data,
			$this->config['cache_duration']
		);
	}

	public function clear_cache() {
		return delete_transient( $this->config['cache_key'] );
	}

	private function fetch_fresh_data() {
		if ( empty( $this->config['url'] ) ) {
			return false;
		}

		$response = wp_remote_get(
			$this->config['url'],
			array(
				'timeout'    => $this->config['timeout'],
				'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			return false;
		}

		return $this->parse_content( $body );
	}

	/** Parse fetched content. */
	private function parse_content( $content ) {
		if ( is_callable( $this->config['parser_callback'] ) ) {
			return call_user_func( $this->config['parser_callback'], $content );
		}

		$json_data = $this->parse_pricing_json( $content );
		if ( false !== $json_data ) {
			return $json_data;
		}

		return $this->parse_pricing_html( $content );
	}

	private function parse_pricing_json( $content ) {
		$json = json_decode( $content, true );

		if ( null === $json || ! is_array( $json ) ) {
			return false;
		}

		if ( ! isset( $json['regular_price'] ) || ! isset( $json['discount_percent'] ) ) {
			return false;
		}

		$pricing_data = array(
			'regular_price'    => (int) $json['regular_price'],
			'sale_price'       => isset( $json['sale_price'] ) ? (float) $json['sale_price'] : null,
			'discount_percent' => (int) $json['discount_percent'],
			'coupon_code'      => isset( $json['coupon_code'] ) ? sanitize_text_field( $json['coupon_code'] ) : null,
			'last_updated'     => current_time( 'mysql' ),
		);

		if ( null === $pricing_data['sale_price'] ) {
			$discount_amount            = $pricing_data['regular_price'] * ( $pricing_data['discount_percent'] / 100 );
			$pricing_data['sale_price'] = $pricing_data['regular_price'] - $discount_amount;
		}

		if ( null === $pricing_data['coupon_code'] ) {
			$pricing_data['coupon_code'] = 'NOW' . $pricing_data['discount_percent'];
		}

		$pricing_data['formatted'] = sprintf(
			'$%d → $%s • Save %d%%',
			$pricing_data['regular_price'],
			number_format( $pricing_data['sale_price'], 2 ),
			$pricing_data['discount_percent']
		);

		return $pricing_data;
	}

	private function parse_pricing_html( $html ) {
		$pricing_data = array(
			'regular_price'    => null,
			'sale_price'       => null,
			'discount_percent' => null,
			'coupon_code'      => null,
			'formatted'        => null,
			'last_updated'     => current_time( 'mysql' ),
		);

		if ( preg_match( '/Single\s+Site[^$]*\$(\d+)\/year/i', $html, $matches ) ) {
			$pricing_data['regular_price'] = (int) $matches[1];
		}

		if ( preg_match( '/(\d+)%\s+Off/i', $html, $matches ) ) {
			$pricing_data['discount_percent'] = (int) $matches[1];
		}

		if ( preg_match( '/coupon\s+code\s+["\']([A-Z0-9]+)["\']/i', $html, $matches ) ) {
			$pricing_data['coupon_code'] = sanitize_text_field( $matches[1] );
		}

		if ( $pricing_data['regular_price'] && $pricing_data['discount_percent'] ) {
			$discount_amount            = $pricing_data['regular_price'] * ( $pricing_data['discount_percent'] / 100 );
			$pricing_data['sale_price'] = $pricing_data['regular_price'] - $discount_amount;
		}

		if ( $pricing_data['regular_price'] && $pricing_data['sale_price'] && $pricing_data['discount_percent'] ) {
			$pricing_data['formatted'] = sprintf(
				'$%d → $%s • Save %d%%',
				$pricing_data['regular_price'],
				number_format( $pricing_data['sale_price'], 2 ),
				$pricing_data['discount_percent']
			);
		}

		if ( null === $pricing_data['regular_price'] ) {
			return false;
		}

		return $pricing_data;
	}

	private function get_fallback_data() {
		if ( ! empty( $this->config['fallback_data'] ) ) {
			return $this->config['fallback_data'];
		}

		return array(
			'regular_price'    => 39,
			'sale_price'       => 29.25,
			'discount_percent' => 25,
			'coupon_code'      => 'NOW25',
			'formatted'        => '$39 → $29.25 • Save 25%',
			'last_updated'     => null,
		);
	}

	private function schedule_retry() {
		$retry_count = (int) get_option( $this->config['retry_count_key'], 0 );

		if ( $retry_count >= $this->config['max_retries'] ) {
			return;
		}

		update_option( $this->config['retry_count_key'], $retry_count + 1 );

		if ( ! wp_next_scheduled( $this->config['cron_hook'] ) ) {
			wp_schedule_single_event(
				time() + $this->config['retry_interval'],
				$this->config['cron_hook']
			);
		}
	}

	public function cron_retry_fetch() {
		$fresh_data = $this->fetch_fresh_data();

		if ( false !== $fresh_data ) {
			$this->set_cache( $fresh_data );
			$this->clear_retry_schedule();
		} else {
			$this->schedule_retry();
		}
	}

	private function clear_retry_schedule() {
		$timestamp = wp_next_scheduled( $this->config['cron_hook'] );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, $this->config['cron_hook'] );
		}

		delete_option( $this->config['retry_count_key'] );
	}

	public function clear_all() {
		$this->clear_cache();
		$this->clear_retry_schedule();
	}
}
