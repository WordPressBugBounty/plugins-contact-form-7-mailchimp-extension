<?php
/**
 * Install data collector.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

namespace Cmatic\Metrics\Core\Collectors;

use Cmatic\Metrics\Core\Storage;
use Cmatic_Options_Repository;

defined( 'ABSPATH' ) || exit;

class Install_Collector {
	public static function collect(): array {
		return array(
			'plugin_slug' => Cmatic_Options_Repository::get_option( 'install.plugin_slug', 'contact-form-7-mailchimp-extension' ),
			'quest'       => Storage::get_quest(),
			'pro'         => array(
				'installed'       => (bool) Cmatic_Options_Repository::get_option( 'install.pro.installed', false ),
				'activated'       => (bool) Cmatic_Options_Repository::get_option( 'install.pro.activated', false ),
				'version'         => Cmatic_Options_Repository::get_option( 'install.pro.version', null ),
				'licensed'        => (bool) Cmatic_Options_Repository::get_option( 'install.pro.licensed', false ),
				'license_expires' => Cmatic_Options_Repository::get_option( 'install.pro.license_expires', null ),
			),
		);
	}
}
