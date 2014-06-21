<?php

/**
 * Adds a Subscriptions tab to user profiles.
 */
class BP_Subscribe2_Component extends BP_Component {
	/**
	 * Initial component setup.
	 */
	public function __construct() {
		parent::start(
			// Unique component ID
			'bps2',

			// Used by BP when listing components (eg in the Dashboard)
			__( 'BP Subscribe2', 'bp-display-user-posts' )
		);

		// Catch <form> submits
		add_action( 'bp_actions', array( $this, 'catch_form_submit' ) );
	}

	/**
	 * Set up component data, as required by BP.
	 */
	public function setup_globals( $args = array() ) {
		parent::setup_globals( array(
			'slug'          => 'subscriptions',
			'has_directory' => false,
		) );
	}

	/**
	 * Set up component navigation, and register display callbacks.
	 */
	public function setup_nav( $main_nav = array(), $sub_nav = array() ) {
		$main_nav = array(
			'name'                => __( 'Subscriptions', 'bp-subscribe2' ),
			'slug'                => $this->slug,
			'position'            => 45,
			'default_subnav_slug' => 'my-subscriptions',
			'screen_function'     => array( $this, 'screen_function_my_subscriptions' ),
		);

		$sub_nav[] = array(
			'name'            => __( 'My Subscriptions', 'subscribe2' ),
			'slug'            => 'my-subscriptions',
			'parent_slug'     => 'subscriptions',
			'parent_url'      => bp_displayed_user_domain() . 'subscriptions/',
			'screen_function' => array( $this, 'screen_function_my_subscriptions' ),
		);

		$sub_nav[] = array(
			'name'            => __( 'Manage', 'subscribe2' ),
			'slug'            => 'manage',
			'parent_slug'     => 'subscriptions',
			'parent_url'      => bp_displayed_user_domain() . 'subscriptions/',
			'screen_function' => array( $this, 'screen_function_manage' ),
			'user_has_access' => bp_is_my_profile(),
		);

		parent::setup_nav( $main_nav, $sub_nav );
	}

	/**
	 * Set up display screen logic for my-subscriptions subnav.
	 *
	 * We are using BP's plugins.php template as a wrapper, which is
	 * the easiest technique for compatibility with themes.
	 */
	public function screen_function_my_subscriptions() {
		add_action( 'bp_template_content', array( $this, 'my_subscriptions_content' ) );
		bp_core_load_template( 'members/single/plugins' );
	}

	/**
	 * Set up display screen logic for manage subnav.
	 *
	 * We are using BP's plugins.php template as a wrapper, which is
	 * the easiest technique for compatibility with themes.
	 */
	public function screen_function_manage() {
		add_action( 'bp_template_content', array( $this, 'manage_content' ) );
		bp_core_load_template( 'members/single/plugins' );
	}

	/**
	 * Markup for the My Subscriptions subtab.
	 */
	public function my_subscriptions_content() {
		$subscriptions = $this->get_subscriptions();

		if ( ! empty( $subscriptions ) ) {
			echo '<p>' . sprintf( __( '%s is subscribed to the following sites:', 'bp-subscribe2' ), bp_get_displayed_user_fullname() ) . '</p>';

			echo '<ul>';
			foreach ( $subscriptions as $s ) {
				printf(
					'<li><a href="%s">%s</a></li>',
					esc_url( $s['blog_url'] ),
					esc_html( $s['blog_name'] )
				);
			}
			echo '</ul>';
		} else {
			echo '<p>' . __( 'This user has no subscriptions.', 'bp-subscribe2' ) . '</p>';
		}
	}

	/**
	 * Markup for the Manage subtab.
	 */
	public function manage_content() {
		echo '<form method="post" action="">';

		$subscriptions = $this->get_subscriptions();

		if ( ! empty( $subscriptions ) ) {
			echo '<p>' . __( 'Use the checkboxes below to unsubscribe from any sites you are following:', 'bp-subscribe2' ) . '</p>';

			echo '<ul>';
			foreach ( $subscriptions as $s ) {
				printf(
					'<li><input type="checkbox" value="%d" name="unsubscribe_ids[]" /> <a href="%s">%s</a></li>',
					intval( $s['blog_id'] ),
					esc_url( $s['blog_url'] ),
					esc_html( $s['blog_name'] )
				);
			}
			echo '</ul>';

			wp_nonce_field( 'bp-subscribe2-unsubscribe' );
			echo '<input type="submit" name="bp-subscribe2-submit" value="' . __( 'Unsubscribe', 'bp-subscribe2' ) . '" />';
		} else {
			echo '<p>' . __( 'You have no subscriptions to manage.', 'bp-subscribe2' ) . '</p>';
		}

		echo '</form>';
	}

	/**
	 * Catch form submit and process.
	 */
	public function catch_form_submit() {
		global $wpdb;

		// Bail if this is not a submit of our form
		if ( ! isset( $_POST['bp-subscribe2-submit'] ) ) {
			return;
		}

		$redirect_url = bp_displayed_user_domain() . '/subscriptions/manage/';

		// Bail if the nonce check fails
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'bp-subscribe2-unsubscribe' ) ) {
			bp_core_add_message( __( 'Please try again.', 'bp-subscribe2' ), 'error' );
			bp_core_redirect( $redirect_url );
			die();
		}

		// Bail if the current user doesn't have the right to edit this
		if ( ! bp_is_my_profile() && ! current_user_can( 'bp_moderate' ) ) {
			bp_core_add_message( __( 'Please try again.', 'bp-subscribe2' ), 'error' );
			bp_core_redirect( $redirect_url );
			die();
		}

		// Collect and sanitize IDs of sites to unsubscribe from
		$unsubscribe_ids = array();
		if ( isset( $_POST['unsubscribe_ids'] ) ) {
			$unsubscribe_ids = wp_parse_id_list( $_POST['unsubscribe_ids'] );
		}

		// Bail if there's nothing to do
		if ( empty( $unsubscribe_ids ) ) {
			bp_core_redirect( $redirect_url );
			die();
		}

		// Process the unsubscriptions
		foreach ( $unsubscribe_ids as $ui ) {
			$meta_key = $wpdb->get_blog_prefix( $ui ) . 's2_subscribed';
			delete_user_meta( bp_displayed_user_id(), $meta_key );
		}

		bp_core_add_message( __( 'You have successfully unsubscribed.', 'bp-subscribe2' ), 'success' );
		bp_core_redirect( $redirect_url );
		die();
	}

	/**
	 * Build an array of Subscribe2 subscriptions for the displayed user.
	 */
	protected function get_subscriptions() {
		global $wpdb;

		$subscriptions = array();

		$user_id = bp_displayed_user_id();
		$user_subscribed = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key LIKE '%%s2_subscribed%%'", $user_id ) );

		foreach ( $user_subscribed as $s ) {
			// Get the blog ID
			preg_match( '/wp_([0-9]+)_s2_subscribed/', $s->meta_key, $matches );

			if ( ! empty( $matches[1] ) ) {
				$blog_id = intval( $matches[1] );
			} else {
				$blog_id = 1;
			}

			// Get some other blog goodies
			$blog_name = get_blog_option( $blog_id, 'blogname' );
			$blog_url  = get_blog_option( $blog_id, 'home' );

			$subscriptions[] = array(
				'blog_id'   => $blog_id,
				'blog_name' => $blog_name,
				'blog_url'  => $blog_url,
			);
		}

		return $subscriptions;
	}
}

/**
 * Bootstrap the component.
 */
function bps2_init() {
	buddypress()->bps2 = new BP_Subscribe2_Component();
}
add_action( 'bp_loaded', 'bps2_init' );
