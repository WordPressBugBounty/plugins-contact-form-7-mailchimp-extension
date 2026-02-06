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

	protected static $namespace = 'chimpmatic-lite/v1';
	protected static $log_prefix = '[ChimpMatic Lite]';
	protected static $text_domain = 'chimpmatic-lite';
	protected static $max_lines = 500;
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

	public static function get_logs( $request ) {
		$log_path     = static::get_log_path();
		$prefix       = static::get_log_prefix();
		$apply_filter = '1' === $request->get_param( 'filter' );

		if ( ! file_exists( $log_path ) ) {
			return new WP_REST_Response(
				array(
					'success'  => false,
					'message'  => __( 'Debug log file not found. Ensure WP_DEBUG_LOG is enabled.', self::$text_domain ),
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
					__( 'No %1$s entries found in the recent log data. Note: This viewer only shows the last %2$d lines of the log file.', self::$text_domain ),
					$prefix,
					self::$max_lines
				)
				: __( 'Debug log is empty.', self::$text_domain );

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
		$log_path = static::get_log_path();

		if ( ! file_exists( $log_path ) ) {
			return new WP_REST_Response(
				array(
					'success' => true,
					'cleared' => false,
					'message' => __( 'Debug log file does not exist.', self::$text_domain ),
				),
				200
			);
		}

		if ( ! wp_is_writable( $log_path ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'cleared' => false,
					'message' => __( 'Debug log file is not writable.', self::$text_domain ),
				),
				500
			);
		}

		$file_handle = fopen( $log_path, 'w' );

		if ( false === $file_handle ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'cleared' => false,
					'message' => __( 'Failed to clear debug log file.', self::$text_domain ),
				),
				500
			);
		}

		fclose( $file_handle );

		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log(
				sprintf(
					'[%s] [ChimpMatic Lite] Debug log cleared by user: %s',
					gmdate( 'd-M-Y H:i:s' ) . ' UTC',
					wp_get_current_user()->user_login
				)
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'cleared' => true,
				'message' => __( 'Debug log cleared successfully.', self::$text_domain ),
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
		$logger = new Cmatic_File_Logger( 'Browser-Console', $logfile_enabled );
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
		$handle = fopen( $filepath, 'r' );
		if ( ! $handle ) {
			return array();
		}

		$result    = array();
		$chunk     = 4096;
		$file_size = filesize( $filepath );
		if ( 0 === $file_size ) {
			fclose( $handle );
			return array();
		}
		$pos    = $file_size;
		$buffer = '';
		while ( $pos > 0 && count( $result ) < $lines ) {
			$read_size = min( $chunk, $pos );
			$pos      -= $read_size;
			fseek( $handle, $pos );
			$buffer       = fread( $handle, $read_size ) . $buffer;
			$buffer_lines = explode( "\n", $buffer );
			$buffer       = array_shift( $buffer_lines );
			$result       = array_merge( $buffer_lines, $result );
		}
		if ( 0 === $pos && ! empty( $buffer ) ) {
			array_unshift( $result, $buffer );
		}
		fclose( $handle );
		return array_slice( $result, -$lines );
	}

	public static function enqueue_assets( $hook ) {
	}

	protected static function get_inline_js() {
		$namespace = self::$namespace;

		return <<<JS
(function($) {
	'use strict';

	var CmaticLogViewer = {
		namespace: '{$namespace}',

		// Get REST API root URL (supports both LITE and PRO configurations).
		getRestRoot: function() {
			if (typeof wpApiSettings !== 'undefined' && wpApiSettings.root) {
				return wpApiSettings.root;
			}
			if (typeof chimpmaticLite !== 'undefined' && chimpmaticLite.restUrl) {
				// chimpmaticLite.restUrl includes 'chimpmatic-lite/v1/' already.
				return chimpmaticLite.restUrl.replace(/chimpmatic-lite\/v1\/$/, '');
			}
			// Fallback: construct from current URL.
			return window.location.origin + '/wp-json/';
		},

		// Get REST API nonce.
		getNonce: function() {
			if (typeof wpApiSettings !== 'undefined' && wpApiSettings.nonce) {
				return wpApiSettings.nonce;
			}
			if (typeof chimpmaticLite !== 'undefined' && chimpmaticLite.restNonce) {
				return chimpmaticLite.restNonce;
			}
			return '';
		},

		init: function() {
			$(document).on('click', '.cme-trigger-log', this.toggleLogs.bind(this));
			$(document).on('click', '.vc-clear-logs', this.clearLogs.bind(this));
		},

		toggleLogs: function(e) {
			e.preventDefault();
			var \$container = $('#eventlog-sys');
			var \$trigger = $(e.currentTarget);

			if (\$container.is(':visible')) {
				\$container.slideUp(200);
				\$trigger.text('View Debug Logs');
			} else {
				\$container.slideDown(200);
				\$trigger.text('Hide Debug Logs');
				this.fetchLogs();
			}
		},

		fetchLogs: function() {
			var self = this;
			var \$panel = $('#log_panel');

			\$panel.text('Loading logs...');

			$.ajax({
				url: this.getRestRoot() + this.namespace + '/logs',
				method: 'GET',
				beforeSend: function(xhr) {
					var nonce = self.getNonce();
					if (nonce) {
						xhr.setRequestHeader('X-WP-Nonce', nonce);
					}
				},
				success: function(response) {
					if (response.logs) {
						\$panel.text(response.logs);
					} else {
						\$panel.text(response.message || 'No logs found.');
					}
				},
				error: function(xhr) {
					\$panel.text('Error loading logs: ' + xhr.statusText);
				}
			});
		},

		clearLogs: function(e) {
			e.preventDefault();
			$('#log_panel').text('Logs cleared.');
		},

		refresh: function() {
			if ($('#eventlog-sys').is(':visible')) {
				this.fetchLogs();
			}
		}
	};

	$(document).ready(function() {
		CmaticLogViewer.init();
	});

	// Expose for external use (e.g., after test submission).
	window.CmaticLogViewer = CmaticLogViewer;
})(jQuery);
JS;
	}

	public static function render( $args = array() ) {
		$defaults = array(
			'title'       => __( 'Submission Logs', self::$text_domain ),
			'clear_text'  => __( 'Clear Logs', self::$text_domain ),
			'placeholder' => __( 'Click "View Debug Logs" to fetch the log content.', self::$text_domain ),
			'class'       => '',
		);

		$args = wp_parse_args( $args, $defaults );
		?>
		<div id="eventlog-sys" class="vc-logs <?php echo esc_attr( $args['class'] ); ?>" style="margin-top: 1em; margin-bottom: 1em; display: none;">
			<div class="mce-custom-fields">
				<div class="vc-logs-header">
					<span class="vc-logs-title"><?php echo esc_html( $args['title'] ); ?></span>
					<span class="vc-logs-actions">
						<a href="#" class="vc-toggle-filter" data-filtered="1"><?php echo esc_html__( 'Show All', 'chimpmatic-lite' ); ?></a>
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
