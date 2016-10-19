=== Varnish Caching ===
Donate link: www.paypal.com/use/email/razvan_stanga@yahoo.com
Contributors: razvanstanga
Tags: varnish, purge, cache, caching, optimization, performance, traffic
Requires at least: 4.0
Tested up to: 4.6
Stable tag: 1.5.2
License: GPLv2 or later

Wordpress Varnish Cache 3.x/4.x integration

== Description ==
Complete Wordpress Varnish Cache 3.x/4.x integration.

This plugin handles all integration with Varnish Cache. It was designed for high traffic websites.

Main features

* admin interface, see screenshots
* console for manual purges, supports regular expressions so you can purge an entire folder or just a single file
* supports every type of Varnish Cache implementation, see screenshots for examples
* unlimited number of Varnish Cache servers
* use of custom headers when communicating with Varnish Cache does not interfere with other caching plugins, cloudflare, etc
* Varnish Cache configuration generator
* purge key method so you don't need to setup ACLs
* debugging
* actively maintained

You can control the following from the Varnish Caching admin panel :

* Enable/Disable caching
* Homepage cache TTL
* Cache TTL (for every other page)
* IPs/Hosts to clear cache to support every type of Varnish Cache implementation
* Override default TTL in posts/pages
* Purge key based PURGE
* Logged in cookie
* Debugging option
* console for precise manual purges

This plugin also auto purges Varnish Cache when your site is modified.

Varnish Caching sends a PURGE request to Varnish Cache when a page or post is modified. This occurs when editing, publishing, commenting or deleting an item, and when changing themes.
Not all pages are purged every time, depending on your Varnish configuration. When a post, page, or custom post type is edited, or a new comment is added, <em>only</em> the following pages will purge:

* The front page
* The post/page edited
* Any categories or tags associated with the page

<a href="https://www.varnish-cache.org/">Varnish Cache</a> is a web application accelerator also known as a caching HTTP reverse proxy. You install it in front of any server that speaks HTTP and configure it to cache the contents. This plugin <em>does not</em> install Varnish for you, nor does it configure Varnish for WordPress. It's expected you already did that on your own using the provided config files.

Inspired from the following :

* https://wordpress.org/plugins/varnish-http-purge/
* https://github.com/dreamhost/varnish-vcl-collection/

== Installation ==

* You must install Varnish Cache on your server(s)
* Go to the configuration generator. Fill in the backends/ACLs then download the configuration files
* Use these configuration files to configure Varnish Cache server(s). Usualy the configuration files are in /etc/varnish. In most cases you must put the downloaded configuration files in /etc/varnish and restart Varnish Cache

Or use the provided Varnish Cache configuration files located in /wp-content/plugins/vcaching/varnish-conf folder.

You can also use the purge key method if you can't setup ACLs. You must fill in lib/purge.vcl the purge key.

== Frequently Asked Questions ==

= What version of Varnish is supported? =

This was built and tested on Varnish 3.x/4.x.

= Why doesn't every page flush when I make a new post? =

The only pages that should purge are the post's page, the front page, categories, and tags.

= How do I manually purge the whole cache? =

Click the 'Purge ALL Varnish Cache' button on the "Right Now" Dashboard.

= How do I manually purge cache? =

Use the console. For example you can purge the whole uploads folder with the URL /wp-content/uploads/.*

= Does this work with W3 Total Cache? =

Yes it does. This plugin uses its own custom headers to communicate with Varnish and does not interfere with the heders sent by W3 Total Cache or any other caching plugin.

= Varnish Statistics =

Statistics need a special setup. More info on the Statistics tab on your Wordpress environment.

= How do I configure my Varnish Cache VCL? =

Use the Varnish Cache configuration generator. Fill in the backends/ACLs then download your configuration files.
Or use the provided Varnish Cache configuration files located in /wp-content/plugins/vcaching/varnish-conf folder.

= Can I use this with a proxy service like CloudFlare? =

Yes.

= What is logged in cookie? =

Logged in cookie is a special cookie this plugin sets upon user login. Varnish Cache uses this cookie to bypass caching for logged in users.

This is a small step towards securing your site for denial of service attacks. Denial of service attacks can happen if the attacker bypasses Varnish Cache and hits the backend directly.
With the current configuration and the way Wordpress works, this can still happen with POST/AJAX requests.

== Changelog ==

= 1.5.2 =
* added AMP URL purge

= 1.5.1 =
* fixed PHP notices
* tested with 4.6

= 1.5 =
* `Purge from Varnish` post/page action link
* removed 10 chars logged in cookie restriction
* code cleanup/some wp coding standards
* vcaching_varnish_ips filter
* vcaching_varnish_hosts filter

= 1.4.3 =
* Truncate option added for too many 'trying to purge' messages. Added check for ZipArchive class to download VCLs.

= 1.4.2 =
* Bugfix release. Replaced home_url with plugins_url to show VCaching image

= 1.4.1 =
* Do not cache static files on admin domain

= 1.4 =
* Varnish Cache configuration generator
* added `logged in cookie`. This replaces the logged in admin/user based on Wordpress standard cookies to bypass caching
* moved backends to conf/backend.vcl
* moved ACLs to conf/acl.vcl
* updated VCLs. If you are using 1.3 VCLs should upgrade to 1.4

= 1.3.3 =
* support for Varnish 4

= 1.3.2 =
* bugfix displaying a single server stats

= 1.3.1 =
* better varnish statistics, generated by varnishstat

= 1.3 =
* varnish statistics

= 1.2.3 =
* wordpress 4.4 compatibility
* Romanian language file

= 1.2.1 =
* wp cli

= 1.2 =
* console for precise manual purge

= 1.1 =
* Play nice with W3 Total Cache

= 1.0 =
* Initial commit

== Upgrade Notice ==

= 1.4 =
Users using 1.3 Varnish Cache VCLs should upgrade to 1.4

== Screenshots ==

1. Settings admin panel
2. Console/manual purge admin panel
3. Varnish Cache Statistics admin panel
4. Varnish Cache configuration generator admin panel
5. override default TTL in posts/pages
6. integration example
