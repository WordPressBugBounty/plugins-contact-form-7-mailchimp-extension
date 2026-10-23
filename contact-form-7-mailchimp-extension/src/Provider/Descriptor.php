<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    hello@chimpmatic.com
 * @copyright 2014-2026 ChimpMatic
 * @license   GPL-3.0+
 */

declare(strict_types=1);

namespace Cmatic\Lite\Provider;

use Cmatic\Lite\Provider\Operations\OperationsInterface;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing -- Typed signatures follow the existing Lite class convention.
final class Descriptor {
	const REQUIRED             = array( 'slug', 'label', 'base_url', 'headers', 'validation_path', 'lists_path', 'fields_path', 'pagination', 'lists', 'fields', 'features', 'vocabulary', 'auth_fields', 'consent', 'operations' );
	const VOCABULARY           = array( 'destination_singular', 'destination_plural', 'person_singular', 'person_plural', 'data_singular', 'data_plural' );
	const HEADER_PLACEHOLDERS  = array( '{key}', '{key_part}', '{basic_key}', '{ua}', '{wp}' );
	const ENTITLEMENT_FEATURES = array( 'routing', 'double_optin', 'status', 'resubscribe', 'consent_metadata', 'create_field' );
	const SUBMISSION_FLAGS     = array( 'typed_fields', 'doi_verification', 'warn_invalid_email', 'list_required', 'zero_values', 'integer_numbers' );

	/** @var string */
	private $slug;

	/** @var string */
	private $label;

	/** @var string */
	private $description;

	/** @var string */
	private $probe_url;

	/** @var string */
	private $base_url;

	/** @var array<string,string> */
	private $headers = array();

	/** @var array<string,string> */
	private $response_format = array();

	/** @var string */
	private $validation_path;

	/** @var string */
	private $lists_path;

	/** @var string */
	private $fields_path;

	/** @var array<mixed> */
	private $pagination;

	/** @var array<mixed> */
	private $lists;

	/** @var array<mixed> */
	private $fields;

	/** @var array<mixed> */
	private $features;

	/** @var array<string,string> */
	private $vocabulary = array();

	/** @var array<int,mixed> */
	private $auth_fields;

	/** @var array<mixed> */
	private $consent;

	/** @var string */
	private $operations;

	/** @var array<string,string> */
	private $entitlements = array();

	/** @var array<string,bool> */
	private $submission = array();

	/** @var array<mixed> */
	private $protocol = array();

	/**
	 * @param array<mixed> $data
	 */
	public function __construct( array $data ) {
		$protocol = $data['protocol'] ?? array();
		if ( ! is_array( $protocol ) ) {
			throw new \InvalidArgumentException( 'Protocol must be a map.' );
		}
		Protocol::validate( $protocol );
		$this->protocol = $protocol;
		foreach ( self::REQUIRED as $key ) {
			if ( ! array_key_exists( $key, $data ) ) {
				throw new \InvalidArgumentException( esc_html( 'Descriptor is missing ' . $key ) );
			}
		}
		$slug = $data['slug'];
		if ( ! is_string( $slug ) || 1 !== preg_match( '/^[a-z]+$/', $slug ) ) {
			throw new \InvalidArgumentException( 'Descriptor slug must be lowercase letters' );
		}
		$label = $data['label'];
		if ( ! is_string( $label ) || '' === $label ) {
			throw new \InvalidArgumentException( esc_html( $slug . ': label must be a non-empty string' ) );
		}
		$base_url = $data['base_url'];
		if ( ! is_string( $base_url ) || 1 !== preg_match( '#^https://([a-z0-9.{}-]+)(/[^/?\#][^?\#]*[^/?\#]|/[^/?\#])?$#', $base_url, $parts ) || ! in_array( $parts[1], Catalog::hosts(), true ) ) {
			throw new \InvalidArgumentException( esc_html( $slug . ': base_url must be https on a declared host, without a trailing slash, query or fragment' ) );
		}
		self::assert_path( $slug, 'base_url path', isset( $parts[2] ) ? $parts[2] : '', array() );
		$description = $data['description'] ?? '';
		$probe_url   = $data['probe_url'] ?? 'https://' . $parts[1] . '/';
		if ( ! is_string( $description ) || ! is_string( $probe_url ) || 1 !== preg_match( '#^https://[a-z0-9.-]+/$#', $probe_url ) ) {
			throw new \InvalidArgumentException( esc_html( $slug . ': description must be text and probe_url an https host root' ) );
		}
		$this->description = $description;
		$this->probe_url   = $probe_url;
		$response_format   = $data['response_format'] ?? array();
		if ( ! is_array( $response_format ) ) {
			throw new \InvalidArgumentException( esc_html( $slug . ': response_format must be a map' ) );
		}
		foreach ( $response_format as $key => $value ) {
			if ( ! in_array( $key, array( 'string_key', 'error_key', 'total_pages_header', 'current_page_header' ), true ) || ! is_string( $value ) || 1 !== preg_match( '/^[A-Za-z][A-Za-z0-9_]*$/', $value ) ) {
				throw new \InvalidArgumentException( esc_html( $slug . ': response_format contains an unsupported key or invalid identifier' ) );
			}
			$this->response_format[ $key ] = $value;
		}
		if ( ! is_array( $data['headers'] ) || array() === $data['headers'] ) {
			throw new \InvalidArgumentException( esc_html( $slug . ': headers must be a non-empty map' ) );
		}
		foreach ( $data['headers'] as $name => $value ) {
			if ( ! is_string( $name ) || 1 !== preg_match( '/^[A-Za-z0-9-]+$/', $name ) || ! is_string( $value ) || '' === $value ) {
				throw new \InvalidArgumentException( esc_html( $slug . ': header names must be tokens and header values non-empty strings' ) );
			}
			self::assert_placeholders( $slug, 'header ' . $name, $value, self::HEADER_PLACEHOLDERS );
			$this->headers[ $name ] = $value;
		}
		$paths = array();
		foreach ( array( 'validation_path', 'lists_path', 'fields_path' ) as $key ) {
			$path = $data[ $key ];
			if ( ! is_string( $path ) ) {
				throw new \InvalidArgumentException( esc_html( $slug . ': ' . $key . ' must be a string' ) );
			}
			self::assert_path( $slug, $key, $path, 'fields_path' === $key ? array( '{list_id}' ) : array() );
			$paths[ $key ] = $path;
		}
		if ( '' === $paths['validation_path'] || '' === $paths['lists_path'] ) {
			throw new \InvalidArgumentException( esc_html( $slug . ': validation_path and lists_path are required' ) );
		}
		$pagination = $data['pagination'];
		if ( ! is_array( $pagination ) ) {
			throw new \InvalidArgumentException( esc_html( $slug . ': pagination must be an array' ) );
		}
		self::assert_pagination( $slug, $pagination );
		foreach ( array( 'lists', 'fields', 'features', 'vocabulary', 'auth_fields', 'consent' ) as $key ) {
			if ( ! is_array( $data[ $key ] ) ) {
				throw new \InvalidArgumentException( esc_html( $slug . ': ' . $key . ' must be an array' ) );
			}
		}
		foreach ( self::VOCABULARY as $key ) {
			$word = $data['vocabulary'][ $key ] ?? null;
			if ( ! is_string( $word ) || '' === $word ) {
				throw new \InvalidArgumentException( esc_html( $slug . ': vocabulary is missing ' . $key ) );
			}
			$this->vocabulary[ $key ] = $word;
		}
		$operations = $data['operations'];
		if ( ! is_string( $operations ) || ! class_exists( $operations ) || ! in_array( OperationsInterface::class, class_implements( $operations ), true ) ) {
			throw new \InvalidArgumentException( esc_html( $slug . ': operations must name a class implementing OperationsInterface' ) );
		}
		$entitlements = $data['entitlements'] ?? array();
		if ( ! is_array( $entitlements ) ) {
			throw new \InvalidArgumentException( esc_html( $slug . ': entitlements must be a map' ) );
		}
		foreach ( $entitlements as $feature => $filter_name ) {
			if ( ! is_string( $feature ) || ! in_array( $feature, self::ENTITLEMENT_FEATURES, true ) || ! is_string( $filter_name ) || 1 !== preg_match( '/^[a-z_]+$/', $filter_name ) ) {
				throw new \InvalidArgumentException( esc_html( $slug . ': entitlements map a declared feature to a filter name of lowercase letters and underscores' ) );
			}
			$this->entitlements[ $feature ] = $filter_name;
		}
		$submission = $data['submission'] ?? array();
		if ( ! is_array( $submission ) ) {
			throw new \InvalidArgumentException( esc_html( $slug . ': submission must be a map' ) );
		}
		foreach ( $submission as $flag => $value ) {
			if ( ! is_string( $flag ) || ! in_array( $flag, self::SUBMISSION_FLAGS, true ) || ! is_bool( $value ) ) {
				throw new \InvalidArgumentException( esc_html( $slug . ': submission flags must be declared booleans' ) );
			}
			$this->submission[ $flag ] = $value;
		}
		$this->slug            = $slug;
		$this->label           = $label;
		$this->base_url        = $base_url;
		$this->validation_path = $paths['validation_path'];
		$this->lists_path      = $paths['lists_path'];
		$this->fields_path     = $paths['fields_path'];
		$this->pagination      = $pagination;
		$this->lists           = is_array( $data['lists'] ) ? $data['lists'] : array();
		$this->fields          = is_array( $data['fields'] ) ? $data['fields'] : array();
		$this->features        = is_array( $data['features'] ) ? $data['features'] : array();
		$this->auth_fields     = is_array( $data['auth_fields'] ) ? array_values( $data['auth_fields'] ) : array();
		$this->consent         = is_array( $data['consent'] ) ? $data['consent'] : array();
		$this->operations      = $operations;
	}

	public function slug(): string {
		return $this->slug;
	}

	/** @return array<mixed> */
	public function protocol(): array {
		return $this->protocol;
	}

	public function label(): string {
		return $this->label;
	}

	public function description(): string {
		return $this->description;
	}

	public function probe_url(): string {
		return $this->probe_url;
	}

	public function base_url(): string {
		return $this->base_url;
	}

	/** @return array<string,string> */
	public function response_format(): array {
		return $this->response_format;
	}

	/**
	 * @return array<string,string>
	 */
	public function headers( string $api_key ): array {
		$key_part = explode( '-', $api_key )[0];
		$replace  = array(
			'{key}'       => $api_key,
			'{key_part}'  => $key_part,
			'{basic_key}' => base64_encode( $api_key . ':x' ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HTTP Basic authentication requires this encoding.
			'{ua}'        => 'ChimpMaticLite/' . SPARTAN_MCE_VERSION,
			'{wp}'        => get_bloginfo( 'version' ),
		);
		$headers  = array();
		foreach ( $this->headers as $name => $value ) {
			$headers[ $name ] = strtr( $value, $replace );
		}
		return $headers;
	}

	public function validation_path(): string {
		return $this->validation_path;
	}

	public function lists_path(): string {
		return $this->lists_path;
	}

	public function fields_path( string $list_id ): string {
		return str_replace( '{list_id}', rawurlencode( $list_id ), $this->fields_path );
	}

	/**
	 * @param array<mixed> $body
	 */
	public function next_lists_path( array $body, string $current_path ): string {
		$type = self::str( $this->pagination['type'] ?? 'none' );
		if ( 'offset' === $type ) {
			$query = wp_parse_url( $current_path, PHP_URL_QUERY );
			parse_str( is_string( $query ) ? $query : '', $parameters );
			$offset    = self::int( $parameters['offset'] ?? 0 );
			$items_key = self::str( $this->pagination['items_key'] ?? 'items' );
			$count_key = self::str( $this->pagination['count_key'] ?? 'count' );
			$received  = isset( $body[ $items_key ] ) && is_array( $body[ $items_key ] ) ? count( $body[ $items_key ] ) : 0;
			$total     = self::int( $body[ $count_key ] ?? 0 );
			$next      = $offset + $received;
			if ( 0 === $received || $next >= $total ) {
				return '';
			}
			return str_replace( '{offset}', (string) $next, self::str( $this->pagination['path'] ?? '' ) );
		}
		if ( 'next_link' === $type ) {
			$links = isset( $body['links'] ) && is_array( $body['links'] ) ? $body['links'] : array();
			$next  = $links['next'] ?? '';
			if ( ! is_string( $next ) || '' === $next ) {
				return '';
			}
			$parts = wp_parse_url( $next );
			if ( ! is_array( $parts ) ) {
				return '';
			}
			$host  = self::str( $parts['host'] ?? '' );
			$path  = self::str( $parts['path'] ?? '' );
			$query = self::str( $parts['query'] ?? '' );
			if ( $host !== self::str( $this->pagination['host'] ?? '' ) || $path !== self::str( $this->pagination['path'] ?? '' ) || '' === $query ) {
				return '';
			}
			return str_replace( '{query}', $query, self::str( $this->pagination['rebase'] ?? '' ) );
		}
		return '';
	}

	/**
	 * @return array<mixed>
	 */
	public function lists_spec(): array {
		return $this->lists;
	}

	/**
	 * @return array<mixed>
	 */
	public function fields_spec(): array {
		return $this->fields;
	}

	/**
	 * @return array<mixed>
	 */
	public function features(): array {
		return array_merge(
			array(
				'multi_group_routing' => false,
				'status_modes'        => array(),
				'consent_metadata'    => false,
				'create_field_types'  => array(),
				'lookup'              => false,
			),
			$this->features
		);
	}

	/**
	 * @return array<mixed>
	 */
	public function manifest(): array {
		return array_merge(
			array(
				'slug'  => $this->slug,
				'label' => $this->label,
			),
			$this->vocabulary,
			array(
				'features'    => $this->features(),
				'auth_fields' => $this->auth_fields,
				'consent'     => $this->consent,
			)
		);
	}

	public function operations_class(): string {
		return $this->operations;
	}

	/**
	 * @return array<string,string>
	 */
	public function entitlements(): array {
		return $this->entitlements;
	}

	public function submission_flag( string $flag ): bool {
		return ! empty( $this->submission[ $flag ] );
	}

	/**
	 * @param array<int,string> $allowed
	 */
	private static function assert_placeholders( string $slug, string $where, string $value, array $allowed ): void {
		preg_match_all( '/\{[^{}]*\}/', $value, $found );
		foreach ( $found[0] as $placeholder ) {
			if ( ! in_array( $placeholder, $allowed, true ) ) {
				throw new \InvalidArgumentException( esc_html( $slug . ': ' . $where . ' carries an unknown placeholder ' . $placeholder ) );
			}
		}
		$remainder = str_replace( $found[0], '', $value );
		if ( false !== strpos( $remainder, '{' ) || false !== strpos( $remainder, '}' ) ) {
			throw new \InvalidArgumentException( esc_html( $slug . ': ' . $where . ' has an unbalanced placeholder' ) );
		}
	}

	/**
	 * @param array<int,string> $allowed
	 */
	private static function assert_path( string $slug, string $where, string $path, array $allowed ): void {
		if ( ( '' !== $path && '/' !== $path[0] ) || false !== strpos( $path, '://' ) || false !== strpos( $path, '//' ) ) {
			throw new \InvalidArgumentException( esc_html( $slug . ': ' . $where . ' must be an absolute path or empty' ) );
		}
		self::assert_placeholders( $slug, $where, $path, $allowed );
	}

	/**
	 * @param array<mixed> $pagination
	 */
	private static function assert_pagination( string $slug, array $pagination ): void {
		$type = $pagination['type'] ?? null;
		if ( 'none' === $type ) {
			$expected = array( 'type' );
		} elseif ( 'offset' === $type ) {
			$expected = array( 'type', 'items_key', 'count_key', 'path' );
		} elseif ( 'next_link' === $type ) {
			$expected = array( 'type', 'host', 'path', 'rebase' );
		} else {
			throw new \InvalidArgumentException( esc_html( $slug . ': pagination type must be none, offset or next_link' ) );
		}
		$keys = array_keys( $pagination );
		sort( $keys );
		sort( $expected );
		if ( $keys !== $expected ) {
			throw new \InvalidArgumentException( esc_html( $slug . ': pagination ' . $type . ' needs exactly the keys ' . implode( ', ', $expected ) ) );
		}
		foreach ( $pagination as $key => $value ) {
			if ( ! is_string( $value ) || '' === $value ) {
				throw new \InvalidArgumentException( esc_html( $slug . ': pagination ' . $key . ' must be a non-empty string' ) );
			}
		}
		if ( 'offset' === $type ) {
			$path = self::str( $pagination['path'] );
			if ( 1 !== substr_count( $path, '{offset}' ) ) {
				throw new \InvalidArgumentException( esc_html( $slug . ': pagination path must carry {offset} once' ) );
			}
			self::assert_path( $slug, 'pagination path', $path, array( '{offset}' ) );
		}
		if ( 'next_link' === $type ) {
			$host   = self::str( $pagination['host'] );
			$path   = self::str( $pagination['path'] );
			$rebase = self::str( $pagination['rebase'] );
			if ( ! in_array( $host, Catalog::hosts(), true ) || false !== strpos( $host, '{' ) ) {
				throw new \InvalidArgumentException( esc_html( $slug . ': pagination host must be a declared host' ) );
			}
			self::assert_path( $slug, 'pagination path', $path, array() );
			if ( 1 !== substr_count( $rebase, '{query}' ) ) {
				throw new \InvalidArgumentException( esc_html( $slug . ': pagination rebase must carry {query} once' ) );
			}
			self::assert_path( $slug, 'pagination rebase', $rebase, array( '{query}' ) );
		}
	}

	/**
	 * @param mixed $value
	 */
	private static function str( $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * @param mixed $value
	 */
	private static function int( $value ): int {
		if ( is_array( $value ) ) {
			return array() === $value ? 0 : 1;
		}
		return is_scalar( $value ) ? (int) $value : 0;
	}
}
