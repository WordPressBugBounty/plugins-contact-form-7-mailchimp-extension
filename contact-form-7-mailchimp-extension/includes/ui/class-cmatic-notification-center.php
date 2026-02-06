<?php
/**
 * Notification center manager.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

class Cmatic_Notification_Center {
	const STORAGE_KEY = 'cmatic_notifications';

	private static $instance            = null;
	private $notifications              = array();
	private $notifications_retrieved    = false;
	private $notifications_dirty        = false;

	public static function get() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'setup_notifications' ), 1 );
		add_action( 'shutdown', array( $this, 'save_notifications' ) );
	}

	public function setup_notifications() {
		$this->retrieve_notifications();
		$this->add_dynamic_notifications();
	}

	private function retrieve_notifications() {
		if ( $this->notifications_retrieved ) {
			return;
		}

		$this->notifications_retrieved = true;
		$user_id                       = get_current_user_id();

		if ( ! $user_id ) {
			return;
		}

		$stored = get_user_option( self::STORAGE_KEY, $user_id );

		if ( ! is_array( $stored ) ) {
			return;
		}

		foreach ( $stored as $data ) {
			$notification = Cmatic_Notification::from_array( $data );
			if ( $notification->display_for_current_user() ) {
				$this->notifications[] = $notification;
			}
		}
	}

	private function add_dynamic_notifications() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$api_key = $this->get_api_key_status();

		if ( ! $api_key ) {
			$this->add_notification(
				new Cmatic_Notification(
					__( 'Connect your Mailchimp API to enable email subscriptions.', 'chimpmatic-lite' ),
					array(
						'id'        => 'cmatic-api-not-connected',
						'type'      => Cmatic_Notification::WARNING,
						'priority'  => 1.0,
						'link'      => $this->get_settings_url(),
						'link_text' => __( 'Connect Now', 'chimpmatic-lite' ),
					)
				)
			);
		}

		if ( class_exists( 'Cmatic_Options_Repository' ) && Cmatic_Options_Repository::get_option( 'debug', false ) ) {
			$this->add_notification(
				new Cmatic_Notification(
					__( 'Debug logging is currently enabled.', 'chimpmatic-lite' ),
					array(
						'id'        => 'cmatic-debug-enabled',
						'type'      => Cmatic_Notification::INFO,
						'priority'  => 0.3,
						'link'      => $this->get_settings_url(),
						'link_text' => __( 'View Settings', 'chimpmatic-lite' ),
					)
				)
			);
		}
	}

	private function get_api_key_status() {
		$cache_key = 'cmatic_api_connected';
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return (bool) $cached;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached via transient.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT 10",
				'cf7_mch_%'
			)
		);

		$is_connected = false;

		if ( ! empty( $results ) ) {
			foreach ( $results as $row ) {
				$data = maybe_unserialize( $row->option_value );
				if ( is_array( $data ) && ! empty( $data['api_key'] ) ) {
					$is_connected = true;
					break;
				}
			}
		}

		set_transient( $cache_key, $is_connected ? '1' : '0', HOUR_IN_SECONDS );

		return $is_connected;
	}

	private function get_settings_url() {
		if ( class_exists( 'Cmatic_Plugin_Links' ) ) {
			$url = Cmatic_Plugin_Links::get_settings_url();
			if ( ! empty( $url ) ) {
				return $url;
			}
		}

		return admin_url( 'admin.php?page=wpcf7' );
	}

	public function add_notification( Cmatic_Notification $notification ) {
		if ( ! $notification->display_for_current_user() ) {
			return;
		}

		if ( $this->is_notification_dismissed( $notification ) ) {
			return;
		}

		$id = $notification->get_id();
		if ( $id ) {
			foreach ( $this->notifications as $existing ) {
				if ( $existing->get_id() === $id ) {
					return;
				}
			}
		}

		$this->notifications[]     = $notification;
		$this->notifications_dirty = true;
	}

	public function remove_notification( $notification_id ) {
		foreach ( $this->notifications as $index => $notification ) {
			if ( $notification->get_id() === $notification_id ) {
				unset( $this->notifications[ $index ] );
				$this->notifications       = array_values( $this->notifications );
				$this->notifications_dirty = true;
				return;
			}
		}
	}

	public function get_notification_by_id( $notification_id ) {
		foreach ( $this->notifications as $notification ) {
			if ( $notification->get_id() === $notification_id ) {
				return $notification;
			}
		}
		return null;
	}

	public function get_notifications() {
		return $this->notifications;
	}

	public function get_notification_count() {
		return count( $this->notifications );
	}

	public function get_sorted_notifications() {
		$notifications = $this->notifications;

		usort(
			$notifications,
			function ( $a, $b ) {
				$type_priority = array(
					Cmatic_Notification::ERROR   => 4,
					Cmatic_Notification::WARNING => 3,
					Cmatic_Notification::INFO    => 2,
					Cmatic_Notification::SUCCESS => 1,
				);

				$a_type = isset( $type_priority[ $a->get_type() ] ) ? $type_priority[ $a->get_type() ] : 0;
				$b_type = isset( $type_priority[ $b->get_type() ] ) ? $type_priority[ $b->get_type() ] : 0;

				if ( $a_type !== $b_type ) {
					return $b_type - $a_type;
				}

				if ( $b->get_priority() > $a->get_priority() ) {
					return 1;
				} elseif ( $b->get_priority() < $a->get_priority() ) {
					return -1;
				}
				return 0;
			}
		);

		return $notifications;
	}

	public function is_notification_dismissed( Cmatic_Notification $notification ) {
		$dismissal_key = $notification->get_dismissal_key();

		if ( empty( $dismissal_key ) ) {
			return false;
		}

		$user_id = get_current_user_id();
		$value   = get_user_option( 'cmatic_dismissed_' . $dismissal_key, $user_id );

		return ! empty( $value );
	}

	public function dismiss_notification( Cmatic_Notification $notification ) {
		$dismissal_key = $notification->get_dismissal_key();

		if ( empty( $dismissal_key ) ) {
			return false;
		}

		$user_id = get_current_user_id();
		$result  = update_user_option( $user_id, 'cmatic_dismissed_' . $dismissal_key, time() );

		if ( $result ) {
			$this->remove_notification( $notification->get_id() );
		}

		return (bool) $result;
	}

	public function save_notifications() {
		if ( ! $this->notifications_dirty ) {
			return;
		}

		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return;
		}

		$to_store = array();

		foreach ( $this->notifications as $notification ) {
			if ( $notification->is_persistent() ) {
				$to_store[] = $notification->to_array();
			}
		}

		if ( empty( $to_store ) ) {
			delete_user_option( $user_id, self::STORAGE_KEY );
		} else {
			update_user_option( $user_id, self::STORAGE_KEY, $to_store );
		}
	}

	public function clear_notifications() {
		$this->notifications       = array();
		$this->notifications_dirty = true;
	}
}
