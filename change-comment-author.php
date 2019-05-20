<?php
/*
Plugin Name: Change Comment Author
Version: 0.1.1
Description: Allows admins to update/edit the authors of existing comments in wp-admin. Also adds dropdown next to comment box for selecting an alternate user to comment as.
Author: WebDevStudios
Author URI: https://webdevstudios.com/
Plugin URI: https://webdevstudios.com/
Text Domain: wds-change-comment-author
Domain Path: /languages
*/

class WDS_Change_Comment_Author {

	/**
	 * Selected user object
	 */
	protected $user_data = false;

	/**
	 * Array of user options for the dropdowns
	 */
	protected $user_options = [];

	/**
	 * Hook in to WordPress
	 */
	public function hooks() {
		add_action( 'comment_form', array( $this, 'select_commentor_dropbox' ), 99 );
		add_action( 'add_meta_boxes_comment', array( $this, 'select_commentor_dropbox' ) );
		add_filter( 'preprocess_comment', array( $this, 'frontend_set_commentor_for_comment' ) );
		add_filter( 'pre_user_id', array( $this, 'set_commentor_id' ) );
		add_filter( 'pre_comment_author_name', array( $this, 'set_comment_author_name' ) );
		add_filter( 'pre_comment_author_url', array( $this, 'set_comment_author_url' ) );
		add_filter( 'pre_comment_author_email', array( $this, 'set_comment_author_email' ) );
		add_filter( 'edit_comment', array( $this, 'set_commentor_id_for_sure' ) );

		add_action( 'admin_footer', array( $this, 'append_comment_dropdown' ) );
	}

	/**
	 * Adds a User dropdown to allow admins to assign comments to users
	 *
	 * Use the wds_change_comment_author_select filter to modify output
	 */
	public function select_commentor_dropbox() {
		if ( ! $this->has_permission() ) {
			return;
		}

		$curr_user = get_current_user_id();

		$select = '';
		$select .= '<p class="comment_author_selection_wrapper">';
		$select .= '<label>' . __( 'Comment as:', 'wds-change-comment-author' ) . '<br>';
		$select .= '<select name="comment_author_selection" class="comment_author_selection">';
		$select .= '<option>' . __( 'Select A User', 'wds-change-comment-author' ) . '</option>';
		foreach ( $this->get_user_options() as $id => $name ) {
			$select .= '<option value="'. $id .'" '. selected( $id, $curr_user, false ) .'>'. $name .'</option>';
		}
		$select .= '</select>';
		$select .= '</label>';
		$select .= '</p>';

		/**
		 * Filter the user dropdown select output
		 *
		 * @param string $can_edit User dropdown select output
		 */
		echo apply_filters( 'wds_change_comment_author_select', $select );
	}

	/**
	 * Gathers the information for the chosen user to assign a comment to
	 *
	 * @param  array  $commentdata Array of comment data to save
	 *
	 * @return array               Possibly modified array of comment data to save
	 */
	public function frontend_set_commentor_for_comment( $commentdata ) {
		$userdata = $this->get_userdata();

		if ( false !== $userdata && isset( $userdata->ID ) ) {
			$commentdata['user_ID']              = $userdata->ID;
			$commentdata['comment_author']       = $userdata->user_login;
			$commentdata['comment_author_email'] = $userdata->user_email;
			$commentdata['comment_author_url']   = $userdata->user_url;
		}

		return $commentdata;
	}

	/**
	 * Maybe modify the saved user ID
	 *
	 * @param mixed  $original_value Original user ID
	 *
	 * @return mixed                 Possibly modified ID
	 */
	public function set_commentor_id( $user_id ) {
		return $this->set_comment_author_param( $user_id, 'ID' );
	}

	/**
	 * Maybe modify the saved user name
	 *
	 * @param mixed  $original_value Original user name
	 *
	 * @return mixed                 Possibly modified name
	 */
	public function set_comment_author_name( $author_name ) {
		return $this->set_comment_author_param( $author_name, 'user_login' );
	}

	/**
	 * Maybe modify the saved user email
	 *
	 * @param mixed  $original_value Original user email
	 *
	 * @return mixed                 Possibly modified email
	 */
	public function set_comment_author_email( $user_email ) {
		return $this->set_comment_author_param( $user_email, 'user_email' );
	}

	/**
	 * Maybe modify the saved user url
	 *
	 * @param mixed  $original_value Original user url
	 *
	 * @return mixed                 Possibly modified url
	 */
	public function set_comment_author_url( $user_url ) {
		return $this->set_comment_author_param( $user_url, 'user_url' );
	}

	/**
	 * Handles returning a modified user paramater
	 *
	 * @param mixed  $original_value Original user value
	 * @param string $param          User param to modify
	 *
	 * @return mixed                 Possibly modified value
	 */
	public function set_comment_author_param( $value, $param ) {
		$userdata = $this->get_userdata();

		if ( false !== $userdata && isset( $userdata->ID ) ) {
			$value = $userdata->{$param};
		}

		return $value;
	}

	/**
	 * WordPress doesn't allow you to set the user_id in the
	 * wp_update_comment function, so we need to do it manually
	 *
	 * Will only run if we've passed other areas.
	 *
	 * @param int  $comment_ID The comment ID of the comment to update
	 */
	public function set_commentor_id_for_sure( $comment_ID ) {
		global $wpdb;

		$userdata = $this->get_userdata();

		if ( false === $userdata || ! isset( $userdata->ID ) ) {
			return;
		}

		$comment = get_comment( $comment_ID, ARRAY_A );

		$data = wp_array_slice_assoc( $comment, array( 'comment_content', 'comment_author', 'comment_author_email', 'comment_approved', 'comment_karma', 'comment_author_url', 'comment_date', 'comment_date_gmt', 'comment_parent' ) );

		$data['user_id'] = $userdata->ID;

		$wpdb->update( $wpdb->comments, $data, compact( 'comment_ID' ) );
	}

	/**
	 * Retrieve an array of users for a select dropdown
	 *
	 * @return array  Array of users. user_id => user name/email
	 */
	public function get_user_options() {

		if ( ! empty( $this->user_options ) ) {
			return $this->user_options;
		}

		$results = $this->get_user_query_results();
		if ( empty( $results ) ) {
			return [];
		}

		foreach ( $results as $user ) {
			if ( isset( $user->ID, $user->data ) ) {
				$this->user_options[ $user->ID ] = $user->data->display_name . ' ('. $user->data->user_email .')';
			}
		}

		asort( $this->user_options, SORT_STRING );

		return $this->user_options;
	}

	/**
	 * Get an array of user objects
	 *
	 * Can be overridden with wds_change_comment_author_pre_get_users
	 * or wds_change_comment_author_get_users
	 *
	 * @return array  Array of user objects
	 */
	public function get_user_query_results() {
		global $wpdb;

		/**
		 * Filter the query results before it is retrieved. Allows overriding query
		 *
		 * Passing an array value to the filter will short-circuit retrieving
		 * the query results, returning the passed value instead.
		 *
		 * @param array|mixed $results Query results. Default null to skip it.
		 */
		$results = apply_filters( 'wds_change_comment_author_pre_get_users', null );

		if ( is_array( $results ) ) {
			return $results;
		}

		$blog_id = get_current_blog_id();

		// Get users who are not 'subscribers'
		$user_query = new WP_User_Query( array(
			'meta_query' => array(
				array(
					'key'     => $wpdb->get_blog_prefix( $blog_id ) . 'capabilities',
					'value'   => 'subscriber',
					'compare' => 'NOT LIKE'
				),
			)
		) );

		$results = empty( $user_query->results ) ? [] : (array) $user_query->results;

		/**
		 * Filter the query results after they are retrieved.
		 *
		 * @param array $results Array of user query results.
		 */
		$results = apply_filters( 'wds_change_comment_author_get_users', $results );

		return $results;
	}

	/**
	 * Retrieve user data for the user selected in the dropdown
	 *
	 * @return WP_User object | false  If successful, a WP_User object
	 */
	public function get_userdata() {
		if ( isset( $_POST['user_ID'] ) ) {
			$userid = (int) $_POST['user_ID'];
			$this->user_data = get_userdata( $userid );
			return $this->user_data;
		}

		if ( ! isset( $_POST['comment_author_selection'] ) || ! $_POST['comment_author_selection'] ) {
			return false;
		}

		if ( ! $this->has_permission() ) {
			return false;
		}

		if ( $this->user_data ) {
			return $this->user_data;
		}
		$this->user_data = get_userdata( absint( $_POST['comment_author_selection'] ) );
		return $this->user_data;
	}

	/**
	 * Permission level for editing comment authors
	 *
	 * Can be overridden with the	wds_change_comment_author_can_edit filter
	 *
	 * @return boolean Whether current user has permission to edit comment authors
	 */
	public function has_permission() {
		/**
		 * Filter the permission level for being able to edit comment authors
		 *
		 * @param bool $can_edit Whether current user can edit comment authors
		 */
		return current_user_can( apply_filters( 'wds_change_comment_author_capability', 'manage_options' ) );
	}

	public function append_comment_dropdown() {
		$screen = get_current_screen();
		if ( ! isset( $screen->id ) || 'edit-comments' !== $screen->id ) {
			return;
		}

		?>
<div id="wds-commentor-dropdown" style="padding: 0 5px;">
	<?php echo $this->select_commentor_dropbox(); ?>
</div>
<script type="text/javascript">
	jQuery( document ).ready( function( $ ){
		var $the_author = $('#user_ID'),
			$the_dropdown = $('#wds-commentor-dropdown'),
			$reply_location = $('#replyhead');

		$( 'body').on( 'change', 'select[name="comment_author_selection"]', function(){
			var new_id = $( this ).val();
			$the_author.attr( 'value', new_id );
		} );

		$reply_location.append( $the_dropdown );

	});
</script>
		<?php
	}

}

$GLOBALS['wds_change_comment_author'] = new WDS_Change_Comment_Author();
$GLOBALS['wds_change_comment_author']->hooks();
