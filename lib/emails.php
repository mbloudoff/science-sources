<?php

namespace ScienceSources;

/**
 * Send an email asking the user to confirm their email address.
 */
function send_email_confirmation_email( Source $source ) {
	$to = $source->get_email();
	$subject = "Please confirm your email address";

	$body = <<<'EMAIL'
Thank you for submitting yourself to %1$s!

Please click this link to confirm your email address:
%2$s
EMAIL;

	$body = sprintf( $body,
		get_bloginfo( 'name' ),
		$source->get_email_confirmation_link()
	);

	wp_mail( $to, $subject, $body );
}

function send_moderator_email( Source $source ) {
	$to = get_option( 'admin_email' );
	$subject = "Please moderate new submission from %s";
	$subject = sprintf( $subject, $source->get_name() );

	$body = <<<'EMAIL'
Please moderate this new submission:
%1$s

Publish it: %2$s

Trash it: %3$s
EMAIL;

	$body = sprintf( $body,
		$source->get_content(),
		$source->get_admin_publish_link(),
		$source->get_admin_trash_link()
	);

	wp_mail( $to, $subject, $body );
}

/**
 * Send an email telling the user their listing is live, and provides their edit link.
 */
function send_published_email( Source $source ) {
	$to = $source->get_email();
	$subject = "You are now listed";

	$body = <<<'EMAIL'
Thank you for submitting yourself to %1$s.

Your listing is now live:
%2$s

To edit your listing at any time in the future, please visit:
%3$s

Keep this email for your records.

If you have any questions, please contact me:
%4$s
EMAIL;

	$body = sprintf( $body,
		get_bloginfo( 'name' ),
		$source->get_permalink(),
		$source->get_edit_post_link(),
		home_url( 'contact' )
	);

	wp_mail( $to, $subject, $body );
}

function wp_mail( $to, $subject, $body ) {
	$subject = sprintf( '[%s] %s', get_bloginfo( 'name' ), $subject );
	\wp_mail( $to, $subject, $body );
}
