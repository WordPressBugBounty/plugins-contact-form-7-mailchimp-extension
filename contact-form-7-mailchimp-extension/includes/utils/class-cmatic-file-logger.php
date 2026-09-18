<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

class Cmatic_File_Logger implements Cmatic_Logger_Interface {

	private $is_write_enabled = false;

	private $log_prefix;

	public function __construct( $context = 'Chimpmatic', $enabled = false ) {
		$this->is_write_enabled = (bool) $enabled;
		$this->log_prefix       = '[' . sanitize_key( $context ) . ']';
	}

	public function log( string $level, string $message, $context = null ): void {
		if ( ! $this->is_write_enabled || ! Cmatic_Debug_Log::logging() ) {
			return;
		}

		$log_message = $this->log_prefix . ' ' . trim( $message );

		if ( ! is_null( $context ) ) {
			$log_message .= ' | Data: ' . $this->format_data( $context );
		}

		Cmatic_Debug_Log::write( $level, $log_message );
	}

	private function format_data( $data ) {
		if ( is_string( $data ) ) {
			return $data;
		}

		if ( ! is_array( $data ) ) {
			return wp_json_encode( $data, JSON_UNESCAPED_SLASHES );
		}

		if ( isset( $data['email_address'] ) && isset( $data['status'] ) ) {
			$summary = array(
				'email'  => $data['email_address'] ?? '',
				'status' => $data['status'] ?? '',
				'id'     => $data['id'] ?? '',
			);
			return wp_json_encode( $summary, JSON_UNESCAPED_SLASHES );
		}

		if ( isset( $data['url'] ) && isset( $data['payload'] ) ) {
			$merge_fields = $data['payload']['merge_fields'] ?? array();
			if ( is_object( $merge_fields ) ) {
				$merge_fields = (array) $merge_fields;
			}
			$summary = array(
				'url'    => $data['url'],
				'email'  => $data['payload']['email_address'] ?? '',
				'status' => $data['payload']['status'] ?? '',
				'fields' => is_array( $merge_fields ) ? array_keys( $merge_fields ) : array(),
			);
			return wp_json_encode( $summary, JSON_UNESCAPED_SLASHES );
		}

		$json = wp_json_encode( $data, JSON_UNESCAPED_SLASHES );
		if ( strlen( $json ) <= 1000 ) {
			return $json;
		}

		return substr( $json, 0, 1000 ) . '... [truncated]';
	}
}
