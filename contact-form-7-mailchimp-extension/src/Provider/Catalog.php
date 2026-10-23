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
final class Catalog {
	const SLUGS = array( 'mailchimp', 'brevo', 'mailerlite', 'klaviyo', 'campaignmonitor', 'getresponse', 'emailoctopus', 'moosend' );

	/** @var HttpTransport */
	private $transport;

	/** @var array<string,Descriptor> */
	private $descriptors = array();

	/** @var array<string,Provider> */
	private $providers = array();

	public function __construct( HttpTransport $transport ) {
		$this->transport = $transport;
	}

	/**
	 * @return array<int,string>
	 */
	public function slugs(): array {
		return self::SLUGS;
	}

	/** @return array<int,string> */
	public function api_key_slugs(): array {
		return array_values( array_diff( self::SLUGS, array( 'mailchimp' ) ) );
	}

	/** @return array<int,string> */
	public static function hosts(): array {
		$hosts = array();
		foreach ( self::SLUGS as $slug ) {
			$data = self::definition( $slug );
			$host = wp_parse_url( is_string( $data['base_url'] ?? null ) ? $data['base_url'] : '', PHP_URL_HOST );
			if ( is_string( $host ) && '' !== $host ) {
				$hosts[] = $host;
			}
		}
		return array_values( array_unique( $hosts ) );
	}

	/** @return array<string,string> */
	public function probe_urls(): array {
		$urls = array();
		foreach ( self::SLUGS as $slug ) {
			$descriptor                   = $this->descriptor( $slug );
			$urls[ $descriptor->label() ] = $descriptor->probe_url();
		}
		return $urls;
	}

	public function has( string $slug ): bool {
		return in_array( $slug, self::SLUGS, true );
	}

	/**
	 * @param array<string,mixed> $settings
	 */
	public function selected( array $settings ): string {
		$slug = isset( $settings['provider'] ) && is_scalar( $settings['provider'] ) ? sanitize_key( (string) $settings['provider'] ) : 'mailchimp';
		return $this->has( $slug ) ? $slug : 'mailchimp';
	}

	public function descriptor( string $slug ): Descriptor {
		$slug = $this->has( $slug ) ? $slug : 'mailchimp';
		if ( ! isset( $this->descriptors[ $slug ] ) ) {
			$this->descriptors[ $slug ] = new Descriptor( self::definition( $slug ) );
		}
		return $this->descriptors[ $slug ];
	}

	public function get( string $slug ): Provider {
		$slug = $this->has( $slug ) ? $slug : 'mailchimp';
		if ( ! isset( $this->providers[ $slug ] ) ) {
			$descriptor = $this->descriptor( $slug );
			$class      = $descriptor->operations_class();
			$operations = new $class( $descriptor, $this->transport );
			if ( ! $operations instanceof OperationsInterface ) {
				throw new \RuntimeException( esc_html( $slug . ': operations class does not implement OperationsInterface' ) );
			}
			$this->providers[ $slug ] = new Provider( $descriptor, $operations );
		}
		return $this->providers[ $slug ];
	}

	/**
	 * @return array<string,Provider>
	 */
	public function all(): array {
		$result = array();
		foreach ( self::SLUGS as $slug ) {
			$result[ $slug ] = $this->get( $slug );
		}
		return $result;
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	public function manifest(): array {
		$result = array();
		foreach ( self::SLUGS as $slug ) {
			$result[ $slug ] = $this->descriptor( $slug )->manifest();
		}
		return $result;
	}

	/**
	 * @return array<string,string>
	 */
	public function selector_options(): array {
		$options = array();
		foreach ( self::SLUGS as $slug ) {
			$options[ $slug ] = $this->descriptor( $slug )->label();
		}
		return $options;
	}

	/** @return array<mixed> */
	private static function definition( string $slug ): array {
		$data = require __DIR__ . '/Descriptors/' . $slug . '.php';
		return is_array( $data ) ? $data : array();
	}
}
