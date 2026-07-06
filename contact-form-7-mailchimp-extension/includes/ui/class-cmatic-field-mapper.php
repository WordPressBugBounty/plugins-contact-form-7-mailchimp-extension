<?php
/**
 * Field mapping UI components.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

final class Cmatic_Field_Mapper_UI {
	public static function render( int $api_valid, ?array $list_data, array $cf7_mch, array $form_tags, int $form_id ): void {
		$disclosure_class = ( 1 === $api_valid ) ? 'chmp-active' : 'chmp-inactive';

		$total_merge   = isset( $cf7_mch['total_merge_fields'] ) ? (int) $cf7_mch['total_merge_fields'] : 0;
		$show_notice   = $total_merge > CMATIC_LITE_FIELDS;
		$notice_class  = $show_notice ? 'cmatic-visible' : 'cmatic-hidden';
		$audience_name = self::resolve_audience_name( $cf7_mch );
		$docs_url      = Cmatic_Pursuit::url( 'https://chimpmatic.com/mailchimp-default-audience-fields-explained', 'plugin', 'fields_notice', 'docs' );
		// Limit-hit is the highest-intent upsell moment in the plugin (the user is
		// looking at fields they cannot map): pair the docs link with a buy CTA that
		// carries the install source + coupon. Discount comes from the live promo
		// endpoint (day-cached), never hardcoded, so server-side price changes
		// propagate here.
		$cmatic_pricing  = Cmatic_Pursuit::pricing();
		$cmatic_discount = (int) ( $cmatic_pricing['discount_percent'] ?? 0 );
		$upgrade_url     = Cmatic_Pursuit::promo_checkout( 'field_limit_notice' );
		$cmatic_unlock_label = $cmatic_discount > 0
			/* translators: %d: current discount percentage from the live promo */
			? sprintf( __( 'Unlock all fields with Pro: %d%% off', 'chimpmatic-lite' ), $cmatic_discount )
			: __( 'Unlock all fields with Pro', 'chimpmatic-lite' );
		?>
		<div class="mce-custom-fields <?php echo esc_attr( $disclosure_class ); ?>" id="cmatic-fields">
			<?php
			// Stale-cache guard: the mapper renders one row per CACHED merge field,
			// so a rotted cache silently drops mapping rows (and syncs empty values)
			// with no error anywhere. If the cache holds fewer fields than the
			// audience supports (up to the Lite cap), say so and point at the fix.
			$cmatic_cached_fields = is_array( $cf7_mch['merge_fields'] ?? null ) ? count( $cf7_mch['merge_fields'] ) : 0;
			$cmatic_expected      = min( $total_merge, CMATIC_LITE_FIELDS );
			if ( $total_merge > 0 && $cmatic_cached_fields < $cmatic_expected ) :
				?>
				<div class="cmatic-defaults-fields-notice cmatic-visible" id="cmatic-stale-cache-notice">
					<p class="cmatic-notice">
						<?php
						printf(
							/* translators: 1: cached merge-field count, 2: audience merge-field count */
							esc_html__( 'Your audience field list looks out of date (%1$d cached, audience reports %2$d). Click Sync Fields above to refresh your mapping rows.', 'chimpmatic-lite' ),
							(int) $cmatic_cached_fields,
							(int) $total_merge
						);
						?>
					</p>
				</div>
				<?php
			endif;
			self::render_merge_fields( $cf7_mch, $form_tags );
			self::render_optin_checkbox( $form_tags, $cf7_mch, $form_id );
			self::render_double_optin( $cf7_mch );
			?>
			<div class="cmatic-defaults-fields-notice <?php echo esc_attr( $notice_class ); ?>" id="cmatic-fields-notice">
				<p class="cmatic-notice">
					<?php if ( $show_notice ) : ?>
						<?php
						$notice_text = sprintf(
							/* translators: 1: audience name wrapped in <strong>, 2: total merge fields count, 3: lite fields limit */
							__( 'Your %1$s audience has %2$d merge fields. Chimpmatic Lite supports up to %3$d field mappings.', 'chimpmatic-lite' ),
							'<strong>' . esc_html( $audience_name ) . '</strong>',
							$total_merge,
							CMATIC_LITE_FIELDS
						);
						echo wp_kses( $notice_text, array( 'strong' => array() ) );
						?>
					<?php endif; ?>
					<?php // The links render even while the notice is hidden: the JS
					// rebuild harvests them from the DOM, and an empty container left
					// the rebuilt banner linkless right after an OAuth connect. ?>
					<a href="<?php echo esc_url( $docs_url ); ?>" class="helping-field" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Read More', 'chimpmatic-lite' ); ?>
					</a>
					<a href="<?php echo esc_url( $upgrade_url ); ?>" class="helping-field cmatic-unlock-fields" target="_blank" rel="noopener noreferrer">
						<?php echo esc_html( $cmatic_unlock_label ); ?>
					</a>
				</p>
			</div>
		</div>
		<?php
		Cmatic_Tags_Preview::render( $form_tags, $cf7_mch, $api_valid );
		Cmatic_Pro_Showcase::render( $api_valid );
	}

	private static function render_merge_fields( array $cf7_mch, array $form_tags ): void {
		$merge_fields = $cf7_mch['merge_fields'] ?? array();
		$max_fields   = CMATIC_LITE_FIELDS;

		for ( $i = 0; $i < $max_fields; $i++ ) {
			$field_index = $i + 3;
			$field_key   = 'field' . $field_index;
			$merge_field = $merge_fields[ $i ] ?? null;

			$field_config = self::build_field_config( $merge_field, $field_index );
			self::render_field_row( $field_key, $form_tags, $cf7_mch, $field_config );
		}
	}

	private static function build_field_config( ?array $merge_field, int $field_index ): array {
		if ( null === $merge_field ) {
			return array(
				'label'       => 'Field ' . $field_index,
				'type'        => 'text',
				'required'    => false,
				'description' => 'Map a form field to Mailchimp',
				'merge_tag'   => '',
			);
		}

		$tag  = $merge_field['tag'] ?? '';
		$name = $merge_field['name'] ?? $tag;
		$type = $merge_field['type'] ?? 'text';

		$config = array(
			'label'       => $name . ' - *|' . $tag . '|* <span class="mce-type">' . esc_html( $type ) . '</span>',
			'type'        => 'text',
			'required'    => false,
			'description' => 'Map a form field to Mailchimp',
			'merge_tag'   => $tag,
		);

		if ( 'EMAIL' === $tag ) {
			$config['required']    = true;
			$config['type']        = 'email';
			$config['description'] = 'MUST be an email tag <a href="' . esc_url( Cmatic_Pursuit::docs( 'mailchimp-required-email', 'email_field' ) ) . '" class="helping-field" target="_blank" title="get help with Subscriber Email:"> Learn More</a>';
		}

		return $config;
	}

	private static function render_field_row( string $field_key, array $form_tags, array $cf7_mch, array $config ): void {
		?>
		<div class="mcee-container">
			<label for="wpcf7-mailchimp-<?php echo esc_attr( $field_key ); ?>">
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is pre-escaped in build_field_config.
				echo $config['label'];
				?>
				<?php if ( $config['required'] ) : ?>
					<span class="mce-required">Required</span>
				<?php endif; ?>
			</label>
			<?php self::render_dropdown( $field_key, $form_tags, $cf7_mch, $config['type'], $config['merge_tag'] ); ?>
		</div>
		<?php
	}

	public static function render_dropdown( string $field_name, array $form_tags, array $cf7_mch, string $filter = '', string $merge_tag = '' ): void {
		$field_name  = sanitize_key( $field_name );
		$saved_value = isset( $cf7_mch[ $field_name ] ) ? trim( sanitize_text_field( $cf7_mch[ $field_name ] ) ) : '';

		if ( '' === $saved_value && ! empty( $merge_tag ) ) {
			$saved_value = self::auto_match_field( $form_tags, $merge_tag, $filter );
		}
		?>
		<select class="chm-select" id="wpcf7-mailchimp-<?php echo esc_attr( $field_name ); ?>" name="wpcf7-mailchimp[<?php echo esc_attr( $field_name ); ?>]">
			<?php if ( 'email' !== $filter ) : ?>
				<option value="" <?php selected( $saved_value, '' ); ?>>
					<?php esc_html_e( 'Choose..', 'chimpmatic-lite' ); ?>
				</option>
			<?php endif; ?>

			<?php foreach ( $form_tags as $tag ) : ?>
				<?php
				if ( 'opt-in' === $tag['name'] ) {
					continue;
				}
				if ( 'email' === $filter ) {
					$is_email = ( 'email' === $tag['basetype'] || false !== strpos( strtolower( $tag['name'] ), 'email' ) );
					if ( ! $is_email ) {
						continue;
					}
				}
				$tag_value = '[' . $tag['name'] . ']';
				?>
				<option value="<?php echo esc_attr( $tag_value ); ?>" <?php selected( $saved_value, $tag_value ); ?>>
					<?php echo esc_html( $tag_value ); ?> - type: <?php echo esc_html( $tag['basetype'] ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	private static function auto_match_field( array $form_tags, string $merge_tag, string $filter ): string {
		$merge_tag_lower = strtolower( $merge_tag );

		foreach ( $form_tags as $tag ) {
			if ( 'email' === $filter ) {
				if ( 'email' === $tag['basetype'] || false !== strpos( strtolower( $tag['name'] ), 'email' ) ) {
					return '[' . $tag['name'] . ']';
				}
			} elseif ( false !== strpos( strtolower( $tag['name'] ), $merge_tag_lower ) ) {
				return '[' . $tag['name'] . ']';
			}
		}

		return '';
	}

	private static function render_optin_checkbox( array $form_tags, array $cf7_mch, int $form_id ): void {
		$checkbox_types  = array( 'checkbox', 'acceptance' );
		$checkbox_fields = array_filter(
			$form_tags,
			function ( $field ) use ( $checkbox_types ) {
				return in_array( $field['basetype'], $checkbox_types, true );
			}
		);
		?>
		<div class="mcee-container">
			<label for="wpcf7-mailchimp-accept">
				<?php esc_html_e( 'Opt-in Checkbox', 'chimpmatic-lite' ); ?>
				<span class="mce-type"><?php esc_html_e( 'Optional', 'chimpmatic-lite' ); ?></span>
			</label>
			<select class="chm-select" id="wpcf7-mailchimp-accept" name="wpcf7-mailchimp[accept]">
				<option value=" " <?php selected( $cf7_mch['accept'] ?? ' ', ' ' ); ?>>
					<?php esc_html_e( 'None - Always subscribe', 'chimpmatic-lite' ); ?>
				</option>
				<?php if ( empty( $checkbox_fields ) ) : ?>
					<option value="" disabled>
						<?php
						$form_title = '';
						if ( function_exists( 'wpcf7_contact_form' ) ) {
							$form_obj   = wpcf7_contact_form( $form_id );
							$form_title = $form_obj ? $form_obj->title() : '';
						}
						printf(
							/* translators: %s: Form title */
							esc_html__( '"%s" has no [checkbox] or [acceptance] fields', 'chimpmatic-lite' ),
							esc_html( $form_title )
						);
						?>
					</option>
				<?php else : ?>
					<?php foreach ( $checkbox_fields as $field ) : ?>
						<?php
						$field_value = '[' . $field['name'] . ']';
						$saved_value = $cf7_mch['accept'] ?? ' ';
						?>
						<option value="<?php echo esc_attr( $field_value ); ?>" <?php selected( $saved_value, $field_value ); ?>>
							<?php echo esc_html( '[' . $field['name'] . ']' ); ?> - type: <?php echo esc_html( $field['basetype'] ); ?>
						</option>
					<?php endforeach; ?>
				<?php endif; ?>
			</select>
			<?php
			// State-aware caption: must match the current selection ('None -
			// Always subscribe' has no checkbox). JS flips it live on change.
			$cmatic_accept_saved  = trim( (string) ( $cf7_mch['accept'] ?? '' ) );
			$cmatic_optin_always  = __( 'Every valid submission is subscribed. Pick a checkbox field to require consent first.', 'chimpmatic-lite' );
			$cmatic_optin_gated   = __( 'Only subscribe if this checkbox is checked', 'chimpmatic-lite' );
			?>
			<small class="description" id="cmatic-optin-helper" data-always="<?php echo esc_attr( $cmatic_optin_always ); ?>" data-gated="<?php echo esc_attr( $cmatic_optin_gated ); ?>">
				<span class="cmatic-optin-helper-text"><?php echo esc_html( '' === $cmatic_accept_saved ? $cmatic_optin_always : $cmatic_optin_gated ); ?></span>
				<a href="<?php echo esc_url( Cmatic_Pursuit::docs( 'mailchimp-opt-in-checkbox', 'optin_field' ) ); ?>" class="helping-field" target="_blank"><?php esc_html_e( 'Learn More', 'chimpmatic-lite' ); ?></a>
			</small>
		</div>
		<?php
	}

	private static function render_double_optin( array $cf7_mch ): void {
		$value = ! empty( $cf7_mch['double_optin'] ) || ! empty( $cf7_mch['confsubs'] ) ? '1' : '0';
		?>
		<div class="mcee-container">
			<label for="wpcf7-mailchimp-double-optin">
				<?php esc_html_e( 'Double Opt-in', 'chimpmatic-lite' ); ?>
				<span class="mce-type"><?php esc_html_e( 'Optional', 'chimpmatic-lite' ); ?></span>
			</label>
			<select class="chm-select" id="wpcf7-mailchimp-double-optin" name="wpcf7-mailchimp[confsubs]" data-field="double_optin">
				<option value="0" <?php selected( $value, '0' ); ?>>
					<?php esc_html_e( 'Subscribers are added immediately', 'chimpmatic-lite' ); ?>
				</option>
				<option value="1" <?php selected( $value, '1' ); ?>>
					<?php esc_html_e( 'Subscribers must confirm via email', 'chimpmatic-lite' ); ?>
				</option>
			</select>
			<small class="description">
				<?php esc_html_e( 'Choose how subscribers are added to your Mailchimp audience', 'chimpmatic-lite' ); ?>
				<a href="<?php echo esc_url( Cmatic_Pursuit::docs( 'mailchimp-double-opt-in', 'double_optin' ) ); ?>" class="helping-field" target="_blank"><?php esc_html_e( 'Learn More', 'chimpmatic-lite' ); ?></a>
			</small>
		</div>
		<?php
	}

	private static function resolve_audience_name( array $cf7_mch ): string {
		$list_id = $cf7_mch['list'] ?? '';
		$lists   = $cf7_mch['lisdata']['lists'] ?? array();

		foreach ( $lists as $list ) {
			if ( isset( $list['id'], $list['name'] ) && $list['id'] === $list_id ) {
				return $list['name'];
			}
		}

		return '';
	}

	private function __construct() {}
}
