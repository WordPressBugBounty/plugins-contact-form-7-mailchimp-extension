<?php
/**
 * Installation data handler.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

class Cmatic_Install_Data {

	public const MIN_VALID_TIMESTAMP = 1000000000;

	/**
	 * Dedicated wp_options row for install_id (independent of serialized blob).
	 * Non-autoloaded — survives blob corruption, autoload failures, and cache races.
	 */
	private const INSTALL_ID_OPTION = 'cmatic_install_id';

	private $options;

	public function __construct( Cmatic_Options_Repository $options ) {
		$this->options = $options;
	}

	public function ensure() {
		$data        = $this->options->get_all();
		$changed     = false;
		$regenerated = false;

		if ( ! isset( $data['install'] ) || ! is_array( $data['install'] ) ) {
			$data['install'] = array();
			$changed         = true;
		}

		if ( empty( $data['install']['id'] ) ) {
			list( $data['install']['id'], $regenerated ) = $this->recover_or_generate_id();
			$changed = true;
		}

		// Record regeneration counters INTO $data so they're saved in the same write.
		if ( $regenerated ) {
			$regen_count                       = isset( $data['install']['id_regen_count'] ) ? (int) $data['install']['id_regen_count'] : 0;
			$data['install']['id_regen_count'] = $regen_count + 1;
			$data['install']['id_regen_at']    = time();
		}

		$quest = isset( $data['install']['quest'] ) ? (int) $data['install']['quest'] : 0;
		if ( $quest < self::MIN_VALID_TIMESTAMP ) {
			$data['install']['quest'] = $this->determine_quest( $data );
			$changed                  = true;
		}

		// Backfill: ensure dedicated option exists for existing installs.
		if ( ! empty( $data['install']['id'] ) && get_option( self::INSTALL_ID_OPTION, '' ) === '' ) {
			update_option( self::INSTALL_ID_OPTION, $data['install']['id'], 'no' );
		}

		if ( $changed ) {
			$this->options->save( $data );
			update_option( self::INSTALL_ID_OPTION, $data['install']['id'], 'no' );
		}
	}

	public function get_install_id() {
		// Tier 1: Repository cache (singleton — warm within this request).
		$install_id = $this->options->get( 'install.id', '' );
		if ( ! empty( $install_id ) ) {
			// Backfill dedicated option on first call after upgrade (one-time).
			if ( get_option( self::INSTALL_ID_OPTION, '' ) === '' ) {
				update_option( self::INSTALL_ID_OPTION, $install_id, 'no' );
			}
			return $install_id;
		}

		// Cache miss — recover from independent sources or generate.
		list( $install_id, $regenerated ) = $this->recover_or_generate_id();

		// Persist recovered/generated ID back to repository + dedicated option.
		$this->options->set( 'install.id', $install_id );
		update_option( self::INSTALL_ID_OPTION, $install_id, 'no' );

		// Record regeneration AFTER the ID is persisted (set() merges into cache).
		if ( $regenerated ) {
			$regen_count = (int) $this->options->get( 'install.id_regen_count', 0 );
			$this->options->set( 'install.id_regen_count', $regen_count + 1 );
			$this->options->set( 'install.id_regen_at', time() );
		}

		return $install_id;
	}

	public function get_quest() {
		$quest = (int) $this->options->get( 'install.quest', 0 );

		if ( $quest >= self::MIN_VALID_TIMESTAMP ) {
			return $quest;
		}

		$quest = $this->determine_quest( $this->options->get_all() );
		$this->options->set( 'install.quest', $quest );

		return $quest;
	}

	/**
	 * Three-tier install_id recovery with generation as last resort.
	 *
	 * @return array{0: string, 1: bool} The install_id and whether it was newly generated.
	 */
	private function recover_or_generate_id(): array {
		// Tier 2: Dedicated option row (independent of serialized blob).
		$standalone = get_option( self::INSTALL_ID_OPTION, '' );
		if ( ! empty( $standalone ) ) {
			return array( $standalone, false );
		}

		// Tier 3: Raw blob read (bypasses repository instance cache).
		$raw = get_option( 'cmatic', array() );
		if ( is_array( $raw ) && ! empty( $raw['install']['id'] ) ) {
			// Found in blob — backfill dedicated option immediately.
			update_option( self::INSTALL_ID_OPTION, $raw['install']['id'], 'no' );
			return array( $raw['install']['id'], false );
		}

		// Last resort: generate new ID.
		return array( $this->generate_install_id(), true );
	}

	private function generate_install_id(): string {
		$new_id = bin2hex( random_bytes( 6 ) );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[ChimpMatic] install_id generated: ' . $new_id ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		return $new_id;
	}

	private function determine_quest( $data ) {
		$candidates = array();

		// 1. Legacy mce_loyalty (highest priority - original timestamp).
		$loyalty = $this->options->get_legacy( 'mce_loyalty' );
		if ( is_array( $loyalty ) && ! empty( $loyalty[0] ) ) {
			$candidates[] = (int) $loyalty[0];
		}

		// 2. Lifecycle activations.
		$activations = isset( $data['lifecycle']['activations'] ) ? $data['lifecycle']['activations'] : array();
		if ( ! empty( $activations ) && is_array( $activations ) ) {
			$candidates[] = (int) min( $activations );
		}

		// 3. Telemetry opt-in date.
		$opt_in = isset( $data['telemetry']['opt_in_date'] ) ? (int) $data['telemetry']['opt_in_date'] : 0;
		if ( $opt_in >= self::MIN_VALID_TIMESTAMP ) {
			$candidates[] = $opt_in;
		}

		// 4. API first connected.
		$api_first = isset( $data['api']['first_connected'] ) ? (int) $data['api']['first_connected'] : 0;
		if ( $api_first >= self::MIN_VALID_TIMESTAMP ) {
			$candidates[] = $api_first;
		}

		// 5. First submission.
		$sub_first = isset( $data['submissions']['first'] ) ? (int) $data['submissions']['first'] : 0;
		if ( $sub_first >= self::MIN_VALID_TIMESTAMP ) {
			$candidates[] = $sub_first;
		}

		// 6. Fallback to current time.
		return ! empty( $candidates ) ? min( $candidates ) : time();
	}
}
