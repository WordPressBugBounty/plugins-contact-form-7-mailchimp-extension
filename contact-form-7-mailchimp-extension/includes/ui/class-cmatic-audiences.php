<?php
/**
 * Mailchimp audiences panel UI.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

class Cmatic_Audiences {
	public static function render( string $apivalid, ?array $listdata, array $cf7_mch ): void {
		// Handle list value - can be string or array (Pro stores as array).
		$raw_list = isset( $cf7_mch['list'] ) ? $cf7_mch['list'] : '';
		$vlist    = is_array( $raw_list ) ? sanitize_text_field( reset( $raw_list ) ) : sanitize_text_field( $raw_list );
		$count = isset( $listdata['lists'] ) && is_array( $listdata['lists'] ) ? count( $listdata['lists'] ) : 0;

		$help_url = Cmatic_Pursuit::docs( 'how-to-get-your-mailchimp-api-key', 'audiences_help' );

		$disclosure_class = ( '1' === $apivalid ) ? 'chmp-active' : 'chmp-inactive';

		?>
		<div class="cmatic-audiences cmatic-field-group <?php echo esc_attr( $disclosure_class ); ?>">
			<label for="wpcf7-mailchimp-list" id="cmatic-audiences-label">
				<?php
				if ( '1' === $apivalid && $count > 0 ) {
					printf(
						/* translators: %d: Number of Mailchimp audiences */
						esc_html__( 'Total Mailchimp Audiences: %d', 'chimpmatic-lite' ),
						(int) $count
					);
				} else {
					esc_html_e( 'Mailchimp Audiences', 'chimpmatic-lite' );
				}
				?>
			</label><br />

			<select id="wpcf7-mailchimp-list" name="wpcf7-mailchimp[list]">
				<?php self::render_options( $listdata, $vlist ); ?>
			</select>

			<button type="button" id="mce_fetch_fields" class="button">
				<?php esc_html_e( 'Sync Fields', 'chimpmatic-lite' ); ?>
			</button>

			<small class="description">
				<?php esc_html_e( 'Hit the Connect button to load your lists', 'chimpmatic-lite' ); ?>
				<a href="<?php echo esc_url( $help_url ); ?>" class="helping-field" target="_blank" rel="noopener noreferrer" title="<?php esc_attr_e( 'Get help with MailChimp List ID', 'chimpmatic-lite' ); ?>">
					<?php esc_html_e( 'Learn More', 'chimpmatic-lite' ); ?>
				</a>
			</small>
		</div>
		<?php
	}

	public static function render_options( ?array $listdata, string $selected_id = '' ): void {
		if ( ! isset( $listdata['lists'] ) || ! is_array( $listdata['lists'] ) || empty( $listdata['lists'] ) ) {
			return;
		}

		$i = 0;
		foreach ( $listdata['lists'] as $list ) :
			if ( ! is_array( $list ) || ! isset( $list['id'], $list['name'] ) ) {
				continue;
			}
			++$i;
			$list_id      = sanitize_text_field( $list['id'] );
			$list_name    = sanitize_text_field( $list['name'] );
			$member_count = isset( $list['stats']['member_count'] ) ? (int) $list['stats']['member_count'] : 0;
			$field_count  = isset( $list['stats']['merge_field_count'] ) ? (int) $list['stats']['merge_field_count'] : 0;
			$selected     = selected( $selected_id, $list_id, false );
			?>
			<option value="<?php echo esc_attr( $list_id ); ?>" <?php echo $selected; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- selected() returns pre-escaped output ?>>
				<?php
				printf(
					'%d:%d %s  %d fields #%s',
					(int) $i,
					(int) $member_count,
					esc_html( $list_name ),
					(int) $field_count,
					esc_html( $list_id )
				);
				?>
			</option>
			<?php
		endforeach;
	}

	public static function output( string $apivalid, ?array $listdata, array $cf7_mch ): void {
		self::render( $apivalid, $listdata, $cf7_mch );
	}
}
