<?php

if (!defined('ABSPATH')) {
    die();
}

if (!defined('WP_CLI')) return;

/**
 * Purges Varnish Cache
 */
class WP_CLI_VarnishCacheing_Purge_Command extends WP_CLI_Command {

    public function __construct() {
        $this->varnish_cacheing = new VarnishCacheing();
    }

    /**
     * Forces a Varnish Purge
     *
     * ## EXAMPLES
     *
     *     wp varnish purge
     *
     */
    public function purge() {
        wp_create_nonce('varnish-http-purge-cli');
        $this->varnish_cacheing->purgeUrl(home_url() .'/?vhp-regex');
        WP_CLI::success('The Varnish cache was purged.');
    }

}

WP_CLI::add_command('varnish', 'WP_CLI_VarnishCacheing_Purge_Command');
