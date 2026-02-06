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

	private const MIN_VALID_TIMESTAMP = 1000000000;

	private $options;

	public function __construct( Cmatic_Options_Repository $options ) {
		$this->options = $options;
	}

	public function ensure() {
		$data    = $this->options->get_all();
		$changed = false;

		if ( ! isset( $data['install'] ) || ! is_array( $data['install'] ) ) {
			$data['install'] = array();
			$changed         = true;
		}

		if ( empty( $data['install']['id'] ) ) {
			$data['install']['id'] = $this->generate_install_id();
			$changed               = true;
		}

		$quest = isset( $data['install']['quest'] ) ? (int) $data['install']['quest'] : 0;
		if ( $quest < self::MIN_VALID_TIMESTAMP ) {
			$data['install']['quest'] = $this->determine_quest( $data );
			$changed                  = true;
		}

		if ( $changed ) {
			$this->options->save( $data );
		}
	}

	public function get_install_id() {
		$install_id = $this->options->get( 'install.id', '' );

		if ( empty( $install_id ) ) {
			$install_id = $this->generate_install_id();
			$this->options->set( 'install.id', $install_id );
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

	private function generate_install_id() {
		return bin2hex( random_bytes( 6 ) );
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
