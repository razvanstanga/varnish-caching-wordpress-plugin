=== Varnish/Nginx Proxy Caching ===
Donate link: https://www.paypal.me/razvanstanga
Contributors: razvanstanga
Tags: varnish, nginx, purge, cache, caching, optimization, performance, traffic
Requires at least: 4.0
Tested up to: 5.2
Requires PHP: 5.2.4
Stable tag: 1.8.3
License: GPL-3.0-or-later

Wordpress Varnish Cache 3.x/4.x/5.x and Nginx Proxy Cache integration

== Description ==
Complete Wordpress Varnish Cache 3.x/4.x/5.x and Nginx Proxy Cache integration.

This plugin handles all integration with Varnish Cache and Nginx Proxy Cache. It was designed for high traffic websites.

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

This plugin also auto purges Varnish Cache / Nginx Proxy Cache when your site is modified.

Varnish Caching sends a PURGE request to Varnish Cache / Nginx Proxy Cache when a page or post is modified. This occurs when editing, publishing, commenting or deleting an item, and when changing themes.
Not all pages are purged every time, depending on your Varnish / Nginx Proxy Cache configuration. When a post, page, or custom post type is edited, or a new comment is added, <em>only</em> the following pages will purge:

* The front page
* The post/page edited
* Any categories or tags associated with the page

<a href="https://www.varnish-cache.org/" target="_blank">Varnish Cache</a> / <a href="https://www.nginx.com/blog/nginx-caching-guide/" target="_blank">Nginx Proxy Cache</a> is a web application accelerator also known as a caching HTTP reverse proxy. You install it in front of any server that speaks HTTP and configure it to cache the contents. This plugin <em>does not</em> install Varnish/Nginx for you, nor does it configure Varnish/Nginx for WordPress. It's expected you already did that on your own using the provided config files.

Inspired from the following :

* https://wordpress.org/plugins/varnish-http-purge/
* https://github.com/dreamhost/varnish-vcl-collection/

== Installation ==

* You must install Varnish Cache/Nginx Proxy Cache on your server(s)
* Go to the configuration generator. Fill in the backends/ACLs then download the configuration files
* Use these configuration files to configure Varnish Cache server(s). Usualy the configuration files are in /etc/varnish. In most cases you must put the downloaded configuration files in /etc/varnish and restart Varnish Cache
* The configuration generator does not support Nginx for now. Read the Nginx documentation on how to enable proxy cache

Or use the provided Varnish Cache configuration files located in /wp-content/plugins/vcaching/varnish-conf folder.

You can also use the purge key method if you can't setup ACLs. You must fill in lib/purge.vcl the purge key.

== Frequently Asked Questions ==

= What version of Varnish Cache is supported? =

This was built and tested on Varnish 3.x/4.x/5.x.

= Is Nginx supported? =

Nginx is supported if you configure Nginx to use the PURGE method. Read the official Nginx documentaton on how to do this. Also there are a lot of tutorials to do this.

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

= Available filters =

* `vcaching_varnish_ips` - change the IPs set in Settings
* `vcaching_varnish_hosts` - change the Hosts set in Settings
* `vcaching_events` - add events to trigger the purge
* `vcaching_schema` - change the schema (default is http://)
* `vcaching_purge_urls` - add additional URLs to purge

== Changelog ==

= 1.8.3 =
* scheduled posts flush

= 1.8.2 =
* readme.txt update

= 1.8.1 =
* typo fix

= 1.8.0 =
* Nginx Proxy Cache support, uninstall script, use ssl optimization

= 1.7.0 =
* various bugfixes

= 1.6.9 =
* fixed php notice

= 1.6.8 =
* fixed wp-cli calling an older method name

= 1.6.7 =
* use sys_get_temp_dir() to address open_basedir restictions to /tmp. thanks @maltfield

= 1.6.6 =
* no more SSl auto detection. If you use SSL with Varnish use the option 'Use SSL (https://) for purge requests.'
* there are cases where the website uses SSL, but the Varnish servers do not

= 1.6.5 =
* added sslverify set default to false to wp_remote_request. thanks @Jules81

= 1.6.4 =
* fixed php notice

= 1.6.3 =
* added SSL to schema filter. thanks @Jules81

= 1.6.2 =
* fixed purge_post empty 2nd param

= 1.6.1 =
* Do/do not purge when saving menus option
* fixed bug showing multiple `Truncate message activated ...`

= 1.6 =
* Varnish 5.x support

= 1.5.5 =
* fixed ob_end_flush error in wp-admin while debug is on. thanks @samlangdon

= 1.5.4 =
* improvements to Varnish configs like websocket support, remove the Google Analytics added parameters, strip hash, remove unnecessary cookies. thanks @pavelprischepa

= 1.5.3 =
* hardcoded on/off VCL Generator, filters added to readme

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
