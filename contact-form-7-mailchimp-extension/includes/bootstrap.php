<?php
/**
 * Plugin bootstrap file.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

// Load and initialize the plugin via OOP bootstrap class.
require_once SPARTAN_MCE_PLUGIN_DIR . 'includes/core/class-cmatic-plugin.php';

$cmatic_plugin = new Cmatic_Plugin( SPARTAN_MCE_PLUGIN_FILE, SPARTAN_MCE_VERSION );
$cmatic_plugin->init();
