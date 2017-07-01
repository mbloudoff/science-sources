<?php

namespace ScienceSources;

/**
 * Attaches general hooks.
 */
function hooks() {
	add_action( 'init', __NAMESPACE__ . '\\init' );
	add_action( 'init', __NAMESPACE__ . '\\register_post_type' );

	add_action( 'transition_post_status', __NAMESPACE__ . '\\transition_post_status', 10, 3 );
	add_filter( 'the_content', __NAMESPACE__ . '\\the_content' );
}

/**
 * Handles early routing.
 */
function init() {
	if ( isset( $_GET['email-confirm'] ) ) {
		$source = new Source( intval( $_GET['email-confirm'] ) );
		if ( $source->email_confirmation_attempt( $_GET['key'] ) ) {
			wp_die( sprintf( 'Thank you for confirming your email address. Your listing is now pending a quick moderation step. <a href="%s">Back to home</a>', home_url() ) );
		}

		wp_die( sprintf( 'This is an invalid link. Having trouble? <a href="%s">Contact</a>', home_url( 'contact' ) ) );
	}
}

/**
 * Registers the 'source' post type.
 */
function register_post_type() {
	\register_post_type( 'source', [
		'label' => 'Sources',
		'singular_label' => 'Source',
		'public' => true,
		'show_ui' => true,
	]);
}

/**
 * Watches post status transitions for source posts.
 *
 * 1. Send out approval emails when posts are approved
 *    for the first time.
 * 2. Delete the 'admin' secret key whenever a post is
 *    approved or trashed.
 */
function transition_post_status( $new_status, $old_status, $post ) {
	if ( $post->post_type !== 'source' ) {
		return;
	}

	$source = new Source( $post );

	if ( $old_status !== 'publish' && $new_status === 'publish' ) {
		$source->delete_key( 'admin' );

		// If we haven't previously sent an approval email.
		if ( ! $source->get_key( 'edit' ) ) {
			$source->send_approval_email();
		}
	}

	if ( $old_status !== 'trash' && $new_status === 'trash' ) {
		$source->delete_key( 'admin' );
	}

}

/**
 * Hijacks the post content. Eventually will be used to insert an edit view.
 */
function the_content( $content ) {
	$post = get_post();

	if ( $post->post_type !== 'source' ) {
		return;
	}

	$source = new Source( $post );

	if ( $source->validate_key( 'edit', $_GET['edit'] ) ) {
		return $content . "\n\n" . '<p>' . 'Edit mode activated' . '</p>';
	}
}
