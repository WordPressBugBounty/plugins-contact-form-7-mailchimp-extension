<?php
/**
 * CF7 form tag utilities.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

final class Cmatic_Form_Tags {

	public static function get_tags_with_types( $contact_form ): array {
		if ( ! $contact_form ) {
			return array();
		}

		$all_tags = $contact_form->scan_form_tags();
		$result   = array();

		foreach ( $all_tags as $tag ) {
			if ( is_object( $tag ) && ! empty( $tag->name ) ) {
				/** @var WPCF7_FormTag $tag */
				$basetype = sanitize_key( (string) $tag->basetype );
				$item     = array(
					'name'             => sanitize_key( (string) $tag->name ),
					'basetype'         => $basetype,
					'routing_eligible' => false,
					'choices'          => array(),
					'required'         => 'acceptance' === $basetype && ! $tag->has_option( 'optional' ),
					'inverted'         => 'acceptance' === $basetype && $tag->has_option( 'invert' ),
					'content'          => sanitize_text_field( trim( (string) ( $tag->content ? $tag->content : reset( $tag->values ) ) ) ),
				);

				$dynamic = (bool) preg_grep( '/^data:/i', (array) $tag->options );
				if ( in_array( $basetype, array( 'checkbox', 'radio', 'select' ), true ) && ! $tag->has_option( 'free_text' ) && ! $dynamic ) {
					$item['routing_eligible'] = true;
					foreach ( (array) $tag->values as $index => $value ) {
						$canonical         = $tag->pipes instanceof WPCF7_Pipes ? $tag->pipes->do_pipe( (string) $value ) : (string) $value;
						$item['choices'][] = array(
							'label' => sanitize_text_field( (string) ( $tag->labels[ $index ] ?? $value ) ),
							'value' => sanitize_text_field( (string) $canonical ),
						);
					}
				}
				$result[] = $item;
			}
		}

		return $result;
	}

	private function __construct() {}
}
