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

	public static function render( array $cf7_mch, string $apivalid = '0' ): void {
		$api_key    = isset( $cf7_mch['api'] ) ? $cf7_mch['api'] : '';
		$masked_key = self::mask_key( $api_key );
		$is_masked  = ! empty( $api_key ) && strlen( $api_key ) >= 12;
		$is_valid   = '1' === $apivalid;

		$btn_value = $is_valid ? 'Connected' : 'Sync Audiences';
		$btn_class = 'button';

		$help_url = Cmatic_Pursuit::docs( 'how-to-get-your-mailchimp-api-key', 'api_panel_help' );

		?>
		<div class="cmatic-field-group">
		<label for="cmatic-api"><?php echo esc_html__( 'MailChimp API Key:', 'chimpmatic-lite' ); ?></label><br />
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
				data-has-key="<?php echo ! empty( $api_key ) ? '1' : '0'; ?>"
			/>
			<button type="button" class="cmatic-eye" title="<?php echo esc_attr__( 'Show/Hide', 'chimpmatic-lite' ); ?>">
				<span class="dashicons <?php echo $is_masked ? 'dashicons-visibility' : 'dashicons-hidden'; ?>"></span>
			</button>
		</div>

		<input
			id="chm_activalist"
			type="button"
			value="<?php echo esc_attr( $btn_value ); ?>"
			class="<?php echo esc_attr( $btn_class ); ?>"
		/>

		<small class="description need-api">
			<a href="<?php echo esc_url( $help_url ); ?>" class="helping-field" target="_blank" rel="noopener noreferrer" title="<?php echo esc_attr__( 'Get help with MailChimp API Key', 'chimpmatic-lite' ); ?>">
				<?php echo esc_html__( 'Find your Mailchimp API here', 'chimpmatic-lite' ); ?>
				<span class="red-icon dashicons dashicons-arrow-right"></span>
				<span class="red-icon dashicons dashicons-arrow-right"></span>
			</a>
		</small>
		</div><!-- .cmatic-field-group -->
		<?php
	}

	public static function output( array $cf7_mch, string $apivalid = '0' ): void {
		self::render( $cf7_mch, $apivalid );
	}
}
