<?php
/**
 * BuddyPress - Members Settings Notifications
 *
 * @package BuddyPress
 * @subpackage bp-legacy
 * @version 12.0.0
 */

/** This action is documented in bp-templates/bp-legacy/buddypress/members/single/settings/profile.php */
do_action( 'bp_before_member_settings_template' ); ?>

<h2 class="bp-screen-reader-text">
	<?php
		/* translators: accessibility text */
		esc_html_e( 'Notification settings', 'buddypress' );
	?>
</h2>

<form action="<?php bp_displayed_user_link( array( bp_get_settings_slug(), 'notifications' ) ); ?>" method="post" class="standard-form" id="settings-form">
	<p><?php esc_html_e( 'Send an email notice when:', 'buddypress' ); ?></p>

	<?php

	/**
	 * Fires at the top of the member template notification settings form.
	 *
	 * @since 1.0.0
	 */
	do_action( 'bp_notification_settings' ); ?>

	<?php

	/**
	 * Fires before the display of the submit button for user notification saving.
	 *
	 * @since 1.5.0
	 */
	do_action( 'bp_members_notification_settings_before_submit' ); ?>

	<div class="submit">
		<input type="submit" name="submit" value="<?php esc_attr_e( 'Save Changes', 'buddypress' ); ?>" id="submit" class="auto" />
	</div>

	<?php

	/**
	 * Fires after the display of the submit button for user notification saving.
	 *
	 * @since 1.5.0
	 */
	do_action( 'bp_members_notification_settings_after_submit' ); ?>

	<?php wp_nonce_field('bp_settings_notifications' ); ?>

</form>

<?php

/** This action is documented in bp-templates/bp-legacy/buddypress/members/single/settings/profile.php */
do_action( 'bp_after_member_settings_template' );
