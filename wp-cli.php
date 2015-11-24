<?php

if (!defined('ABSPATH')) {
    die();
}

if (!defined('WP_CLI')) return;

/**
 * Purges Varnish Cache
 */
class WP_CLI_VCaching_Purge_Command extends WP_CLI_Command {

    public function __construct() {
        $this->vcaching = new VCaching();
    }

    /**
     * Forces a Varnish Purge
     *
     * ## EXAMPLES
     *
     *     wp vcaching purge
     *
     */
    public function purge() {
        wp_create_nonce('vcaching-purge-cli');
        $this->vcaching->purgeUrl(home_url() .'/?vc-regex');
        WP_CLI::success('ALL Varnish cache was purged.');
    }

}

WP_CLI::add_command('vcaching', 'WP_CLI_VCaching_Purge_Command');
