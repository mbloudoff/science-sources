<?php

namespace ScienceSources;

/**
 * Attach hooks for the dashboard.
 */
function admin_hooks() {
	add_action( 'load-edit.php', __NAMESPACE__ . '\\load_edit_php' );
	add_action( 'post_row_actions', __NAMESPACE__ . '\\post_row_actions', 10, 2 );
	add_action( 'display_post_states', __NAMESPACE__ . '\\display_post_states', 10, 2 );
}

/**
 * Handles admin actions and corresponding admin notices.
 */
function load_edit_php() {
	if ( get_current_screen()->post_type !== 'source' ) {
		return;
	}

	if ( isset( $_GET['source-notice'] ) ) {
		if ( in_array( $_GET['source-notice'], [ 'invalid', 'published', 'trashed'] ) ) {
			add_action( 'admin_notices', __NAMESPACE__ . '\\admin_notice_' . $_GET['source-notice'] );
		}
	}

	if ( isset( $_GET['source-action'], $_GET['id'], $_GET['nonce'] ) ) {
		$source = new Source( intval( $_GET['id'] ) );
		$return = remove_query_arg( ['source-action', 'id', 'nonce'], wp_unslash($_SERVER['REQUEST_URI'] ) );

		if ( ! $source->validate_key( 'admin', $_GET['nonce'] ) ) {
			wp_redirect( add_query_arg( 'source-notice', 'invalid', $return ) );
			exit;
		}

		switch ( $_GET['source-action'] ) {
			case 'publish' :
				$source->publish();
				wp_redirect( add_query_arg( 'source-notice', 'published', $return ) );
				exit;
			case 'trash' :
				$source->trash();
				wp_redirect( add_query_arg( 'source-notice', 'trashed', $return ) );
				exit;
		}
	}
}

/**
 * Displays renamed post states in the list table.
 */
function display_post_states( $states, $post ) {
	if ( $post->post_type !== 'source' ) {
		return $states;
	}

	if ( isset( $states['draft'] ) ) {
		$states['draft'] = 'Awaiting Email Confirmation';
	}
	if ( isset( $states['pending'] ) ) {
		$states['pending'] = 'Needs Moderation';
	}

	return $states;
}

/**
 * Adds post actions in the list table for approvals.
 */
function post_row_actions( $actions, $post ) {
	if ( $post->post_type !== 'source' ) {
		return $states;
	}

	$ordering = [ 'source-publish' => false, 'trash' => false ];
	$actions = array_merge( $ordering, $actions );

	if ( $post->post_status === 'pending' ) {
		$source = new Source( $post );
		$actions['source-publish'] = sprintf( '<a style="color: #090" href="%s">Publish</a>', $source->get_admin_publish_link() );
	}

	return array_filter( $actions );
}

function admin_notice_invalid() {
	echo '<div class="notice notice-error"><p>Sorry, that link was invalid. Try moderating submissions below.</p></div>';
}

function admin_notice_published() {
	echo '<div class="notice notice-info"><p>Submission published.</p></div>';
}

function admin_notice_trashed() {
	echo '<div class="notice notice-info"><p>Submission trashed.</p></div>';
}