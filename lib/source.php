<?php

namespace ScienceSources;

/**
 * Object representing a single 'source', or journalist/contact.
 */
class Source {
	/**
	 * Adds a new source. Returns a Source object.
	 */
	public static function add( $data ) {
		$post_id = wp_insert_post([
			'post_type'     => 'source',
			'post_status'   => 'draft',
			'post_title'    => wp_slash( $data['name'] ),
			'post_content'  => wp_slash( $data['content'] ),
		], true );

		if ( is_wp_error( $post_id ) ) {
			return false;
		}

		update_post_meta( $post_id, '_source_email', wp_slash( $data['email'] ) );

		$source = new Source( $post_id );
		send_email_confirmation_email( $source );
		return $source;
	}

	/**
	 * Constructor takes a post ID or post object.
	 */
	public function __construct( $post_id ) {
		$this->post = get_post( $post_id );
	}

	/**
	 * Retreive the public link for a source.
	 */
	function get_permalink() {
		return get_permalink( $this->post->ID );
	}

	/**
	 * Retreive the frontend editable link for a source.
	 */
	function get_edit_post_link() {
		return add_query_arg( 'edit', $this->force_get_nonce( 'edit' ), $this->get_permalink() );
	}

	/**
	 * Get source's name.
	 */
	function get_name() {
		return get_the_title( $this->post );
	}

	/**
	 * Get a summary of the source's submission.
	 */
	function get_content() {
		return $this->post->post_content;
	}

	/**
	 * Get source's email.
	 */
	function get_email() {
		return get_post_meta( $this->post->ID, '_source_email', true );
	}

	/**
	 * Get a nonce, by $type, from postmeta.
	 */
	function get_nonce( $type ) {
		return get_post_meta( $this->post->ID, $this->get_meta_key_from_nonce_type( $type ), true );
	}

	/**
	 * Get a nonce, by $type, from postmeta. Generate a new one if necessary.
	 */
	function force_get_nonce( $type ) {
		$nonce = $this->get_nonce( $type );
		if ( $nonce ) {
			return $nonce;
		}

		return $this->generate_nonce( $type );
	}

	/**
	 * Generate a nonce, by $type, and store it in postmeta.
	 */
	function generate_nonce( $type ) {
		$nonce = strtolower( wp_generate_password( 30, false ) );
		update_post_meta( $this->post->ID, $this->get_meta_key_from_nonce_type( $type ), $nonce );
		return $nonce;
	}

	/**
	 * Delete a nonce, by $type, from postmeta.
	 */
	function delete_nonce( $type ) {
		return delete_post_meta( $this->post->ID, $this->get_meta_key_from_nonce_type( $type ) );
	}

	/**
	 * Check a string against a nonce stored in postmeta, by $type.
	 */
	function validate_nonce( $type, $value ) {
		$value = (string) $value;
		return ( $value !== '' && $value === $this->get_nonce( $type ) );
	}

	/**
	 * Convert nonce types to the underlying postmeta key.
	 */
	protected function get_meta_key_from_nonce_type( $type ) {
		switch ( $type ) {
			case 'edit' :
			case 'confirm' :
			case 'admin' :
				return '_source_nonce_' . $type;
			default :
				throw new Exception( 'Invalid nonce type' );
		}
	}

	function get_email_confirmation_link() {
		$nonce = $this->force_get_nonce( 'email' );
		return home_url( sprintf( '/?email-confirm=%s&nonce=%s', $this->post->ID, $nonce ) );
	}

	function get_admin_publish_link() {
		return admin_url( sprintf( 'edit.php?post_type=source&source-action=publish&id=%s&nonce=%s',
			$this->post->ID, $this->force_get_nonce( 'admin' ) ) );
	}

	function get_admin_trash_link() {
		return admin_url( sprintf( 'edit.php?post_type=source&source-action=trash&id=%s&nonce=%s',
			$this->post->ID, $this->force_get_nonce( 'admin' ) ) );
	}

	/**
	 * Approve a source's listing.
	 *
	 * Shorthand for wp_publish_post(). Any special actions are hooked
	 * into transition_post_status to keep WP core functionality intact.
	 */
	function publish() {
		wp_publish_post( $this->post->ID );
	}

	/**
	 * Trash a source's listing.
	 *
	 * Shorthand for wp_trash_post(). Any special actions are hooked
	 * into transition_post_status to keep WP core functionality intact.
	 */
	function trash() {
		wp_trash_post( $this->post->ID );
	}

	/**
	 * Process an attempt to confirm an email address.
	 *
	 * If successful, make the post 'pending' and send the admin an email
	 * letting them know a post is ready for moderation.
	 */
	function email_confirmation_attempt( $nonce ) {
		$nonce = (string) $nonce;
		if ( $this->validate_nonce( 'email', $nonce ) ) {
			$this->delete_nonce( 'email' );
			wp_update_post([ 'ID' => $this->post->ID, 'post_status' => 'pending' ]);
			send_moderator_email( $this );
			return true;
		}

		return false;
	}
}