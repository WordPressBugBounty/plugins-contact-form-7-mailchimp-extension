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
final class Collection {
	/**
	 * @param array<mixed> $spec
	 * @param callable(string):array<string,mixed> $request
	 * @return array<string,mixed>
	 */
	public static function fetch( array $spec, string $path, callable $request ): array {
		$result = self::pages( $spec, $path, $request );
		if ( empty( $result['success'] ) || ! isset( $spec['children'] ) || ! is_array( $spec['children'] ) ) {
			return $result;
		}
		$parents = $result['body'];
		$child   = $spec['children'];
		$records = array();
		foreach ( is_array( $parents ) ? $parents : array() as $parent ) {
			if ( ! is_array( $parent ) ) {
				return HttpTransport::failure( 'api_error', 'Invalid parent catalog record.' );
			}
			$id     = Protocol::text( Protocol::read( $parent, Protocol::text( $spec['id'] ?? 'id' ) ) );
			$result = self::pages( $child, str_replace( '{parent_id}', rawurlencode( $id ), Protocol::text( $child['path'] ?? '' ) ), $request );
			if ( empty( $result['success'] ) ) {
				return $result;
			}
			foreach ( is_array( $result['body'] ) ? $result['body'] : array() as $record ) {
				if ( ! is_array( $record ) ) {
					continue;
				}
				$name_key = Protocol::text( $child['name'] ?? 'name' );
				$prefix   = Protocol::text( Protocol::read( $parent, Protocol::text( $spec['name'] ?? 'name' ) ) );
				$name     = Protocol::text( Protocol::read( $record, $name_key ) );
				Protocol::put( $record, $name_key, '' === $prefix ? $name : sanitize_text_field( $prefix ) . ' / ' . $name );
				$records[ Protocol::text( Protocol::read( $record, Protocol::text( $child['id'] ?? 'id' ) ) ) ] = $record;
			}
		}
		$result['body'] = array_values( $records );
		return $result;
	}

	/**
	 * @param array<mixed> $spec
	 * @param callable(string):array<string,mixed> $request
	 * @return array<string,mixed>
	 */
	private static function pages( array $spec, string $path, callable $request ): array {
		$records = array();
		$total   = null;
		$seen    = array();
		$paging  = Protocol::text( $spec['paging'] ?? 'none' );
		for ( $page = 1; $page <= 100; ++$page ) {
			$current_path = str_replace( '{page}', (string) $page, $path );
			if ( isset( $seen[ $current_path ] ) ) {
				return HttpTransport::failure( 'api_error', 'Provider repeated a catalog page.' );
			}
			$seen[ $current_path ] = true;
			$result                = $request( $current_path );
			if ( empty( $result['success'] ) ) {
				return $result;
			}
			$body   = isset( $result['body'] ) && is_array( $result['body'] ) ? $result['body'] : array();
			$source = Protocol::read( $body, Protocol::text( $spec['root'] ?? '' ) );
			if ( ! is_array( $source ) || array_values( $source ) !== $source || ( isset( $spec['limit'] ) && count( $source ) > $spec['limit'] ) ) {
				return HttpTransport::failure( 'api_error', 'Provider returned an invalid catalog.' );
			}
			foreach ( $source as $record ) {
				if ( ! is_array( $record ) ) {
					return HttpTransport::failure( 'api_error', 'Provider returned an invalid catalog record.' );
				}
				if ( ! empty( $spec['id'] ) ) {
					$id   = Protocol::read( $record, Protocol::text( $spec['id'] ) );
					$name = Protocol::read( $record, Protocol::text( $spec['name'] ?? 'name' ) );
					if ( ! is_string( $id ) || '' === $id || ! is_string( $name ) || ( isset( $records[ $id ] ) && empty( $spec['deduplicate'] ) ) ) {
						return HttpTransport::failure( 'api_error', 'Provider returned an invalid or repeated catalog record.' );
					}
					if ( ! isset( $records[ $id ] ) ) {
						$records[ $id ] = $record;
					}
				} else {
					$records[] = $record;
				}
			}
			$done = 'none' === $paging;
			if ( 'headers' === $paging || 'pages' === $paging ) {
				$metadata = 'headers' === $paging ? $result : $body;
				$pages    = Protocol::page( Protocol::read( $metadata, Protocol::text( $spec['total'] ?? 'total_pages' ) ) );
				$current  = Protocol::page( Protocol::read( $metadata, Protocol::text( $spec['current'] ?? 'current_page' ) ) );
				if ( null === $pages || $pages > 100 || $page !== $current || ( null !== $total && $pages !== $total ) || ( 0 === $pages && array() !== $source ) || ( $pages > 1 && array() === $source ) ) {
					return HttpTransport::failure( 'api_error', 'Provider returned an invalid or changing paginated catalog.' );
				}
				$total = $pages;
				$done  = $page >= $pages;
			}
			if ( 'cursor' === $paging ) {
				if ( ! Protocol::has( $body, Protocol::text( $spec['next'] ?? 'paging.next' ) ) ) {
					return HttpTransport::failure( 'api_error', 'Provider omitted pagination metadata.' );
				}
				$next = Protocol::read( $body, Protocol::text( $spec['next'] ?? 'paging.next' ) );
				$done = null === $next;
				if ( ! $done ) {
					$cursor = is_array( $next ) ? Protocol::read( $next, Protocol::text( $spec['cursor'] ?? 'starting_after' ) ) : $next;
					if ( ! is_string( $cursor ) || '' === $cursor || array() === $source ) {
						return HttpTransport::failure( 'api_error', 'Provider returned an invalid pagination cursor.' );
					}
					$base = Protocol::text( $spec['path'] ?? '' );
					$path = $base . ( false === strpos( $base, '?' ) ? '?' : '&' ) . rawurlencode( Protocol::text( $spec['cursor_parameter'] ?? 'starting_after' ) ) . '=' . rawurlencode( $cursor );
				}
			}
			if ( $done ) {
				$result['body'] = array_values( $records );
				return $result;
			}
		}
		return HttpTransport::failure( 'api_error', 'Provider pagination exceeded 100 pages.' );
	}
}
