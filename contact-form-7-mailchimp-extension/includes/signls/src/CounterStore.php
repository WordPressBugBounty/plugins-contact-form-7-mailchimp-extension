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

	const SCHEMA_VERSION = 2;

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
		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'query' ) || ! method_exists( $wpdb, 'prepare' ) || ! method_exists( $wpdb, 'get_var' ) ) {
			return false;
		}
		$stored_version = get_option( 'signls_sdk_counter_schema_version', 0 );
		$stored_version = is_scalar( $stored_version ) ? (int) $stored_version : 0;
		if ( self::SCHEMA_VERSION === $stored_version ) {
			return true;
		}

		$lock_name = 'signls_sdk_counter_schema_v2';
		$locked    = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s,5)', $lock_name ) );
		if ( 1 !== $locked ) {
			return false;
		}

		$table        = self::table();
		$daily_table  = self::daily_table();
		$reason_table = self::reason_table();
		$lifetime_sql = "CREATE TABLE IF NOT EXISTS {$table} (
			product_hash char(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
			integration_slug varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
			operation_slug varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
			counter_slug varchar(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
			counter_value bigint unsigned NOT NULL DEFAULT 0,
			first_recorded_at bigint unsigned NOT NULL DEFAULT 0,
			last_success_at bigint unsigned NOT NULL DEFAULT 0,
			last_failure_at bigint unsigned NOT NULL DEFAULT 0,
			updated_at bigint unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY (product_hash,integration_slug,operation_slug,counter_slug),
			KEY updated_at (updated_at)
		) ENGINE=InnoDB";
		$daily_sql    = "CREATE TABLE IF NOT EXISTS {$daily_table} (
			product_hash char(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
			integration_slug varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
			operation_slug varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
			signal_date date NOT NULL,
			attempts bigint unsigned NOT NULL DEFAULT 0,
			successes bigint unsigned NOT NULL DEFAULT 0,
			failures bigint unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY (product_hash,integration_slug,operation_slug,signal_date),
			KEY signal_date (signal_date)
		) ENGINE=InnoDB";
		$reason_sql   = "CREATE TABLE IF NOT EXISTS {$reason_table} (
			product_hash char(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
			integration_slug varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
			operation_slug varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
			reason_code varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
			counter_value bigint unsigned NOT NULL DEFAULT 0,
			sample varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
			last_seen_at bigint unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY (product_hash,integration_slug,operation_slug,reason_code),
			KEY last_seen_at (last_seen_at)
		) ENGINE=InnoDB";

		$created = false !== $wpdb->query( $lifetime_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Fixed SDK-owned DDL.
		$created = $created && false !== $wpdb->query( $daily_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Fixed SDK-owned DDL.
		$created = $created && false !== $wpdb->query( $reason_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Fixed SDK-owned DDL.
		foreach ( array( 'first_recorded_at', 'last_success_at', 'last_failure_at' ) as $column ) {
			if ( $created && ! self::column_exists( $table, $column ) ) {
				$alter_sql = $wpdb->prepare( 'ALTER TABLE %i ADD COLUMN %i bigint unsigned NOT NULL DEFAULT 0 AFTER counter_value', $table, $column );
				$alter_sql = self::finalize_identifier_placeholders( $alter_sql, array( $table, $column ) );
				$created   = '' !== $alter_sql && false !== $wpdb->query( $alter_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Fixed SDK-owned identifiers selected from a closed list.
			}
		}
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

	public static function record_outcome( string $product, string $integration, string $operation, bool $success, string $failure_class = 'unknown', string $reason_code = '', string $reason_sample = '' ): bool {
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
		$hash     = self::product_hash( $product );
		$now      = time();
		foreach ( $counters as $counter ) {
			$values[] = '(%s,%s,%s,%s,1,%d,%d,%d,%d)';
			array_push( $args, $hash, $integration, $operation, $counter, $now, $success ? $now : 0, $success ? 0 : $now, $now );
		}

		$lifetime_sql  = 'INSERT INTO ' . self::table() . ' (product_hash,integration_slug,operation_slug,counter_slug,counter_value,first_recorded_at,last_success_at,last_failure_at,updated_at) VALUES ' . implode( ',', $values ) . ' ON DUPLICATE KEY UPDATE counter_value=counter_value+1,first_recorded_at=IF(first_recorded_at=0,VALUES(first_recorded_at),LEAST(first_recorded_at,VALUES(first_recorded_at))),last_success_at=GREATEST(last_success_at,VALUES(last_success_at)),last_failure_at=GREATEST(last_failure_at,VALUES(last_failure_at)),updated_at=VALUES(updated_at)';
		$daily_table   = self::daily_table();
		$daily_sql     = $wpdb->prepare(
			'INSERT INTO %i (product_hash,integration_slug,operation_slug,signal_date,attempts,successes,failures) VALUES (%s,%s,%s,%s,1,%d,%d) ON DUPLICATE KEY UPDATE attempts=attempts+1,successes=successes+VALUES(successes),failures=failures+VALUES(failures)',
			$daily_table,
			$hash,
			$integration,
			$operation,
			gmdate( 'Y-m-d', $now ),
			$success ? 1 : 0,
			$success ? 0 : 1
		);
		$daily_sql     = self::finalize_identifier_placeholders( $daily_sql, array( $daily_table ) );
		$reason_code   = Sanitizer::slug( $reason_code, 32 );
		$reason_sample = Sanitizer::text( $reason_sample, 50 );

		if ( false === $wpdb->query( 'START TRANSACTION' ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- SDK-owned atomic counter transaction.
			return false;
		}
		$ok = false !== $wpdb->query( $wpdb->prepare( $lifetime_sql, $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic placeholders only; identifiers are SDK-owned.
		$ok = $ok && false !== $wpdb->query( $daily_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared above.
		if ( $ok && ! $success && '' !== $reason_code ) {
			$reason_table = self::reason_table();
			$reason_sql   = $wpdb->prepare(
				'INSERT INTO %i (product_hash,integration_slug,operation_slug,reason_code,counter_value,sample,last_seen_at) VALUES (%s,%s,%s,%s,1,%s,%d) ON DUPLICATE KEY UPDATE counter_value=counter_value+1,sample=IF(VALUES(sample)<>\'\',VALUES(sample),sample),last_seen_at=VALUES(last_seen_at)',
				$reason_table,
				$hash,
				$integration,
				$operation,
				$reason_code,
				$reason_sample,
				$now
			);
			$reason_sql   = self::finalize_identifier_placeholders( $reason_sql, array( $reason_table ) );
			$ok           = false !== $wpdb->query( $reason_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared above.
		}
		if ( ! $ok ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- SDK-owned atomic counter transaction.
			return false;
		}
		if ( false === $wpdb->query( 'COMMIT' ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- SDK-owned atomic counter transaction.
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- SDK-owned atomic counter transaction.
			return false;
		}
		return true;
	}

	public static function read_daily( string $product, int $days ): array {
		global $wpdb;
		if ( ! self::ensure_schema() ) {
			return array();
		}
		$days = max( 1, min( 365, $days ) );
		$from = gmdate( 'Y-m-d', time() - ( $days - 1 ) * DAY_IN_SECONDS );
		$sql  = $wpdb->prepare( 'SELECT integration_slug,operation_slug,signal_date,attempts,successes,failures FROM ' . self::daily_table() . ' WHERE product_hash=%s AND signal_date>=%s ORDER BY signal_date ASC,integration_slug ASC,operation_slug ASC', self::product_hash( $product ), $from ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Fixed SDK-owned table and prepared values.
		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared above.
		return is_array( $rows ) ? $rows : array();
	}

	public static function read_reasons( string $product ): array {
		global $wpdb;
		if ( ! self::ensure_schema() ) {
			return array();
		}
		$sql  = $wpdb->prepare( 'SELECT integration_slug,operation_slug,reason_code,counter_value,sample,last_seen_at FROM ' . self::reason_table() . ' WHERE product_hash=%s ORDER BY counter_value DESC,reason_code ASC', self::product_hash( $product ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Fixed SDK-owned table and prepared value.
		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared above.
		return is_array( $rows ) ? $rows : array();
	}

	public static function read_product( string $product ): array {
		global $wpdb;
		if ( ! self::ensure_schema() ) {
			return array();
		}
		// phpcs:disable Generic.Formatting.MultipleStatementAlignment -- The prepared-query annotation would otherwise distort local assignment indentation.
		$hash = self::product_hash( $product );
		$sql  = $wpdb->prepare( 'SELECT integration_slug,operation_slug,counter_slug,counter_value,first_recorded_at,last_success_at,last_failure_at,updated_at FROM ' . self::table() . ' WHERE product_hash=%s', $hash ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The SDK owns the fixed table name; the value remains prepared.
		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared above.
		// phpcs:enable Generic.Formatting.MultipleStatementAlignment
		return is_array( $rows ) ? $rows : array();
	}

	public static function delete_product( string $product ): bool {
		global $wpdb;
		$hash = self::product_hash( $product );
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- SDK-owned product cleanup transaction.
			return false;
		}
		foreach ( array( self::reason_table(), self::daily_table(), self::table() ) as $table ) {
			if ( false === $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $table . ' WHERE product_hash=%s', $hash ) ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Closed SDK-owned table list and prepared value.
				$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- SDK-owned product cleanup transaction.
				return false;
			}
		}
		return false !== $wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- SDK-owned product cleanup transaction.
	}

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'signls_signal_counters';
	}

	public static function daily_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'signls_signal_daily_counters';
	}

	public static function reason_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'signls_signal_reason_counters';
	}

	private static function product_hash( string $product ): string {
		return substr( hash( 'sha256', $product ), 0, 12 );
	}

	private static function finalize_identifier_placeholders( string $sql, array $identifiers ): string {
		foreach ( $identifiers as $identifier ) {
			if ( false === strpos( $sql, '%i' ) ) {
				break;
			}
			if ( ! is_string( $identifier ) || 1 !== preg_match( '/^[A-Za-z0-9_]+$/', $identifier ) ) {
				return '';
			}
			$sql = (string) preg_replace( '/%i/', '`' . $identifier . '`', $sql, 1 );
		}
		return false === strpos( $sql, '%i' ) ? $sql : '';
	}

	private static function column_exists( string $table, string $column ): bool {
		global $wpdb;
		$count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s AND COLUMN_NAME=%s',
				$table,
				$column
			)
		);
		return (int) $count > 0;
	}
}
