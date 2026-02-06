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
		?>
		<div class="mce-custom-fields <?php echo esc_attr( $disclosure_class ); ?>" id="cmatic-fields">
			<?php
			self::render_merge_fields( $cf7_mch, $form_tags );
			self::render_optin_checkbox( $form_tags, $cf7_mch, $form_id );
			self::render_double_optin( $cf7_mch );
			?>
		</div>
		<?php
		Cmatic_Tags_Preview::render( $form_tags, $cf7_mch, $api_valid );
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
			<small class="description">
				<?php esc_html_e( 'Only subscribe if this checkbox is checked', 'chimpmatic-lite' ); ?>
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
				<?php esc_html_e( 'Choose how subscribers are added to your Mailchimp list', 'chimpmatic-lite' ); ?>
				<a href="<?php echo esc_url( Cmatic_Pursuit::docs( 'mailchimp-double-opt-in', 'double_optin' ) ); ?>" class="helping-field" target="_blank"><?php esc_html_e( 'Learn More', 'chimpmatic-lite' ); ?></a>
			</small>
		</div>
		<?php
	}

	private function __construct() {}
}
