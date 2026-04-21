=== Scheduled Content Dashboard ===
Contributors: jeangalea
Tags: scheduled, dashboard, widget, editorial calendar, missed schedule
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

See every scheduled post, page, and custom post type on your WordPress dashboard. Auto-fixes missed schedules. Lightweight, no bloat.

== Description ==

Scheduled Content Dashboard gives you a clean, at-a-glance view of everything queued up for publication. It adds a dashboard widget grouping scheduled content by when it's due, flags posts WordPress failed to publish on time, and quietly republishes them for you.

Most scheduling plugins bolt on social sharing, complex settings, and marketing upsells. This one doesn't. It does one thing — show what's scheduled and keep it publishing — and stays out of your way.

= Features =

* **Dashboard widget** — scheduled content grouped by Today, Tomorrow, This Week, Next Week, and Later
* **Missed-schedule detection** — posts stuck in `future` status past their date are flagged in red
* **Auto-fix missed schedules** — the plugin quietly republishes stuck posts on admin page loads (most competitors gate this in a paid tier)
* **One-click "Publish now"** — manually push any missed post live from the widget
* **Admin bar counter** — see how many posts are scheduled (and if any are missed) from anywhere in the admin
* **"Mine only" toggle** — multi-author sites can filter the widget to the current user's content
* **All public post types** — posts, pages, products, events, custom post types
* **Quick edit links** — click any title to go straight to the edit screen
* **Zero config** — no settings page, no onboarding, just install and use
* **Privacy-friendly** — no tracking, no external requests, no cookies

= Use cases =

* Editorial teams managing a content calendar
* Bloggers scheduling posts in advance
* Agencies juggling multiple client sites
* Anyone frustrated by WordPress missing scheduled publish times

= Privacy =

This plugin does not collect data, send data to external servers, use cookies, or track users. Everything displayed is already in your WordPress database.

= Developer hooks =

`scheduled_content_dashboard_query_args` — filter the WP_Query args used for the scheduled content list.

`scheduled_content_dashboard_auto_fix_missed` — return `false` to disable the auto-publish of missed scheduled posts.

== Installation ==

1. Go to Plugins > Add New in your WordPress admin
2. Search for "Scheduled Content Dashboard"
3. Click Install Now, then Activate
4. Visit your Dashboard to see the widget

== Frequently Asked Questions ==

= Where does the widget appear? =

On your main WordPress admin dashboard. You can drag it to reposition it among your other widgets.

= What content types are displayed? =

All public post types: posts, pages, and any custom post type registered as public (products, events, portfolios, etc.).

= What is a "missed schedule"? =

WordPress uses wp-cron to publish scheduled posts at their designated time. If cron doesn't fire (low traffic sites, server cron issues, fatal errors), posts stay stuck in `future` status past their publish date. This plugin detects those posts, flags them, and by default auto-publishes them next time you load an admin page.

= How do I disable auto-fix? =

Add this to your theme's `functions.php` or a mu-plugin:

`add_filter( 'scheduled_content_dashboard_auto_fix_missed', '__return_false' );`

You'll still see missed posts flagged in the widget with a manual "Publish now" button.

= How many scheduled items are shown? =

Up to 50 per group, ordered by scheduled date (soonest first).

= Does this work with Gutenberg / the block editor? =

Yes. The plugin displays scheduled content and links to standard edit screens — editor-agnostic.

= Does this work with Multisite? =

Yes. Each site has its own widget showing that site's scheduled content.

= The widget isn't showing — what now? =

1. Confirm the plugin is activated
2. Confirm you have scheduled content (posts with future publish dates)
3. On the Dashboard, click Screen Options at the top and make sure "Scheduled Content" is ticked

== Screenshots ==

1. The Scheduled Content widget grouping posts by when they're due
2. Missed-schedule detection flagging stuck posts with a "Publish now" button
3. Admin bar counter showing scheduled post count

== Changelog ==

= 1.1.0 =
* Added: Missed-schedule detection with red flagging in the widget
* Added: Auto-fix missed scheduled posts (admin-page-load cron, filterable)
* Added: One-click "Publish now" button for missed posts
* Added: Admin bar counter showing scheduled + missed post counts
* Added: "Mine only" toggle to filter the widget by current user
* Added: `scheduled_content_dashboard_auto_fix_missed` filter
* Changed: Scheduled items query skips missed posts (they render in their own group)

= 1.0.0 =
* Initial release
* Dashboard widget with grouped scheduled content
* Support for all public post types

== Upgrade Notice ==

= 1.1.0 =
Adds missed-schedule detection with free auto-fix, admin bar counter, and per-user filtering. Auto-fix is on by default — disable with the scheduled_content_dashboard_auto_fix_missed filter if you want manual control.
