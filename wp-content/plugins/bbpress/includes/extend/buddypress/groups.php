<?php

/**
 * bbPress BuddyPress Group Extension Class
 *
 * This file is responsible for connecting bbPress to the BuddyPress Groups
 * Component. It's a great example of how to perform both simple and advanced
 * techniques to manipulate the default output provided by both.
 *
 * @package bbPress
 * @subpackage BuddyPress
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'BBP_Forums_Group_Extension' ) && class_exists( 'BP_Group_Extension' ) ) :
/**
 * Loads Group Extension for Forums Component
 *
 * @since 2.1.0 bbPress (r3552)
 *
 * @package bbPress
 * @subpackage BuddyPress
 */
class BBP_Forums_Group_Extension extends BP_Group_Extension {

	/** Methods ***************************************************************/

	/**
	 * Setup bbPress group extension variables
	 *
	 * @since 2.1.0 bbPress (r3552)
	 */
	public function __construct() {
		$this->setup_variables();
		$this->setup_actions();
		$this->setup_filters();
		$this->maybe_unset_forum_menu();
		$this->fully_loaded();
	}

	/**
	 * Setup the group forums class variables
	 *
	 * @since 2.1.0 bbPress (r3552)
	 */
	private function setup_variables() {

		// Component Name
		$this->name          = esc_html__( 'Forum', 'bbpress' );
		$this->nav_item_name = esc_html__( 'Forum', 'bbpress' );

		// Component slugs (hardcoded to match bbPress 1.x functionality)
		$this->slug          = 'forum';
		$this->topic_slug    = 'topic';
		$this->reply_slug    = 'reply';

		// Forum component is visible
		$this->visibility = 'public';

		// Set positions towards end
		$this->create_step_position = 15;
		$this->nav_item_position    = 10;

		// Allow create step and show in nav
		$this->enable_create_step   = true;
		$this->enable_nav_item      = true;
		$this->enable_edit_item     = true;

		// Template file to load, and action to hook display on to
		$this->template_file        = 'groups/single/plugins';
		$this->display_hook         = 'bp_template_content';
	}

	/**
	 * Setup the group forums class actions
	 *
	 * @since 2.3.0 bbPress (r4552)
	 */
	private function setup_actions() {

		// Possibly redirect
		add_action( 'bbp_template_redirect',          array( $this, 'redirect_canonical'              ) );

		// Remove group forum cap map when view is done
		add_action( 'bbp_after_group_forum_display',  array( $this, 'remove_group_forum_meta_cap_map' ) );

		// Validate group IDs when editing topics & replies
		add_action( 'bbp_edit_topic_pre_extras',      array( $this, 'validate_topic_forum_id' ) );
		add_action( 'bbp_edit_reply_pre_extras',      array( $this, 'validate_reply_to_id'    ) );

		// Check if group-forum attributes should be changed
		add_action( 'groups_group_after_save',        array( $this, 'update_group_forum' ) );

		// bbPress needs to listen to BuddyPress group deletion
		add_action( 'groups_before_delete_group',     array( $this, 'disconnect_forum_from_group'     ) );

		// Adds a bbPress meta-box to the new BuddyPress Group Admin UI
		add_action( 'bp_groups_admin_meta_boxes',     array( $this, 'group_admin_ui_edit_screen'      ) );

		// Saves the bbPress options if they come from the BuddyPress Group Admin UI
		add_action( 'bp_group_admin_edit_after',      array( $this, 'edit_screen_save'                ) );

		// Adds a hidden input value to the "Group Settings" page
		add_action( 'bp_before_group_settings_admin', array( $this, 'group_settings_hidden_field'     ) );
	}

	/**
	 * Setup the group forums class filters
	 *
	 * @since 2.3.0 bbPress (r4552)
	 */
	private function setup_filters() {

		// Group forum pagination
		add_filter( 'bbp_topic_pagination',      array( $this, 'topic_pagination'   ) );
		add_filter( 'bbp_replies_pagination',    array( $this, 'replies_pagination' ) );
		add_filter( 'bbp_get_global_object',     array( $this, 'rewrite_pagination' ), 10, 2 );

		// Tweak the redirect field
		add_filter( 'bbp_new_topic_redirect_to', array( $this, 'new_topic_redirect_to'        ), 10, 3 );
		add_filter( 'bbp_new_reply_redirect_to', array( $this, 'new_reply_redirect_to'        ), 10, 3 );

		// Map forum/topic/reply permalinks to their groups
		add_filter( 'bbp_get_forum_permalink',   array( $this, 'map_forum_permalink_to_group' ), 10, 2 );
		add_filter( 'bbp_get_topic_permalink',   array( $this, 'map_topic_permalink_to_group' ), 10, 2 );
		add_filter( 'bbp_get_reply_permalink',   array( $this, 'map_reply_permalink_to_group' ), 10, 2 );

		// Map reply edit links to their groups
		add_filter( 'bbp_get_reply_edit_url',    array( $this, 'map_reply_edit_url_to_group'  ), 10, 2 );

		// Map assorted template function permalinks
		add_filter( 'post_link',                 array( $this, 'post_link'                    ), 10, 2 );
		add_filter( 'page_link',                 array( $this, 'page_link'                    ), 10, 2 );
		add_filter( 'post_type_link',            array( $this, 'post_type_link'               ), 10, 2 );

		// Map group forum activity items to groups
		add_filter( 'bbp_before_record_activity_parse_args', array( $this, 'map_activity_to_group' ) );

		/** Caps **************************************************************/

		// Only add these filters if inside a group forum
		if ( bp_is_single_item() && bp_is_group() && bp_is_current_action( $this->slug ) ) {

			// Ensure bbp_is_single_forum() returns true on group forums.
			add_filter( 'bbp_is_single_forum', array( $this, 'is_single_forum' ) );

			// Ensure bbp_is_single_topic() returns true on group forum topics.
			add_filter( 'bbp_is_single_topic', array( $this, 'is_single_topic' ) );

			// Allow group member to view private/hidden forums
			add_filter( 'bbp_map_meta_caps', array( $this, 'map_group_forum_meta_caps' ), 10, 4 );

			// Group member permissions to view the topic and reply forms
			add_filter( 'bbp_current_user_can_access_create_topic_form', array( $this, 'form_permissions' ) );
			add_filter( 'bbp_current_user_can_access_create_reply_form', array( $this, 'form_permissions' ) );
		}
	}

	/**
	 * Allow the variables, actions, and filters to be modified by third party
	 * plugins and themes.
	 *
	 * @since 2.6.0 bbPress (r6808)
	 */
	private function fully_loaded() {
		do_action_ref_array( 'bbp_buddypress_groups_loaded', array( $this ) );
	}

	/**
	 * Ensure that bbp_is_single_forum() returns true on group forum pages.
	 *
	 * @see https://bbpress.trac.wordpress.org/ticket/2974
	 *
	 * @since 2.6.0 bbPress (r6366)
	 *
	 * @param  bool $retval Current boolean.
	 * @return bool
	 */
	public function is_single_forum( $retval = false ) {

		// Additional BuddyPress specific single-forum conditionals
		if ( ( false === $retval ) && ! bp_is_action_variable( $this->topic_slug, 0 ) ) {
			$retval = true;
		}

		return $retval;
	}

	/**
	 * Ensure that bbp_is_single_topic() returns true on group forum topic pages.
	 *
	 * @see https://bbpress.trac.wordpress.org/ticket/2974
	 *
	 * @since 2.6.0 bbPress (r6220)
	 *
	 * @param  bool $retval Current boolean.
	 * @return bool
	 */
	public function is_single_topic( $retval = false ) {

		// Additional BuddyPress specific single-topic conditionals
		if ( ( false === $retval ) && bp_is_action_variable( $this->topic_slug, 0 ) && bp_action_variable( 1 ) ) {
			$retval = true;
		}

		return $retval;
	}

	/**
	 * The primary display function for group forums
	 *
	 * @since 2.1.0 bbPress (r3746)
	 *
	 * @param int $group_id ID of the current group. Available only on BP 2.2+.
	 */
	public function display( $group_id = null ) {

		// Prevent Topic Parent from appearing
		add_action( 'bbp_theme_before_topic_form_forum', array( $this, 'ob_start'     ) );
		add_action( 'bbp_theme_after_topic_form_forum',  array( $this, 'ob_end_clean' ) );
		add_action( 'bbp_theme_after_topic_form_forum',  array( $this, 'topic_parent' ) );

		// Prevent Forum Parent from appearing
		add_action( 'bbp_theme_before_forum_form_parent', array( $this, 'ob_start'     ) );
		add_action( 'bbp_theme_after_forum_form_parent',  array( $this, 'ob_end_clean' ) );
		add_action( 'bbp_theme_after_forum_form_parent',  array( $this, 'forum_parent' ) );

		// Hide breadcrumb
		add_filter( 'bbp_no_breadcrumb', '__return_true' );

		$this->display_forums( 0 );
	}

	/**
	 * Maybe unset the group forum nav item if group does not have a forum
	 *
	 * @since 2.3.0 bbPress (r4552)
	 *
	 * @return If not viewing a single group
	 */
	public function maybe_unset_forum_menu() {

		// Bail if not viewing a single group
		if ( ! bp_is_group() ) {
			return;
		}

		// Are forums enabled for this group?
		$checked = bp_get_new_group_enable_forum() || groups_get_groupmeta( bp_get_new_group_id(), 'forum_id' );

		// Tweak the nav item variable based on if group has forum or not
		$this->enable_nav_item = (bool) $checked;
	}

	/**
	 * Allow group members to have advanced privileges in group forum topics.
	 *
	 * @since 2.2.0 bbPress (r4434)
	 *
	 * @param array $caps
	 * @param string $cap
	 * @param int $user_id
	 * @param array $args
	 * @return array
	 */
	public function map_group_forum_meta_caps( $caps = array(), $cap = '', $user_id = 0, $args = array() ) {

		switch ( $cap ) {

			// If user is a group mmember, allow them to create content.
			case 'read_forum'          :
			case 'publish_replies'     :
			case 'publish_topics'      :
			case 'read_hidden_forums'  :
			case 'read_private_forums' :
				if ( bbp_group_is_banned() ) {
					$caps = array( 'do_not_allow' );
				} elseif ( bbp_group_is_member() || bbp_group_is_mod() || bbp_group_is_admin() ) {
					$caps = array( 'participate' );
				}
				break;

			// If user is a group mod ar admin, map to participate cap.
			case 'moderate'            :
			case 'edit_topic'          :
			case 'edit_reply'          :
			case 'view_trash'          :
			case 'edit_others_replies' :
			case 'edit_others_topics'  :
				if ( bbp_group_is_mod() || bbp_group_is_admin() ) {
					$caps = array( 'participate' );
				}
				break;

			// If user is a group admin, allow them to delete topics and replies.
			case 'delete_topic' :
			case 'delete_reply' :
				if ( bbp_group_is_admin() ) {
					$caps = array( 'participate' );
				}
				break;
		}

		// Filter & return
		return (array) apply_filters( 'bbp_map_group_forum_topic_meta_caps', $caps, $cap, $user_id, $args );
	}

	/**
	 * Remove the topic meta cap map, so it doesn't interfere with sidebars.
	 *
	 * @since 2.2.0 bbPress (r4434)
	 */
	public function remove_group_forum_meta_cap_map() {
		remove_filter( 'bbp_map_meta_caps', array( $this, 'map_group_forum_meta_caps' ), 99, 4 );
	}

	/**
	 * Validate the forum ID for a topic in a group forum.
	 *
	 * This method ensures that when a topic is saved, it is only allowed to be
	 * saved to a forum that is either:
	 *
	 * - A forum in the current group
	 * - A forum the current user can moderate outside of this group
	 *
	 * If all checks fail, an error gets added to prevent the topic from saving.
	 *
	 * @since 2.6.14
	 *
	 * @param int $topic_id
	 */
	public function validate_topic_forum_id( $topic_id = 0 ) {

		// Bail if no topic
		if ( empty( $topic_id ) ) {
			return;
		}

		// Get current forum ID
		$forum_id = bbp_get_topic_forum_id( $topic_id );

		// Bail if not a group forum
		if ( ! bbp_is_forum_group_forum( $forum_id ) ) {
			return;
		}

		// Get current group ID
		$group_id = bp_get_current_group_id();

		// Bail if unknown group ID
		if ( empty( $group_id ) ) {
			return;
		}

		// Get group forum IDs
		$forum_ids = bbp_get_group_forum_ids( $group_id );

		// Get posted forum ID
		$new_forum_id = ! empty( $_POST['bbp_forum_id'] )
			? absint( $_POST['bbp_forum_id'] )
			: 0;

		// Bail if new forum ID is a forum in this group
		if ( in_array( $new_forum_id, $forum_ids, true ) ) {
			return;
		}

		// Get the current user ID
		$user_id = bbp_get_current_user_id();

		// Bail if current user can moderate the new forum ID
		if ( bbp_is_user_forum_moderator( $user_id, $new_forum_id ) ) {
			return;
		}

		// If everything else has failed, then something is wrong and we need
		// to add an error to prevent this topic from saving.
		bbp_add_error( 'bbp_topic_forum_id', __( '<strong>Error</strong>: Forum ID is invalid.', 'bbpress' ) );
	}

	/**
	 * Validate the reply to for a reply in a group forum.
	 *
	 * This method ensures that when a reply to is saved, it is only allowed to
	 * be saved to the current topic.
	 *
	 * If all checks fail, an error gets added to prevent the reply from saving.
	 *
	 * @since 2.6.14
	 *
	 * @param int $reply_id
	 */
	public function validate_reply_to_id( $reply_id = 0 ) {

		// Bail if no reply
		if ( empty( $reply_id ) ) {
			return;
		}

		// Get posted reply to
		$new_reply_to = ! empty( $_POST['bbp_reply_to'] )
			? absint( $_POST['bbp_reply_to'] )
			: 0;

		// Bail if no reply to (assumes topic ID)
		if ( empty( $new_reply_to ) ) {
			return;
		}

		// Get current forum ID
		$forum_id = bbp_get_reply_forum_id( $reply_id );

		// Bail if not a group forum
		if ( ! bbp_is_forum_group_forum( $forum_id ) ) {
			return;
		}

		// Get current group ID
		$group_id = bp_get_current_group_id();

		// Bail if unknown group ID
		if ( empty( $group_id ) ) {
			return;
		}

		// Get current topic ID
		$topic_id = bbp_get_reply_topic_id( $reply_id );

		// Get topic reply IDs
		$reply_ids = bbp_get_public_child_ids( $topic_id, bbp_get_reply_post_type() );

		// Avoid recursion
		unset( $reply_ids[ $reply_id ] );

		// Bail if new reply parent ID is in this topic
		if ( in_array( $new_reply_to, $reply_ids, true ) ) {
			return;
		}

		// Add an error to prevent this reply from saving.
		bbp_add_error( 'bbp_reply_to_id', __( '<strong>Error</strong>: Reply To is invalid.', 'bbpress' ) );
	}

	/** Edit ******************************************************************/

	/**
	 * Show forums and new forum form when editing a group
	 *
	 * @since 2.1.0 bbPress (r3563)
	 *
	 * @param object $group (the group to edit if in Group Admin UI)
	 */
	public function edit_screen( $group = false ) {
		$forum_id  = 0;
		$group_id  = empty( $group->id ) ? bp_get_new_group_id() : $group->id;
		$forum_ids = bbp_get_group_forum_ids( $group_id );

		// Get the first forum ID
		if ( ! empty( $forum_ids ) ) {
			$forum_id = (int) is_array( $forum_ids ) ? $forum_ids[0] : $forum_ids;
		}

		// Should box be checked already?
		$checked = is_admin() ? bp_group_is_forum_enabled( $group ) : bp_get_new_group_enable_forum() || bp_group_is_forum_enabled( bp_get_group_id() ); ?>

		<h2><?php esc_html_e( 'Group Forum Settings', 'bbpress' ); ?></h2>

		<fieldset>
			<legend class="screen-reader-text"><?php esc_html_e( 'Group Forum Settings', 'bbpress' ); ?></legend>
			<p><?php esc_html_e( 'Create a discussion forum to allow members of this group to communicate in a structured, bulletin-board style fashion.', 'bbpress' ); ?></p>

			<div class="field-group">
				<div class="checkbox">
					<label for="bbp-edit-group-forum"><input type="checkbox" name="bbp-edit-group-forum" id="bbp-edit-group-forum" value="1"<?php checked( $checked ); ?> /> <?php esc_html_e( 'Yes. I want this group to have a forum.', 'bbpress' ); ?></label>
				</div>

				<p class="description"><?php esc_html_e( 'Saying no will not delete existing forum content.', 'bbpress' ); ?></p>
			</div>

			<?php if ( bbp_is_user_keymaster() ) : ?>
				<div class="field-group">
					<label for="bbp_group_forum_id"><?php esc_html_e( 'Group Forum:', 'bbpress' ); ?></label>
					<?php
						bbp_dropdown( array(
							'select_id' => 'bbp_group_forum_id',
							'show_none' => esc_html__( '&mdash; No forum &mdash;', 'bbpress' ),
							'selected'  => $forum_id
						) );
					?>
					<p class="description"><?php esc_html_e( 'Network administrators can reconfigure which forum belongs to this group.', 'bbpress' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( ! is_admin() ) : ?>
				<input type="submit" value="<?php esc_attr_e( 'Save Settings', 'bbpress' ); ?>" />
			<?php endif; ?>

		</fieldset>

		<?php

		// Verify intent
		if ( is_admin() ) {
			wp_nonce_field( 'groups_edit_save_' . $this->slug, 'forum_group_admin_ui' );
		} else {
			wp_nonce_field( 'groups_edit_save_' . $this->slug );
		}
	}

	/**
	 * Save the Group Forum data on edit
	 *
	 * @since 2.0.0 bbPress (r3465)
	 *
	 * @param int $group_id (to handle Group Admin UI hook bp_group_admin_edit_after )
	 */
	public function edit_screen_save( $group_id = 0 ) {

		// Bail if not a POST action
		if ( ! bbp_is_post_request() ) {
			return;
		}

		// Admin Nonce check
		if ( is_admin() ) {
			check_admin_referer( 'groups_edit_save_' . $this->slug, 'forum_group_admin_ui' );

		// Theme-side Nonce check
		} elseif ( ! bbp_verify_nonce_request( 'groups_edit_save_' . $this->slug ) ) {
			bbp_add_error( 'bbp_edit_group_forum_screen_save', __( '<strong>Error</strong>: Are you sure you wanted to do that?', 'bbpress' ) );
			return;
		}

		$edit_forum = ! empty( $_POST['bbp-edit-group-forum'] ) ? true : false;
		$forum_id   = 0;
		$group_id   = ! empty( $group_id ) ? $group_id : bp_get_current_group_id();

		// Keymasters have the ability to reconfigure forums
		if ( bbp_is_user_keymaster() ) {
			$forum_ids = ! empty( $_POST['bbp_group_forum_id'] ) ? (array) (int) $_POST['bbp_group_forum_id'] : array();

		// Use the existing forum IDs
		} else {
			$forum_ids = array_values( bbp_get_group_forum_ids( $group_id ) );
		}

		// Normalize group forum relationships now
		if ( ! empty( $forum_ids ) ) {

			// Loop through forums, and make sure they exist
			foreach ( $forum_ids as $forum_id ) {

				// Look for forum
				$forum = bbp_get_forum( $forum_id );

				// No forum exists, so break the relationship
				if ( empty( $forum ) ) {
					$this->remove_forum( array( 'forum_id' => $forum_id ) );
					unset( $forum_ids[ $forum_id ] );
				}
			}

			// No support for multiple forums yet
			$forum_id = (int) ( is_array( $forum_ids )
				? $forum_ids[0]
				: $forum_ids );
		}

		// Update the group ID and forum ID relationships
		bbp_update_group_forum_ids( $group_id, (array) $forum_ids );
		bbp_update_forum_group_ids( $forum_id, (array) $group_id  );

		// Update the group forum setting
		$group = $this->toggle_group_forum( $group_id, $edit_forum );

		// Create a new forum
		if ( empty( $forum_id ) && ( true === $edit_forum ) ) {

			// Set the default forum status
			switch ( $group->status ) {
				case 'hidden'  :
					$status = bbp_get_hidden_status_id();
					break;
				case 'private' :
					$status = bbp_get_private_status_id();
					break;
				case 'public'  :
				default        :
					$status = bbp_get_public_status_id();
					break;
			}

			// Create the initial forum
			$forum_id = bbp_insert_forum( array(
				'post_parent'  => bbp_get_group_forums_root_id(),
				'post_title'   => $group->name,
				'post_content' => $group->description,
				'post_status'  => $status
			) );

			// Setup forum args with forum ID
			$new_forum_args = array( 'forum_id' => $forum_id );

			// If in admin, also include the group ID
			if ( is_admin() && ! empty( $group_id ) ) {
				$new_forum_args['group_id'] = $group_id;
			}

			// Run the BP-specific functions for new groups
			$this->new_forum( $new_forum_args );
		}

		// Redirect after save when not in admin
		if ( ! is_admin() ) {
			bp_core_redirect( trailingslashit( $this->group_url( buddypress()->groups->current_group ) . '/admin/' . $this->slug ) );
		}
	}

	/**
	 * Adds a meta-box to BuddyPress Group Admin UI
	 *
	 * @since 2.3.0 bbPress (r4814)
	 */
	public function group_admin_ui_edit_screen() {
		add_meta_box(
			'bbpress_group_admin_ui_meta_box',
			_x( 'Discussion Forum', 'group admin edit screen', 'bbpress' ),
			array( $this, 'group_admin_ui_display_metabox' ),
			get_current_screen()->id,
			'side',
			'core'
		);
	}

	/**
	 * Displays the bbPress meta-box in BuddyPress Group Admin UI
	 *
	 * @since 2.3.0 bbPress (r4814)
	 *
	 * @param object $item (group object)
	 */
	public function group_admin_ui_display_metabox( $item ) {
		$this->edit_screen( $item );
	}

	/** Create ****************************************************************/

	/**
	 * Show forums and new forum form when creating a group
	 *
	 * @since 2.0.0 bbPress (r3465)
	 */
	public function create_screen( $group_id = 0 ) {

		// Bail if not looking at this screen
		if ( !bp_is_group_creation_step( $this->slug ) ) {
			return false;
		}

		// Check for possibly empty group_id
		if ( empty( $group_id ) ) {
			$group_id = bp_get_new_group_id();
		}

		$checked = bp_get_new_group_enable_forum() || groups_get_groupmeta( $group_id, 'forum_id' ); ?>

		<h2><?php esc_html_e( 'Group Forum', 'bbpress' ); ?></h2>

		<p><?php esc_html_e( 'Create a discussion forum to allow members of this group to communicate in a structured, bulletin-board style fashion.', 'bbpress' ); ?></p>

		<div class="checkbox">
			<label for="bbp-create-group-forum"><input type="checkbox" name="bbp-create-group-forum" id="bbp-create-group-forum" value="1"<?php checked( $checked ); ?> /> <?php esc_html_e( 'Yes. I want this group to have a forum.', 'bbpress' ); ?></label>
		</div>

		<?php
	}

	/**
	 * Save the Group Forum data on create
	 *
	 * @since 2.0.0 bbPress (r3465)
	 */
	public function create_screen_save( $group_id = 0 ) {

		// Nonce check
		if ( ! bbp_verify_nonce_request( 'groups_create_save_' . $this->slug ) ) {
			bbp_add_error( 'bbp_create_group_forum_screen_save', __( '<strong>Error</strong>: Are you sure you wanted to do that?', 'bbpress' ) );
			return;
		}

		// Check for possibly empty group_id
		if ( empty( $group_id ) ) {
			$group_id = bp_get_new_group_id();
		}

		$create_forum = ! empty( $_POST['bbp-create-group-forum'] ) ? true : false;
		$forum_id     = 0;
		$forum_ids    = bbp_get_group_forum_ids( $group_id );

		if ( ! empty( $forum_ids ) ) {
			$forum_id = (int) is_array( $forum_ids ) ? $forum_ids[0] : $forum_ids;
		}

		// Create a forum, or not
		switch ( $create_forum ) {
			case true  :

				// Bail if initial content was already created
				if ( ! empty( $forum_id ) ) {
					return;
				}

				// Set the default forum status
				switch ( bp_get_new_group_status() ) {
					case 'hidden'  :
						$status = bbp_get_hidden_status_id();
						break;
					case 'private' :
						$status = bbp_get_private_status_id();
						break;
					case 'public'  :
					default        :
						$status = bbp_get_public_status_id();
						break;
				}

				// Create the initial forum
				$forum_id = bbp_insert_forum( array(
					'post_parent'  => bbp_get_group_forums_root_id(),
					'post_title'   => bp_get_new_group_name(),
					'post_content' => bp_get_new_group_description(),
					'post_status'  => $status
				) );

				// Run the BP-specific functions for new groups
				$this->new_forum( array( 'forum_id' => $forum_id ) );

				// Update forum active
				groups_update_groupmeta( bp_get_new_group_id(), '_bbp_forum_enabled_' . $forum_id, true );

				// Toggle forum on
				$this->toggle_group_forum( bp_get_new_group_id(), true );

				break;
			case false :

				// Forum was created but is now being undone
				if ( ! empty( $forum_id ) ) {

					// Delete the forum
					wp_delete_post( $forum_id, true );

					// Delete meta values
					groups_delete_groupmeta( bp_get_new_group_id(), 'forum_id' );
					groups_delete_groupmeta( bp_get_new_group_id(), '_bbp_forum_enabled_' . $forum_id );

					// Toggle forum off
					$this->toggle_group_forum( bp_get_new_group_id(), false );
				}

				break;
		}
	}

	/**
	 * Used to start an output buffer
	 *
	 * @since 2.1.0 bbPress (r3746)
	 */
	public function ob_start() {
		ob_start();
	}

	/**
	 * Used to end an output buffer
	 *
	 * @since 2.1.0 bbPress (r3746)
	 */
	public function ob_end_clean() {
		ob_end_clean();
	}

	/**
	 * Creating a group forum or category (including root for group)
	 *
	 * @since 2.1.0 bbPress (r3653)
	 *
	 * @param array $forum_args
	 *
	 * @return void if no forum_id is available
	 */
	public function new_forum( $forum_args = array() ) {

		// Bail if no forum_id was passed
		if ( empty( $forum_args['forum_id'] ) ) {
			return;
		}

		// Validate forum_id
		$forum_id = bbp_get_forum_id( $forum_args['forum_id'] );
		$group_id = ! empty( $forum_args['group_id'] ) ? $forum_args['group_id'] : bp_get_current_group_id();

		bbp_add_forum_id_to_group( $group_id, $forum_id );
		bbp_add_group_id_to_forum( $forum_id, $group_id );
	}

	/**
	 * Removing a group forum or category (including root for group)
	 *
	 * @since 2.1.0 bbPress (r3653)
	 *
	 * @param array $forum_args
	 *
	 * @return void if no forum_id is available
	 */
	public function remove_forum( $forum_args = array() ) {

		// Bail if no forum_id was passed
		if ( empty( $forum_args['forum_id'] ) ) {
			return;
		}

		// Validate forum_id
		$forum_id = bbp_get_forum_id( $forum_args['forum_id'] );
		$group_id = ! empty( $forum_args['group_id'] ) ? $forum_args['group_id'] : bp_get_current_group_id();

		bbp_remove_forum_id_from_group( $group_id, $forum_id );
		bbp_remove_group_id_from_forum( $forum_id, $group_id );
	}

	/**
	 * Listening to BuddyPress Group deletion to remove the forum
	 *
	 * @since 2.3.0 bbPress (r4815)
	 *
	 * @param int $group_id The group ID
	 */
	public function disconnect_forum_from_group( $group_id = 0 ) {

		// Bail if no group ID available
		if ( empty( $group_id ) ) {
			return;
		}

		// Get the forums for the current group
		$forum_ids = bbp_get_group_forum_ids( $group_id );

		// Bail if no forum IDs available
		if ( empty( $forum_ids ) ) {
			return;
		}

		// Get the first forum ID
		$forum_id = (int) is_array( $forum_ids ) ? $forum_ids[0] : $forum_ids;
		$this->remove_forum( array(
			'forum_id' => $forum_id,
			'group_id' => $group_id
		) );
	}

	/**
	 * Update forum attributes to match those of the associated group.
	 *
	 * Fired whenever a group is saved
	 *
	 * @since 2.6.7 bbPress (r7208)
	 *
	 * @param BP_Groups_Group $group Group object.
	 */
	public static function update_group_forum( BP_Groups_Group $group ) {

		// Get group forum IDs
		$forum_ids = bbp_get_group_forum_ids( $group->id );

		// Bail if no forum IDs available
		if ( empty( $forum_ids ) ) {
			return;
		}

		// Loop through forum IDs
		foreach ( $forum_ids as $forum_id ) {

			// Get forum from ID
			$forum = bbp_get_forum( $forum_id );

			// Check for change
			if ( $group->status !== $forum->post_status ) {
				switch ( $group->status ) {

					// Changed to hidden
					case 'hidden' :
						bbp_hide_forum( $forum_id, $forum->post_status );
						break;

					// Changed to private
					case 'private' :
						bbp_privatize_forum( $forum_id, $forum->post_status );
						break;

					// Changed to public
					case 'public' :
					default :
						bbp_publicize_forum( $forum_id, $forum->post_status );
						break;
				}
			}
		}

		// Maybe update the first group forum title, content, and slug
		if ( ! empty( $forum_ids[0] ) ) {

			// Get forum from ID
			$forum = bbp_get_forum( $forum_ids[0] );

			// Only update the forum if changes are being made
			if (

				// Title
				( $forum->post_title !== $group->name )

				||

				// Content
				( $forum->post_content !== $group->description )

				||

				// Slug
				( $forum->post_name !== $group->slug )
			) {
				wp_update_post(
					array(
						'ID'           => $forum->ID,
						'post_title'   => $group->name,
						'post_content' => $group->description,
						'post_name'    => $group->slug
					)
				);
			}
		}
	}

	/**
	 * Toggle the enable_forum group setting on or off
	 *
	 * @since 2.3.0 bbPress (r4612)
	 *
	 * @param int $group_id The group to toggle
	 * @param bool $enabled True for on, false for off
	 * @return False if group is not found, otherwise return the group
	 */
	public function toggle_group_forum( $group_id = 0, $enabled = false ) {

		// Get the group
		$group = groups_get_group( array( 'group_id' => $group_id ) );

		// Bail if group cannot be found
		if ( empty( $group ) ) {
			return false;
		}

		// Set forum enabled status
		$group->enable_forum = (int) $enabled;

		// Save the group
		$group->save();

		// Maybe disconnect forum from group
		if ( empty( $enabled ) ) {
			$this->disconnect_forum_from_group( $group_id );
		}

		// Update internal private and forum ID variables
		bbp_repair_forum_visibility();

		// Return the group
		return $group;
	}

	/** Display Methods *******************************************************/

	/**
	 * Output the forums for a group in the edit screens
	 *
	 * As of right now, bbPress only supports 1-to-1 group forum relationships.
	 * In the future, many-to-many should be allowed.
	 *
	 * @since 2.1.0 bbPress (r3653)
	 */
	public function display_forums( $offset = 0 ) {

		// Allow actions immediately before group forum output
		do_action( 'bbp_before_group_forum_display' );

		// Load up bbPress once
		$bbp = bbpress();

		/** Query Resets ******************************************************/

		// Forum data
		$forum_action = bp_action_variable( $offset );
		$forum_ids    = bbp_get_group_forum_ids( bp_get_current_group_id() );
		$forum_id     = array_shift( $forum_ids );

		// Always load up the group forum
		bbp_has_forums( array(
			'p'           => $forum_id,
			'post_parent' => null
		) );

		// Set the global forum ID
		$bbp->current_forum_id = $forum_id;

		// Assume forum query
		bbp_set_query_name( 'bbp_single_forum' ); ?>

		<div id="bbpress-forums" class="bbpress-wrapper">

			<?php switch ( $forum_action ) :

				/** Single Forum **********************************************/

				case false  :
				case 'page' :

					// Strip the super stickies from topic query
					add_filter( 'bbp_get_super_stickies', array( $this, 'no_super_stickies'  ), 10, 1 );

					// Unset the super sticky option on topic form
					add_filter( 'bbp_get_topic_types',    array( $this, 'unset_super_sticky' ), 10, 1 );

					// Query forums and show them if they exist
					if ( bbp_forums() ) :

						// Setup the forum
						bbp_the_forum();

						// Only wrap title in H2 if not empty
						$title = bbp_get_forum_title();
						if ( ! empty( $title ) ) {
							echo '<h2>' . $title . '</h2>';
						}

						// Output forum
						bbp_get_template_part( 'content', 'single-forum' );

					// No forums found
					else : ?>

						<div id="message" class="info">
							<p><?php esc_html_e( 'This group does not currently have a forum.', 'bbpress' ); ?></p>
						</div>

					<?php endif;

					break;

				/** Single Topic **********************************************/

				case $this->topic_slug :

					// hide the 'to front' admin links
					add_filter( 'bbp_get_topic_stick_link', array( $this, 'hide_super_sticky_admin_link' ), 10, 2 );

					// Get the topic
					bbp_has_topics( array(
						'name'           => bp_action_variable( $offset + 1 ),
						'posts_per_page' => 1,
						'show_stickies'  => false
					) );

					// If no topic, 404
					if ( ! bbp_topics() ) {
						bp_do_404( bbp_get_forum_permalink( $forum_id ) );

						// Only wrap title in H2 if not empty
						$title = bbp_get_forum_title();
						if ( ! empty( $title ) ) {
							echo '<h2>' . $title . '</h2>';
						}

						// No topics
						bbp_get_template_part( 'feedback', 'no-topics' );
						break;
					}

					// Setup the topic
					bbp_the_topic();

					// Only wrap title in H2 if not empty
					$title = bbp_get_topic_title();
					if ( ! empty( $title ) ) {
						echo '<h2>' . $title . '</h2>';
					}

					// Topic edit
					if ( bp_action_variable( $offset + 2 ) === bbp_get_edit_slug() ) :

						// Unset the super sticky link on edit topic template
						add_filter( 'bbp_get_topic_types', array( $this, 'unset_super_sticky' ), 10, 1 );

						// Get the main query object
						$wp_query = bbp_get_wp_query();

						// Set the edit switches
						$wp_query->bbp_is_edit       = true;
						$wp_query->bbp_is_topic_edit = true;

						// Setup the global forum ID
						$bbp->current_topic_id       = get_the_ID();

						// Merge
						if ( ! empty( $_GET['action'] ) && 'merge' === $_GET['action'] ) :
							bbp_set_query_name( 'bbp_topic_merge' );
							bbp_get_template_part( 'form', 'topic-merge' );

						// Split
						elseif ( ! empty( $_GET['action'] ) && 'split' === $_GET['action'] ) :
							bbp_set_query_name( 'bbp_topic_split' );
							bbp_get_template_part( 'form', 'topic-split' );

						// Edit
						else :
							bbp_set_query_name( 'bbp_topic_form' );
							bbp_get_template_part( 'form', 'topic' );

						endif;

					// Single Topic
					else :
						bbp_set_query_name( 'bbp_single_topic' );
						bbp_get_template_part( 'content', 'single-topic' );
					endif;
					break;

				/** Single Reply **********************************************/

				case $this->reply_slug :

					// Get the reply
					bbp_has_replies( array(
						'name'           => bp_action_variable( $offset + 1 ),
						'posts_per_page' => 1
					) );

					// If no topic, 404
					if ( ! bbp_replies() ) {
						bp_do_404( bbp_get_forum_permalink( $forum_id ) );

						// Only wrap title in H2 if not empty
						$title = bbp_get_forum_title();
						if ( ! empty( $title ) ) {
							echo '<h2>' . $title . '</h2>';
						}

						// No replies
						bbp_get_template_part( 'feedback', 'no-replies' );
						break;
					}

					// Setup the reply
					bbp_the_reply();

					// Only wrap title in H2 if not empty
					$title = bbp_get_reply_title();
					if ( ! empty( $title ) ) {
						echo '<h2>' . $title . '</h2>';
					}

					if ( bp_action_variable( $offset + 2 ) === bbp_get_edit_slug() ) :

						// Get the main query object
						$wp_query = bbp_get_wp_query();

						// Set the edit switches
						$wp_query->bbp_is_edit       = true;
						$wp_query->bbp_is_reply_edit = true;

						// Setup the global reply ID
						$bbp->current_reply_id       = get_the_ID();

						// Move
						if ( ! empty( $_GET['action'] ) && ( 'move' === $_GET['action'] ) ) :
							bbp_set_query_name( 'bbp_reply_move' );
							bbp_get_template_part( 'form', 'reply-move' );

						// Edit
						else :
							bbp_set_query_name( 'bbp_reply_form' );
							bbp_get_template_part( 'form', 'reply' );
						endif;
					endif;
					break;
			endswitch;

			// Reset the query
			wp_reset_query(); ?>

		</div><!-- #bbpress-forums -->

		<?php

		// Allow actions immediately after group forum output
		do_action( 'bbp_after_group_forum_display' );
	}

	/** Super sticky filters ***************************************************/

	/**
	 * Strip super stickies from the topic query
	 *
	 * @since 2.3.0 bbPress (r4810)
	 *
	 * @access private
	 * @param array $super the super sticky post ID's
	 * @return array (empty)
	 */
	public function no_super_stickies( $super = array() ) {
		$super = array();
		return $super;
	}

	/**
	 * Unset the type super sticky from topic type
	 *
	 * @since 2.3.0 bbPress (r4810)
	 *
	 * @access private
	 * @param array $args
	 * @return array $args without the to-front link
	 */
	public function unset_super_sticky( $args = array() ) {
		if ( isset( $args['super'] ) ) {
			unset( $args['super'] );
		}
		return $args;
	}

	/**
	 * Ugly preg_replace to hide the to front admin link
	 *
	 * @since 2.3.0 bbPress (r4810)
	 *
	 * @access private
	 * @param string $retval
	 * @param array $args
	 * @return string $retval without the to-front link
	 */
	public function hide_super_sticky_admin_link( $retval = '', $args = array() ) {
		if ( strpos( $retval, '(' ) ) {
			$retval = preg_replace( '/(\(.+?)+(\))/i', '', $retval );
		}

		return $retval;
	}

	/** Redirect Helpers ******************************************************/

	/**
	 * Redirect to the group forum screen
	 *
	 * @since 2.1.0 bbPress (r3653)
	 *
	 * @param str $redirect_url
	 * @param str $redirect_to
	 */
	public function new_topic_redirect_to( $redirect_url = '', $redirect_to = '', $topic_id = 0 ) {
		if ( bp_is_group() ) {
			$topic        = bbp_get_topic( $topic_id );
			$topic_hash   = '#post-' . $topic_id;
			$redirect_url = trailingslashit( $this->group_url( groups_get_current_group() ) ) . trailingslashit( $this->slug ) . trailingslashit( $this->topic_slug ) . trailingslashit( $topic->post_name ) . $topic_hash;
		}

		return $redirect_url;
	}

	/**
	 * Redirect to the group forum screen
	 *
	 * @since 2.1.0 bbPress (r3653)
	 */
	public function new_reply_redirect_to( $redirect_url = '', $redirect_to = '', $reply_id = 0 ) {

		if ( bp_is_group() ) {
			$topic_id       = bbp_get_reply_topic_id( $reply_id );
			$topic          = bbp_get_topic( $topic_id );
			$reply_position = bbp_get_reply_position( $reply_id, $topic_id );
			$reply_page     = ceil( (int) $reply_position / (int) bbp_get_replies_per_page() );
			$reply_hash     = '#post-' . $reply_id;
			$topic_url      = trailingslashit( $this->group_url( groups_get_current_group() ) ) . trailingslashit( $this->slug ) . trailingslashit( $this->topic_slug ) . trailingslashit( $topic->post_name );

			// Don't include pagination if on first page
			if ( 1 >= $reply_page ) {
				$redirect_url = trailingslashit( $topic_url ) . $reply_hash;

			// Include pagination
			} else {
				$redirect_url = trailingslashit( $topic_url ) . trailingslashit( bbp_get_paged_slug() ) . trailingslashit( $reply_page ) . $reply_hash;
			}

			// Add topic view query arg back to end if it is set
			if ( bbp_get_view_all() ) {
				$redirect_url = bbp_add_view_all( $redirect_url );
			}
		}

		return $redirect_url;
	}

	/**
	 * Redirect to the group admin forum edit screen
	 *
	 * @since 2.1.0 bbPress (r3653)
	 */
	public function edit_redirect_to( $redirect_url = '' ) {

		// Get the current group, if there is one
		$group = groups_get_current_group();

		// If this is a group of any kind, empty out the redirect URL
		if ( bp_is_group_admin_screen( $this->slug ) ) {
			$redirect_url = trailingslashit( bp_get_root_domain() . '/' . bp_get_groups_root_slug() . '/' . $group->slug . '/admin/' . $this->slug );
		}

		return $redirect_url;
	}

	/** Form Helpers **********************************************************/

	/**
	 * Make Forum Parent a hidden field instead of a selectable one.
	 *
	 * @since 2.1.0 bbPress (r3746)
	 */
	public function forum_parent() {
	?>

		<input type="hidden" name="bbp_forum_parent_id" id="bbp_forum_parent_id" value="<?php bbp_group_forums_root_id(); ?>" />

	<?php
	}

	/**
	 * Output a dropdown for picking which group forum this topic is for.
	 *
	 * @since 2.1.0 bbPress (r3746)
	 */
	public function topic_parent() {

		// Get the group ID
		$gid       = bp_get_current_group_id();

		// Get the forum IDs for this group
		$forum_ids = bbp_get_group_forum_ids( $gid );

		// Attempt to get the current topic forum ID
		$topic_id  = bbp_get_topic_id();
		$forum_id  = bbp_get_topic_forum_id( $topic_id );

		// Setup the query arguments - note that these may be overridden later
		// by various bbPress visibility and capability filters.
		$args = array(
			'post_type'   => bbp_get_forum_post_type(),
			'post_status' => bbp_get_public_status_id(),
			'include'     => $forum_ids,
			'numberposts' => -1,
			'orderby'     => 'menu_order',
			'order'       => 'ASC',
		);

		// Get the forum objects for these forum IDs
		$forums = get_posts( $args );

		// Setup the dropdown arguments
		$dd_args = array(
			'posts'    => $forums,
			'selected' => $forum_id,
		); ?>

		<p>
			<label for="bbp_forum_id"><?php esc_html_e( 'Forum:', 'bbpress' ); ?></label><br />
			<?php bbp_dropdown( $dd_args ); ?>
		</p>

	<?php
	}

	/**
	 * Permissions to view the 'New Topic'/'Reply To' form in a BuddyPress group.
	 *
	 * @since 2.3.0 bbPress (r4608)
	 *
	 * @param bool $retval Are we allowed to view the reply form?
	 *
	 * @return bool
	 */
	public function form_permissions( $retval = false ) {

		// Bail if user is not logged in
		if ( ! is_user_logged_in() ) {
			return $retval;

		// Keymasters can always pass go
		} elseif ( bbp_is_user_keymaster() ) {
			$retval = true;

		// Non-members cannot see forms
		} elseif ( ! bbp_group_is_member() ) {
			$retval = false;

		// Banned users cannot see forms
		} elseif ( bbp_group_is_banned() ) {
			$retval = false;
		}

		return $retval;
	}

	/**
	 * Add a hidden input field on the group settings page if the group forum is
	 * enabled.
	 *
	 * Due to the way BuddyPress' group admin settings page saves its settings,
	 * we need to let BP know that bbPress added a forum.
	 *
	 * @since 2.4.0 bbPress (r5026)
	 *
	 * @link https://bbpress.trac.wordpress.org/ticket/2339/
	 * @see groups_screen_group_admin_settings()
	 */
	public function group_settings_hidden_field() {

		// if a forum is not enabled, we don't need to add this field
		if ( ! bp_group_is_forum_enabled() ) {
			return;
		} ?>

		<input type="hidden" name="group-show-forum" id="group-show-forum" value="1" />

	<?php
	}

	/** Permalink Mappers *****************************************************/

	/**
	 * Get the URL for a group.
	 *
	 * @since 2.6.14
	 *
	 * @param int $group_id
	 * @return string
	 */
	private function group_url( $group_id = 0 ) {

		// Default return value
		$retval = '';

		// BuddyPress < 12.0 (deprecated code is intentionally included)
		if ( function_exists( 'bp_get_group_permalink' ) ) {
			$retval = bp_get_group_permalink( $group_id );

		// BuddyPress > 12.0 (rewrite rules)
		} elseif ( function_exists( 'bp_get_group_url' ) ) {
			$retval = bp_get_group_url( $group_id );
		}

		// Return
		return $retval;
	}

	/**
	 * Get the management URL for a group.
	 *
	 * @since 2.6.14
	 *
	 * @param int $group_id
	 * @return string
	 */
	private function group_manage_url( $group_id = 0 ) {

		// Default return value
		$retval = '';

		// BuddyPress < 12.0 (deprecated code is intentionally included)
		if ( function_exists( 'bp_get_group_admin_permalink' ) ) {
			$retval = bp_get_group_admin_permalink( $group_id );

		// BuddyPress > 12.0 (rewrite rules)
		} elseif ( function_exists( 'bp_get_group_manage_url' ) ) {
			$retval = bp_get_group_manage_url( $group_id );
		}

		// Return
		return $retval;
	}

	/**
	 * Maybe map a bbPress forum/topic/reply permalink to the corresponding group
	 *
	 * @since 2.2.0 bbPress (r4266)
	 *
	 * @param int $post_id
	 * @return Bail early if not a group forum post
	 * @return string
	 */
	private function maybe_map_permalink_to_group( $post_id = 0, $url = false ) {

		switch ( get_post_type( $post_id ) ) {

			// Reply
			case bbp_get_reply_post_type() :
				$forum_id = bbp_get_reply_forum_id( $post_id );
				$url_end  = trailingslashit( $this->reply_slug ) . get_post_field( 'post_name', $post_id );
				break;

			// Topic
			case bbp_get_topic_post_type() :
				$forum_id = bbp_get_topic_forum_id( $post_id );
				$url_end  = trailingslashit( $this->topic_slug ) . get_post_field( 'post_name', $post_id );
				break;

			// Forum
			case bbp_get_forum_post_type() :
				$forum_id = $post_id;
				$url_end  = ''; //get_post_field( 'post_name', $post_id );
				break;

			// Unknown
			default :
				return $url;
		}

		// Get group ID's for this forum
		$group_ids = bbp_get_forum_group_ids( $forum_id );

		// Bail if the post isn't associated with a group
		if ( empty( $group_ids ) ) {
			return $url;
		}

		// @todo Multiple group forums/forum groups
		$group_id = $group_ids[0];
		$group    = groups_get_group( array( 'group_id' => $group_id ) );

		// Admin
		$group_permalink = bp_is_group_admin_screen( $this->slug )
			? $this->group_manage_url( $group )
			: $this->group_url( $group );

		return trailingslashit( trailingslashit( trailingslashit( $group_permalink ) . $this->slug ) . $url_end );
	}

	/**
	 * Map a forum permalink to its corresponding group
	 *
	 * @since 2.1.0 bbPress (r3802)
	 *
	 * @param string $url
	 * @param int $forum_id
	 * @return string
	 */
	public function map_forum_permalink_to_group( $url, $forum_id ) {
		return $this->maybe_map_permalink_to_group( $forum_id, $url );
	}

	/**
	 * Map a topic permalink to its group forum
	 *
	 * @since 2.1.0 bbPress (r3802)
	 *
	 * @param string $url
	 * @param int $topic_id
	 * @return string
	 */
	public function map_topic_permalink_to_group( $url, $topic_id ) {
		return $this->maybe_map_permalink_to_group( $topic_id, $url );
	}

	/**
	 * Map a reply permalink to its group forum
	 *
	 * @since 2.1.0 bbPress (r3802)
	 *
	 * @param string $url
	 * @param int $reply_id
	 * @return string
	 */
	public function map_reply_permalink_to_group( $url, $reply_id ) {
		return $this->maybe_map_permalink_to_group( bbp_get_reply_topic_id( $reply_id ), $url );
	}

	/**
	 * Map a reply edit link to its group forum
	 *
	 * @since 2.2.0 bbPress (r4266)
	 *
	 * @param string $url
	 * @param int $reply_id
	 * @return string
	 */
	public function map_reply_edit_url_to_group( $url, $reply_id ) {
		$new = $this->maybe_map_permalink_to_group( $reply_id );

		if ( empty( $new ) ) {
			return $url;
		}

		return trailingslashit( $new ) . bbpress()->edit_id  . '/';
	}

	/**
	 * Map a post link to its group forum
	 *
	 * @since 2.2.0 bbPress (r4266)
	 *
	 * @param string $url
	 * @param obj $post
	 * @param boolean $leavename
	 * @return string
	 */
	public function post_link( $url, $post ) {
		return $this->maybe_map_permalink_to_group( $post->ID, $url );
	}

	/**
	 * Map a page link to its group forum
	 *
	 * @since 2.2.0 bbPress (r4266)
	 *
	 * @param string $url
	 * @param int $post_id
	 * @param $sample
	 * @return string
	 */
	public function page_link( $url, $post_id ) {
		return $this->maybe_map_permalink_to_group( $post_id, $url );
	}

	/**
	 * Map a custom post type link to its group forum
	 *
	 * @since 2.2.0 bbPress (r4266)
	 *
	 * @param string $url
	 * @param obj $post
	 * @param $leavename
	 * @param $sample
	 * @return string
	 */
	public function post_type_link( $url, $post ) {
		return $this->maybe_map_permalink_to_group( $post->ID, $url );
	}

	/**
	 * Fix pagination of topics on forum view
	 *
	 * @since 2.2.0 bbPress (r4266)
	 *
	 * @param array $args
	 * @return array
	 */
	public function topic_pagination( $args ) {
		$new = $this->maybe_map_permalink_to_group( bbp_get_forum_id() );

		if ( empty( $new ) ) {
			return $args;
		}

		$args['base'] = trailingslashit( $new ) . bbp_get_paged_slug() . '/%#%/';

		return $args;
	}

	/**
	 * Fix pagination of replies on topic view
	 *
	 * @since 2.2.0 bbPress (r4266)
	 *
	 * @param array $args
	 * @return array
	 */
	public function replies_pagination( $args ) {
		$new = $this->maybe_map_permalink_to_group( bbp_get_topic_id() );
		if ( empty( $new ) ) {
			return $args;
		}

		$args['base'] = trailingslashit( $new ) . bbp_get_paged_slug() . '/%#%/';

		return $args;
	}

    /**
     * Fixes rewrite pagination in BuddyPress Group Forums & Topics.
	 *
     * Required for compatibility with BuddyPress > 12.0, where the /groups/
	 * rewrite rule will be caught before bbPress's /page/ rule.
	 *
	 * @since 2.6.14
	 *
	 * @param  object object  Verified object
	 * @param  string $type   Type of variable to check with `is_a()`
	 * @return mixed  $object Verified object if valid, Default or null if invalid
     */
    public function rewrite_pagination( $object, $type = '' ) {

		// Bail if wrong global
        if ( 'wp_query' !== $type ) {
            return $object;
        }

		// Bail if not inside a BuddyPress Group
        if ( ! bp_is_group() ) {
            return $object;
        }

		// Bail if not inside a BuddyPress Group Forum
        if ( ! bp_is_current_action( 'forum' ) ) {
            return $object;
        }

		// Default "paged" value
        $page_number = null;

        // Can't use bbp_is_single_topic() because it triggers a loop.
        $is_single_topic = bp_is_action_variable( 'topic', 0 );

		// Single Topic
        if ( true === $is_single_topic ) {

			// Get the page number from 3rd position
            if ( bp_is_action_variable( 'page', 2 ) ) {
                $page_number = bp_action_variable( 3 );
            }

		// Single Forum
        } else {

			// Get the page number from 1st position
            if ( bp_is_action_variable( 'page', 0 ) ) {
                $page_number = bp_action_variable( 1 );
            }
        }

		// Bail if no page number
        if ( empty( $page_number ) ) {
            return $object;
        }

		// Set the 'paged' WP_Query var to the new action-based value
        $object->set( 'paged', $page_number );

		// Return the filtered/modified object
        return $object;
    }

	/**
	 * Ensure that forum content associated with a BuddyPress group can only be
	 * viewed via the group URL.
	 *
	 * @since 2.1.0 bbPress (r3802)
	 */
	public function redirect_canonical() {

		// Bail if on a RSS feed
		if ( is_feed() ) {
			return;
		}

		// Viewing a single forum
		if ( bbp_is_single_forum() ) {
			$forum_id  = get_the_ID();
			$group_ids = bbp_get_forum_group_ids( $forum_id );

		// Viewing a single topic
		} elseif ( bbp_is_single_topic() ) {
			$topic_id  = get_the_ID();
			$slug      = get_post_field( 'post_name', $topic_id );
			$forum_id  = bbp_get_topic_forum_id( $topic_id );
			$group_ids = bbp_get_forum_group_ids( $forum_id );

		// Not a forum or topic
		} else {
			return;
		}

		// Bail if not a group forum
		if ( empty( $group_ids ) ) {
			return;
		}

		// Use the first group ID
		$group_id 	 = $group_ids[0];
		$group    	 = groups_get_group( array( 'group_id' => $group_id ) );
		$group_link  = trailingslashit( $this->group_url( $group ) );
		$redirect_to = trailingslashit( $group_link . $this->slug );

		// Add topic slug to URL
		if ( bbp_is_single_topic() ) {
			$redirect_to  = trailingslashit( $redirect_to . $this->topic_slug . '/' . $slug );
		}

		bp_core_redirect( $redirect_to );
	}

	/** Activity **************************************************************/

	/**
	 * Map a forum post to its corresponding group in the group activity stream.
	 *
	 * @since 2.2.0 bbPress (r4396)
	 *
	 * @param array $args Arguments from BBP_BuddyPress_Activity::record_activity()
	 *
	 * @return array
	 */
	public function map_activity_to_group( $args = array() ) {

		// Get current BP group
		$group = groups_get_current_group();

		// Not posting from a BuddyPress group? stop now!
		if ( empty( $group ) ) {
			return $args;
		}

		// Set the component to 'groups' so the activity item shows up in the group
		$args['component']         = buddypress()->groups->id;

		// Move the forum post ID to the secondary item ID
		$args['secondary_item_id'] = $args['item_id'];

		// Set the item ID to the group ID so the activity item shows up in the group
		$args['item_id']           = $group->id;

		// Update the group's last activity
		groups_update_last_activity( $group->id );

		return $args;
	}
}
endif;
