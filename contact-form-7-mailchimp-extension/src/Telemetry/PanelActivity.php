<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    hello@chimpmatic.com
 * @copyright 2014-2026 ChimpMatic
 * @license   GPL-3.0+
 */

namespace Cmatic\Lite\Telemetry;

defined( 'ABSPATH' ) || exit;

final class PanelActivity {

	public const OPTION     = 'cmatic_lite_panel_activity';
	public const SCHEMA     = 1;
	public const MAX_EVENTS = 120;

	private const PRODUCT = 'contact-form-7-mailchimp-extension';

	private const BROWSER_EVENTS = array( 'panel_opened', 'test_opened', 'promo_seen' );

	private const SAVE_FLAGS = array( 'confsubs', 'cfactive', 'addunsubscr' );

	private const NAMESPACES = array( '/chimpmatic-lite/v1/', '/cmatic/v1/' );

	private const QUIET_ROUTES = array( 'panel_event', 'logs_browser', 'oauth_status', 'oauth_event', 'deactivation_feedback' );

	private const SETTING_ROUTES = array( 'form/field', 'form/setting', 'settings/toggle' );

	/**
	 * @var array<int,float>
	 */
	private static $started = array();

	/**
	 * @var array<int,array<string,mixed>>
	 */
	private static $before = array();

	public static function init(): void {
		add_action( 'rest_api_init', array( self::class, 'register_route' ) );
		add_filter( 'rest_request_before_callbacks', array( self::class, 'rest_started' ), 10, 3 );
		add_filter( 'rest_request_after_callbacks', array( self::class, 'rest_finished' ), 10, 3 );
		add_filter( 'wpcf7_feedback_response', array( self::class, 'test_result' ), 20, 2 );
		add_action( 'wpcf7_after_save', array( self::class, 'capture_before_save' ), 0 );
		add_action( 'wpcf7_after_save', array( self::class, 'form_saved' ), 20 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ), 30 );
	}

	public static function active(): bool {
		return ! defined( 'CMATIC_VERSION' );
	}

	public static function register_route(): void {
		if ( ! self::active() ) {
			return;
		}
		register_rest_route(
			'chimpmatic-lite/v1',
			'/panel/event',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'browser_event' ),
				'permission_callback' => array( 'Cmatic_Rest_Permissions', 'admin' ),
				'args'                => array(
					'event' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function browser_event( $request ) {
		$event = self::browser_slug( self::text( $request->get_param( 'event' ) ) );
		if ( '' === $event ) {
			return new \WP_Error( 'invalid_event', 'Unknown event.', array( 'status' => 400 ) );
		}
		self::record( $event );
		return new \WP_REST_Response( array( 'recorded' => true ), 202 );
	}

	public static function browser_slug( string $event ): string {
		$event = strtolower( trim( $event ) );
		if ( in_array( $event, self::BROWSER_EVENTS, true ) ) {
			return $event;
		}
		return 1 === preg_match( '/^(help|link)_[a-z0-9][a-z0-9_]{0,39}$/', $event ) ? $event : '';
	}

	public static function record( string $event, int $elapsed_ms = -1 ): bool {
		$event = self::slug( $event );
		if ( '' === $event || ! self::active() ) {
			return false;
		}
		$events = self::events();
		if ( ! isset( $events[ $event ] ) && count( $events ) >= self::MAX_EVENTS ) {
			return false;
		}
		$now             = time();
		$row             = $events[ $event ] ?? array(
			'count'    => 0,
			'first_at' => $now,
			'last_at'  => $now,
			'ms_total' => 0,
			'ms_max'   => 0,
		);
		$row['count']    = min( 4294967295, (int) $row['count'] + 1 );
		$row['last_at']  = $now;
		$row['first_at'] = (int) $row['first_at'] > 0 ? (int) $row['first_at'] : $now;
		if ( $elapsed_ms >= 0 ) {
			$row['ms_total'] = min( 4294967295, (int) $row['ms_total'] + $elapsed_ms );
			$row['ms_max']   = max( (int) $row['ms_max'], min( 4294967295, $elapsed_ms ) );
		}
		$events[ $event ] = $row;
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, $events, '', false );
		} else {
			update_option( self::OPTION, $events, false );
		}
		self::send();
		return true;
	}

	/**
	 * @return array{schema:int,events:array<string,array{count:int,first_at:int,last_at:int,ms_total:int,ms_max:int}>}
	 */
	public static function snapshot(): array {
		return array(
			'schema' => self::SCHEMA,
			'events' => self::events(),
		);
	}

	/**
	 * @return array<string,array{count:int,first_at:int,last_at:int,ms_total:int,ms_max:int}>
	 */
	private static function events(): array {
		$stored = get_option( self::OPTION, array() );
		$events = array();
		foreach ( is_array( $stored ) ? $stored : array() as $name => $row ) {
			$name = self::slug( (string) $name );
			if ( '' === $name || ! is_array( $row ) || count( $events ) >= self::MAX_EVENTS ) {
				continue;
			}
			$events[ $name ] = array(
				'count'    => max( 0, self::number( $row['count'] ?? 0 ) ),
				'first_at' => max( 0, self::number( $row['first_at'] ?? 0 ) ),
				'last_at'  => max( 0, self::number( $row['last_at'] ?? 0 ) ),
				'ms_total' => max( 0, self::number( $row['ms_total'] ?? 0 ) ),
				'ms_max'   => max( 0, self::number( $row['ms_max'] ?? 0 ) ),
			);
		}
		return $events;
	}

	public static function slug( string $value ): string {
		$value = strtolower( (string) preg_replace( '/[^a-z0-9]+/i', '_', $value ) );
		$value = trim( (string) substr( $value, 0, 48 ), '_' );
		return 1 === preg_match( '/^[a-z0-9][a-z0-9_]{0,47}$/', $value ) ? $value : '';
	}

	/**
	 * @param mixed $value
	 */
	private static function text( $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * @param mixed $value
	 */
	private static function number( $value ): int {
		return is_numeric( $value ) ? (int) $value : 0;
	}

	/**
	 * @param mixed $value
	 */
	private static function enabled( $value ): bool {
		return '' !== self::text( $value ) && '0' !== self::text( $value );
	}

	private static function send(): void {
		try {
			if ( class_exists( 'Signls_Sdk_Bridge_1_1_7', false ) ) {
				\Signls_Sdk_Bridge_1_1_7::relevant_change( self::PRODUCT );
			}
		} catch ( \Throwable $error ) {
			return;
		}
	}

	/**
	 * @param mixed            $response
	 * @param mixed            $handler
	 * @param \WP_REST_Request $request
	 * @return mixed
	 */
	public static function rest_started( $response, $handler, $request ) {
		if ( $request instanceof \WP_REST_Request && '' !== self::route_event( $request ) ) {
			self::$started[ spl_object_id( $request ) ] = microtime( true );
		}
		return $response;
	}

	/**
	 * @param mixed            $response
	 * @param mixed            $handler
	 * @param \WP_REST_Request $request
	 * @return mixed
	 */
	public static function rest_finished( $response, $handler, $request ) {
		if ( ! $request instanceof \WP_REST_Request ) {
			return $response;
		}
		$event = self::route_event( $request );
		$id    = spl_object_id( $request );
		if ( '' === $event || ! isset( self::$started[ $id ] ) ) {
			return $response;
		}
		$elapsed = (int) round( ( microtime( true ) - self::$started[ $id ] ) * 1000 );
		unset( self::$started[ $id ] );
		$status = $response instanceof \WP_Error ? 400 : ( $response instanceof \WP_REST_Response ? $response->get_status() : 200 );
		$failed = $response instanceof \WP_Error || $status >= 400;
		$change = self::setting_event( $request );
		if ( '' !== $change && ! $failed ) {
			self::record( $change );
		}
		self::record( $event . ( $failed ? '_failed' : '' ), $elapsed );
		return $response;
	}

	private static function route_path( \WP_REST_Request $request ): string {
		$route = (string) $request->get_route();
		foreach ( self::NAMESPACES as $namespace ) {
			if ( 0 === strpos( $route, $namespace ) ) {
				return substr( $route, strlen( $namespace ) );
			}
		}
		return '';
	}

	public static function route_event( \WP_REST_Request $request ): string {
		$path = self::route_path( $request );
		if ( '' === $path || ! self::active() || ! in_array( $request->get_method(), array( 'POST', 'GET' ), true ) ) {
			return '';
		}
		$parts = array();
		foreach ( explode( '/', $path ) as $part ) {
			if ( '' === $part || ctype_digit( $part ) || strlen( $part ) > 24 ) {
				continue;
			}
			$parts[] = $part;
		}
		$name = self::slug( implode( '_', $parts ) );
		if ( '' === $name || in_array( $name, self::QUIET_ROUTES, true ) ) {
			return '';
		}
		return self::slug( 'api_' . $name );
	}

	private static function setting_event( \WP_REST_Request $request ): string {
		if ( ! in_array( self::route_path( $request ), self::SETTING_ROUTES, true ) ) {
			return '';
		}
		$field = self::slug( self::text( $request->get_param( 'field' ) ) );
		if ( '' === $field ) {
			return '';
		}
		$value = $request->get_param( 'enabled' );
		if ( null === $value ) {
			$value = $request->get_param( 'value' );
		}
		if ( is_bool( $value ) || in_array( $value, array( '0', '1', 0, 1, 'true', 'false' ), true ) ) {
			return self::slug( 'set_' . $field . '_' . ( rest_sanitize_boolean( $value ) ? 'on' : 'off' ) );
		}
		return self::slug( 'set_' . $field );
	}

	/**
	 * @param mixed $response
	 * @param mixed $result
	 * @return mixed
	 */
	public static function test_result( $response, $result ) {
		unset( $result );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Contact Form 7 verified the submission; only a flag is read.
		$marker = isset( $_POST['_cmatic_test_modal'] ) && is_string( $_POST['_cmatic_test_modal'] ) ? sanitize_text_field( wp_unslash( $_POST['_cmatic_test_modal'] ) ) : '';
		if ( '1' !== $marker || ! self::active() ) {
			return $response;
		}
		self::record( 'test_sent' );
		$outcome = is_array( $response ) && isset( $response['chimpmatic'] ) && is_array( $response['chimpmatic'] ) ? $response['chimpmatic'] : array();
		if ( true === ( $outcome['success'] ?? null ) ) {
			self::record( 'test_ok' );
		} elseif ( ! empty( $outcome['skipped'] ) ) {
			self::record( self::slug( 'test_skipped_' . self::text( $outcome['reason'] ?? 'unknown' ) ) );
		} elseif ( false === ( $outcome['success'] ?? null ) ) {
			self::record( self::slug( 'test_failed_' . self::text( $outcome['reason'] ?? 'unknown' ) ) );
		} else {
			$status = is_array( $response ) ? self::text( $response['status'] ?? '' ) : '';
			self::record( '' !== $status ? self::slug( 'test_' . $status ) : 'test_no_answer' );
		}
		return $response;
	}

	/**
	 * @param \WPCF7_ContactForm $contact_form
	 */
	public static function capture_before_save( $contact_form ): void {
		if ( ! self::active() || ! self::panel_posted() ) {
			return;
		}
		$form_id                  = (int) $contact_form->id();
		self::$before[ $form_id ] = \Cmatic\Lite\Settings\FormSettingsRepository::load( $form_id )->record();
	}

	/**
	 * @param \WPCF7_ContactForm $contact_form
	 */
	public static function form_saved( $contact_form ): void {
		$form_id = (int) $contact_form->id();
		if ( ! isset( self::$before[ $form_id ] ) || ! current_user_can( 'wpcf7_edit_contact_form', $form_id ) ) {
			return;
		}
		$before = self::$before[ $form_id ];
		unset( self::$before[ $form_id ] );
		$after = \Cmatic\Lite\Settings\FormSettingsRepository::load( $form_id )->record();
		self::record( 'form_saved' );
		foreach ( self::SAVE_FLAGS as $flag ) {
			$old = self::enabled( $before[ $flag ] ?? '' );
			$new = self::enabled( $after[ $flag ] ?? '' );
			if ( $old !== $new ) {
				self::record( 'save_' . $flag . '_' . ( $new ? 'on' : 'off' ) );
			}
		}
		foreach ( array( 'list', 'provider', 'api-validation' ) as $key ) {
			$now = self::text( $after[ $key ] ?? '' );
			if ( self::text( $before[ $key ] ?? '' ) !== $now && '' !== $now ) {
				self::record( 'save_' . $key . '_changed' );
			}
		}
	}

	private static function panel_posted(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Contact Form 7 verified its own save nonce; only key presence is read.
		return isset( $_POST['wpcf7-mailchimp'] ) || isset( $_POST['wpcf7-cmatic-provider'] );
	}

	public static function enqueue( string $hook_suffix ): void {
		if ( ! self::active() || false === strpos( $hook_suffix, 'wpcf7' ) || ! wp_script_is( 'chimpmatic-lite-js', 'enqueued' ) ) {
			return;
		}
		$path = SPARTAN_MCE_PLUGIN_DIR . 'assets/js/cmatic-panel-signals.js';
		wp_enqueue_script( 'cmatic-lite-panel-signals', SPARTAN_MCE_PLUGIN_URL . 'assets/js/cmatic-panel-signals.js', array(), (string) filemtime( $path ), true );
		wp_localize_script(
			'cmatic-lite-panel-signals',
			'cmaticPanelSignals',
			array(
				'url'   => esc_url_raw( rest_url( 'chimpmatic-lite/v1/panel/event' ) ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
				'panel' => 'Chimpmatic',
				'promo' => '.promo-2022',
			)
		);
	}

	private function __construct() {}
}
