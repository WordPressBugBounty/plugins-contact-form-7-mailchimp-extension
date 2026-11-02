<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    hello@chimpmatic.com
 * @copyright 2014-2026 ChimpMatic
 * @license   GPL-3.0+
 */

declare(strict_types=1);

namespace Cmatic\Lite\Submission;

use Cmatic\Lite\Settings\Entitlements;
use Cmatic\Lite\Settings\FormSettings;
use Cmatic\Lite\Settings\FormTags;
use Cmatic\Lite\Settings\MappingSchema;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing -- Typed signatures follow the existing Lite class convention.
final class Router {
	/**
	 * @param mixed $contact_form The Contact Form 7 form being submitted.
	 */
	public static function route( $contact_form, FormSettings $settings ): void {
		$form_id = $settings->form_id();
		$slug    = $settings->provider();
		$section = $settings->section( $slug );
		if ( ! self::connected( $settings ) ) {
			return;
		}
		$api_key = self::credential( $settings );
		if ( 'mailchimp' !== $slug && '' === $api_key ) {
			return;
		}
		$submission = \WPCF7_Submission::get_instance();
		if ( ! $submission ) {
			return;
		}
		$posted_data = $submission->get_posted_data();
		$logger      = new \Cmatic_File_Logger( 'mailchimp' === $slug ? 'api-events' : 'api-events-' . $slug, (bool) \Cmatic_Options_Repository::get_option( 'debug', false ) );
		$policy      = Entitlements::apply( $slug, $section, $form_id );
		$pipeline    = new Pipeline( \Cmatic_Lite_Esp_Registry::catalog()->get( $slug ) );
		$pipeline->run( $form_id, $section, $policy, is_array( $posted_data ) ? $posted_data : array(), FormTags::get_tags_with_types( $contact_form ), $api_key, $logger );
	}

	public static function connected( FormSettings $settings ): bool {
		$slug    = $settings->provider();
		$section = $settings->section( $slug );
		if ( 'mailchimp' !== $slug ) {
			return FormSettings::is_connected( $section );
		}
		$form_id = $settings->form_id();
		if ( empty( $section ) ) {
			return false;
		}
		if ( ! empty( $section['api-validation'] ) && 1 === MappingSchema::int( $section['api-validation'] ) && ! empty( $section['api'] ) ) {
			return true;
		}
		if ( isset( $section['auth_type'] ) && 'oauth' === $section['auth_type'] && ! empty( $section['api-validation'] ) && 1 === MappingSchema::int( $section['api-validation'] ) ) {
			$auth_manager = \Cmatic_Lite_Container::get( 'auth.manager' );
			if ( $auth_manager && $auth_manager->has_oauth( $form_id ) ) {
				return true;
			}
		}
		if ( $form_id && empty( $section['api'] ) ) {
			$auth_manager = \Cmatic_Lite_Container::get( 'auth.manager' );
			if ( $auth_manager && $auth_manager->has_oauth( $form_id ) ) {
				return true;
			}
		}
		return false;
	}

	private static function credential( FormSettings $settings ): string {
		$slug = $settings->provider();
		if ( 'mailchimp' !== $slug ) {
			return \Cmatic_Lite_Esp_Credentials::get( $settings->form_id(), $slug );
		}
		$record       = $settings->record();
		$auth_manager = \Cmatic_Lite_Container::get( 'auth.manager' );
		if ( $auth_manager ) {
			return MappingSchema::str( $auth_manager->resolve_api_key( $settings->form_id(), '', $record ) );
		}
		return MappingSchema::str( $record['api'] ?? '' );
	}

	private function __construct() {}
}
