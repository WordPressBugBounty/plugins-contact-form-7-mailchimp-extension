<?php
/**
 * Features data collector.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

namespace Cmatic\Metrics\Core\Collectors;

use Cmatic_Options_Repository;

defined( 'ABSPATH' ) || exit;

class Features_Collector {
	public static function collect(): array {
		$features = array(
			'double_optin'         => 0,
			'required_consent'     => 0,
			'debug_logger'         => 0,
			'custom_merge_fields'  => 0,
			'interest_groups'      => 0,
			'groups_total_mapped'  => 0,
			'tags_enabled'         => 0,
			'tags_total_selected'  => 0,
			'arbitrary_tags'       => 0,
			'conditional_logic'    => 0,
			'auto_update'          => (bool) Cmatic_Options_Repository::get_option( 'auto_update', true ),
			'telemetry_enabled'    => true,
			'debug'                => (bool) Cmatic_Options_Repository::get_option( 'debug', false ),
			'backlink'             => (bool) Cmatic_Options_Repository::get_option( 'backlink', false ),
		);

		$cf7_forms = Forms_Collector::get_cf7_forms();

		$form_ids     = array_map( fn( $form ) => $form->id(), $cf7_forms );
		$form_options = Forms_Collector::batch_load_form_options( $form_ids );

		foreach ( $cf7_forms as $form ) {
			$form_id = $form->id();
			$cf7_mch = $form_options[ $form_id ]['settings'] ?? array();

			$features = self::check_double_optin( $cf7_mch, $features );
			$features = self::check_required_consent( $cf7_mch, $features );
			$features = self::check_debug_logger( $cf7_mch, $features );
			$features = self::check_custom_merge_fields( $cf7_mch, $features );
			$features = self::check_interest_groups( $cf7_mch, $features );
			$features = self::check_tags( $cf7_mch, $features );
			$features = self::check_conditional_logic( $cf7_mch, $features );
		}

		return self::build_data( $features );
	}

	private static function check_double_optin( array $cf7_mch, array $features ): array {
		if ( isset( $cf7_mch['confsubs'] ) && '1' === $cf7_mch['confsubs'] ) {
			++$features['double_optin'];
		}
		return $features;
	}

	private static function check_required_consent( array $cf7_mch, array $features ): array {
		if ( ! empty( $cf7_mch['consent_required'] ) && ' ' !== $cf7_mch['consent_required'] ) {
			++$features['required_consent'];
		}
		return $features;
	}

	private static function check_debug_logger( array $cf7_mch, array $features ): array {
		if ( isset( $cf7_mch['logfileEnabled'] ) && '1' === $cf7_mch['logfileEnabled'] ) {
			++$features['debug_logger'];
		}
		return $features;
	}

	private static function check_custom_merge_fields( array $cf7_mch, array $features ): array {
		$merge_fields_raw = array();
		if ( ! empty( $cf7_mch['merge_fields'] ) && is_array( $cf7_mch['merge_fields'] ) ) {
			$merge_fields_raw = $cf7_mch['merge_fields'];
		} elseif ( ! empty( $cf7_mch['merge-vars'] ) && is_array( $cf7_mch['merge-vars'] ) ) {
			$merge_fields_raw = $cf7_mch['merge-vars'];
		}

		if ( ! empty( $merge_fields_raw ) ) {
			$default_tags = array( 'EMAIL', 'FNAME', 'LNAME', 'ADDRESS', 'PHONE' );
			foreach ( $merge_fields_raw as $field ) {
				if ( isset( $field['tag'] ) && ! in_array( $field['tag'], $default_tags, true ) ) {
					++$features['custom_merge_fields'];
				}
			}
		}

		return $features;
	}

	private static function check_interest_groups( array $cf7_mch, array $features ): array {
		$group_count = 0;
		for ( $i = 1; $i <= 20; $i++ ) {
			$key   = $cf7_mch[ "ggCustomKey{$i}" ] ?? '';
			$value = $cf7_mch[ "ggCustomValue{$i}" ] ?? '';
			if ( ! empty( $key ) && ! empty( trim( $value ) ) && ' ' !== $value ) {
				++$group_count;
			}
		}
		if ( $group_count > 0 ) {
			++$features['interest_groups'];
			$features['groups_total_mapped'] += $group_count;
		}
		return $features;
	}

	private static function check_tags( array $cf7_mch, array $features ): array {
		if ( ! empty( $cf7_mch['labeltags'] ) && is_array( $cf7_mch['labeltags'] ) ) {
			$enabled_tags = array_filter( $cf7_mch['labeltags'], fn( $v ) => '1' === $v );
			if ( count( $enabled_tags ) > 0 ) {
				++$features['tags_enabled'];
				$features['tags_total_selected'] += count( $enabled_tags );
			}
		}

		if ( ! empty( $cf7_mch['labeltags_cm-tag'] ) && trim( $cf7_mch['labeltags_cm-tag'] ) !== '' ) {
			++$features['arbitrary_tags'];
		}

		return $features;
	}

	private static function check_conditional_logic( array $cf7_mch, array $features ): array {
		if ( ! empty( $cf7_mch['conditional_logic'] ) ) {
			++$features['conditional_logic'];
		}
		return $features;
	}

	private static function build_data( array $features ): array {
		$data = array(
			'double_optin_count'        => $features['double_optin'],
			'required_consent_count'    => $features['required_consent'],
			'debug_logger_count'        => $features['debug_logger'],
			'custom_merge_fields_count' => $features['custom_merge_fields'],
			'interest_groups_count'     => $features['interest_groups'],
			'groups_total_mapped'       => $features['groups_total_mapped'],
			'tags_enabled_count'        => $features['tags_enabled'],
			'tags_total_selected'       => $features['tags_total_selected'],
			'arbitrary_tags_count'      => $features['arbitrary_tags'],
			'conditional_logic_count'   => $features['conditional_logic'],
			'double_optin'              => $features['double_optin'] > 0,
			'required_consent'          => $features['required_consent'] > 0,
			'debug_logger'              => $features['debug_logger'] > 0,
			'custom_merge_fields'       => $features['custom_merge_fields'] > 0,
			'interest_groups'           => $features['interest_groups'] > 0,
			'tags_enabled'              => $features['tags_enabled'] > 0,
			'arbitrary_tags'            => $features['arbitrary_tags'] > 0,
			'conditional_logic'         => $features['conditional_logic'] > 0,
			'auto_update'               => $features['auto_update'],
			'telemetry_enabled'         => $features['telemetry_enabled'],
			'debug'                     => $features['debug'],
			'backlink'                  => $features['backlink'],
			'total_features_enabled'    => count( array_filter( $features ) ),
			'features_usage_percentage' => round( ( count( array_filter( $features ) ) / count( $features ) ) * 100, 2 ),
			'webhook_enabled'           => (bool) Cmatic_Options_Repository::get_option( 'features.webhook_enabled', false ),
			'custom_api_endpoint'       => (bool) Cmatic_Options_Repository::get_option( 'features.custom_api_endpoint', false ),
			'email_notifications'       => (bool) Cmatic_Options_Repository::get_option( 'features.email_notifications', false ),
			'test_modal_used'           => (bool) Cmatic_Options_Repository::get_option( 'features.test_modal_used', false ),
			'contact_lookup_used'       => (bool) Cmatic_Options_Repository::get_option( 'features.contact_lookup_used', false ),
		);

		return array_filter( $data, fn( $v ) => $v !== 0 && $v !== false && $v !== '' );
	}
}
