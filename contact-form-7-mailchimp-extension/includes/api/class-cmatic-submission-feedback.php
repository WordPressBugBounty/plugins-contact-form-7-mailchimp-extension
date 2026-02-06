<?php
/**
 * Form submission feedback handler.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

class Cmatic_Submission_Feedback {

	private static $last_result = null;

	public static function init() {
		add_filter( 'wpcf7_feedback_response', array( __CLASS__, 'inject_feedback' ), 10, 2 );
	}

	public static function set_result( $result ) {
		self::$last_result = $result;
	}

	public static function get_result() {
		return self::$last_result;
	}

	public static function clear() {
		self::$last_result = null;
	}

	public static function inject_feedback( $response, $result ) {
		if ( null !== self::$last_result ) {
			$response['chimpmatic'] = self::$last_result;
			self::clear();
		}
		return $response;
	}

	public static function success( $email, $status, $merge_vars = array(), $api_response = array() ) {
		$received = array();

		if ( ! empty( $api_response['email_address'] ) ) {
			$received['EMAIL'] = $api_response['email_address'];
		}

		if ( ! empty( $api_response['merge_fields'] ) && is_array( $api_response['merge_fields'] ) ) {
			foreach ( $api_response['merge_fields'] as $key => $value ) {
				if ( ! empty( $value ) || '0' === $value || 0 === $value ) {
					$received[ $key ] = $value;
				}
			}
		}

		if ( ! empty( $api_response['tags'] ) && is_array( $api_response['tags'] ) ) {
			$tag_names = array_column( $api_response['tags'], 'name' );
			if ( ! empty( $tag_names ) ) {
				$received['TAGS'] = implode( ', ', $tag_names );
			}
		}

		if ( ! empty( $merge_vars['INTERESTS'] ) ) {
			$received['INTERESTS'] = '✓ ' . $merge_vars['INTERESTS'];
		} elseif ( ! empty( $api_response['interests'] ) && is_array( $api_response['interests'] ) ) {
			$enabled_count = count( array_filter( $api_response['interests'] ) );
			if ( $enabled_count > 0 ) {
				$received['INTERESTS'] = '✓ ' . $enabled_count . ( 1 === $enabled_count ? ' group' : ' groups' );
			}
		}

		if ( ! empty( $merge_vars['GDPR'] ) ) {
			$received['GDPR'] = '✓ ' . $merge_vars['GDPR'];
		} elseif ( ! empty( $api_response['marketing_permissions'] ) && is_array( $api_response['marketing_permissions'] ) ) {
			$enabled_count = 0;
			$total_count   = count( $api_response['marketing_permissions'] );
			foreach ( $api_response['marketing_permissions'] as $permission ) {
				if ( ! empty( $permission['enabled'] ) ) {
					++$enabled_count;
				}
			}
			$received['GDPR'] = '✓ ' . $enabled_count . ' of ' . $total_count . ( 1 === $total_count ? ' permission' : ' permissions' );
		}

		if ( ! empty( $api_response['location'] ) && is_array( $api_response['location'] ) ) {
			$lat = $api_response['location']['latitude'] ?? 0;
			$lng = $api_response['location']['longitude'] ?? 0;
			if ( 0 !== $lat || 0 !== $lng ) {
				$received['LOCATION'] = round( $lat, 4 ) . ', ' . round( $lng, 4 );
			}
		}

		if ( ! empty( $api_response['language'] ) ) {
			$received['LANGUAGE'] = strtoupper( $api_response['language'] );
		}

		if ( ! empty( $api_response['email_type'] ) ) {
			$received['EMAIL_TYPE'] = strtoupper( $api_response['email_type'] );
		}

		if ( ! empty( $api_response['status'] ) ) {
			$received['STATUS'] = ucfirst( $api_response['status'] );
		}

		return array(
			'success'     => true,
			'email'       => $email,
			'status'      => $status,
			'status_text' => self::get_status_text( $status ),
			'merge_vars'  => $merge_vars,
			'received'    => $received,
			'message'     => self::get_success_message( $email, $status ),
		);
	}

	public static function failure( $reason, $detail = '', $email = '' ) {
		return array(
			'success' => false,
			'reason'  => $reason,
			'detail'  => $detail,
			'email'   => $email,
			'message' => self::get_failure_message( $reason, $detail ),
		);
	}

	public static function skipped( $reason ) {
		return array(
			'success' => null,
			'skipped' => true,
			'reason'  => $reason,
			'message' => self::get_skip_message( $reason ),
		);
	}

	private static function get_status_text( $status ) {
		$statuses = array(
			'subscribed'   => __( 'Subscribed', 'chimpmatic-lite' ),
			'pending'      => __( 'Pending Confirmation', 'chimpmatic-lite' ),
			'unsubscribed' => __( 'Unsubscribed', 'chimpmatic-lite' ),
		);
		return isset( $statuses[ $status ] ) ? $statuses[ $status ] : $status;
	}

	private static function get_success_message( $email, $status ) {
		if ( 'pending' === $status ) {
			/* translators: %s: email address */
			return sprintf( __( '%s added - awaiting confirmation email.', 'chimpmatic-lite' ), $email );
		}
		/* translators: %s: email address */
		return sprintf( __( '%s subscribed successfully!', 'chimpmatic-lite' ), $email );
	}

	private static function get_failure_message( $reason, $detail = '' ) {
		$messages = array(
			'invalid_email'         => __( 'Invalid email address provided.', 'chimpmatic-lite' ),
			'already_subscribed'    => __( 'This email is already subscribed.', 'chimpmatic-lite' ),
			'permanently_deleted'   => __( 'This email was permanently deleted and cannot be re-imported.', 'chimpmatic-lite' ),
			'previously_unsubscribed' => __( 'This email previously unsubscribed and cannot be re-added.', 'chimpmatic-lite' ),
			'compliance_state'      => __( 'This email is in a compliance state and cannot be subscribed.', 'chimpmatic-lite' ),
			'api_error'             => __( 'Mailchimp API error occurred.', 'chimpmatic-lite' ),
			'network_error'         => __( 'Network error connecting to Mailchimp.', 'chimpmatic-lite' ),
		);

		$message = isset( $messages[ $reason ] ) ? $messages[ $reason ] : __( 'Subscription failed.', 'chimpmatic-lite' );

		if ( ! empty( $detail ) ) {
			$message .= ' ' . $detail;
		}

		return $message;
	}

	private static function get_skip_message( $reason ) {
		$messages = array(
			'acceptance_not_checked' => __( 'Opt-in checkbox was not checked.', 'chimpmatic-lite' ),
			'no_api_configured'      => __( 'Mailchimp API is not configured for this form.', 'chimpmatic-lite' ),
		);
		return isset( $messages[ $reason ] ) ? $messages[ $reason ] : __( 'Subscription skipped.', 'chimpmatic-lite' );
	}

	public static function parse_api_error( $api_response, $email = '' ) {
		$title  = isset( $api_response['title'] ) ? $api_response['title'] : '';
		$detail = isset( $api_response['detail'] ) ? $api_response['detail'] : '';

		if ( strpos( strtolower( $title ), 'member exists' ) !== false ) {
			return self::failure( 'already_subscribed', '', $email );
		}

		if ( strpos( strtolower( $detail ), 'permanently deleted' ) !== false ) {
			return self::failure( 'permanently_deleted', '', $email );
		}

		if ( strpos( strtolower( $detail ), 'compliance state' ) !== false ) {
			return self::failure( 'compliance_state', '', $email );
		}

		if ( strpos( strtolower( $title ), 'forgotten email' ) !== false ) {
			return self::failure( 'permanently_deleted', '', $email );
		}

		return self::failure( 'api_error', $detail, $email );
	}
}
