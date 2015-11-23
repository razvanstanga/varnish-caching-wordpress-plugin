=== Varnish Caching ===
Tags: varnish, purge, cache
Requires at least: 4.0
Tested up to: 4.3
Stable tag: 3.7.3

== Description ==
Varnish Cache Wordpress implementation

You can control from the Varnish Cache admin panel the following :
- Enable/Disable caching
- Homepage cache TTL
- Cache TTL (for every other page)
- IPs/Hosts to clear cache to support every type of Varnish Cache implementation
- Override default TTL in posts/pages
- Purge key based PURGE
- Debugging option

Purges Varnish Cache when your site is modified.
Varnish Caching sends a PURGE request to Varnish Cache when a page or post is modified. This occurs when editing, publishing, commenting or deleting an item, and when changing themes.

<a href="https://www.varnish-cache.org/">Varnish</a> is a web application accelerator also known as a caching HTTP reverse proxy. You install it in front of any server that speaks HTTP and configure it to cache the contents. This plugin <em>does not</em> install Varnish for you, nor does it configure Varnish for WordPress. It's expected you already did that on your own.

Not all pages are purged every time, depending on your Varnish configuration. When a post, page, or custom post type is edited, or a new comment is added, <em>only</em> the following pages will purge:

* The front page
* The post/page edited
* Any categories or tags associated with the page

Inspired from the following :
- https://wordpress.org/plugins/varnish-http-purge/
- https://github.com/dreamhost/varnish-vcl-collection/

Implemented on :
- www.bvoltaire.fr