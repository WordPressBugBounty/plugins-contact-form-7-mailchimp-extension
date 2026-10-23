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

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing -- Typed signatures follow the existing Lite class convention.
final class FormSettings {
	const OPTION_PREFIX = 'cf7_mch_';

	/** @var int */
	private $form_id;

	/** @var array<string,mixed> */
	private $record;

	/**
	 * @param mixed $record
	 */
	public function __construct( int $form_id, $record ) {
		$this->form_id = $form_id;
		$this->record  = is_array( $record ) ? $record : array();
	}

	public static function option_name( int $form_id ): string {
		return self::OPTION_PREFIX . $form_id;
	}

	public function form_id(): int {
		return $this->form_id;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function record(): array {
		return $this->record;
	}

	public function is_empty(): bool {
		return array() === $this->record;
	}

	public function provider(): string {
		return \Cmatic_Lite_Esp_Registry::get_selected( $this->record );
	}

	/**
	 * @return array<string,mixed>
	 */
	public function section( string $slug ): array {
		if ( 'mailchimp' === $slug ) {
			return $this->record;
		}
		$providers = isset( $this->record['providers'] ) && is_array( $this->record['providers'] ) ? $this->record['providers'] : array();
		return isset( $providers[ $slug ] ) && is_array( $providers[ $slug ] ) ? $providers[ $slug ] : array();
	}

	/**
	 * @param array<string,mixed> $section
	 */
	public function with_section( string $slug, array $section ): FormSettings {
		if ( 'mailchimp' === $slug ) {
			return new self( $this->form_id, $section );
		}
		$record                       = $this->record;
		$record['providers']          = isset( $record['providers'] ) && is_array( $record['providers'] ) ? $record['providers'] : array();
		$record['providers'][ $slug ] = $section;
		return new self( $this->form_id, $record );
	}

	/**
	 * @param array<string,mixed> $changes
	 */
	public function with( array $changes ): FormSettings {
		return new self( $this->form_id, array_merge( $this->record, $changes ) );
	}

	/**
	 * @param array<int,string> $keys
	 */
	public function without( array $keys ): FormSettings {
		$record = $this->record;
		foreach ( $keys as $key ) {
			unset( $record[ $key ] );
		}
		return new self( $this->form_id, $record );
	}

	/**
	 * @param array<string,mixed> $section
	 */
	public static function is_connected( array $section ): bool {
		return 1 === MappingSchema::int( $section['api-validation'] ?? 0 );
	}

	/**
	 * @param array<string,mixed> $section
	 */
	public static function raw_list( array $section ): string {
		$list = $section['list'] ?? '';
		if ( is_array( $list ) ) {
			$first = reset( $list );
			$list  = false === $first ? '' : $first;
		}
		return is_scalar( $list ) ? (string) $list : '';
	}

	/**
	 * @param array<string,mixed> $section
	 */
	public static function selected_list( array $section ): string {
		return sanitize_text_field( self::raw_list( $section ) );
	}

	/**
	 * @param array<string,mixed> $section
	 * @return array<int,mixed>
	 */
	public static function lists( array $section ): array {
		if ( ! isset( $section['lisdata'] ) || ! is_array( $section['lisdata'] ) ) {
			return array();
		}
		return isset( $section['lisdata']['lists'] ) && is_array( $section['lisdata']['lists'] ) ? $section['lisdata']['lists'] : array();
	}

	/**
	 * @param array<string,mixed> $section
	 */
	public static function list_exists( array $section, string $list_id ): bool {
		foreach ( self::lists( $section ) as $list ) {
			if ( is_array( $list ) && isset( $list['id'] ) && is_scalar( $list['id'] ) && $list_id === (string) $list['id'] ) {
				return true;
			}
		}
		return false;
	}
}
