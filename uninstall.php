<?php

if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

delete_site_option( 'varnish_caching_enable' );
delete_site_option( 'varnish_caching_ttl' );
delete_site_option( 'varnish_caching_homepage_ttl' );
delete_site_option( 'varnish_caching_ips' );
delete_site_option( 'varnish_caching_dynamic_host' );
delete_site_option( 'varnish_caching_hosts' );
delete_site_option( 'varnish_caching_override' );
delete_site_option( 'varnish_caching_purge_key' );
delete_site_option( 'varnish_caching_cookie' );
delete_site_option( 'varnish_caching_stats_json_file' );
delete_site_option( 'varnish_caching_truncate_notice' );
delete_site_option( 'varnish_caching_purge_menu_save' );
delete_site_option( 'varnish_caching_ssl' );
delete_site_option( 'varnish_caching_debug' );
