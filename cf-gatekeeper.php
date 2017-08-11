<?php
/**
 * Plugin Name: CF Gatekeeper
 * Description: Redirect to login page if the user is not logged in.
 * Author: Crowd Favorite
 * Author URI: http://crowdfavorite.com
 * Version: 1.8.3
 *
 * @package cf_gatekeeper
 */

define( 'CF_GATEKEEPER', true );
define( 'CFGK_VER', '1.8.3' );

// Load localization library.
load_plugin_textdomain( 'cf_gatekeeper' );

/**
 * Main function.
 *
 * @return void
 */
function cf_gatekeeper() {
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		return;
	}

	global $current_user;
	if ( ! isset( $current_user ) || empty( $current_user->ID ) ) {
		global $cf_user_api;
		$cf_user_api->key_login();
	}
	$user_capability = apply_filters( 'cf_gatekeeper_capability', 'read' );
	$gatekeeper_enabled = apply_filters( 'cf_gatekeeper_enabled', true );
	if ( ! current_user_can( $user_capability ) && $gatekeeper_enabled ) {
		$login_page = site_url( 'wp-login.php' );
		is_ssl() ? $proto = 'https://' : $proto = 'http://';
		$requested = ( ! empty( $_SERVER['HTTP_HOST'] ) && ! empty( $_SERVER['REQUEST_URI'] ) )
			? $proto . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']
			: '';
		if ( $login_page !== substr( $requested, 0, strlen( $login_page ) ) ) {
			auth_redirect();
		}
	}
}
if ( ! defined( 'XMLRPC_REQUEST' ) ) {
	// This needs to run at 11+ as to run after cfgk_process_users.
	// And to catch any filters running at default priority 10.
	add_action( 'init', 'cf_gatekeeper', 12 );
}

/**
 * User API class.
 */
class CF_User_API {
	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {
	}

	/**
	 * Generate a key for a given user.
	 *
	 * @param  integer $user_id User id.
	 * @return string           User key.
	 */
	function generate_key( $user_id ) {
		return md5( $user_id . AUTH_KEY );
	}

	/**
	 * Add key to a given user.
	 *
	 * @param integer $user_id User id.
	 * @param string  $key     User key.
	 * @return void
	 */
	function add_key_to_user( $user_id, $key = null ) {
		if ( is_null( $key ) ) {
			$key = $this->generate_key( $user_id );
		}
		update_user_meta( $user_id, 'cf_user_key', $key );
	}

	/**
	 * Generate and assign keys to all users that should have a key.
	 *
	 * @return void
	 */
	function process_users() {
		global $wpdb;
		$keyed_users = $wpdb->get_results(
			'SELECT user_id FROM ' . $wpdb->usermeta . ' WHERE meta_key = "cf_user_key"'
		);

		$user_ids = array();
		foreach ( $keyed_users as $user_id ) {
			if ( is_object( $user_id ) ) {
				$user_ids[] = $user_id->user_id;
			} elseif ( is_int( $user_id ) ) {
				$user_ids[] = $user_id;
			} else {
				return;
			}
		}

		if ( is_array( $user_ids ) && count( $user_ids ) > 0 ) {
			$where = ' WHERE ID NOT IN (' . implode( ', ', $user_ids ) . ') ';
		} else {
			$where = ' ';
		}
		$users = $wpdb->get_results( 'SELECT * FROM ' . $wpdb->users . $where );

		if ( count( $users ) ) {
			foreach ( $users as $user ) {
				$this->add_key_to_user( $user->ID );
			}
		}
	}

	/**
	 * Login user based on key.
	 *
	 * @return boolean True on success.
	 */
	function key_login() {
		if ( ! empty( $_GET['cf_user_key'] ) ) {
			global $wpdb;
			$query = $wpdb->prepare( '
				SELECT user_id
				FROM ' . $wpdb->usermeta . '
				WHERE meta_key = "cf_user_key"
				AND meta_value = %s',
				$wpdb->escape( stripslashes( $_GET['cf_user_key'] ) )
			);

			$user_id = (int) $wpdb->get_var( $query );

			if ( $user_id > 0 ) {
				wp_set_current_user( $user_id );
				return true;
			}
		}
		return false;
	}
}

/**
 * Generate and assign keys to all users that should have a key.
 *
 * @return void
 */
function cfgk_process_users() {
	global $cf_user_api;

	// Make sure we have an object to deal with.
	// This was throwing Fatal Errors on plugin activation without the check.
	if ( ! is_object( $cf_user_api ) ) {
		$cf_user_api = new CF_User_API();
	}
	$cf_user_api->process_users();

	// Don't turn on by default.
	update_option( 'cfgk_enabled', '0' );
}

// Do inital assignment of cf_user_key's.
add_action( 'init', 'cfgk_process_users' );

/**
 * Generate and assign key to user.
 *
 * @param integer $user_id User id.
 * @param string  $unused  Obsolete param.
 * @return void
 */
function cfgk_add_key_to_user( $user_id, $unused = null ) {
	global $cf_user_api;
	$cf_user_api->add_key_to_user( $user_id );
}

$cf_user_api = new CF_User_API();

add_action( 'user_register', 'cfgk_add_key_to_user' );
add_action( 'profile_update', 'cfgk_add_key_to_user' );

/**
 * Generate API feed URLs.
 *
 * @param  string $url Original URL.
 * @return string      Updated URL.
 */
function cfgk_user_api_feeds( $url ) {
	global $userdata;
	if ( ! empty( $userdata->ID ) ) {
		$key = get_user_meta( $userdata->ID, 'cf_user_key', true );
		if ( ! empty( $key ) ) {
			if ( false !== strpos( $url, '?' ) ) {
				$url .= '&amp;cf_user_key=' . rawurlencode( $key );
			} else {
				$url .= '?cf_user_key=' . rawurlencode( $key );
			}
		}
	}
	return $url;
}
add_filter( 'feed_link', 'cfgk_user_api_feeds' );
add_filter( 'category_feed_link', 'cfgk_user_api_feeds' );
add_filter( 'tag_feed_link', 'cfgk_user_api_feeds' );
add_filter( 'search_feed_link', 'cfgk_user_api_feeds' );
add_filter( 'author_feed_link', 'cfgk_user_api_feeds' );
add_filter( 'post_comments_feed_link', 'cfgk_user_api_feeds' );

/**
 * Display API key element in page.
 *
 * @return void
 */
function cfgk_show_api_key() {
	global $profileuser;
	$key = get_user_meta( $profileuser->ID, 'cf_user_key' );
	if ( is_array( $key ) && ! empty( $key ) ) {
		$key = $key[0];
	}
	?>
	<table class="form-table">
	<tr>
		<th><label for="description"><?php esc_html_e( 'Gatekeeper API Key', 'cf_gatekeeper' ); ?></label></th>
		<td><span><?php echo esc_html( $key ); ?></span></td>
	</tr>
	</table>
	<?php
}
add_action( 'show_user_profile', 'cfgk_show_api_key' );
add_action( 'edit_user_profile', 'cfgk_show_api_key' );

/**
 * Request handler.
 *
 * @return void
 */
function cfgk_request_handler() {
	if ( ! isset( $_POST['cf_action'] ) ) {
		return;
	}
	$page = ( ! empty( $_GET['page'] ) ) ? $_GET['page'] : '';

	switch ( $_POST['cf_action'] ) {
		case 'save_gatekeeper_options':
			if ( update_option( 'cfgk_enabled', $_POST['cfgk_enable_gatekeeper'] ) ) {
				if ( get_option( 'cfgk_enabled' ) ) {
					do_action( 'cfgk_enabled' );
					$message_id = 1;
				} else {
					do_action( 'cfgk_disabled' );
					$message_id = 2;
				}

				$query_args = array(
					'page' => $page,
					'updated' => true,
					'message' => $message_id,
				);
				// Redirect properly, with a message id.
				wp_safe_redirect( basename( $_SERVER['SCRIPT_NAME'] ) . '?page=' . $page . '&updated=true&message=' . $message_id );
				exit;
			}

			// Nothing updated.
			wp_safe_redirect( basename( $_SERVER['SCRIPT_NAME'] ) . '?page=' . $page );
			exit;
			break;
		default:
			break;
	}
}
add_action( 'init', 'cfgk_request_handler' );

/**
 * Display the settings form.
 *
 * @return void
 */
function cfgk_settings_form() {
	$option_value = get_option( 'cfgk_enabled' );

	$enabled_options = array(
		'Yes' => '1',
		'No' => '0',
	);

	?>
	<div class="wrap">
		<h2>CF Gatekeeper</h2>
		<?php
		if ( ! empty( $_GET['message'] ) ) :
			do_action( 'cfgk_settings_form_notices', $_GET['message'] );
		endif;
		?>
		<form method="post">
			<div>
			<label for="cfgk_enable_gatekeeper">Enable Gatekeeper?</label>
			<p>
				<?php foreach ( $enabled_options as $label => $value ) : ?>
					<input type="radio" name="cfgk_enable_gatekeeper"
						id="cfgk_enable_gatekeeper_<?php echo esc_attr( strtolower( $label ) ); ?>"
						value="<?php echo esc_attr( $value ); ?>"
						<?php checked( $option_value, $value, true ); ?> />
					<label for="cfgk_enable_gatekeeper_<?php echo esc_attr( strtolower( $label ) ); ?>"><?php echo esc_attr( $label ); ?></label>
				<?php endforeach; ?>
			</p>
			</div>
			<button type="submit" class="button-primary">Save Option</button>
			<input type="hidden" name="cf_action" value="save_gatekeeper_options" />
		</form>
	</div>
	<?php
}

/**
 * Removing for this version.
 *
 * @return void
 */
function cfgk_admin_menu() {
	if ( current_user_can( 'manage_options' ) ) {
		add_options_page(
			__( 'CF Gatekeeper', '' ),
			__( 'CF Gatekeeper', '' ),
			'manage_options',
			'cf-gatekeeper',
			'cfgk_settings_form'
		);
	}
}

// Disabled in this version.
// add_action( 'admin_menu', 'cfgk_admin_menu' );
