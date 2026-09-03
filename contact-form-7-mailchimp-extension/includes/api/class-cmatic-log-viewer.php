<?php
/**
 * Debug log viewer component.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

class Cmatic_Log_Viewer {

	protected static $namespace   = 'chimpmatic-lite/v1';
	protected static $log_prefix  = '[Chimpmatic Lite]';
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
		add_action( 'admin_enqueue_scripts', array( static::class, 'enqueue_assets' ) );

		self::$initialized = true;
	}

	public static function register_routes() {
		register_rest_route(
			self::$namespace,
			'/logs',
			array(
				'methods'             => 'GET',
				'callback'            => array( static::class, 'get_logs' ),
				'permission_callback' => array( static::class, 'check_permission' ),
				'args'                => array(
					'filter' => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => '1',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $param ) {
							return in_array( $param, array( '0', '1' ), true );
						},
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
				'permission_callback' => array( static::class, 'check_permission' ),
			)
		);

		register_rest_route(
			self::$namespace,
			'/logs/browser',
			array(
				'methods'             => 'POST',
				'callback'            => array( static::class, 'log_browser_console' ),
				'permission_callback' => array( static::class, 'check_permission' ),
				'args'                => array(
					'level'   => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $param ) {
							return in_array( $param, array( 'log', 'info', 'warn', 'error', 'debug' ), true );
						},
					),
					'message' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'data'    => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
				),
			)
		);
	}

	public static function check_permission() {
		return current_user_can( 'manage_options' );
	}

	public static function get_log_prefix() {
		return static::$log_prefix;
	}

	public static function get_log_path() {
		if ( defined( 'WP_DEBUG_LOG' ) && is_string( WP_DEBUG_LOG ) ) {
			return WP_DEBUG_LOG;
		}
		return WP_CONTENT_DIR . '/debug.log';
	}

	private static function filesystem() {
		global $wp_filesystem;

		require_once ABSPATH . 'wp-admin/includes/file.php';
		if ( ! WP_Filesystem() || ! $wp_filesystem instanceof WP_Filesystem_Base ) {
			return null;
		}

		return $wp_filesystem;
	}

	public static function get_logs( $request ) {
		$log_path     = static::get_log_path();
		$prefix       = static::get_log_prefix();
		$apply_filter = '1' === $request->get_param( 'filter' );
		$filesystem   = self::filesystem();

		if ( ! $filesystem || ! $filesystem->exists( $log_path ) ) {
			return new WP_REST_Response(
				array(
					'success'  => false,
					'message'  => __( 'Debug log file not found. Ensure WP_DEBUG_LOG is enabled.', 'contact-form-7-mailchimp-extension' ),
					'logs'     => '',
					'filtered' => $apply_filter,
				),
				200
			);
		}

		$lines = static::read_last_lines( $log_path, self::$max_lines );

		if ( $apply_filter ) {
			$output = array();
			foreach ( $lines as $line ) {
				if ( strpos( $line, $prefix ) !== false ) {
					$output[] = $line;
				}
			}
		} else {
			$output = array_filter(
				$lines,
				function ( $line ) {
					return '' !== trim( $line );
				}
			);
		}

		if ( empty( $output ) ) {
			$message = $apply_filter
				? sprintf(
					/* translators: %1$s: prefix, %2$d: number of lines checked */
					__( 'No %1$s entries found in the recent log data. Note: This viewer only shows the last %2$d lines of the log file.', 'contact-form-7-mailchimp-extension' ),
					$prefix,
					self::$max_lines
				)
				: __( 'Debug log is empty.', 'contact-form-7-mailchimp-extension' );

			return new WP_REST_Response(
				array(
					'success'  => true,
					'message'  => $message,
					'logs'     => '',
					'count'    => 0,
					'filtered' => $apply_filter,
				),
				200
			);
		}

		return new WP_REST_Response(
			array(
				'success'  => true,
				'message'  => '',
				'logs'     => implode( "\n", $output ),
				'count'    => count( $output ),
				'filtered' => $apply_filter,
			),
			200
		);
	}

	public static function clear_logs( $request ) {
		$log_path   = static::get_log_path();
		$filesystem = self::filesystem();

		if ( ! $filesystem || ! $filesystem->exists( $log_path ) ) {
			return new WP_REST_Response(
				array(
					'success' => true,
					'cleared' => false,
					'message' => __( 'Debug log file does not exist.', 'contact-form-7-mailchimp-extension' ),
				),
				200
			);
		}

		if ( ! $filesystem->is_writable( $log_path ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'cleared' => false,
					'message' => __( 'Debug log file is not writable.', 'contact-form-7-mailchimp-extension' ),
				),
				500
			);
		}

		if ( ! $filesystem->put_contents( $log_path, '', FS_CHMOD_FILE ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'cleared' => false,
					'message' => __( 'Failed to clear debug log file.', 'contact-form-7-mailchimp-extension' ),
				),
				500
			);
		}

		$logger = new Cmatic_File_Logger( 'Log-Viewer', true );
		$logger->log( 'INFO', 'Debug log cleared by an administrator.', array( 'user_id' => get_current_user_id() ) );

		return new WP_REST_Response(
			array(
				'success' => true,
				'cleared' => true,
				'message' => __( 'Debug log cleared successfully.', 'contact-form-7-mailchimp-extension' ),
			),
			200
		);
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

		$wp_level    = $level_map[ $level ] ?? 'INFO';
		$log_message = sprintf(
			'[%s] %s [Browser Console - %s] %s',
			gmdate( 'd-M-Y H:i:s' ) . ' UTC',
			static::$log_prefix,
			strtoupper( $level ),
			$message
		);

		if ( ! empty( $data ) ) {
			$log_message .= ' | Data: ' . $data;
		}
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( $log_message );
		}
		$logfile_enabled = (bool) get_option( CMATIC_LOG_OPTION, false );
		$logger          = new Cmatic_File_Logger( 'Browser-Console', $logfile_enabled );
		$logger->log( $wp_level, 'Browser: ' . $message, $data ? json_decode( $data, true ) : null );

		return new WP_REST_Response(
			array(
				'success' => true,
				'logged'  => true,
			),
			200
		);
	}

	protected static function read_last_lines( $filepath, $lines = 500 ) {
		$filesystem = self::filesystem();
		if ( ! $filesystem ) {
			return array();
		}

		$contents = $filesystem->get_contents( $filepath );
		if ( false === $contents || '' === $contents ) {
			return array();
		}

		$result = preg_split( '/\r\n|\r|\n/', $contents );
		return is_array( $result ) ? array_slice( $result, -absint( $lines ) ) : array();
	}

	public static function enqueue_assets( $hook ) {
	}

	protected static function get_inline_js() {
		return '';
	}

	public static function render( $args = array() ) {
		$defaults = array(
			'title'       => __( 'Submission Logs', 'contact-form-7-mailchimp-extension' ),
			'clear_text'  => __( 'Clear Logs', 'contact-form-7-mailchimp-extension' ),
			'placeholder' => __( 'Click "View Debug Logs" to fetch the log content.', 'contact-form-7-mailchimp-extension' ),
			'class'       => '',
		);

		$args = wp_parse_args( $args, $defaults );
		?>
		<div id="eventlog-sys" class="vc-logs <?php echo esc_attr( $args['class'] ); ?>" style="margin-top: 1em; margin-bottom: 1em; display: none;">
			<div class="mce-custom-fields">
				<div class="vc-logs-header">
					<span class="vc-logs-title"><?php echo esc_html( $args['title'] ); ?></span>
					<span class="vc-logs-actions">
						<a href="#" class="vc-toggle-filter" data-filtered="1"><?php echo esc_html__( 'Show All', 'contact-form-7-mailchimp-extension' ); ?></a>
						<span class="vc-logs-separator">|</span>
						<a href="#" class="vc-clear-logs"><?php echo esc_html( $args['clear_text'] ); ?></a>
					</span>
				</div>
				<pre><code id="log_panel"><?php echo esc_html( $args['placeholder'] ); ?></code></pre>
			</div>
		</div>
		<?php
	}
}
