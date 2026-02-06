<?php
/**
 * Metrics sync handler.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

namespace Cmatic\Metrics\Core;

use Cmatic\Metrics\Security\Signature;

defined( 'ABSPATH' ) || exit;

class Sync {

	private static $endpoint_url = '';

	public static function set_endpoint( $url ) {
		self::$endpoint_url = $url;
	}

	public static function send( $payload ) {
		try {
			if ( empty( self::$endpoint_url ) ) {
				return false;
			}

			$request = self::prepare_request( $payload );

			$response = wp_remote_post(
				self::$endpoint_url,
				array(
					'body'    => wp_json_encode( $request ),
					'headers' => array( 'Content-Type' => 'application/json' ),
					'timeout' => 5,
				)
			);

			return self::handle_response( $response, $payload );
		} catch ( \Exception $e ) {
			return false;
		}
	}

	public static function send_async( $payload ) {
		try {
			if ( empty( self::$endpoint_url ) ) {
				return;
			}

			$request = self::prepare_request( $payload );

			wp_remote_post(
				self::$endpoint_url,
				array(
					'body'     => wp_json_encode( $request ),
					'headers'  => array( 'Content-Type' => 'application/json' ),
					'timeout'  => 5,
					'blocking' => false,
				)
			);

			Storage::update_last_heartbeat();
		} catch ( \Exception $e ) {
			return;
		}
	}

	private static function prepare_request( $payload ) {
		$install_id   = Storage::get_install_id();
		$timestamp    = time();
		$payload_json = wp_json_encode( $payload );
		$signature    = Signature::generate( $install_id, $timestamp, $payload_json );

		return array(
			'install_id'   => $install_id,
			'timestamp'    => $timestamp,
			'signature'    => $signature,
			'public_key'   => Signature::get_public_key(),
			'payload_json' => $payload_json,
		);
	}

	private static function handle_response( $response, $payload ) {
		if ( is_wp_error( $response ) ) {
			self::handle_failure( $response->get_error_message(), $payload );
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code && 201 !== $code ) {
			$body = wp_remote_retrieve_body( $response );
			self::handle_failure( "HTTP {$code}: {$body}", $payload );
			return false;
		}

		self::handle_success( $payload );
		return true;
	}

	private static function handle_success( $payload ) {
		Storage::update_last_heartbeat();

		$payload_hash = md5( wp_json_encode( $payload ) );
		Storage::save_payload_hash( $payload_hash );
		Storage::save_error( '' );

		if ( 'sparse' === Storage::get_schedule() ) {
			Scheduler::schedule_next_sparse();
		}
	}

	private static function handle_failure( $error, $payload ) {
		Storage::update_last_heartbeat();
		Storage::increment_failed_count();
		Storage::save_error( $error );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "[ChimpMatic Metrics] Heartbeat failed: {$error}" ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		if ( 'sparse' === Storage::get_schedule() ) {
			Scheduler::schedule_next_sparse();
		}
	}

	public static function send_lifecycle_signal( $event ) {
		$options = get_option( 'cmatic', array() );

		$default_enabled   = 'activation' === $event;
		$telemetry_enabled = $options['telemetry']['enabled'] ?? $default_enabled;
		if ( ! $telemetry_enabled ) {
			return;
		}

		$install_id = $options['install']['id'] ?? '';
		if ( empty( $install_id ) ) {
			return;
		}

		global $wpdb;
		$payload = array(
			'event'         => $event,
			'install_id'    => $install_id,
			'version'       => defined( 'SPARTAN_MCE_VERSION' ) ? SPARTAN_MCE_VERSION : '1.0.0',
			'site_url'      => home_url(),
			'timestamp'     => time(),
			'wp_version'    => get_bloginfo( 'version' ),
			'php'           => PHP_VERSION,
			'mysql_version' => $wpdb->db_version(),
			'software'      => array(
				'server' => isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : 'unknown', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
			),
		);

		$timestamp    = time();
		$payload_json = wp_json_encode( $payload );
		$signature    = Signature::generate( $install_id, $timestamp, $payload_json );

		$request = array(
			'install_id'   => $install_id,
			'timestamp'    => $timestamp,
			'signature'    => $signature,
			'public_key'   => Signature::get_public_key(),
			'payload_json' => $payload_json,
		);

		wp_remote_post(
			'https://ping.chimpmatic.com/wp-json/chimpmatic/v1/telemetry',
			array(
				'body'     => wp_json_encode( $request ),
				'headers'  => array( 'Content-Type' => 'application/json' ),
				'timeout'  => 5,
				'blocking' => true,
			)
		);
	}
}
