## CF Gatekeeper

The CF Gatekeeper plugin gives admin users the ability to restrict viewing a site to logged in users with a selected user level.  The plugin adds a dashboard option for restricting the display as well as a settings page.  When turned on, the plugin checks the level of the current user to see if they are at or above the permissions level set.  If the user is not logged in, or if they are logged in and do not have high enough permissions, they will be directed to a login screen.

The plugin also adds and API key to each user so they will have the ability to view items like RSS feeds even if CF Gatekeeper is turned on.  The API key will also only work is the user associated with the API has permissions at or above the selected level.

### Dashboard/Settings Page

The plugin has added options to both the Dashboard and CF Gatekeeper settings page of the site.  When the plugin is first run, the users for the site will need to be processed so the API keys can be set.  This will need to be done for each blog that the CF Gatekeeper plugin is used on.

#### Processing Users

To process users:

- Login to the WP Admin
- On the Dashboard, click on the Process Users Button
	- Once the process script has run, the process button will only show up on the Settings page
- On the Settings Page, click on the Process Users Button

After the user processing is complete for a blog, all new users created will automatically be processed upon creation

#### Blocking Access to the Site

To block access to the site:

- Login to the WP Admin
- On the Dashboard
	- Find the CF Gatekeeper settings panel
		- Set the Enable radio button to Yes
		- Select the User level to restrict users
- On the Settings Page
	- Set the Enable radio button to Yes
	- Select the User level to restrict users

### Gatekeeper Blocking

The CF Gatekeeper plugin has been designed so work can be done on a blog without leaving it open to view from the outside.  The plugin has Tie-Ins to other plugins also prevent viewing in other areas of the site.  The plugins included with the Tie-In are CF Links, CF Global Posts, and CF Advanced Search.  All of these plugins will remove any reference to a CF Gatekeeper restricted blog if the current logged in user does not have access to view the blog.  If the user does have access to view the blog, everything will display as normal.
