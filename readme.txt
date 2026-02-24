=== Scheduled Content Dashboard ===
Contributors: jeangalea
Tags: scheduled, dashboard, widget, posts, editorial calendar
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display all your scheduled posts, pages, and custom post types in a convenient dashboard widget with quick edit links.

== Description ==

Scheduled Content Dashboard adds a widget to your WordPress admin dashboard that displays all content scheduled for future publication. Get a quick overview of what's coming up without navigating through multiple screens.

= Features =

* **Dashboard Widget** - See all scheduled content at a glance on your main dashboard
* **All Content Types** - Displays scheduled posts, pages, and any public custom post types
* **Smart Grouping** - Content is organized by time: Today, Tomorrow, This Week, Next Week, and Later
* **Quick Edit Access** - Click any title to go directly to the edit screen
* **Clean Interface** - Native WordPress styling that integrates seamlessly with your dashboard
* **Lightweight** - No external dependencies, minimal database queries

= Use Cases =

* Editorial teams managing content calendars
* Bloggers scheduling posts in advance
* Agencies managing multiple client sites
* Anyone who schedules content and wants a quick overview

= Privacy =

This plugin does not:

* Collect any user data
* Send data to external servers
* Use cookies
* Track users

All data displayed is already stored in your WordPress database.

== Installation ==

= Automatic Installation =

1. Go to Plugins > Add New in your WordPress admin
2. Search for "Scheduled Content Dashboard"
3. Click "Install Now" and then "Activate"
4. Visit your Dashboard to see the widget

= Manual Installation =

1. Download the plugin zip file
2. Go to Plugins > Add New > Upload Plugin
3. Upload the zip file and click "Install Now"
4. Activate the plugin
5. Visit your Dashboard to see the widget

= FTP Installation =

1. Download and extract the plugin zip file
2. Upload the `scheduled-content-dashboard` folder to `/wp-content/plugins/`
3. Activate the plugin through the Plugins menu in WordPress
4. Visit your Dashboard to see the widget

== Frequently Asked Questions ==

= Where does the widget appear? =

The widget appears on your main WordPress admin dashboard (the page you see when you first log in). Look for the "Scheduled Content" widget. You can drag it to reposition it among your other dashboard widgets.

= What content types are displayed? =

The plugin displays all scheduled content from any public post type, including:

* Posts
* Pages
* Any registered public custom post types (products, events, portfolios, etc.)

= How many scheduled items are shown? =

The widget displays up to 50 scheduled items, ordered by scheduled publication date (soonest first).

= Can I customize the time groupings? =

The current version uses fixed groupings: Today, Tomorrow, This Week, Next Week, and Later. Custom groupings may be added in a future version.

= Does this work with Gutenberg/Block Editor? =

Yes, this plugin works regardless of which editor you use. It simply displays scheduled content and links to the standard edit screens.

= Does this work with Multisite? =

Yes, the plugin works with WordPress Multisite. Each site will have its own dashboard widget showing that site's scheduled content.

= The widget is not showing. What should I do? =

1. Make sure the plugin is activated
2. Check that you have scheduled content (posts/pages with a future publish date)
3. Go to your main Dashboard (not a submenu page)
4. Click "Screen Options" at the top and ensure "Scheduled Content" is checked

= Can I hide the widget? =

Yes, click "Screen Options" at the top of your Dashboard and uncheck "Scheduled Content" to hide the widget.

== Screenshots ==

1. The Scheduled Content widget on the WordPress dashboard showing grouped scheduled posts
2. Widget displaying multiple content types including posts, pages, and custom post types
3. Empty state when no content is scheduled

== Changelog ==

= 1.0.0 =
* Initial release
* Dashboard widget with scheduled content display
* Smart time-based grouping
* Support for all public post types
* Quick edit links for each item

== Upgrade Notice ==

= 1.0.0 =
Initial release of Scheduled Content Dashboard.
