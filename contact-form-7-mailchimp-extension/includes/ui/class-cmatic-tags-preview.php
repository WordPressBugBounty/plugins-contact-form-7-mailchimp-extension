<?php
/**
 * Tags preview panel for Pro feature showcase.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

class Cmatic_Tags_Preview {

	private static $type_abbrev = array(
		'checkbox'      => 'CHK',
		'radio'         => 'RAD',
		'select'        => 'SEL',
		'text'          => 'TXT',
		'hidden'        => 'HID',
		'dynamictext'   => 'DYN',
		'dynamichidden' => 'DYN',
		'tel'           => 'TEL',
		'number'        => 'NUM',
	);

	private static $allowed_types = array(
		'checkbox',
		'radio',
		'select',
		'text',
		'hidden',
		'dynamictext',
		'dynamichidden',
		'tel',
		'number',
	);

	public static function render( array $form_tags, array $cf7_mch, int $api_valid ): void {
		if ( empty( $form_tags ) || ! is_array( $form_tags ) ) {
			return;
		}

		$filtered_tags = array_filter(
			$form_tags,
			function ( $tag ) {
				$basetype = is_array( $tag ) ? ( $tag['basetype'] ?? '' ) : ( $tag->basetype ?? '' );
				return in_array( $basetype, self::$allowed_types, true );
			}
		);

		if ( empty( $filtered_tags ) ) {
			return;
		}

		$disclosure_class = ( 1 === $api_valid ) ? 'spt-response-out spt-valid' : 'spt-response-out chmp-inactive';
		$name_list        = self::get_audience_name( $cf7_mch );
		?>
		<div class="<?php echo esc_attr( $disclosure_class ); ?>">
			<div class="mce-custom-fields holder-img">
				<h3 class="title cmatic-title-with-toggle">
					<span>Tags for <span class="audience-name"><?php echo esc_html( $name_list ); ?></span></span>
					<label class="cmatic-toggle-row">
						<span class="cmatic-toggle-label">Sync Tags</span>
						<a href="<?php echo esc_url( Cmatic_Pursuit::upgrade( 'sync_tags_help' ) ); ?>" target="_blank" class="cmatic-help-icon" title="Learn about Sync Tags">?</a>
						<span class="cmatic-toggle">
							<input type="checkbox" data-field="sync_tags" value="1"<?php echo ! empty( $cf7_mch['sync_tags'] ) ? ' checked' : ''; ?>>
							<span class="cmatic-toggle-slider"></span>
						</span>
					</label>
				</h3>
				<p>You can add these as your contacts tags:</p>
				<div id="chm_panel_camposformatags">
					<?php self::render_tag_chips( $filtered_tags, $cf7_mch ); ?>
					<label class="atags"><b>Arbitrary Tags Here:</b> <input type="text" id="wpcf7-mailchimp-labeltags_cm-tag" name="wpcf7-mailchimp[labeltags_cm-tag]" value="<?php echo isset( $cf7_mch['labeltags_cm-tag'] ) ? esc_attr( $cf7_mch['labeltags_cm-tag'] ) : ''; ?>" placeholder="comma, separated, texts, or [mail-tags]">
						<p class="description">You can type in your tags here. Comma separated text or [mail-tags]</p>
					</label>
				</div>
				<a class="lin-to-pro" href="<?php echo esc_url( Cmatic_Pursuit::upgrade( 'tags_link' ) ); ?>" target="_blank" title="ChimpMatic Pro Options"><span>PRO Feature <span>Learn More...</span></span></a>
			</div>
		</div>
		<?php
	}

	private static function render_tag_chips( array $tags, array $cf7_mch ): void {
		echo '<div class="cmatic-tags-grid">';
		$i = 1;
		foreach ( $tags as $tag ) {
			$tag_name     = is_array( $tag ) ? ( $tag['name'] ?? null ) : ( $tag->name ?? null );
			$tag_basetype = is_array( $tag ) ? ( $tag['basetype'] ?? null ) : ( $tag->basetype ?? null );

			if ( empty( $tag_name ) || empty( $tag_basetype ) ) {
				continue;
			}

			$is_checked     = isset( $cf7_mch['labeltags'][ $tag_name ] );
			$type_short     = self::$type_abbrev[ $tag_basetype ] ?? strtoupper( substr( $tag_basetype, 0, 3 ) );
			$selected_class = $is_checked ? ' selected' : '';
			?>
			<label class="cmatic-tag-chip<?php echo esc_attr( $selected_class ); ?>">
				<input type="checkbox" id="wpcf7-mailchimp-labeltags-<?php echo esc_attr( $i ); ?>" name="wpcf7-mailchimp[labeltags][<?php echo esc_attr( trim( $tag_name ) ); ?>]" value="1"<?php echo $is_checked ? ' checked="checked"' : ''; ?> />
				<span class="cmatic-tag-name">[<?php echo esc_html( $tag_name ); ?>]</span>
				<span class="cmatic-tag-type"><?php echo esc_html( $type_short ); ?></span>
			</label>
			<?php
			++$i;
		}
		echo '</div>';
	}

	private static function get_audience_name( array $cf7_mch ): string {
		$arrlist = isset( $cf7_mch['lisdata']['lists'] ) ? array_column( $cf7_mch['lisdata']['lists'], 'name', 'id' ) : array();
		$idlist  = '';

		if ( isset( $cf7_mch['list'] ) ) {
			if ( is_array( $cf7_mch['list'] ) ) {
				$idlist = reset( $cf7_mch['list'] );
				if ( false === $idlist ) {
					$idlist = '';
				}
			} else {
				$idlist = $cf7_mch['list'];
			}
		}

		return ( ! empty( $idlist ) && isset( $arrlist[ $idlist ] ) ) ? $arrlist[ $idlist ] : '';
	}

	private function __construct() {}
}
