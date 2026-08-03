<?php
/**
 * ChimpMatic Lite forms and audiences Signls collector.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

final class Cmatic_Lite_Signls_Forms_Collector {

	private const MAX_FORMS         = 100;
	private const MAX_DETAILS       = 50;
	private const MAX_FIELDS        = 30;
	private const MAX_MAPPINGS      = 30;
	private const MAX_AUDIENCES     = 100;
	private const MAX_REMOTE_FIELDS = 50;

	private $forms;
	private $configurations;

	public function collect(): array {
		$forms          = $this->forms();
		$configurations = $this->configurations();
		$total_forms    = $this->total_form_count();
		$audiences      = $this->audiences( $configurations );
		$details        = array();
		$field_types    = array();
		$total_fields   = 0;
		$total_mappings = 0;
		$total_remote   = 0;
		$active_forms   = 0;
		$with_api       = 0;
		$with_lists     = 0;
		$total_lists    = 0;
		$with_sends     = 0;
		$never_sent     = 0;
		$with_double    = 0;
		$with_consent   = 0;
		$total_sends    = 0;
		$oldest         = 0;
		$newest         = 0;
		$field_counts   = array();
		$list_counts    = array();

		foreach ( $forms as $form_id => $form ) {
			$config        = isset( $configurations[ $form_id ]['settings'] ) ? $configurations[ $form_id ]['settings'] : array();
			$provider      = Cmatic_Lite_Esp_Registry::get_selected( $config );
			$settings      = $this->provider_settings( $config, $provider );
			$authenticated = $this->has_authentication( $form_id, $provider, $config );
			$lists         = $this->cached_lists( $settings );
			$selected      = $this->selected_list( $settings );
			$tags          = is_object( $form ) && is_callable( array( $form, 'scan_form_tags' ) ) ? (array) $form->scan_form_tags() : array();
			$fields        = $this->fields( $tags, $field_types );
			$mappings      = $this->mappings( $settings, $fields );
			$features      = $this->features( $settings );
			$submissions   = isset( $configurations[ $form_id ]['submissions'] ) ? max( 0, (int) $configurations[ $form_id ]['submissions'] ) : 0;

			if ( $authenticated ) {
				++$active_forms;
				++$with_api;
			}
			if ( ! empty( $lists ) ) {
				++$with_lists;
			}
			$total_lists += count( $lists );
			if ( $submissions > 0 ) {
				++$with_sends;
			} else {
				++$never_sent;
			}
			$with_double    += $features['double_optin'] ? 1 : 0;
			$with_consent   += $features['required_consent'] ? 1 : 0;
			$total_sends    += $submissions;
			$total_fields   += count( $fields );
			$total_mappings += $mappings['mapped_count'];
			$total_remote   += $mappings['remote_total'];
			$field_counts[]  = count( $fields );
			$list_counts[]   = count( $lists );

			$created = get_post_field( 'post_date', $form_id, 'raw' );
			$created = is_string( $created ) ? (int) strtotime( $created ) : 0;
			if ( $created > 0 ) {
				$oldest = 0 === $oldest ? $created : min( $oldest, $created );
				$newest = max( $newest, $created );
			}

			if ( count( $details ) < self::MAX_DETAILS ) {
				$details[] = array(
					'form_id_sha256'            => hash( 'sha256', (string) $form_id ),
					'field_count'               => count( $fields ),
					'fields'                    => array(
						'items'          => array_slice( $fields, 0, self::MAX_FIELDS ),
						'reported_total' => count( $fields ),
						'truncated'      => count( $fields ) > self::MAX_FIELDS,
					),
					'paired_audience_id_sha256' => '' !== $selected ? hash( 'sha256', $selected ) : null,
					'mappings'                  => array(
						'items'          => array_slice( $mappings['items'], 0, self::MAX_MAPPINGS ),
						'reported_total' => $mappings['reported_total'],
						'truncated'      => $mappings['reported_total'] > self::MAX_MAPPINGS,
					),
					'unmapped_cf7_fields'       => $mappings['unmapped_fields'],
					'unmapped_mc_fields'        => max( 0, $mappings['remote_total'] - $mappings['reported_total'] ),
					'features'                  => $features,
				);
			}
		}

		usort(
			$details,
			static function ( array $left, array $right ): int {
				return strcmp( $left['form_id_sha256'], $right['form_id_sha256'] );
			}
		);
		ksort( $field_types, SORT_STRING );
		$processed      = count( $forms );
		$now            = time();
		$audience_items = array_slice( $audiences, 0, self::MAX_AUDIENCES );

		return array(
			'forms' => array(
				'total_forms'                 => $total_forms,
				'processed_forms'             => $processed,
				'active_forms'                => $active_forms,
				'forms_with_api'              => $with_api,
				'forms_with_lists'            => $with_lists,
				'inactive_forms'              => max( 0, $processed - $active_forms ),
				'total_audiences'             => count( $audiences ),
				'audiences'                   => array(
					'items'          => $audience_items,
					'reported_total' => count( $audiences ),
					'truncated'      => count( $audiences ) > self::MAX_AUDIENCES,
				),
				'total_contacts'              => array_sum( array_column( $audiences, 'member_count' ) ),
				'avg_lists_per_form'          => $processed > 0 ? round( $total_lists / $processed, 2 ) : null,
				'max_lists_per_form'          => ! empty( $list_counts ) ? max( $list_counts ) : 0,
				'total_fields_all_forms'      => $total_fields,
				'avg_fields_per_form'         => $processed > 0 ? round( $total_fields / $processed, 2 ) : null,
				'min_fields_per_form'         => ! empty( $field_counts ) ? min( $field_counts ) : 0,
				'max_fields_per_form'         => ! empty( $field_counts ) ? max( $field_counts ) : 0,
				'oldest_form_created'         => $oldest,
				'newest_form_created'         => $newest,
				'days_since_oldest_form'      => $oldest > 0 ? (int) floor( max( 0, $now - $oldest ) / DAY_IN_SECONDS ) : 0,
				'days_since_newest_form'      => $newest > 0 ? (int) floor( max( 0, $now - $newest ) / DAY_IN_SECONDS ) : 0,
				'forms_with_submissions'      => $with_sends,
				'forms_never_submitted'       => $never_sent,
				'forms_with_double_opt'       => $with_double,
				'forms_with_consent'          => $with_consent,
				'total_submissions_all_forms' => $total_sends,
				'form_utilization_rate'       => $processed > 0 ? round( ( $active_forms / $processed ) * 100, 2 ) : null,
				'forms_detail'                => array(
					'items'          => $details,
					'reported_total' => $processed,
					'truncated'      => $processed > self::MAX_DETAILS,
				),
				'forms_truncated'             => $total_forms > $processed,
				'forms_detail_truncated'      => $processed > self::MAX_DETAILS,
				'field_types_aggregate'       => $field_types,
				'mapping_stats'               => array(
					'total_cf7_fields' => $total_fields,
					'total_mc_fields'  => $total_remote,
					'mapped_fields'    => $total_mappings,
					'mapping_rate'     => $total_fields > 0 ? round( ( $total_mappings / $total_fields ) * 100, 2 ) : null,
				),
			),
		);
	}

	public function configurations(): array {
		if ( null !== $this->configurations ) {
			return $this->configurations;
		}
		global $wpdb;

		$form_ids = array_keys( $this->forms() );
		$result   = array();
		foreach ( $form_ids as $form_id ) {
			$result[ $form_id ] = array(
				'settings'        => array(),
				'submissions'     => 0,
				'last_submission' => 0,
			);
		}
		if ( empty( $form_ids ) ) {
			$this->configurations = $result;
			return $result;
		}

		$names = array();
		foreach ( $form_ids as $form_id ) {
			$names[] = 'cf7_mch_' . $form_id;
			$names[] = 'cf7_mch_submissions_' . $form_id;
			$names[] = 'cf7_mch_last_submission_' . $form_id;
		}
		$placeholders = implode( ',', array_fill( 0, count( $names ), '%s' ) );
		$query        = $wpdb->prepare(
			"SELECT option_name,option_value FROM {$wpdb->options} WHERE option_name IN ({$placeholders}) ORDER BY option_name ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Placeholder count and table are generated from bounded local IDs.
			...$names
		);
		$rows         = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One bounded ordered read avoids per-form queries.
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$name_candidate  = isset( $row['option_name'] ) ? $row['option_name'] : '';
			$value_candidate = isset( $row['option_value'] ) ? $row['option_value'] : null;
			$name            = is_scalar( $name_candidate ) ? (string) $name_candidate : '';
			$value           = is_scalar( $value_candidate ) ? maybe_unserialize( (string) $value_candidate ) : null;
			if ( 1 === preg_match( '/^cf7_mch_([0-9]+)$/', $name, $match ) && isset( $result[ (int) $match[1] ] ) ) {
				$result[ (int) $match[1] ]['settings'] = is_array( $value ) ? $value : array();
			} elseif ( 1 === preg_match( '/^cf7_mch_submissions_([0-9]+)$/', $name, $match ) && isset( $result[ (int) $match[1] ] ) ) {
				$result[ (int) $match[1] ]['submissions'] = self::nonnegative_int( $value );
			} elseif ( 1 === preg_match( '/^cf7_mch_last_submission_([0-9]+)$/', $name, $match ) && isset( $result[ (int) $match[1] ] ) ) {
				$result[ (int) $match[1] ]['last_submission'] = self::nonnegative_int( $value );
			}
		}
		$this->configurations = $result;
		return $result;
	}

	private function forms(): array {
		if ( null !== $this->forms ) {
			return $this->forms;
		}
		$this->forms = array();
		if ( ! class_exists( 'WPCF7_ContactForm' ) ) {
			return $this->forms;
		}
		$ids = get_posts(
			array(
				'post_type'      => 'wpcf7_contact_form',
				'posts_per_page' => self::MAX_FORMS,
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);
		sort( $ids, SORT_NUMERIC );
		foreach ( $ids as $id ) {
			$form = WPCF7_ContactForm::get_instance( $id );
			if ( $form ) {
				$this->forms[ (int) $id ] = $form;
			}
		}
		return $this->forms;
	}

	private function total_form_count(): int {
		$counts = wp_count_posts( 'wpcf7_contact_form' );
		return is_object( $counts ) && isset( $counts->publish ) ? max( 0, (int) $counts->publish ) : count( $this->forms() );
	}

	private function audiences( array $configurations ): array {
		$rows   = array();
		$global = Cmatic_Options_Repository::get_option( 'lisdata', array() );
		$this->merge_audiences( $rows, 'mailchimp', is_array( $global ) ? $global : array(), '' );
		foreach ( $configurations as $configuration ) {
			$config   = isset( $configuration['settings'] ) && is_array( $configuration['settings'] ) ? $configuration['settings'] : array();
			$provider = Cmatic_Lite_Esp_Registry::get_selected( $config );
			$settings = $this->provider_settings( $config, $provider );
			$this->merge_audiences( $rows, $provider, isset( $settings['lisdata'] ) && is_array( $settings['lisdata'] ) ? $settings['lisdata'] : array(), $this->selected_list( $settings ) );
		}
		ksort( $rows, SORT_STRING );
		return array_values( $rows );
	}

	private function merge_audiences( array &$rows, string $provider, array $list_data, string $selected ): void {
		$lists = isset( $list_data['lists'] ) && is_array( $list_data['lists'] ) ? $list_data['lists'] : array();
		foreach ( $lists as $list ) {
			if ( ! is_array( $list ) || ! isset( $list['id'] ) || ! is_scalar( $list['id'] ) || '' === (string) $list['id'] ) {
				continue;
			}
			$id    = (string) $list['id'];
			$key   = $provider . '|' . $id;
			$stats = isset( $list['stats'] ) && is_array( $list['stats'] ) ? $list['stats'] : array();
			if ( ! isset( $rows[ $key ] ) ) {
				$member_count      = isset( $stats['member_count'] ) ? $stats['member_count'] : ( isset( $list['member_count'] ) ? $list['member_count'] : 0 );
				$merge_field_count = isset( $stats['merge_field_count'] ) ? $stats['merge_field_count'] : ( isset( $list['field_count'] ) ? $list['field_count'] : 0 );
				$campaign_count    = isset( $stats['campaign_count'] ) ? $stats['campaign_count'] : 0;
				$rows[ $key ]      = array(
					'audience_id_sha256'    => hash( 'sha256', $id ),
					'member_count'          => self::nonnegative_int( $member_count ),
					'merge_field_count'     => self::nonnegative_int( $merge_field_count ),
					'double_optin'          => ! empty( $list['double_optin'] ) || 'double' === ( isset( $list['opt_in_process'] ) ? $list['opt_in_process'] : '' ),
					'marketing_permissions' => ! empty( $list['marketing_permissions'] ),
					'campaign_count'        => self::nonnegative_int( $campaign_count ),
					'is_paired'             => false,
				);
			}
			if ( '' !== $selected && hash_equals( $selected, $id ) ) {
				$rows[ $key ]['is_paired'] = true;
			}
		}
	}

	private function cached_lists( array $settings ): array {
		return isset( $settings['lisdata']['lists'] ) && is_array( $settings['lisdata']['lists'] ) ? $settings['lisdata']['lists'] : array();
	}

	private function fields( array $tags, array &$aggregate ): array {
		$fields = array();
		foreach ( $tags as $tag ) {
			$name = is_object( $tag ) && isset( $tag->name ) ? $tag->name : ( is_array( $tag ) && isset( $tag['name'] ) ? $tag['name'] : '' );
			$type = is_object( $tag ) && isset( $tag->basetype ) ? $tag->basetype : ( is_array( $tag ) && isset( $tag['basetype'] ) ? $tag['basetype'] : '' );
			if ( ! is_scalar( $name ) || ! is_scalar( $type ) || '' === (string) $name || '' === (string) $type ) {
				continue;
			}
			$name               = (string) $name;
			$type               = (string) $type;
			$fields[]           = array(
				'name' => $name,
				'type' => $type,
			);
			$aggregate[ $type ] = isset( $aggregate[ $type ] ) ? $aggregate[ $type ] + 1 : 1;
		}
		return $fields;
	}

	private function mappings( array $settings, array $fields ): array {
		$definitions = isset( $settings['merge_fields'] ) && is_array( $settings['merge_fields'] ) ? array_values( $settings['merge_fields'] ) : array();
		if ( empty( $definitions ) ) {
			for ( $index = 1; $index <= self::MAX_REMOTE_FIELDS; $index++ ) {
				$tag = isset( $settings[ 'CustomKey' . $index ] ) && is_scalar( $settings[ 'CustomKey' . $index ] ) ? (string) $settings[ 'CustomKey' . $index ] : '';
				if ( '' !== $tag ) {
					$definitions[] = array(
						'tag'             => $tag,
						'type'            => isset( $settings[ 'CustomKeyType' . $index ] ) ? (string) $settings[ 'CustomKeyType' . $index ] : 'text',
						'_legacy_mapping' => isset( $settings[ 'CustomValue' . $index ] ) ? (string) $settings[ 'CustomValue' . $index ] : '',
					);
				}
			}
		}
		$items   = array();
		$targets = array();
		foreach ( array_slice( $definitions, 0, self::MAX_REMOTE_FIELDS ) as $offset => $definition ) {
			if ( ! is_array( $definition ) || ! isset( $definition['tag'] ) || ! is_scalar( $definition['tag'] ) || '' === (string) $definition['tag'] ) {
				continue;
			}
			$field = isset( $definition['_legacy_mapping'] ) ? $definition['_legacy_mapping'] : ( isset( $settings[ 'field' . ( $offset + 3 ) ] ) ? $settings[ 'field' . ( $offset + 3 ) ] : '' );
			$field = is_scalar( $field ) ? trim( (string) $field ) : '';
			if ( '' === $field ) {
				continue;
			}
			$items[] = array(
				'cf7_field' => $field,
				'mc_tag'    => (string) $definition['tag'],
				'mc_type'   => isset( $definition['type'] ) && is_scalar( $definition['type'] ) ? (string) $definition['type'] : 'text',
			);
			$target  = self::normalize_field_name( $field );
			if ( '' !== $target ) {
				$targets[ $target ] = true;
			}
		}
		$mapped   = 0;
		$unmapped = 0;
		foreach ( $fields as $form_field ) {
			$name = self::normalize_field_name( $form_field['name'] );
			if ( '' !== $name && isset( $targets[ $name ] ) ) {
				++$mapped;
			} else {
				++$unmapped;
			}
		}
		$reported = count( $items );
		$remote   = isset( $settings['total_merge_fields'] ) ? max( count( $definitions ), self::nonnegative_int( $settings['total_merge_fields'] ) ) : count( $definitions );
		return array(
			'items'           => $items,
			'reported_total'  => $reported,
			'remote_total'    => max( 0, $remote ),
			'mapped_count'    => $mapped,
			'unmapped_fields' => $unmapped,
		);
	}

	private static function normalize_field_name( string $name ): string {
		return trim( $name, "[] \t\n\r\0\x0B" );
	}

	private function features( array $settings ): array {
		$tags   = isset( $settings['labeltags'] ) && is_array( $settings['labeltags'] ) ? array_filter( $settings['labeltags'] ) : array();
		$groups = false;
		for ( $index = 1; $index <= 20; $index++ ) {
			if ( ! empty( $settings[ 'ggCustomKey' . $index ] ) && ! empty( trim( (string) ( isset( $settings[ 'ggCustomValue' . $index ] ) ? $settings[ 'ggCustomValue' . $index ] : '' ) ) ) ) {
				$groups = true;
				break;
			}
		}
		return array(
			'double_optin'        => ! empty( $settings['confsubs'] ) || 'double' === ( isset( $settings['subscription_mode'] ) ? $settings['subscription_mode'] : '' ),
			'required_consent'    => ! empty( $settings['accept'] ) || ! empty( $settings['consent_required'] ) || 'required' === ( isset( $settings['consent_gate'] ) ? $settings['consent_gate'] : '' ),
			'debug_logger'        => ! empty( $settings['logfileEnabled'] ),
			'tags_enabled'        => ! empty( $tags ) || ! empty( $settings['tags'] ),
			'interest_groups'     => $groups || ! empty( $settings['groups'] ) || ! empty( $settings['base_groups'] ),
			'custom_merge_fields' => $this->has_custom_fields( $settings ),
			'conditional_logic'   => ! empty( $settings['conditional_logic'] ) || ! empty( $settings['conditions'] ),
		);
	}

	private function has_custom_fields( array $settings ): bool {
		$defaults = array( 'EMAIL', 'FNAME', 'LNAME', 'ADDRESS', 'PHONE' );
		$fields   = isset( $settings['merge_fields'] ) && is_array( $settings['merge_fields'] ) ? $settings['merge_fields'] : array();
		if ( empty( $fields ) && isset( $settings['merge-vars'] ) && is_array( $settings['merge-vars'] ) ) {
			$fields = $settings['merge-vars'];
		}
		foreach ( $fields as $field ) {
			if ( is_array( $field ) && isset( $field['tag'] ) && is_scalar( $field['tag'] ) && ! in_array( strtoupper( (string) $field['tag'] ), $defaults, true ) ) {
				return true;
			}
		}
		return false;
	}

	private function provider_settings( array $config, string $provider ): array {
		return 'mailchimp' === $provider ? $config : ( isset( $config['providers'][ $provider ] ) && is_array( $config['providers'][ $provider ] ) ? $config['providers'][ $provider ] : array() );
	}

	private function selected_list( array $settings ): string {
		$list = isset( $settings['list'] ) ? $settings['list'] : '';
		if ( is_array( $list ) ) {
			$list = reset( $list );
		}
		return is_scalar( $list ) ? trim( (string) $list ) : '';
	}

	private function has_authentication( int $form_id, string $provider, array $config ): bool {
		if ( 'mailchimp' === $provider ) {
			return ! empty( $config['api'] ) || ( new Cmatic_Lite_Auth_Manager() )->has_oauth( $form_id );
		}
		return Cmatic_Lite_Esp_Credentials::has( $form_id, $provider );
	}

	private static function nonnegative_int( $value ): int {
		return is_scalar( $value ) && is_numeric( $value ) ? max( 0, (int) $value ) : 0;
	}
}
