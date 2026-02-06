<?php
/**
 * WordPress data collector.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

namespace Cmatic\Metrics\Core\Collectors;

defined( 'ABSPATH' ) || exit;

class WordPress_Collector {
	public static function collect(): array {
		global $wpdb;

		$post_counts    = wp_count_posts( 'post' );
		$page_counts    = wp_count_posts( 'page' );
		$comment_counts = wp_count_comments();
		$user_count     = count_users();
		$media_counts   = wp_count_posts( 'attachment' );
		$category_count = wp_count_terms( 'category' );
		$tag_count      = wp_count_terms( 'post_tag' );
		$revision_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'" );

		$data = array(
			'posts_published'      => isset( $post_counts->publish ) ? (int) $post_counts->publish : 0,
			'posts_draft'          => isset( $post_counts->draft ) ? (int) $post_counts->draft : 0,
			'pages_published'      => isset( $page_counts->publish ) ? (int) $page_counts->publish : 0,
			'media_items'          => isset( $media_counts->inherit ) ? (int) $media_counts->inherit : 0,
			'comments_pending'     => isset( $comment_counts->moderated ) ? (int) $comment_counts->moderated : 0,
			'comments_spam'        => isset( $comment_counts->spam ) ? (int) $comment_counts->spam : 0,
			'users_total'          => isset( $user_count['total_users'] ) ? (int) $user_count['total_users'] : 0,
			'users_administrators' => isset( $user_count['avail_roles']['administrator'] ) ? (int) $user_count['avail_roles']['administrator'] : 0,
			'users_editors'        => isset( $user_count['avail_roles']['editor'] ) ? (int) $user_count['avail_roles']['editor'] : 0,
			'users_authors'        => isset( $user_count['avail_roles']['author'] ) ? (int) $user_count['avail_roles']['author'] : 0,
			'users_subscribers'    => isset( $user_count['avail_roles']['subscriber'] ) ? (int) $user_count['avail_roles']['subscriber'] : 0,
			'categories_count'     => is_wp_error( $category_count ) ? 0 : (int) $category_count,
			'tags_count'           => is_wp_error( $tag_count ) ? 0 : (int) $tag_count,
			'revisions_count'      => (int) $revision_count,
		);

		$data = array_filter( $data, fn( $v ) => $v !== 0 );
		$data['auto_updates_enabled'] = (bool) get_option( 'auto_update_plugins', false );

		return $data;
	}
}
