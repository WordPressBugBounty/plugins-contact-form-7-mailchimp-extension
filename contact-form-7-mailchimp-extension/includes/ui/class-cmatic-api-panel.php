<?php
/**
 * API key panel UI component.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

class Cmatic_Api_Panel {
	const CMATIC_FB_C = '.com';

	public static function mask_key( string $key ): string {
		if ( empty( $key ) || strlen( $key ) < 12 ) {
			return $key;
		}
		$prefix = substr( $key, 0, 8 );
		$suffix = substr( $key, -4 );
		return $prefix . str_repeat( "\u{2022}", 20 ) . $suffix;
	}

	public static function render( array $cf7_mch, string $apivalid = '0', int $form_id = 0 ): void {
		$api_key     = isset( $cf7_mch['api'] ) ? $cf7_mch['api'] : '';
		$is_valid    = '1' === $apivalid;
		$has_api_key = ! empty( $api_key );
		$auth_type   = isset( $cf7_mch['auth_type'] ) ? $cf7_mch['auth_type'] : '';
		$is_oauth    = 'oauth' === $auth_type && $is_valid;

		if ( ! $has_api_key && ! $is_oauth && $form_id ) {
			$auth_manager = Cmatic_Lite_Container::get( 'auth.manager' );
			if ( $auth_manager && $auth_manager->has_oauth( $form_id ) ) {
				$is_oauth = true;
				$is_valid = true;
			}
		}

		$help_url = Cmatic_Pursuit::docs( 'how-to-get-your-mailchimp-api-key', 'api_panel_help' );

		if ( $is_oauth ) {
			self::render_oauth_connected( $form_id, $cf7_mch );
		} elseif ( $has_api_key ) {
			$masked_key = self::mask_key( $api_key );
			$is_masked  = strlen( $api_key ) >= 12;

			$btn_value = $is_valid ? 'Connected' : 'Sync Audiences';
			$btn_class = 'button';
			?>
			<div class="cmatic-field-group">
			<label for="cmatic-api"><?php echo esc_html__( 'Mailchimp API Key:', 'chimpmatic-lite' ); ?></label><br />
			<div class="cmatic-api-wrap">
				<input
					type="text"
					id="cmatic-api"
					name="wpcf7-mailchimp[api]"
					class="wide"
					placeholder="<?php echo esc_attr__( 'Enter Your Mailchimp API key Here', 'chimpmatic-lite' ); ?>"
					value="<?php echo esc_attr( $is_masked ? $masked_key : $api_key ); ?>"
					data-masked-key="<?php echo esc_attr( $masked_key ); ?>"
					data-is-masked="<?php echo $is_masked ? '1' : '0'; ?>"
					data-has-key="1"
				/>
			</div>
			<small class="description"><?php esc_html_e( 'Stored credentials stay hidden. Paste a new key to replace this one.', 'chimpmatic-lite' ); ?></small>

			<input
				id="chm_activalist"
				type="button"
				value="<?php echo esc_attr( $btn_value ); ?>"
				class="<?php echo esc_attr( $btn_class ); ?>"
			/>

			<small class="description need-api">
				<a href="<?php echo esc_url( $help_url ); ?>" class="helping-field" target="_blank" rel="noopener noreferrer" title="<?php echo esc_attr__( 'Get help with Mailchimp API Key', 'chimpmatic-lite' ); ?>">
					<?php echo esc_html__( 'Find your Mailchimp API here', 'chimpmatic-lite' ); ?>
					<span class="red-icon dashicons dashicons-arrow-right"></span>
					<span class="red-icon dashicons dashicons-arrow-right"></span>
				</a>
			</small>
			<div id="chmp-new-user" class="new-user <?php echo esc_attr( $is_valid ? 'chmp-inactive' : 'chmp-active' ); ?>">
				<?php Cmatic_Banners::render_new_user_help(); ?>
			</div>
			</div><!-- .cmatic-field-group -->
			<?php
		} else {
			self::render_fresh_form( $form_id, $help_url );
		}
	}

	private static function render_oauth_connected( int $form_id, array $cf7_mch = array() ): void {
		$account_name   = isset( $cf7_mch['oauth_account_name'] ) ? $cf7_mch['oauth_account_name'] : '';
		$connected_by   = isset( $cf7_mch['oauth_connected_by'] ) ? $cf7_mch['oauth_connected_by'] : '';
		$connected_date = isset( $cf7_mch['oauth_connected_date'] ) ? $cf7_mch['oauth_connected_date'] : '';
		$auth_manager   = Cmatic_Lite_Container::get( 'auth.manager' );
		$credentials    = $auth_manager->get_credentials( $form_id );
		$dc             = $credentials ? $credentials->get_datacenter() : '';

		if ( $account_name ) {
			/* translators: %1$s: Mailchimp account name, %2$s: datacenter ID */
			$status_text = sprintf( __( "Connected to %1\$s's Mailchimp (%2\$s)", 'chimpmatic-lite' ), $account_name, $dc );
		} else {
			/* translators: %s: datacenter ID */
			$status_text = sprintf( __( 'Connected to Mailchimp (%s)', 'chimpmatic-lite' ), $dc );
		}

		$authorized_text = '';
		if ( $connected_by && $connected_date ) {
			$date_formatted = date_i18n( get_option( 'date_format' ), strtotime( $connected_date ) );
			/* translators: %1$s: WordPress user name, %2$s: formatted date */
			$authorized_text = sprintf( __( 'Authorized by %1$s on %2$s', 'chimpmatic-lite' ), $connected_by, $date_formatted );
		}

		$learn_more_url = Cmatic_Pursuit::docs( 'how-to-get-your-mailchimp-api-key', 'oauth_learn_more' );
		?>
		<div class="cmatic-field-group cmatic-oauth-state">
			<label><?php echo esc_html__( 'Mailchimp Connection:', 'chimpmatic-lite' ); ?></label><br />
			<span class="cmatic-oauth-status-text">
				<span class="dashicons dashicons-yes-alt"></span>
				<?php echo esc_html( $status_text ); ?>
			</span>
			<input
				id="chm_activalist"
				type="button"
				value="Connected"
				class="button cmatic-hidden"
				data-form-id="<?php echo esc_attr( $form_id ); ?>"
			/>
			<button type="button" class="button cmatic-oauth-disconnect" data-form-id="<?php echo esc_attr( $form_id ); ?>">
				<?php echo esc_html__( 'Disconnect', 'chimpmatic-lite' ); ?>
			</button>
			<?php if ( $authorized_text ) : ?>
			<br />
			<small class="description">
				<?php echo esc_html( $authorized_text ); ?>
				<a href="<?php echo esc_url( $learn_more_url ); ?>" class="helping-field" target="_blank" rel="noopener noreferrer" title="<?php echo esc_attr__( 'Learn more about Mailchimp OAuth', 'chimpmatic-lite' ); ?>">
					<?php echo esc_html__( 'Learn More', 'chimpmatic-lite' ); ?>
					<span class="red-icon dashicons dashicons-arrow-right"></span>
					<span class="red-icon dashicons dashicons-arrow-right"></span>
				</a>
			</small>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function render_fresh_form( int $form_id, string $help_url ): void {
		?>
		<div class="cmatic-field-group cmatic-oauth-state">
			<div class="cmatic-mailchimp-connect-intro">
				<h2 id="cmatic-mailchimp-connect-heading"><?php echo esc_html__( 'Connect Mailchimp', 'chimpmatic-lite' ); ?></h2>
				<p><?php echo esc_html__( 'Connect Mailchimp to choose an audience and map its fields.', 'chimpmatic-lite' ); ?></p>
			</div>
			<div class="cmatic-connect-options" aria-label="<?php echo esc_attr__( 'Mailchimp connection options', 'chimpmatic-lite' ); ?>">
				<div class="cmatic-auth-choice-wrap cmatic-oauth-connect-wrap">
					<button type="button" class="cmatic-auth-choice cmatic-auth-choice--recommended cmatic-oauth-connect" data-form-id="<?php echo esc_attr( $form_id ); ?>">
						<span class="cmatic-auth-choice__top">
							<span class="cmatic-auth-choice__icon dashicons dashicons-yes" aria-hidden="true"></span>
							<span class="cmatic-recommended-badge"><?php echo esc_html__( 'Recommended', 'chimpmatic-lite' ); ?></span>
						</span>
						<strong class="cmatic-auth-choice__title"><?php echo esc_html__( 'Sign in with Mailchimp', 'chimpmatic-lite' ); ?></strong>
						<span class="cmatic-auth-choice__description" data-cmatic-oauth-description><?php echo esc_html__( 'The quickest setup. Sign in securely without copying or managing an API key.', 'chimpmatic-lite' ); ?></span>
						<span class="cmatic-oauth-status" role="status" aria-live="polite" hidden></span>
						<span class="cmatic-auth-choice__action">
							<span data-cmatic-oauth-action-label><?php echo esc_html__( 'Continue with Mailchimp', 'chimpmatic-lite' ); ?></span>
							<span aria-hidden="true">&rarr;</span>
						</span>
					</button>
				</div>
				<div class="cmatic-auth-choice-wrap">
					<button type="button" class="cmatic-auth-choice cmatic-show-api-key" aria-expanded="false" aria-controls="cmatic-manual-api-panel">
						<span class="cmatic-auth-choice__top">
							<span class="cmatic-auth-choice__icon" aria-hidden="true">&bull;&bull;&bull;</span>
						</span>
						<strong class="cmatic-auth-choice__title"><?php echo esc_html__( 'Use an API key', 'chimpmatic-lite' ); ?></strong>
						<span class="cmatic-auth-choice__description"><?php echo esc_html__( 'Choose this when you already have a key or someone else manages the Mailchimp account.', 'chimpmatic-lite' ); ?></span>
						<span class="cmatic-auth-choice__action">
							<span data-cmatic-api-action-label><?php echo esc_html__( 'Enter API key', 'chimpmatic-lite' ); ?></span>
							<span aria-hidden="true">&rarr;</span>
						</span>
					</button>
				</div>
			</div>

			<div id="cmatic-manual-api-panel" class="cmatic-manual-api-panel cmatic-hidden">
				<button type="button" class="cmatic-back-auth-options">&larr; <?php echo esc_html__( 'Connection options', 'chimpmatic-lite' ); ?></button>
				<label for="cmatic-api"><?php echo esc_html__( 'Mailchimp API Key:', 'chimpmatic-lite' ); ?></label><br />
				<div class="cmatic-api-wrap">
					<input
						type="password"
						id="cmatic-api"
						name="wpcf7-mailchimp[api]"
						class="wide"
						placeholder="<?php echo esc_attr__( 'Enter Your Mailchimp API key Here', 'chimpmatic-lite' ); ?>"
						value=""
						data-masked-key=""
						data-is-masked="0"
						data-has-key="0"
					/>
					<button type="button" class="cmatic-eye" title="<?php echo esc_attr__( 'Show/Hide', 'chimpmatic-lite' ); ?>">
						<span class="dashicons dashicons-hidden"></span>
					</button>
				</div>
				<input
					id="chm_activalist"
					type="button"
					value="<?php echo esc_attr__( 'Sync Audiences', 'chimpmatic-lite' ); ?>"
					class="button"
					data-form-id="<?php echo esc_attr( $form_id ); ?>"
				/>
				<small class="description need-api">
					<a href="<?php echo esc_url( $help_url ); ?>" class="helping-field" target="_blank" rel="noopener noreferrer" title="<?php echo esc_attr__( 'Get help with Mailchimp API Key', 'chimpmatic-lite' ); ?>">
						<?php echo esc_html__( 'Find your Mailchimp API here', 'chimpmatic-lite' ); ?>
						<span class="red-icon dashicons dashicons-arrow-right"></span>
						<span class="red-icon dashicons dashicons-arrow-right"></span>
					</a>
				</small>
			</div>
		</div>
		<?php
	}

	public static function output( array $cf7_mch, string $apivalid = '0', int $form_id = 0 ): void {
		self::render( $cf7_mch, $apivalid, $form_id );
	}
}
