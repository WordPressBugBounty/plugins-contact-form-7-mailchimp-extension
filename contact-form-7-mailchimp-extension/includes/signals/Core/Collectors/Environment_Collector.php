<?php
/**
 * Environment data collector.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

namespace Cmatic\Metrics\Core\Collectors;

defined( 'ABSPATH' ) || exit;

class Environment_Collector {
	public static function collect(): array {
		global $wp_version, $wpdb;

		$php_extensions      = get_loaded_extensions();
		$critical_extensions = array( 'curl', 'json', 'mbstring', 'openssl', 'zip', 'gd', 'xml', 'dom', 'SimpleXML' );
		$loaded_critical     = array_intersect( $critical_extensions, $php_extensions );

		$theme        = wp_get_theme();
		$parent_theme = $theme->parent() ? $theme->parent()->get( 'Name' ) : '';

		$data = array(
			'php_version'                    => phpversion(),
			'php_sapi'                       => php_sapi_name(),
			'php_os'                         => PHP_OS,
			'php_architecture'               => PHP_INT_SIZE === 8 ? '64-bit' : '32-bit',
			'php_memory_limit'               => ini_get( 'memory_limit' ),
			'php_max_execution_time'         => (int) ini_get( 'max_execution_time' ),
			'php_max_input_time'             => (int) ini_get( 'max_input_time' ),
			'php_max_input_vars'             => (int) ini_get( 'max_input_vars' ),
			'php_post_max_size'              => ini_get( 'post_max_size' ),
			'php_upload_max_filesize'        => ini_get( 'upload_max_filesize' ),
			'php_default_timezone'           => ini_get( 'date.timezone' ),
			'php_log_errors'                 => ini_get( 'log_errors' ),
			'php_extensions_count'           => count( $php_extensions ),
			'php_critical_extensions'        => implode( ',', $loaded_critical ),
			'php_curl_version'               => function_exists( 'curl_version' ) ? curl_version()['version'] : '',
			'php_openssl_version'            => OPENSSL_VERSION_TEXT,
			'wp_version'                     => $wp_version,
			'wp_db_version'                  => get_option( 'db_version' ),
			'wp_memory_limit'                => WP_MEMORY_LIMIT,
			'wp_max_memory_limit'            => WP_MAX_MEMORY_LIMIT,
			'wp_debug'                       => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'wp_debug_log'                   => defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG,
			'wp_debug_display'               => defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY,
			'script_debug'                   => defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG,
			'wp_cache'                       => defined( 'WP_CACHE' ) && WP_CACHE,
			'wp_cron_disabled'               => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
			'wp_auto_update_core'            => get_option( 'auto_update_core', 'enabled' ),
			'mysql_version'                  => $wpdb->db_version(),
			'mysql_client_version'           => $wpdb->get_var( 'SELECT VERSION()' ),
			'db_charset'                     => $wpdb->charset,
			'db_collate'                     => $wpdb->collate,
			'db_prefix'                      => strlen( $wpdb->prefix ),
			'server_software'                => isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '',
			'server_protocol'                => isset( $_SERVER['SERVER_PROTOCOL'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_PROTOCOL'] ) ) : '',
			'server_port'                    => isset( $_SERVER['SERVER_PORT'] ) ? (int) $_SERVER['SERVER_PORT'] : 0,
			'https'                          => is_ssl(),
			'http_host'                      => hash( 'sha256', isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '' ),
			'locale'                         => get_locale(),
			'timezone'                       => wp_timezone_string(),
			'site_language'                  => get_bloginfo( 'language' ),
			'site_charset'                   => get_bloginfo( 'charset' ),
			'permalink_structure'            => get_option( 'permalink_structure' ),
			'home_url'                       => hash( 'sha256', home_url() ),
			'site_url'                       => hash( 'sha256', site_url() ),
			'admin_email'                    => hash( 'sha256', get_option( 'admin_email' ) ),
			'theme'                          => $theme->get( 'Name' ),
			'theme_version'                  => $theme->get( 'Version' ),
			'theme_author'                   => $theme->get( 'Author' ),
			'parent_theme'                   => $parent_theme,
			'is_child_theme'                 => ! empty( $parent_theme ),
			'theme_supports_html5'           => current_theme_supports( 'html5' ),
			'theme_supports_post_thumbnails' => current_theme_supports( 'post-thumbnails' ),
			'active_plugins_count'           => count( get_option( 'active_plugins', array() ) ),
			'total_plugins_count'            => count( get_plugins() ),
			'must_use_plugins_count'         => count( wp_get_mu_plugins() ),
			'is_multisite'                   => is_multisite(),
			'is_subdomain_install'           => is_multisite() ? ( defined( 'SUBDOMAIN_INSTALL' ) && SUBDOMAIN_INSTALL ) : false,
			'network_count'                  => is_multisite() ? get_blog_count() : 1,
			'is_main_site'                   => is_multisite() ? is_main_site() : true,
			'cf7_version'                    => defined( 'WPCF7_VERSION' ) ? WPCF7_VERSION : '',
			'cf7_installed'                  => class_exists( 'WPCF7_ContactForm' ),
			'plugin_version'                 => defined( 'SPARTAN_MCE_VERSION' ) ? SPARTAN_MCE_VERSION : '',
			'user_agent'                     => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
		);

		return array_filter( $data, fn( $v ) => $v !== 0 && $v !== false && $v !== '' && $v !== 'none' );
	}
}
