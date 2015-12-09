=== VCaching ===
Donate: PAYPAL razvan_stanga@yahoo.com
Contributors: razvanstanga
Tags: varnish, purge, cache
Requires at least: 4.0
Tested up to: 4.4
Stable tag: 1.3
License: GPLv2 or later

Varnish Cache Wordpress integration

== Description ==
Varnish Cache Wordpress integration

You can control from the Varnish Cache admin panel the following :

* Enable/Disable caching
* Homepage cache TTL
* Cache TTL (for every other page)
* IPs/Hosts to clear cache to support every type of Varnish Cache implementation
* Override default TTL in posts/pages
* Purge key based PURGE
* Debugging option
* console precise manual purges

This plugin also purges Varnish Cache when your site is modified.

Varnish Caching sends a PURGE request to Varnish Cache when a page or post is modified. This occurs when editing, publishing, commenting or deleting an item, and when changing themes.

<a href="https://www.varnish-cache.org/">Varnish</a> is a web application accelerator also known as a caching HTTP reverse proxy. You install it in front of any server that speaks HTTP and configure it to cache the contents. This plugin <em>does not</em> install Varnish for you, nor does it configure Varnish for WordPress. It's expected you already did that on your own using the provided config files.

Not all pages are purged every time, depending on your Varnish configuration. When a post, page, or custom post type is edited, or a new comment is added, <em>only</em> the following pages will purge:

* The front page
* The post/page edited
* Any categories or tags associated with the page

Inspired from the following :

* https://wordpress.org/plugins/varnish-http-purge/
* https://github.com/dreamhost/varnish-vcl-collection/

Implemented on :

* www.bvoltaire.fr

== Installation ==

Use the provided config files for Varnish Cache. Just edit the backend IP/port and ACLs.
You can also use the purge key method. You must fill in lib/purge.vcl the purge key.

== Frequently Asked Questions ==

= What version of Varnish is supported? =

This was built and tested on Varnish 3.x. Soon Varnish 4.x.

= Why doesn't every page flush when I make a new post? =

The only pages that should purge are the post's page, the front page, categories, and tags.

= How do I manually purge the whole cache? =

Click the 'Purge ALL Varnish Cache' button on the "Right Now" Dashboard.

= How do I manually purge cache? =

Use the console. For example you can purge the whole uploads folder with the URL /wp-content/uploads/.*

= Does this work with W3 Total Cache? =

Yes it does. This plugin uses its own custom headers to communicate with Varnish and does not interfere with the heders sent by W3 Total Cache or any other caching plugin.

= Varnish Statistics =

Stats are available only with Varnish PECL extension installed. See <a href="https://pecl.php.net/package/varnish" target="_blank">https://pecl.php.net/package/varnish</a>.

== Changelog ==

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

* none

== Screenshots ==

1. admin panel
2. example integration
3. override default TTL in posts/pages
4. console purge
5. varnish statistics