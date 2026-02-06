<?php
/**
 * CF7 form tag utilities.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

final class Cmatic_Form_Tags {

	public static function get_tags_with_types( $contact_form ): array {
		if ( ! $contact_form ) {
			return array();
		}

		$mail_tags = $contact_form->collect_mail_tags();
		$all_tags  = $contact_form->scan_form_tags();
		$result    = array();

		foreach ( $all_tags as $tag ) {
			if ( ! empty( $tag->name ) && in_array( $tag->name, $mail_tags, true ) ) {
				$result[] = array(
					'name'     => $tag->name,
					'basetype' => $tag->basetype,
				);
			}
		}

		return $result;
	}

	private function __construct() {}
}
