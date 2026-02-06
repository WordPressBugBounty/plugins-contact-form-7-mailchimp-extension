<?php
/**
 * Metrics data collector orchestrator.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

namespace Cmatic\Metrics\Core;

use Cmatic\Metrics\Core\Collectors\Install_Collector;
use Cmatic\Metrics\Core\Collectors\Metadata_Collector;
use Cmatic\Metrics\Core\Collectors\Lifecycle_Collector;
use Cmatic\Metrics\Core\Collectors\Environment_Collector;
use Cmatic\Metrics\Core\Collectors\Api_Collector;
use Cmatic\Metrics\Core\Collectors\Submissions_Collector;
use Cmatic\Metrics\Core\Collectors\Features_Collector;
use Cmatic\Metrics\Core\Collectors\Forms_Collector;
use Cmatic\Metrics\Core\Collectors\Performance_Collector;
use Cmatic\Metrics\Core\Collectors\Plugins_Collector;
use Cmatic\Metrics\Core\Collectors\Competitors_Collector;
use Cmatic\Metrics\Core\Collectors\Server_Collector;
use Cmatic\Metrics\Core\Collectors\WordPress_Collector;

defined( 'ABSPATH' ) || exit;

class Collector {

	public static function collect( $event = 'heartbeat' ): array {
		return array(
			'install_id'  => Storage::get_install_id(),
			'timestamp'   => time(),
			'event'       => $event,
			'install'     => Install_Collector::collect(),
			'metadata'    => Metadata_Collector::collect(),
			'lifecycle'   => Lifecycle_Collector::collect(),
			'environment' => Environment_Collector::collect(),
			'api'         => Api_Collector::collect(),
			'submissions' => Submissions_Collector::collect(),
			'features'    => Features_Collector::collect(),
			'forms'       => Forms_Collector::collect(),
			'performance' => Performance_Collector::collect(),
			'plugins'     => Plugins_Collector::collect(),
			'competitors' => Competitors_Collector::collect(),
			'server'      => Server_Collector::collect(),
			'wordpress'   => WordPress_Collector::collect(),
		);
	}
}
