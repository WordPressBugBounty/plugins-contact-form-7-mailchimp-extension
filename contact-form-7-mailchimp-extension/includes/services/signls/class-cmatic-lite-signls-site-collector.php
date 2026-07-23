<?php
/**
 * ChimpMatic Lite site Signls collector.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

final class Cmatic_Lite_Signls_Site_Collector {

	private $inventory;

	public function __construct( $inventory = null ) {
		$this->inventory = $inventory;
	}

	public function collect(): array {
		return array(
			'environment'   => $this->environment(),
			'server'        => $this->server(),
			'wordpress'     => $this->wordpress(),
			'opportunities' => array(
				'environment_type' => $this->environment_type(),
				'rest_api_ok'      => $this->rest_api_ok(),
				'host_class'       => $this->host_class(),
				'cache_dropins'    => $this->cache_dropins(),
			),
		);
	}

	private function environment(): array {
		global $wpdb, $wp_version;

		$this->load_plugin_api();
		$extensions = get_loaded_extensions();
		$critical   = array( 'curl', 'json', 'mbstring', 'openssl', 'zip', 'gd', 'xml', 'dom', 'SimpleXML' );
		$theme      = is_object( $this->inventory ) && is_callable( array( $this->inventory, 'theme_facts' ) ) ? $this->inventory->theme_facts() : $this->theme_facts();
		$db_version = get_option( 'db_version', 0 );
		$db_version = is_scalar( $db_version ) ? (int) $db_version : 0;

		return array_merge(
			array(
				'php_version'                    => PHP_VERSION,
				'php_sapi'                       => php_sapi_name(),
				'php_os'                         => PHP_OS,
				'php_architecture'               => 8 === PHP_INT_SIZE ? '64-bit' : '32-bit',
				'php_memory_limit'               => (string) ini_get( 'memory_limit' ),
				'php_max_execution_time'         => (int) ini_get( 'max_execution_time' ),
				'php_max_input_time'             => (int) ini_get( 'max_input_time' ),
				'php_max_input_vars'             => (int) ini_get( 'max_input_vars' ),
				'php_post_max_size'              => (string) ini_get( 'post_max_size' ),
				'php_upload_max_filesize'        => (string) ini_get( 'upload_max_filesize' ),
				'php_default_timezone'           => (string) ini_get( 'date.timezone' ),
				'php_log_errors'                 => (string) ini_get( 'log_errors' ),
				'php_extensions_count'           => count( $extensions ),
				'php_critical_extensions'        => implode( ',', array_values( array_intersect( $critical, $extensions ) ) ),
				'php_curl_version'               => $this->curl_version(),
				'php_openssl_version'            => defined( 'OPENSSL_VERSION_TEXT' ) ? (string) OPENSSL_VERSION_TEXT : '',
				'wp_version'                     => (string) $wp_version,
				'wp_db_version'                  => $db_version,
				'wp_memory_limit'                => defined( 'WP_MEMORY_LIMIT' ) ? (string) WP_MEMORY_LIMIT : '',
				'wp_max_memory_limit'            => defined( 'WP_MAX_MEMORY_LIMIT' ) ? (string) WP_MAX_MEMORY_LIMIT : '',
				'wp_debug'                       => defined( 'WP_DEBUG' ) && WP_DEBUG,
				'wp_debug_log'                   => defined( 'WP_DEBUG_LOG' ) && (bool) WP_DEBUG_LOG,
				'wp_debug_display'               => defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY,
				'script_debug'                   => defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG,
				'wp_cache'                       => defined( 'WP_CACHE' ) && WP_CACHE,
				'wp_cron_disabled'               => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
				'wp_auto_update_core'            => $this->scalar_text( get_option( 'auto_update_core', 'enabled' ) ),
				'mysql_version'                  => (string) $wpdb->db_version(),
				'mysql_client_version'           => (string) $wpdb->get_var( 'SELECT VERSION()' ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One scalar compatibility fact is read from the active server.
				'db_charset'                     => (string) $wpdb->charset,
				'db_collate'                     => (string) $wpdb->collate,
				'db_prefix_length'               => strlen( (string) $wpdb->prefix ),
				'server_software'                => $this->server_value( 'SERVER_SOFTWARE' ),
				'server_protocol'                => $this->server_value( 'SERVER_PROTOCOL' ),
				'server_port'                    => (int) $this->server_value( 'SERVER_PORT' ),
				'https'                          => is_ssl(),
				'http_host_sha256'               => $this->hash_value( $this->server_value( 'HTTP_HOST' ) ),
				'locale'                         => get_locale(),
				'timezone'                       => wp_timezone_string(),
				'site_language'                  => get_bloginfo( 'language' ),
				'site_charset'                   => get_bloginfo( 'charset' ),
				'permalink_structure'            => $this->scalar_text( get_option( 'permalink_structure', '' ) ),
				'home_url_sha256'                => $this->hash_value( home_url() ),
				'site_url_sha256'                => $this->hash_value( site_url() ),
				'admin_email_sha256'             => $this->hash_value( $this->scalar_text( get_option( 'admin_email', '' ) ) ),
				'theme_supports_html5'           => current_theme_supports( 'html5' ),
				'theme_supports_post_thumbnails' => current_theme_supports( 'post-thumbnails' ),
				'active_plugins_count'           => count( (array) get_option( 'active_plugins', array() ) ),
				'total_plugins_count'            => count( get_plugins() ),
				'must_use_plugins_count'         => count( get_mu_plugins() ),
				'is_multisite'                   => is_multisite(),
				'is_subdomain_install'           => is_multisite() && is_subdomain_install(),
				'network_count'                  => is_multisite() && function_exists( 'get_blog_count' ) ? (int) get_blog_count() : 1,
				'is_main_site'                   => ! is_multisite() || is_main_site(),
				'cf7_version'                    => defined( 'WPCF7_VERSION' ) ? (string) WPCF7_VERSION : '',
				'cf7_installed'                  => class_exists( 'WPCF7_ContactForm' ),
				'plugin_version'                 => defined( 'SPARTAN_MCE_VERSION' ) ? (string) SPARTAN_MCE_VERSION : '',
				'user_agent'                     => $this->server_value( 'HTTP_USER_AGENT' ),
			),
			$theme
		);
	}

	private function server(): array {
		$load      = function_exists( 'sys_getloadavg' ) ? sys_getloadavg() : false;
		$disk_path = realpath( ABSPATH );
		$free      = function_exists( 'disk_free_space' ) && is_string( $disk_path ) && is_readable( $disk_path ) ? disk_free_space( $disk_path ) : false;
		$total     = function_exists( 'disk_total_space' ) && is_string( $disk_path ) && is_readable( $disk_path ) ? disk_total_space( $disk_path ) : false;
		$used      = false !== $free && false !== $total ? max( 0, $total - $free ) : null;
		$host      = function_exists( 'gethostname' ) ? gethostname() : false;
		$arch      = function_exists( 'php_uname' ) ? php_uname( 'm' ) : false;

		return array(
			'load_average_1min'      => is_array( $load ) ? round( (float) $load[0], 2 ) : null,
			'load_average_5min'      => is_array( $load ) ? round( (float) $load[1], 2 ) : null,
			'load_average_15min'     => is_array( $load ) ? round( (float) $load[2], 2 ) : null,
			'disk_usage_percent'     => null !== $used && $total > 0 ? round( ( $used / $total ) * 100, 2 ) : null,
			'disk_total_gb'          => false !== $total ? round( $total / 1073741824, 2 ) : null,
			'server_ip_sha256'       => $this->hash_value( $this->server_value( 'SERVER_ADDR' ) ),
			'server_hostname_sha256' => $this->hash_value( false !== $host ? (string) $host : '' ),
			'server_os'              => PHP_OS,
			'server_architecture'    => false !== $arch ? (string) $arch : '',
		);
	}

	private function wordpress(): array {
		global $wpdb;

		$post_counts    = wp_count_posts( 'post' );
		$page_counts    = wp_count_posts( 'page' );
		$media_counts   = wp_count_posts( 'attachment' );
		$comment_counts = wp_count_comments();
		$user_counts    = count_users();
		$categories     = wp_count_terms( array( 'taxonomy' => 'category' ) );
		$tags           = wp_count_terms( array( 'taxonomy' => 'post_tag' ) );
		$revisions      = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Aggregate count over the current site's posts table.

		return array(
			'posts_published'      => isset( $post_counts->publish ) ? (int) $post_counts->publish : 0,
			'posts_draft'          => isset( $post_counts->draft ) ? (int) $post_counts->draft : 0,
			'pages_published'      => isset( $page_counts->publish ) ? (int) $page_counts->publish : 0,
			'media_items'          => isset( $media_counts->inherit ) ? (int) $media_counts->inherit : 0,
			'comments_pending'     => isset( $comment_counts->moderated ) ? (int) $comment_counts->moderated : 0,
			'comments_spam'        => isset( $comment_counts->spam ) ? (int) $comment_counts->spam : 0,
			'users_total'          => isset( $user_counts['total_users'] ) ? (int) $user_counts['total_users'] : 0,
			'users_administrators' => isset( $user_counts['avail_roles']['administrator'] ) ? (int) $user_counts['avail_roles']['administrator'] : 0,
			'users_editors'        => isset( $user_counts['avail_roles']['editor'] ) ? (int) $user_counts['avail_roles']['editor'] : 0,
			'users_authors'        => isset( $user_counts['avail_roles']['author'] ) ? (int) $user_counts['avail_roles']['author'] : 0,
			'users_subscribers'    => isset( $user_counts['avail_roles']['subscriber'] ) ? (int) $user_counts['avail_roles']['subscriber'] : 0,
			'categories_count'     => is_wp_error( $categories ) ? 0 : (int) $categories,
			'tags_count'           => is_wp_error( $tags ) ? 0 : (int) $tags,
			'revisions_count'      => (int) $revisions,
			'auto_updates_enabled' => ! empty( get_option( 'auto_update_plugins', array() ) ),
		);
	}

	private function cache_dropins(): array {
		$this->load_plugin_api();
		$slugs = array();
		foreach ( array_keys( get_dropins() ) as $file ) {
			$slug = sanitize_key( basename( (string) $file, '.php' ) );
			if ( '' !== $slug ) {
				$slugs[] = $slug;
			}
		}
		sort( $slugs, SORT_STRING );
		$total = count( $slugs );
		return array(
			'items'          => array_slice( $slugs, 0, 8 ),
			'reported_total' => $total,
			'truncated'      => $total > 8,
		);
	}

	private function environment_type(): string {
		$type = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'unknown';
		return in_array( $type, array( 'production', 'staging', 'development', 'local' ), true ) ? $type : 'unknown';
	}

	private function host_class(): string {
		if ( defined( 'WPCOM_IS_VIP_ENV' ) || defined( 'VIP_GO_APP_ID' ) ) {
			return 'wpcom_vip';
		}
		if ( defined( 'IS_WPCOM' ) && IS_WPCOM ) {
			return 'wpcom';
		}
		if ( defined( 'PRESSABLE_HOSTING' ) || defined( 'PRESSABLE_SITE_ID' ) ) {
			return 'pressable';
		}
		if ( defined( 'WPE_APIKEY' ) || defined( 'WPE_CLUSTER_ID' ) || defined( 'PWP_NAME' ) ) {
			return 'wp_engine';
		}
		return '' !== $this->server_value( 'SERVER_SOFTWARE' ) ? 'other' : 'unknown';
	}

	private function rest_api_ok(): ?bool {
		if ( ! function_exists( 'rest_url' ) || ! class_exists( 'WP_REST_Server' ) ) {
			return null;
		}
		return '' !== (string) rest_url();
	}

	private function theme_facts(): array {
		$theme  = wp_get_theme();
		$parent = $theme->parent();
		return array(
			'theme'          => (string) $theme->get( 'Name' ),
			'theme_version'  => (string) $theme->get( 'Version' ),
			'theme_author'   => wp_strip_all_tags( (string) $theme->get( 'Author' ) ),
			'parent_theme'   => $parent ? (string) $parent->get( 'Name' ) : '',
			'is_child_theme' => (bool) $parent,
		);
	}

	private function load_plugin_api(): void {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
	}

	private function curl_version(): string {
		if ( ! function_exists( 'curl_version' ) ) {
			return '';
		}
		$data = curl_version();
		return is_array( $data ) && isset( $data['version'] ) ? (string) $data['version'] : '';
	}

	private function server_value( string $key ): string {
		return isset( $_SERVER[ $key ] ) && is_scalar( $_SERVER[ $key ] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER[ $key ] ) ) : '';
	}

	private function hash_value( string $value ): string {
		return '' === $value ? '' : hash( 'sha256', $value );
	}

	private function scalar_text( $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	}
}
