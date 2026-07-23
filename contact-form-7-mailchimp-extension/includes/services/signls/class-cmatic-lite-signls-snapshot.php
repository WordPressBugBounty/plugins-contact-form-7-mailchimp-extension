<?php
/**
 * ChimpMatic Lite rich Signls snapshot composer.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

final class Cmatic_Lite_Signls_Snapshot {

	private const SECTIONS = array(
		'install',
		'metadata',
		'lifecycle',
		'environment',
		'api',
		'submissions',
		'features',
		'forms',
		'performance',
		'plugins',
		'competitors',
		'server',
		'wordpress',
		'legacy_lifecycle',
	);

	private $site_collector;
	private $inventory_collector;
	private $performance_collector;
	private $product_collector;
	private $forms_collector;

	public function __construct( $site_collector, $inventory_collector, $performance_collector, $product_collector, $forms_collector ) {
		$this->site_collector        = $site_collector;
		$this->inventory_collector   = $inventory_collector;
		$this->performance_collector = $performance_collector;
		$this->product_collector     = $product_collector;
		$this->forms_collector       = $forms_collector;
	}

	public function collect(): array {
		$observations                  = array_fill_keys( self::SECTIONS, array() );
		$observations['opportunities'] = array();
		$missing                       = array();
		$successes                     = 0;
		$narrow                        = $this->empty_narrow();

		$this->collect_domain(
			$this->product_collector,
			array( 'install', 'metadata', 'lifecycle', 'api', 'submissions', 'features', 'legacy_lifecycle' ),
			$observations,
			$missing,
			$successes,
			$narrow
		);
		$this->collect_domain(
			$this->site_collector,
			array( 'environment', 'server', 'wordpress' ),
			$observations,
			$missing,
			$successes
		);
		$this->collect_domain(
			$this->inventory_collector,
			array( 'plugins', 'competitors' ),
			$observations,
			$missing,
			$successes
		);
		$this->collect_domain(
			$this->performance_collector,
			array( 'performance' ),
			$observations,
			$missing,
			$successes
		);
		$this->collect_domain(
			$this->forms_collector,
			array( 'forms' ),
			$observations,
			$missing,
			$successes
		);

		$missing                           = array_values( array_unique( $missing ) );
		$observations['collection_status'] = empty( $missing ) ? 'complete' : ( 0 === $successes ? 'failed' : 'partial' );
		$observations['missing_sections']  = $missing;
		$narrow['observations']            = $observations;
		return $narrow;
	}

	private function collect_domain( $collector, array $sections, array &$observations, array &$missing, int &$successes, ?array &$narrow = null ): void {
		try {
			$result = is_object( $collector ) && is_callable( array( $collector, 'collect' ) ) ? $collector->collect() : null;
			if ( ! is_array( $result ) ) {
				throw new RuntimeException( 'Invalid collector result.' );
			}
			++$successes;
			if ( null !== $narrow && isset( $result['narrow'] ) && is_array( $result['narrow'] ) ) {
				$narrow = array_replace( $narrow, array_intersect_key( $result['narrow'], $narrow ) );
			}
			foreach ( $sections as $section ) {
				if ( ! isset( $result[ $section ] ) || ! is_array( $result[ $section ] ) ) {
					$missing[] = $section;
					continue;
				}
				$observations[ $section ] = $result[ $section ];
			}
			if ( isset( $result['opportunities'] ) && is_array( $result['opportunities'] ) ) {
				$observations['opportunities'] = array_replace( $observations['opportunities'], $result['opportunities'] );
			}
		} catch ( Throwable $exception ) {
			unset( $exception );
			foreach ( $sections as $section ) {
				$missing[] = $section;
			}
		}
	}

	private function empty_narrow(): array {
		return array(
			'versions'         => array(),
			'is_multisite'     => false,
			'configured_units' => 0,
			'active_units'     => 0,
			'integrations'     => array(),
			'features'         => array(),
			'operation_health' => array(),
			'companions'       => array(),
		);
	}
}
