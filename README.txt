## CF Gatekeeper

The CF Gatekeeper plugins provides admin users with the ability to block access to an entire site, except for users that are logged in.  When the plugin is activated, it will redirect all logged out users to the wp-login.php page.

The plugin also adds an API key to each user so they will have the ability to view items like RSS feeds even if CF Gatekeeper is turned on.  

### Blocking Access to the Site

To block access to a site, simply activate the plugin on the `Plugins` page in the WordPress Admin.

### User Keys

To find the User Key for a particular user, simply open the User Edit screen for that user, and look for the `API Key` section.  To use a user key, simply add `?cf_user_key=##KEY##` to the end of any URL on the site.
