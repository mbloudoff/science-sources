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
		return add_query_arg( 'edit', $this->force_get_key( 'edit' ), $this->get_permalink() );
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
	 * Get a secret key, by $type, from postmeta.
	 */
	function get_key( $type ) {
		return get_post_meta( $this->post->ID, $this->get_meta_key_from_key_type( $type ), true );
	}

	/**
	 * Get a secret key, by $type, from postmeta. Generate a new one if necessary.
	 */
	function force_get_key( $type ) {
		$key = $this->get_key( $type );
		if ( $key ) {
			return $key;
		}

		return $this->generate_key( $type );
	}

	/**
	 * Generate a secret key, by $type, and store it in postmeta.
	 */
	function generate_key( $type ) {
		$key = strtolower( wp_generate_password( 30, false ) );
		update_post_meta( $this->post->ID, $this->get_meta_key_from_key_type( $type ), $key );
		return $key;
	}

	/**
	 * Delete a secret key, by $type, from postmeta.
	 */
	function delete_key( $type ) {
		return delete_post_meta( $this->post->ID, $this->get_meta_key_from_key_type( $type ) );
	}

	/**
	 * Check a string against a secret key stored in postmeta, by $type.
	 */
	function validate_key( $type, $value ) {
		$value = (string) $value;
		return ( $value !== '' && $value === $this->get_key( $type ) );
	}

	/**
	 * Convert secret key shorthands to the underlying postmeta key.
	 */
	protected function get_meta_key_from_key_type( $type ) {
		switch ( $type ) {
			case 'edit' :
				return '_source_edit_key';
			case 'confirm' :
				return '_source_email_confirm';
			case 'admin' :
				return '_source_admin_nonce';
			default :
				throw new Exception( 'Invalid key type' );
		}
	}

	function get_email_confirmation_link() {
		$key = $this->force_get_key( 'confirm' );
		return home_url( sprintf( '/?email-confirm=%s&key=%s', $this->post->ID, $key ) );
	}

	function get_admin_publish_link() {
		return admin_url( sprintf( 'edit.php?post_type=source&source-action=publish&id=%s&nonce=%s',
			$this->post->ID, $this->force_get_key( 'admin' ) ) );
	}

	function get_admin_trash_link() {
		return admin_url( sprintf( 'edit.php?post_type=source&source-action=trash&id=%s&nonce=%s',
			$this->post->ID, $this->force_get_key( 'admin' ) ) );
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
	function email_confirmation_attempt( $key ) {
		$key = (string) $key;
		if ( $this->validate_key( 'confirm', $key ) ) {
			$this->delete_key( 'confirm' );
			wp_update_post([ 'ID' => $this->post->ID, 'post_status' => 'pending' ]);
			send_moderator_email( $this );
			return true;
		}

		return false;
	}
}