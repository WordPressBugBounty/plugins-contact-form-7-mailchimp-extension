<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    hello@chimpmatic.com
 * @copyright 2014-2026 ChimpMatic
 * @license   GPL-3.0+
 */

declare(strict_types=1);

namespace Cmatic\Lite\Provider;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing -- Typed signatures follow the existing Lite class convention.
final class Payload {
	/**
	 * @param array<mixed> $spec
	 * @param array<mixed> $fields
	 * @return array<mixed>
	 */
	public static function build( array $spec, string $list_id, string $email, array $fields ): array {
		$body = array();
		Protocol::put( $body, Protocol::text( $spec['email'] ), $email );
		if ( ! empty( $spec['list'] ) ) {
			Protocol::put( $body, Protocol::text( $spec['list'] ), $list_id );
		}
		foreach ( isset( $spec['defaults'] ) && is_array( $spec['defaults'] ) ? $spec['defaults'] : array() as $path => $value ) {
			Protocol::put( $body, (string) $path, $value );
		}
		if ( ! empty( $spec['name'] ) && isset( $fields['NAME'] ) && is_scalar( $fields['NAME'] ) ) {
			Protocol::put( $body, Protocol::text( $spec['name'] ), (string) $fields['NAME'] );
		}
		$custom = array();
		foreach ( $fields as $key => $value ) {
			if ( 'EMAIL' === $key || ( 'NAME' === $key && ! empty( $spec['name'] ) ) ) {
				continue;
			}
			$parts = array_values( array_filter( is_array( $value ) ? $value : array( $value ), 'is_scalar' ) );
			if ( array() === $parts ) {
				continue;
			}
			$strings = array_map( 'strval', $parts );
			if ( 'map' === $spec['format'] ) {
				$custom[ $key ] = is_array( $value ) ? $parts : $parts[0];
			} elseif ( 'pairs' === $spec['format'] ) {
				$custom[] = (string) $key . '=' . implode( ', ', $strings );
			} elseif ( 'arrays' === $spec['format'] ) {
				$custom[] = array(
					Protocol::text( $spec['field_key'] )   => (string) $key,
					Protocol::text( $spec['field_value'] ) => $strings,
				);
			} else {
				foreach ( $strings as $part ) {
					$custom[] = array(
						Protocol::text( $spec['field_key'] )   => (string) $key,
						Protocol::text( $spec['field_value'] ) => $part,
					);
				}
			}
		}
		if ( array() !== $custom ) {
			Protocol::put( $body, Protocol::text( $spec['fields'] ), 'map' === $spec['format'] ? (object) $custom : $custom );
		}
		return $body;
	}

	/**
	 * @param array<mixed> $spec
	 * @param array<string,mixed> $result
	 * @return array<string,mixed>
	 */
	public static function answer( array $spec, array $result, string $email ): array {
		$status = $result['status'] ?? 0;
		$body   = isset( $result['body'] ) && is_array( $result['body'] ) ? $result['body'] : array();
		foreach ( isset( $spec['duplicates'] ) && is_array( $spec['duplicates'] ) ? $spec['duplicates'] : array() as $duplicate ) {
			if ( is_array( $duplicate ) && $status === ( $duplicate['status'] ?? null ) && ( ! isset( $duplicate['error_path'] ) || Protocol::read( $body, Protocol::text( $duplicate['error_path'] ) ) === ( $duplicate['value'] ?? null ) ) ) {
				$result['reason'] = 'already_subscribed';
				return $result;
			}
		}
		if ( empty( $result['success'] ) ) {
			return $result;
		}
		if ( ( isset( $spec['codes'] ) && is_array( $spec['codes'] ) && ! in_array( $status, $spec['codes'], true ) ) || ( isset( $spec['ack_email'] ) && 0 !== strcasecmp( $email, Protocol::text( Protocol::read( $body, Protocol::text( $spec['ack_email'] ) ) ) ) ) ) {
			return HttpTransport::failure( 'api_error', 'Provider did not acknowledge the submitted contact.' );
		}
		if ( ! empty( $spec['queued'] ) ) {
			$result['data'] = array( 'status' => 'queued' );
		}
		if ( isset( $spec['status_path'], $spec['status_map'] ) && is_array( $spec['status_map'] ) ) {
			$remote = Protocol::text( Protocol::read( $body, Protocol::text( $spec['status_path'] ) ) );
			$status = $spec['status_map'][ $remote ] ?? null;
			if ( ! is_string( $status ) || ! in_array( $status, array( 'subscribed', 'pending' ), true ) ) {
				return HttpTransport::failure( 'api_error', 'Provider did not acknowledge an accepted subscription status.' );
			}
			$result['subscription_status'] = $status;
		}
		return $result;
	}
}
