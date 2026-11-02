<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    hello@chimpmatic.com
 * @copyright 2014-2026 ChimpMatic
 * @license   GPL-3.0+
 */

declare(strict_types=1);

namespace Cmatic\Lite\Settings;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing -- Typed signatures follow the existing Lite class convention.
final class FormSettingsRepository {
	public static function load( int $form_id ): FormSettings {
		return new FormSettings( $form_id, get_option( FormSettings::option_name( $form_id ), array() ) );
	}

	/**
	 * @param array<string,mixed> $record
	 */
	public static function store( int $form_id, array $record ): bool {
		return (bool) update_option( FormSettings::option_name( $form_id ), $record );
	}

	public static function reset_choices( int $form_id ): void {
		$current             = self::load( $form_id );
		$keys                = array_fill_keys( array( 'api', 'api-validation', 'auth_type', 'api_key_backup', 'lisdata', 'lists', 'account', 'account_name', 'dc', 'user_id' ), true );
		$record              = array_intersect_key( $current->record(), $keys );
		$record['provider']  = $current->provider();
		$record['providers'] = array();
		foreach ( \Cmatic_Lite_Esp_Registry::catalog()->api_key_slugs() as $slug ) {
			$section = array_intersect_key( $current->section( $slug ), $keys );
			if ( array() !== $section ) {
				$record['providers'][ $slug ] = $section;
			}
		}
		self::store( $form_id, $record );
	}

	public static function delete( int $form_id ): bool {
		return (bool) delete_option( FormSettings::option_name( $form_id ) );
	}

	/**
	 * @param array<string,mixed> $changes
	 * @param array<int,string>   $drop
	 * @return array<string,mixed>
	 */
	public static function merge( int $form_id, array $changes, array $drop = array() ): array {
		$record = self::load( $form_id )->with( $changes )->without( $drop )->record();
		self::store( $form_id, $record );
		return $record;
	}

	/**
	 * @param array<string,mixed> $changes
	 */
	public static function merge_section( int $form_id, string $slug, array $changes ): bool {
		$settings = self::load( $form_id );
		$record   = $settings->with_section( $slug, array_merge( $settings->section( $slug ), $changes ) )->record();
		if ( self::store( $form_id, $record ) ) {
			return true;
		}
		$stored = self::load( $form_id )->section( $slug );
		foreach ( $changes as $key => $value ) {
			if ( ! array_key_exists( $key, $stored ) || maybe_serialize( $stored[ $key ] ) !== maybe_serialize( $value ) ) {
				return false;
			}
		}
		return true;
	}

	public static function remember_provider( int $form_id, string $slug ): void {
		$record = self::load( $form_id )->record();
		if ( ( $record['provider'] ?? '' ) === $slug ) {
			return;
		}
		$record['provider'] = $slug;
		self::store( $form_id, $record );
	}

	public static function increment_sent( int $form_id ): void {
		$record               = self::load( $form_id )->record();
		$record['stats_sent'] = MappingSchema::int( $record['stats_sent'] ?? 0 ) + 1;
		self::store( $form_id, $record );
	}

	private function __construct() {}
}
