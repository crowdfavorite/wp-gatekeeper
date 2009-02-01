<?php

/*
Plugin Name: Gatekeeper
Description: Redirect to login page if the user is not logged in.
Author: Crowd Favorite
Author URI: http://crowdfavorite.com
Version: 1.0
*/

define('CF_GATEKEEPER', true);

if (!defined('PLUGINDIR')) {
	define('PLUGINDIR','wp-content/plugins');
}
if (is_file(trailingslashit(ABSPATH.PLUGINDIR).'cf-gatekeeper.php')) {
	define('CFSZ_FILE', trailingslashit(ABSPATH.PLUGINDIR).'cf-gatekeeper.php');
}
else if (is_file(trailingslashit(ABSPATH.PLUGINDIR).'cf-gatekeeper/cf-gatekeeper.php')) {
	define('CFSZ_FILE', trailingslashit(ABSPATH.PLUGINDIR).'cf-gatekeeper/cf-gatekeeper.php');
}

register_activation_hook(CFSZ_FILE, 'cfgk_process_users');

load_plugin_textdomain('cf_gatekeeper');

function cf_gatekeeper() {
	global $userdata;
	if (!isset($userdata) || empty($userdata->ID)) {
		global $cf_user_api;
		if (!$cf_user_api->key_login()) {
			$login_page = site_url('wp-login.php');
			is_ssl() ? $proto = 'https://' : $proto = 'http://';
			$requested = $proto.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
			if (substr($requested, 0, strlen($login_page)) != $login_page) {
				auth_redirect();
			}
		}
	}
}
add_action('init', 'cf_gatekeeper');

class cf_user_api {
	function generate_key($user_id) {
		return md5($user_id.AUTH_KEY);
	}
	
	function add_key_to_user($user_id, $key = null) {
		if (is_null($key)) {
			$key = $this->generate_key($user_id);
		}
		update_usermeta($user_id, 'cf_user_key', $key);
	}
	
	function process_users() {
		global $wpdb;
		$keyed_users = $wpdb->get_results("
			SELECT user_id
			FROM $wpdb->usermeta 
			WHERE meta_key = 'cf_user_key'
		");
		$user_ids = array();
		foreach ($keyed_users as $user_id) {
			$user_ids[] = $user_id;
		}
		if (count($user_ids)) {
			$where = ' WHERE ID NOT IN ('.implode(',', $user_ids).') ';

		}
		else {
			$where = ' ';
		}
		$users = $wpdb->get_results("
			SELECT *
			FROM $wpdb->users
			$where
		");
		if (count($users)) {
			foreach ($users as $user) {
				$this->add_key_to_user($user->ID);
			}
		}
	}
	
	function key_login() {
		if (!empty($_GET['cf_user_key'])) {
			global $wpdb;
			$user_id = $wpdb->get_var("
				SELECT user_id
				FROM $wpdb->usermeta
				WHERE meta_key = 'cf_user_key'
				AND meta_value = '".$wpdb->escape(stripslashes($_GET['cf_user_key']))."'
			");
			$user_id = intval($user_id);
			if ($user_id > 0) {
				setup_userdata($user_id);
				return true;
			}
		}
		return false;
	}
}

function cfgk_process_users() {
	global $cf_user_api;
	$cf_user_api->process_users();
}

function cfgk_add_key_to_user($user_id, $unused = null) {
	global $cf_user_api;
	$cf_user_api->add_key_to_user($user_id);
}

$cf_user_api = new cf_user_api();

add_action('user_register', 'cfgk_add_key_to_user');
add_action('profile_update', 'cfgk_add_key_to_user');

function cfgk_user_api_feeds($url) {
	global $userdata;
	if (!empty($userdata->ID)) {
		$key = get_usermeta($userdata->ID, 'cf_user_key');
		if (!empty($key)) {
			if (strpos($url, '?') !== false) {
				$url .= '&amp;cf_user_key='.urlencode($key);
			}
			else {
				$url .= '?cf_user_key='.urlencode($key);
			}
		}
	}
	return $url;
}
add_filter('feed_link', 'cfgk_user_api_feeds');
add_filter('category_feed_link', 'cfgk_user_api_feeds');
add_filter('tag_feed_link', 'cfgk_user_api_feeds');
add_filter('search_feed_link', 'cfgk_user_api_feeds');
add_filter('author_feed_link', 'cfgk_user_api_feeds');
add_filter('post_comments_feed_link', 'cfgk_user_api_feeds');

function cfgk_show_api_key() {
	global $profileuser;
	$key = get_usermeta($profileuser->ID, 'cf_user_key');
?>
<table class="form-table">
<tr>
	<th><label for="description"><?php _e('API Key', 'cf_gatekeeper'); ?></label></th>
	<td><span><?php echo $key; ?></span></td>
</tr>
</table>
<?php
}
add_action('show_user_profile', 'cfgk_show_api_key');
add_action('edit_user_profile', 'cfgk_show_api_key');

?>