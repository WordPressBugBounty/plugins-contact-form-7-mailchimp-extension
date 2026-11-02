<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

use Cmatic\Lite\Rest\Args;

class Cmatic_Log_Viewer {

	protected static $namespace   = 'chimpmatic-lite/v1';
	protected static $log_prefix  = '[Contact Form 7 Extension]';
	protected static $text_domain = 'contact-form-7-mailchimp-extension';
	protected static $max_lines   = 500;
	protected static $initialized = false;

	public static function init( $namespace = null, $log_prefix = null, $text_domain = null ) {
		if ( self::$initialized ) {
			return;
		}

		if ( $namespace ) {
			self::$namespace = $namespace . '/v1';
		}
		if ( $log_prefix ) {
			self::$log_prefix = $log_prefix;
		}
		if ( $text_domain ) {
			self::$text_domain = $text_domain;
		}

		add_action( 'rest_api_init', array( static::class, 'register_routes' ) );

		self::$initialized = true;
	}

	public static function register_routes() {
		register_rest_route(
			self::$namespace,
			'/logs',
			array(
				'methods'             => 'GET',
				'callback'            => array( static::class, 'get_logs' ),
				'permission_callback' => array( 'Cmatic_Rest_Permissions', 'admin' ),
				'args'                => array(
					'filter' => Args::of( 'string', 'sanitize_text_field', false ) + array(
						'default'           => '1',
						'validate_callback' => Args::one_of( array( '0', '1' ) ),
					),
					'source' => Args::of( 'string', 'sanitize_key', false ) + array(
						'default'           => 'own',
						'validate_callback' => Args::one_of( array( 'own', 'site' ) ),
					),
				),
			)
		);

		register_rest_route(
			self::$namespace,
			'/logs/clear',
			array(
				'methods'             => 'POST',
				'callback'            => array( static::class, 'clear_logs' ),
				'permission_callback' => array( 'Cmatic_Rest_Permissions', 'admin' ),
			)
		);

		register_rest_route(
			self::$namespace,
			'/logs/download',
			array(
				'methods'             => 'GET',
				'callback'            => array( static::class, 'download_logs' ),
				'permission_callback' => array( 'Cmatic_Rest_Permissions', 'admin' ),
			)
		);

		register_rest_route(
			self::$namespace,
			'/logs/browser',
			array(
				'methods'             => 'POST',
				'callback'            => array( static::class, 'log_browser_console' ),
				'permission_callback' => array( 'Cmatic_Rest_Permissions', 'admin' ),
				'args'                => array(
					'level'   => Args::of( 'string', 'sanitize_text_field' ) + array(
						'validate_callback' => Args::one_of( array( 'log', 'info', 'warn', 'error', 'debug' ) ),
					),
					'message' => Args::of( 'string', 'sanitize_text_field' ),
					'data'    => Args::of( 'string', 'sanitize_textarea_field', false ),
				),
			)
		);
	}

	public static function get_logs( $request ) {
		$apply_filter = '1' === $request->get_param( 'filter' );
		$source       = 'site' === $request->get_param( 'source' ) ? 'site' : 'own';

		if ( 'site' === $source ) {
			$path  = Cmatic_Debug_Log::site_log_path();
			$lines = array_map( array( 'Cmatic_Debug_Log', 'redact' ), Cmatic_Debug_Log::site_log_tail( $apply_filter ) );
		} else {
			$path  = Cmatic_Debug_Log::path();
			$lines = Cmatic_Debug_Log::tail();
		}
		$exists = '' !== $path && is_file( $path );

		if ( ! $exists ) {
			$message = 'site' === $source
				? __( 'This site keeps no PHP error log. Set WP_DEBUG_LOG in wp-config.php while you test, then look here again.', 'contact-form-7-mailchimp-extension' )
				: __( 'No log yet. Switch the Debug Logger on and submit a form.', 'contact-form-7-mailchimp-extension' );
		} elseif ( empty( $lines ) ) {
			$message = __( 'The log is empty.', 'contact-form-7-mailchimp-extension' );
		} else {
			$message = '';
		}

		return new WP_REST_Response(
			array(
				'success'  => true,
				'message'  => $message,
				'logs'     => implode( "\n", $lines ),
				'count'    => count( $lines ),
				'filtered' => $apply_filter,
				'source'   => $source,
				'exists'   => $exists,
				'path'     => $exists ? basename( $path ) : '',
				'size'     => $exists ? (int) filesize( $path ) : 0,
				'logging'  => Cmatic_Debug_Log::logging(),
			),
			200
		);
	}

	public static function clear_logs( $request ) {
		$had_file = is_file( Cmatic_Debug_Log::path() );
		$cleared  = Cmatic_Debug_Log::clear();

		return new WP_REST_Response(
			array(
				'success' => $cleared,
				'cleared' => $cleared && $had_file,
				'message' => $cleared
					? ( $had_file ? __( 'Debug log cleared successfully.', 'contact-form-7-mailchimp-extension' ) : __( 'Debug log file does not exist.', 'contact-form-7-mailchimp-extension' ) )
					: __( 'Failed to clear debug log file.', 'contact-form-7-mailchimp-extension' ),
			),
			$cleared ? 200 : 500
		);
	}

	public static function download_logs() {
		$path = Cmatic_Debug_Log::path();
		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="chimpmatic-debug-' . gmdate( 'Ymd-His' ) . '.log"' );
		if ( is_file( $path ) ) {
			readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- streaming the plugin's own log file to an administrator.
		}
		exit;
	}

	public static function log_browser_console( $request ) {
		$level   = $request->get_param( 'level' );
		$message = $request->get_param( 'message' );
		$data    = $request->get_param( 'data' );

		$level_map = array(
			'log'   => 'INFO',
			'info'  => 'INFO',
			'warn'  => 'WARNING',
			'error' => 'ERROR',
			'debug' => 'DEBUG',
		);

		$logger = new Cmatic_File_Logger( 'Browser-Console', Cmatic_Debug_Log::logging() );
		$logger->log( $level_map[ $level ] ?? 'INFO', 'Browser: ' . $message, $data ? json_decode( $data, true ) : null );

		return new WP_REST_Response(
			array(
				'success' => true,
				'logged'  => true,
			),
			200
		);
	}

	public static function render_card(): void {
		?>
		<div class="cmatic-adv-viewer" id="cmatic-adv-viewer">
			<div class="cmatic-adv-tabs" role="group" aria-label="<?php esc_attr_e( 'Log controls', 'contact-form-7-mailchimp-extension' ); ?>">
				<button type="button" class="button button-small is-active" data-cmatic-log-source="own"><?php esc_html_e( 'Plugin log', 'contact-form-7-mailchimp-extension' ); ?></button>
				<button type="button" class="button button-small" data-cmatic-log-source="site"><?php esc_html_e( 'Site errors', 'contact-form-7-mailchimp-extension' ); ?></button>
				<label class="cmatic-adv-auto"><input type="checkbox" id="cmatic-log-auto"> <?php esc_html_e( 'Auto-refresh', 'contact-form-7-mailchimp-extension' ); ?></label>
			</div>
			<pre class="cmatic-adv-log" id="log_panel" tabindex="0"><?php esc_html_e( 'Open the log to read it.', 'contact-form-7-mailchimp-extension' ); ?></pre>
			<p class="description" id="cmatic-log-meta"></p>
			<div class="cmatic-provider-actions">
				<button type="button" class="button vc-open-logs"><?php esc_html_e( 'Open', 'contact-form-7-mailchimp-extension' ); ?></button>
				<button type="button" class="button vc-clear-logs"><?php esc_html_e( 'Clear', 'contact-form-7-mailchimp-extension' ); ?></button>
				<button type="button" class="button vc-download-logs"><?php esc_html_e( 'Download', 'contact-form-7-mailchimp-extension' ); ?></button>
			</div>
		</div>
		<?php
	}

	public static function render( $args = array() ) {
		$defaults = array(
			'title'       => __( 'Submission Logs', 'contact-form-7-mailchimp-extension' ),
			'clear_text'  => __( 'Clear Logs', 'contact-form-7-mailchimp-extension' ),
			'placeholder' => __( 'Click "View Debug Logs" to fetch the log content.', 'contact-form-7-mailchimp-extension' ),
			'class'       => '',
			'visible'     => false,
		);

		$args = wp_parse_args( $args, $defaults );
		?>
		<div id="eventlog-sys" class="vc-logs <?php echo esc_attr( $args['class'] ); ?>"<?php echo $args['visible'] ? '' : ' style="margin-top: 1em; margin-bottom: 1em; display: none;"'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Constant inline style. ?>>
			<div class="mce-custom-fields">
				<div class="vc-logs-header">
					<span class="vc-logs-title"><?php echo esc_html( $args['title'] ); ?></span>
					<span class="vc-logs-actions">
						<a href="#" class="vc-open-logs"><?php echo esc_html__( 'Open log', 'contact-form-7-mailchimp-extension' ); ?></a>
						<span class="vc-logs-separator">|</span>
						<a href="#" class="vc-log-source" data-source="own"><?php echo esc_html__( 'Site errors', 'contact-form-7-mailchimp-extension' ); ?></a>
						<span class="vc-logs-separator">|</span>
						<span class="vc-filter-wrap" style="display:none"><a href="#" class="vc-toggle-filter" data-filtered="1"><?php echo esc_html__( 'Show All', 'contact-form-7-mailchimp-extension' ); ?></a>
						<span class="vc-logs-separator">|</span></span>
						<a href="#" class="vc-download-logs"><?php echo esc_html__( 'Download', 'contact-form-7-mailchimp-extension' ); ?></a>
						<span class="vc-logs-separator">|</span>
						<a href="#" class="vc-clear-logs"><?php echo esc_html( $args['clear_text'] ); ?></a>
					</span>
				</div>
				<pre><code id="log_panel"><?php echo esc_html( $args['placeholder'] ); ?></code></pre>
				<span class="description" id="cmatic-log-meta"></span>
			</div>
		</div>
		<?php
	}
}
