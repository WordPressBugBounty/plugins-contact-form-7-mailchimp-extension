<?php
/**
 * Shared aggregate counter storage.
 *
 * @package signls-sdk
 * @license GPL-3.0-or-later
 */

namespace Signls\Sdk\V1;

defined( 'ABSPATH' ) || exit;

final class CounterStore {

	const SCHEMA_VERSION = 1;

	private const FAILURE_CLASSES = array(
		'transport_dns',
		'transport_tls',
		'transport_connect',
		'transport_timeout',
		'http_4xx',
		'http_429',
		'http_5xx',
		'auth',
		'configuration',
		'validation',
		'remote_rejected',
		'unknown',
	);

	public static function ensure_schema(): bool {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! method_exists( $wpdb, 'query' ) ) {
			return false;
		}
		if ( self::SCHEMA_VERSION === (int) get_option( 'signls_sdk_counter_schema_version', 0 ) ) {
			return true;
		}

		$lock_name = 'signls_sdk_counter_schema_v1';
		$locked    = method_exists( $wpdb, 'get_var' ) ? (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s,5)', $lock_name ) ) : 0;
		if ( 1 !== $locked ) {
			return false;
		}

		$table = self::table();
		$sql   = "CREATE TABLE IF NOT EXISTS {$table} (
			product_hash char(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
			integration_slug varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
			operation_slug varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
			counter_slug varchar(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
			counter_value bigint unsigned NOT NULL DEFAULT 0,
			updated_at bigint unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY (product_hash,integration_slug,operation_slug,counter_slug),
			KEY updated_at (updated_at)
		) ENGINE=InnoDB";

		$created = false !== $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Fixed SDK-owned DDL.
		if ( $created ) {
			if ( false === get_option( 'signls_sdk_counter_schema_version', false ) ) {
				add_option( 'signls_sdk_counter_schema_version', self::SCHEMA_VERSION, '', false );
			} else {
				update_option( 'signls_sdk_counter_schema_version', self::SCHEMA_VERSION, false );
			}
		}
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
		return $created;
	}

	public static function record_outcome( string $product, string $integration, string $operation, bool $success, string $failure_class = 'unknown' ): bool {
		global $wpdb;
		if ( ! self::ensure_schema() ) {
			return false;
		}

		$integration = Sanitizer::slug( $integration, 32 );
		$operation   = Sanitizer::slug( $operation, 32 );
		$failure     = Sanitizer::slug( $failure_class, 32, 'unknown' );
		if ( '' === $integration || '' === $operation || ! in_array( $failure, self::FAILURE_CLASSES, true ) ) {
			return false;
		}

		$counters = array( 'attempts', $success ? 'successes' : 'failures', $success ? 'outcome_success' : 'failure_' . $failure );
		$values   = array();
		$args     = array();
		$hash     = substr( hash( 'sha256', $product ), 0, 12 );
		$now      = time();
		foreach ( $counters as $counter ) {
			$values[] = '(%s,%s,%s,%s,1,%d)';
			array_push( $args, $hash, $integration, $operation, $counter, $now );
		}

		$sql = 'INSERT INTO ' . self::table() . ' (product_hash,integration_slug,operation_slug,counter_slug,counter_value,updated_at) VALUES ' . implode( ',', $values ) . ' ON DUPLICATE KEY UPDATE counter_value=counter_value+1,updated_at=VALUES(updated_at)';
		return false !== $wpdb->query( $wpdb->prepare( $sql, $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic placeholders only; identifiers are SDK-owned.
	}

	public static function read_product( string $product ): array {
		global $wpdb;
		if ( ! self::ensure_schema() ) {
			return array();
		}
		// phpcs:disable Generic.Formatting.MultipleStatementAlignment -- The prepared-query annotation would otherwise distort local assignment indentation.
		$hash = substr( hash( 'sha256', $product ), 0, 12 );
		$sql  = $wpdb->prepare( 'SELECT integration_slug,operation_slug,counter_slug,counter_value,updated_at FROM ' . self::table() . ' WHERE product_hash=%s', $hash ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The SDK owns the fixed table name; the value remains prepared.
		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared above.
		// phpcs:enable Generic.Formatting.MultipleStatementAlignment
		return is_array( $rows ) ? $rows : array();
	}

	public static function delete_product( string $product ): bool {
		global $wpdb;
		$hash = substr( hash( 'sha256', $product ), 0, 12 );
		return false !== $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table() . ' WHERE product_hash=%s', $hash ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Fixed SDK-owned table.
	}

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'signls_signal_counters';
	}
}
