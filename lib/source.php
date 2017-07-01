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
		$source->send_confirmation_email();
		return $source;
	}

	/**
	 * Constructor takes a post ID or post object.
	 */
	public function __construct( $post_id ) {
		$this->post = get_post( $post_id );
	}

	/**
	 * Send an email asking the user to confirm their email address.
	 */
	function send_confirmation_email() {
		$email = get_post_meta( $this->post->ID, '_source_email', true );
		$body = "Click this link to confirm your submission:\n\n%s";

		$this->generate_key( 'confirm' );

		$url = $this->get_email_confirmation_link();

		$body = sprintf( $body, $url );

		wp_mail( $email, 'Please confirm your email address', $body );
	}

	/**
	 * Send an email telling the user their listing is live, and provides their edit link.
	 */
	function send_approval_email() {
		$email = get_post_meta( $this->post->ID, '_source_email', true );
		$body = "Thanks, your listing is now available at:\n%s";
		$body .= "\n\nPlease keep this email. To edit your listing, click this link: %s";

		$this->generate_key( 'edit' );
		$body = sprintf( $body, $this->get_permalink(), $this->get_edit_post_link() );

		wp_mail( $email, 'Listing is live', $body );
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
		return add_query_arg( 'edit', $this->get_key( 'edit' ), $this->get_permalink() );
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
		$key = $this->get_key( 'confirm' );
		return home_url( sprintf( '/?email-confirm=%s&key=%s', $this->post->ID, $key ) );
	}

	function send_admin_pending_email() {
		$body = "Please moderate this submission: %s\n\nPublish: %s\nTrash: %s";
		$body = sprintf( $body, $this->post->post_title, $this->get_admin_publish_link(), $this->get_admin_trash_link() );
		$success = wp_mail( get_option( 'admin_email' ), 'New submission', $body );
	}

	function get_admin_publish_link() {
		return admin_url( sprintf( 'edit.php?post_type=source&source-action=publish&id=%s&nonce=%s', $this->post->ID, $this->force_get_key( 'admin' ) ) );
	}

	function get_admin_trash_link() {
		return admin_url( sprintf( 'edit.php?post_type=source&source-action=trash&id=%s&nonce=%s', $this->post->ID, $this->force_get_key( 'admin' ) ) );
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
		$expected = $this->get_key( 'confirm' );
		if ( $key !== '' && $key === $expected ) {
			$this->delete_key( 'confirm' );
			wp_update_post([ 'ID' => $this->post->ID, 'post_status' => 'pending' ]);
			$this->send_admin_pending_email();
			return true;
		}

		return false;
	}
}