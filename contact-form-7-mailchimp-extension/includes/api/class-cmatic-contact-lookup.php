<?php
/**
 * Contact lookup REST endpoint.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

class Cmatic_Contact_Lookup {

	protected static $namespace = 'chimpmatic-lite/v1';
	protected static $initialized = false;

	public static function cmatic_is_pro_active() {
		return apply_filters( 'cmatic_contact_lookup_is_pro', false );
	}

	protected static function cmatic_fltr_value( $value ) {
		if ( empty( $value ) || $value === null ) {
			return null;
		}
		return substr( md5( wp_json_encode( $value ) . wp_salt() ), 0, 7 );
	}

	protected static function cmatic_fltr_pro_fields( $result ) {
		if ( ! $result['found'] ) {
			return $result;
		}

		foreach ( Cmatic_Lite_Get_Fields::cmatic_lite_fields() as $field ) {
			if ( isset( $result[ $field ] ) ) {
				$result[ $field ] = self::cmatic_fltr_value( $result[ $field ] );
			}
		}

		if ( ! empty( $result['merge_fields'] ) && is_array( $result['merge_fields'] ) ) {
			$field_index = 0;
			foreach ( $result['merge_fields'] as $tag => $value ) {
				if ( $field_index >= Cmatic_Lite_Get_Fields::cmatic_lite_merge_fields() ) {
					$result['merge_fields'][ $tag ] = self::cmatic_fltr_value( $value );
				}
				++$field_index;
			}
		}

		foreach ( Cmatic_Lite_Get_Fields::cmatic_lite_sections() as $section ) {
			if ( isset( $result[ $section ] ) && ! empty( $result[ $section ] ) ) {
				if ( is_array( $result[ $section ] ) ) {
					$result[ $section ] = self::cmatic_fltr_array( $result[ $section ] );
				} else {
					$result[ $section ] = self::cmatic_fltr_value( $result[ $section ] );
				}
			}
		}

		return $result;
	}

	protected static function cmatic_fltr_array( $arr ) {
		$fltred = array();
		foreach ( $arr as $key => $value ) {
			if ( is_array( $value ) ) {
				$fltred[ self::cmatic_fltr_value( $key ) ] = self::cmatic_fltr_array( $value );
			} else {
				$fltred[] = self::cmatic_fltr_value( $value );
			}
		}
		return $fltred;
	}

	public static function init() {
		if ( self::$initialized ) {
			return;
		}

		add_action( 'rest_api_init', array( static::class, 'cmatic_register_routes' ) );
		self::$initialized = true;
	}

	public static function cmatic_register_routes() {
		register_rest_route(
			self::$namespace,
			'/contact/lookup',
			array(
				'methods'             => 'POST',
				'callback'            => array( static::class, 'cmatic_lookup_contact' ),
				'permission_callback' => array( static::class, 'cmatic_check_permission' ),
				'args'                => array(
					'email'   => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
						'validate_callback' => function( $param ) {
							return is_email( $param );
						},
					),
					'form_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	public static function cmatic_check_permission() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to access this endpoint.', 'chimpmatic-lite' ),
				array( 'status' => 403 )
			);
		}
		return true;
	}

	public static function cmatic_lookup_contact( $request ) {
		$email   = strtolower( $request->get_param( 'email' ) );
		$form_id = $request->get_param( 'form_id' );

		$option_name = 'cf7_mch_' . $form_id;
		$cf7_mch     = get_option( $option_name, array() );
		$api_key     = $cf7_mch['api'] ?? '';

		if ( empty( $api_key ) ) {
			return new WP_Error(
				'no_api_key',
				__( 'No API key configured. Please connect to Mailchimp first.', 'chimpmatic-lite' ),
				array( 'status' => 400 )
			);
		}

		if ( ! preg_match( '/^([a-f0-9]{32})-([a-z]{2,3}\d+)$/', $api_key, $matches ) ) {
			return new WP_Error(
				'invalid_api_key',
				__( 'Invalid API key format.', 'chimpmatic-lite' ),
				array( 'status' => 400 )
			);
		}

		$key = $matches[1];
		$dc  = $matches[2];

		$lists_url  = "https://{$dc}.api.mailchimp.com/3.0/lists?count=50&fields=lists.id,lists.name";
		$lists_resp = Cmatic_Lite_Api_Service::get( $key, $lists_url );

		if ( is_wp_error( $lists_resp[2] ) || 200 !== wp_remote_retrieve_response_code( $lists_resp[2] ) ) {
			return new WP_Error(
				'api_error',
				__( 'Failed to retrieve audiences from Mailchimp.', 'chimpmatic-lite' ),
				array( 'status' => 500 )
			);
		}

		$lists_data = $lists_resp[0];
		$lists      = $lists_data['lists'] ?? array();

		if ( empty( $lists ) ) {
			return rest_ensure_response(
				array(
					'success' => true,
					'email'   => $email,
					'found'   => false,
					'message' => __( 'No audiences found in your Mailchimp account.', 'chimpmatic-lite' ),
					'results' => array(),
				)
			);
		}

		$subscriber_hash = md5( $email );
		$results         = array();
		$found_count     = 0;

		foreach ( $lists as $list ) {
			$list_id   = $list['id'];
			$list_name = $list['name'];

			$member_url  = "https://{$dc}.api.mailchimp.com/3.0/lists/{$list_id}/members/{$subscriber_hash}";
			$member_resp = Cmatic_Lite_Api_Service::get( $key, $member_url );

			$status_code = wp_remote_retrieve_response_code( $member_resp[2] );

			if ( 200 === $status_code ) {
				$member_data = $member_resp[0];
				++$found_count;

				$interests        = array();
				$interests_url    = "https://{$dc}.api.mailchimp.com/3.0/lists/{$list_id}/interest-categories?count=100";
				$interests_resp   = Cmatic_Lite_Api_Service::get( $key, $interests_url );
				$interests_status = wp_remote_retrieve_response_code( $interests_resp[2] );

				if ( 200 === $interests_status && ! empty( $interests_resp[0]['categories'] ) ) {
					foreach ( $interests_resp[0]['categories'] as $category ) {
						$cat_id       = $category['id'];
						$cat_title    = $category['title'];
						$cat_interest = array();

						$cat_interests_url  = "https://{$dc}.api.mailchimp.com/3.0/lists/{$list_id}/interest-categories/{$cat_id}/interests?count=100";
						$cat_interests_resp = Cmatic_Lite_Api_Service::get( $key, $cat_interests_url );

						if ( 200 === wp_remote_retrieve_response_code( $cat_interests_resp[2] ) && ! empty( $cat_interests_resp[0]['interests'] ) ) {
							$member_interests = $member_data['interests'] ?? array();
							foreach ( $cat_interests_resp[0]['interests'] as $interest ) {
								$interest_id   = $interest['id'];
								$interest_name = $interest['name'];
								$is_subscribed = ! empty( $member_interests[ $interest_id ] );
								if ( $is_subscribed ) {
									$cat_interest[] = $interest_name;
								}
							}
						}

						if ( ! empty( $cat_interest ) ) {
							$interests[ $cat_title ] = $cat_interest;
						}
					}
				}

				$results[] = array(
					'list_id'               => $list_id,
					'list_name'             => $list_name,
					'found'                 => true,
					'status'                => $member_data['status'] ?? 'unknown',
					'email'                 => $member_data['email_address'] ?? $email,
					'merge_fields'          => $member_data['merge_fields'] ?? array(),
					'tags'                  => array_column( $member_data['tags'] ?? array(), 'name' ),
					'interests'             => $interests,
					'marketing_permissions' => $member_data['marketing_permissions'] ?? array(),
					'source'                => $member_data['source'] ?? null,
					'ip_signup'             => $member_data['ip_signup'] ?? null,
					'timestamp_signup'      => $member_data['timestamp_signup'] ?? null,
					'subscribed'            => $member_data['timestamp_opt'] ?? null,
					'last_changed'          => $member_data['last_changed'] ?? null,
					'unsubscribe_reason'    => $member_data['unsubscribe_reason'] ?? null,
					'language'              => $member_data['language'] ?? null,
					'email_type'            => $member_data['email_type'] ?? null,
					'vip'                   => $member_data['vip'] ?? false,
					'email_client'          => $member_data['email_client'] ?? null,
					'location'              => $member_data['location'] ?? null,
					'member_rating'         => $member_data['member_rating'] ?? null,
					'consents_to_one_to_one_messaging' => $member_data['consents_to_one_to_one_messaging'] ?? null,
				);
			} elseif ( 404 === $status_code ) {
				$results[] = array(
					'list_id'   => $list_id,
					'list_name' => $list_name,
					'found'     => false,
					'status'    => 'not_subscribed',
				);
			}
		}

		$is_pro = self::cmatic_is_pro_active();

		if ( ! $is_pro ) {
			$results = array_map( array( static::class, 'cmatic_fltr_pro_fields' ), $results );
		}

		Cmatic_Options_Repository::set_option( 'features.contact_lookup_used', true );
		do_action( 'cmatic_subscription_success', $form_id, $email );

		return rest_ensure_response(
			array(
				'success'     => true,
				'email'       => $email,
				'found'       => $found_count > 0,
				'found_count' => $found_count,
				'total_lists' => count( $lists ),
				'is_pro'      => $is_pro,
				'message'     => $found_count > 0
					? sprintf(
						/* translators: %1$d: found count, %2$d: total lists */
						__( 'Contact found in %1$d of %2$d audiences.', 'chimpmatic-lite' ),
						$found_count,
						count( $lists )
					)
					: __( 'Contact not found in any audience.', 'chimpmatic-lite' ),
				'results'     => $results,
			)
		);
	}

	public static function cmatic_render( $args = array() ) {
		$defaults = array(
			'form_id' => 0,
		);
		$args = wp_parse_args( $args, $defaults );
		?>
		<div id="cmatic-contact-lookup" class="postbox mce-move mce-hidden">
			<div class="inside" style="padding: 15px;">
				<h3 style="margin: 0 0 10px;"><?php esc_html_e( 'Contact Lookup', 'chimpmatic-lite' ); ?></h3>
				<p><?php esc_html_e( 'Debug tool: Check if a subscriber exists in your Mailchimp account and view their status across all your audiences.', 'chimpmatic-lite' ); ?></p>

				<div style="margin: 15px 0;">
					<input type="email"
						id="cmatic-lookup-email"
						placeholder="<?php esc_attr_e( 'Enter email address...', 'chimpmatic-lite' ); ?>"
						data-form-id="<?php echo esc_attr( $args['form_id'] ); ?>"
						style="width: 100%; margin-bottom: 8px;">
					<button type="button" id="cmatic-lookup-btn" class="button button-primary" style="width: 100%;">
						<?php esc_html_e( 'Lookup', 'chimpmatic-lite' ); ?>
					</button>
				</div>

				<div id="cmatic-lookup-results" class="cmatic-hidden"></div>
			</div>
		</div>
		<?php
	}
}
