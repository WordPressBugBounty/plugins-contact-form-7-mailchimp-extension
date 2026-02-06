<?php
/**
 * Accordion panel toggle buttons.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

class Cmatic_Panel_Toggles {
	public static function cmatic_get_default_buttons() {
		return array(
			'advanced_settings' => array(
				'label'         => __( 'Advanced Settings', 'flavor' ),
				'aria_controls' => 'cme-container',
				'extra_class'   => '',
				'priority'      => 10,
			),
			'submission_logs'   => array(
				'label'         => __( 'Submission Logs', 'flavor' ),
				'aria_controls' => 'eventlog-sys',
				'extra_class'   => '',
				'priority'      => 40,
			),
			'form_preview'      => array(
				'label'         => __( 'Form Preview and Test', 'flavor' ),
				'aria_controls' => 'cmatic-test-container',
				'extra_class'   => 'vc-test-submission',
				'priority'      => 50,
			),
		);
	}

	public static function cmatic_get_buttons() {
		$buttons = self::cmatic_get_default_buttons();
		$buttons = apply_filters( 'cmatic_panel_toggle_buttons', $buttons );

		// Sort by priority.
		uasort(
			$buttons,
			function ( $a, $b ) {
				$priority_a = isset( $a['priority'] ) ? $a['priority'] : 50;
				$priority_b = isset( $b['priority'] ) ? $b['priority'] : 50;
				return $priority_a - $priority_b;
			}
		);

		return $buttons;
	}

	public static function cmatic_render_button( $key, $config ) {
		$classes = 'button site-health-view-passed cmatic-accordion-btn';
		if ( ! empty( $config['extra_class'] ) ) {
			$classes .= ' ' . esc_attr( $config['extra_class'] );
		}

		printf(
			'<button type="button" class="%s" aria-expanded="false" aria-controls="%s">%s<span class="icon"></span></button>',
			esc_attr( $classes ),
			esc_attr( $config['aria_controls'] ),
			esc_html( $config['label'] )
		);
	}

	public static function cmatic_render() {
		$buttons = self::cmatic_get_buttons();

		if ( empty( $buttons ) ) {
			return;
		}

		echo '<div class="cmatic-section cmatic-panel-toggles">';

		foreach ( $buttons as $key => $config ) {
			self::cmatic_render_button( $key, $config );
		}

		echo '</div>';
	}
}
