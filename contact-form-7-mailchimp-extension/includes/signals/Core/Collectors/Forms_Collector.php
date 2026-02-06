<?php
/**
 * Forms data collector.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

namespace Cmatic\Metrics\Core\Collectors;

use Cmatic_Options_Repository;

defined( 'ABSPATH' ) || exit;

class Forms_Collector {
	private const MAX_FORMS = 100;

	public static function collect(): array {
		$cf7_forms         = self::get_cf7_forms();
		$processed_forms   = count( $cf7_forms );
		$total_forms       = self::get_total_form_count();
		$forms_truncated   = $total_forms > self::MAX_FORMS;
		$active_forms      = 0;
		$forms_with_api    = 0;
		$forms_with_lists  = 0;
		$total_lists       = 0;
		$total_fields      = 0;
		$unique_audiences  = array();
		$forms_data        = array();
		$paired_lists      = array();

		$forms_detail      = array();
		$field_type_counts = array();
		$total_mappings    = 0;
		$total_mc_fields   = 0;

		$unique_audiences = self::build_audiences_from_global();

		$form_ids     = array_map( fn( $form ) => $form->id(), $cf7_forms );
		$form_options = self::batch_load_form_options( $form_ids );

		foreach ( $cf7_forms as $form ) {
			$form_id = $form->id();
			$cf7_mch = $form_options[ $form_id ]['settings'] ?? array();

			if ( ! empty( $cf7_mch['api'] ) ) {
				++$forms_with_api;
				++$active_forms;
			}

			$selected_list = self::get_selected_list( $cf7_mch );

			if ( ! empty( $selected_list ) && isset( $unique_audiences[ $selected_list ] ) ) {
				$unique_audiences[ $selected_list ]['is_paired'] = true;
				$paired_lists[ $selected_list ]                  = true;
			}

			$audience_count = 0;
			$has_list       = false;
			if ( isset( $cf7_mch['lisdata']['lists'] ) && is_array( $cf7_mch['lisdata']['lists'] ) ) {
				$audience_count = count( $cf7_mch['lisdata']['lists'] );
				$has_list       = $audience_count > 0;
				$total_lists   += $audience_count;
				if ( $has_list ) {
					++$forms_with_lists;
				}
			}

			$form_fields   = $form->scan_form_tags();
			$total_fields += count( $form_fields );

			$form_field_details = self::extract_field_details( $form_fields, $field_type_counts );

			list( $form_mappings, $unmapped_cf7, $unmapped_mc, $mapped_cf7_fields, $form_mc_fields, $form_total_mappings ) =
				self::extract_mappings( $cf7_mch, $form_field_details );

			$total_mc_fields += $form_mc_fields;
			$total_mappings  += $form_total_mappings;

			$form_features = self::extract_form_features( $cf7_mch );

			if ( count( $forms_detail ) < 50 ) {
				$forms_detail[] = array(
					'form_id'             => hash( 'sha256', (string) $form_id ),
					'field_count'         => count( $form_field_details ),
					'fields'              => $form_field_details,
					'paired_audience_id'  => ! empty( $selected_list ) ? hash( 'sha256', $selected_list ) : null,
					'mappings'            => $form_mappings,
					'unmapped_cf7_fields' => $unmapped_cf7,
					'unmapped_mc_fields'  => $unmapped_mc,
					'features'            => $form_features,
				);
			}

			$forms_data[] = array(
				'form_id'         => hash( 'sha256', (string) $form_id ),
				'has_api'         => ! empty( $cf7_mch['api'] ),
				'has_list'        => $has_list,
				'audience_count'  => $audience_count,
				'field_count'     => count( $form_fields ),
				'has_double_opt'  => isset( $cf7_mch['confsubs'] ) && '1' === $cf7_mch['confsubs'],
				'has_consent'     => ! empty( $cf7_mch['accept'] ) && ' ' !== $cf7_mch['accept'],
				'submissions'     => (int) ( $form_options[ $form_id ]['submissions'] ?? 0 ),
				'last_submission' => (int) ( $form_options[ $form_id ]['last_submission'] ?? 0 ),
			);
		}

		$avg_fields_per_form = $processed_forms > 0 ? round( $total_fields / $processed_forms, 2 ) : 0;
		$avg_lists_per_form  = $forms_with_lists > 0 ? round( $total_lists / $forms_with_lists, 2 ) : 0;

		list( $oldest_form, $newest_form ) = self::get_form_age_range( $cf7_forms );

		$audience_data          = array_values( $unique_audiences );
		$total_unique_audiences = count( $audience_data );
		$total_contacts         = array_sum( array_column( $audience_data, 'member_count' ) );

		return array(
			'total_forms'                 => $total_forms,
			'processed_forms'             => $processed_forms,
			'active_forms'                => $active_forms,
			'forms_with_api'              => $forms_with_api,
			'forms_with_lists'            => $forms_with_lists,
			'inactive_forms'              => $processed_forms - $active_forms,
			'total_audiences'             => $total_unique_audiences,
			'audiences'                   => $audience_data,
			'total_contacts'              => $total_contacts,
			'avg_lists_per_form'          => $avg_lists_per_form,
			'max_lists_per_form'          => $total_lists > 0 ? max( array_column( $forms_data, 'audience_count' ) ) : 0,
			'total_fields_all_forms'      => $total_fields,
			'avg_fields_per_form'         => $avg_fields_per_form,
			'min_fields_per_form'         => $processed_forms > 0 ? min( array_column( $forms_data, 'field_count' ) ) : 0,
			'max_fields_per_form'         => $processed_forms > 0 ? max( array_column( $forms_data, 'field_count' ) ) : 0,
			'oldest_form_created'         => $oldest_form,
			'newest_form_created'         => $newest_form,
			'days_since_oldest_form'      => $oldest_form > 0 ? floor( ( time() - $oldest_form ) / DAY_IN_SECONDS ) : 0,
			'days_since_newest_form'      => $newest_form > 0 ? floor( ( time() - $newest_form ) / DAY_IN_SECONDS ) : 0,
			'forms_with_submissions'      => count( array_filter( $forms_data, fn( $f ) => $f['submissions'] > 0 ) ),
			'forms_never_submitted'       => count( array_filter( $forms_data, fn( $f ) => 0 === $f['submissions'] ) ),
			'forms_with_double_opt'       => count( array_filter( $forms_data, fn( $f ) => $f['has_double_opt'] ) ),
			'forms_with_consent'          => count( array_filter( $forms_data, fn( $f ) => $f['has_consent'] ) ),
			'total_submissions_all_forms' => array_sum( array_column( $forms_data, 'submissions' ) ),
			'form_utilization_rate'       => $processed_forms > 0 ? round( ( $active_forms / $processed_forms ) * 100, 2 ) : 0,
			'forms_detail'                => $forms_detail,
			'forms_truncated'             => $forms_truncated,
			'forms_detail_truncated'      => $processed_forms > 50,
			'field_types_aggregate'       => $field_type_counts,
			'mapping_stats'               => array(
				'total_cf7_fields' => $total_fields,
				'total_mc_fields'  => $total_mc_fields,
				'mapped_fields'    => $total_mappings,
				'mapping_rate'     => $total_fields > 0 ? round( ( $total_mappings / $total_fields ) * 100, 2 ) : 0,
			),
		);
	}

	public static function get_cf7_forms(): array {
		if ( ! class_exists( 'WPCF7_ContactForm' ) ) {
			return array();
		}

		$post_ids = get_posts(
			array(
				'post_type'      => 'wpcf7_contact_form',
				'posts_per_page' => self::MAX_FORMS,
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);

		$forms = array();
		foreach ( $post_ids as $post_id ) {
			$form = \WPCF7_ContactForm::get_instance( $post_id );
			if ( $form ) {
				$forms[] = $form;
			}
		}

		return $forms;
	}

	public static function get_total_form_count(): int {
		if ( ! class_exists( 'WPCF7_ContactForm' ) ) {
			return 0;
		}

		global $wpdb;

		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'wpcf7_contact_form' AND post_status = 'publish'"
		);
	}

	public static function batch_load_form_options( array $form_ids ): array {
		global $wpdb;

		if ( empty( $form_ids ) ) {
			return array();
		}

		$option_names = array();
		foreach ( $form_ids as $form_id ) {
			$option_names[] = 'cf7_mch_' . $form_id;
			$option_names[] = 'cf7_mch_submissions_' . $form_id;
			$option_names[] = 'cf7_mch_last_submission_' . $form_id;
		}

		$placeholders = implode( ', ', array_fill( 0, count( $option_names ), '%s' ) );
		$query        = $wpdb->prepare(
			"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name IN ({$placeholders})",
			...$option_names
		);

		$results = $wpdb->get_results( $query, ARRAY_A );

		$options_map = array();
		foreach ( $results as $row ) {
			$options_map[ $row['option_name'] ] = maybe_unserialize( $row['option_value'] );
		}

		$form_options = array();
		foreach ( $form_ids as $form_id ) {
			$form_options[ $form_id ] = array(
				'settings'        => $options_map[ 'cf7_mch_' . $form_id ] ?? array(),
				'submissions'     => $options_map[ 'cf7_mch_submissions_' . $form_id ] ?? 0,
				'last_submission' => $options_map[ 'cf7_mch_last_submission_' . $form_id ] ?? 0,
			);
		}

		return $form_options;
	}

	private static function build_audiences_from_global(): array {
		$unique_audiences = array();
		$global_lisdata   = Cmatic_Options_Repository::get_option( 'lisdata', array() );

		if ( ! empty( $global_lisdata['lists'] ) && is_array( $global_lisdata['lists'] ) ) {
			foreach ( $global_lisdata['lists'] as $list ) {
				$list_id = $list['id'] ?? '';
				if ( empty( $list_id ) ) {
					continue;
				}

				$unique_audiences[ $list_id ] = array(
					'audience_id'           => hash( 'sha256', $list_id ),
					'member_count'          => (int) ( $list['stats']['member_count'] ?? 0 ),
					'merge_field_count'     => (int) ( $list['stats']['merge_field_count'] ?? 0 ),
					'double_optin'          => ! empty( $list['double_optin'] ),
					'marketing_permissions' => ! empty( $list['marketing_permissions'] ),
					'campaign_count'        => (int) ( $list['stats']['campaign_count'] ?? 0 ),
					'is_paired'             => false,
				);
			}
		}

		return $unique_audiences;
	}

	private static function get_selected_list( array $cf7_mch ): string {
		$selected_list = $cf7_mch['list'] ?? '';
		if ( is_array( $selected_list ) ) {
			$selected_list = $selected_list[0] ?? '';
		}
		return $selected_list;
	}

	private static function extract_field_details( array $form_fields, array &$field_type_counts ): array {
		$form_field_details = array();
		$form_field_limit   = 30;

		foreach ( $form_fields as $ff_index => $tag ) {
			if ( $ff_index >= $form_field_limit ) {
				break;
			}

			$basetype = '';
			if ( is_object( $tag ) && isset( $tag->basetype ) ) {
				$basetype = $tag->basetype;
			} elseif ( is_array( $tag ) && isset( $tag['basetype'] ) ) {
				$basetype = $tag['basetype'];
			}

			$field_name = '';
			if ( is_object( $tag ) && isset( $tag->name ) ) {
				$field_name = $tag->name;
			} elseif ( is_array( $tag ) && isset( $tag['name'] ) ) {
				$field_name = $tag['name'];
			}

			if ( ! empty( $field_name ) && ! empty( $basetype ) ) {
				$form_field_details[] = array(
					'name' => $field_name,
					'type' => $basetype,
				);

				if ( ! isset( $field_type_counts[ $basetype ] ) ) {
					$field_type_counts[ $basetype ] = 0;
				}
				++$field_type_counts[ $basetype ];
			}
		}

		return $form_field_details;
	}

	private static function extract_mappings( array $cf7_mch, array $form_field_details ): array {
		$form_mappings     = array();
		$unmapped_cf7      = 0;
		$unmapped_mc       = 0;
		$mapped_cf7_fields = array();
		$total_mc_fields   = 0;
		$total_mappings    = 0;

		for ( $i = 1; $i <= 20; $i++ ) {
			$mc_tag    = $cf7_mch[ 'CustomKey' . $i ] ?? '';
			$mc_type   = $cf7_mch[ 'CustomKeyType' . $i ] ?? '';
			$cf7_field = trim( $cf7_mch[ 'CustomValue' . $i ] ?? '' );

			if ( ! empty( $mc_tag ) ) {
				++$total_mc_fields;
				if ( '' !== $cf7_field ) {
					$form_mappings[] = array(
						'cf7_field' => $cf7_field,
						'mc_tag'    => $mc_tag,
						'mc_type'   => $mc_type,
					);
					$mapped_cf7_fields[] = $cf7_field;
					++$total_mappings;
				} else {
					++$unmapped_mc;
				}
			}
		}

		foreach ( $form_field_details as $field ) {
			if ( ! in_array( $field['name'], $mapped_cf7_fields, true ) ) {
				++$unmapped_cf7;
			}
		}

		return array( $form_mappings, $unmapped_cf7, $unmapped_mc, $mapped_cf7_fields, $total_mc_fields, $total_mappings );
	}

	private static function extract_form_features( array $cf7_mch ): array {
		$form_features = array();

		if ( isset( $cf7_mch['confsubs'] ) && '1' === $cf7_mch['confsubs'] ) {
			$form_features['double_optin'] = true;
		}

		if ( ! empty( $cf7_mch['consent_required'] ) && ' ' !== $cf7_mch['consent_required'] ) {
			$form_features['required_consent'] = true;
		}

		if ( isset( $cf7_mch['logfileEnabled'] ) && '1' === $cf7_mch['logfileEnabled'] ) {
			$form_features['debug_logger'] = true;
		}

		if ( ! empty( $cf7_mch['labeltags'] ) && is_array( $cf7_mch['labeltags'] ) ) {
			$enabled_tags = array_filter( $cf7_mch['labeltags'], fn( $v ) => '1' === $v );
			if ( count( $enabled_tags ) > 0 ) {
				$form_features['tags_enabled'] = true;
			}
		}

		$form_group_count = 0;
		for ( $gi = 1; $gi <= 20; $gi++ ) {
			$gkey   = $cf7_mch[ "ggCustomKey{$gi}" ] ?? '';
			$gvalue = $cf7_mch[ "ggCustomValue{$gi}" ] ?? '';
			if ( ! empty( $gkey ) && ! empty( trim( $gvalue ) ) && ' ' !== $gvalue ) {
				++$form_group_count;
			}
		}
		if ( $form_group_count > 0 ) {
			$form_features['interest_groups'] = true;
		}

		$form_merge_fields_raw = array();
		if ( ! empty( $cf7_mch['merge_fields'] ) && is_array( $cf7_mch['merge_fields'] ) ) {
			$form_merge_fields_raw = $cf7_mch['merge_fields'];
		} elseif ( ! empty( $cf7_mch['merge-vars'] ) && is_array( $cf7_mch['merge-vars'] ) ) {
			$form_merge_fields_raw = $cf7_mch['merge-vars'];
		}

		if ( ! empty( $form_merge_fields_raw ) ) {
			$default_tags       = array( 'EMAIL', 'FNAME', 'LNAME', 'ADDRESS', 'PHONE' );
			$custom_field_count = 0;
			foreach ( $form_merge_fields_raw as $mfield ) {
				if ( isset( $mfield['tag'] ) && ! in_array( $mfield['tag'], $default_tags, true ) ) {
					++$custom_field_count;
				}
			}
			if ( $custom_field_count > 0 ) {
				$form_features['custom_merge_fields'] = true;
			}
		}

		if ( ! empty( $cf7_mch['conditional_logic'] ) ) {
			$form_features['conditional_logic'] = true;
		}

		return $form_features;
	}

	private static function get_form_age_range( array $cf7_forms ): array {
		$oldest_form = 0;
		$newest_form = 0;

		foreach ( $cf7_forms as $form ) {
			$created   = get_post_field( 'post_date', $form->id(), 'raw' );
			$timestamp = strtotime( $created );

			if ( 0 === $oldest_form || $timestamp < $oldest_form ) {
				$oldest_form = $timestamp;
			}
			if ( 0 === $newest_form || $timestamp > $newest_form ) {
				$newest_form = $timestamp;
			}
		}

		return array( $oldest_form, $newest_form );
	}
}
