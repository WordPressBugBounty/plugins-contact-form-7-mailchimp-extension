<?php
/**
 * Notification message handler.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

class Cmatic_Notification {
	const ERROR   = 'error';
	const WARNING = 'warning';
	const INFO    = 'info';
	const SUCCESS = 'success';

	private $message;
	private $options  = array();
	private $defaults = array(
		'type'          => self::INFO,
		'id'            => '',
		'user_id'       => null,
		'priority'      => 0.5,
		'dismissal_key' => null,
		'capabilities'  => array( 'manage_options' ),
		'link'          => '',
		'link_text'     => '',
	);

	public function __construct( $message, $options = array() ) {
		$this->message = $message;
		$this->options = wp_parse_args( $options, $this->defaults );

		if ( null === $this->options['user_id'] ) {
			$this->options['user_id'] = get_current_user_id();
		}

		$this->options['priority'] = min( 1, max( 0, $this->options['priority'] ) );
	}

	public function get_id() {
		return $this->options['id'];
	}

	public function get_message() {
		return $this->message;
	}

	public function get_type() {
		return $this->options['type'];
	}

	public function get_priority() {
		return $this->options['priority'];
	}

	public function get_user_id() {
		return (int) $this->options['user_id'];
	}

	public function get_dismissal_key() {
		if ( empty( $this->options['dismissal_key'] ) ) {
			return $this->options['id'];
		}
		return $this->options['dismissal_key'];
	}

	public function get_link() {
		return $this->options['link'];
	}

	public function get_link_text() {
		return $this->options['link_text'];
	}

	public function is_persistent() {
		return ! empty( $this->options['id'] );
	}

	public function display_for_current_user() {
		if ( ! $this->is_persistent() ) {
			return true;
		}

		return $this->user_has_capabilities();
	}

	private function user_has_capabilities() {
		$capabilities = $this->options['capabilities'];

		if ( empty( $capabilities ) ) {
			return true;
		}

		foreach ( $capabilities as $capability ) {
			if ( ! current_user_can( $capability ) ) {
				return false;
			}
		}

		return true;
	}

	public function to_array() {
		return array(
			'message' => $this->message,
			'options' => $this->options,
		);
	}

	public static function from_array( $data ) {
		$message = isset( $data['message'] ) ? $data['message'] : '';
		$options = isset( $data['options'] ) ? $data['options'] : array();
		return new self( $message, $options );
	}
}
