<?php
/*
Plugin Name: VCaching
Plugin URI: http://wordpress.org/extend/plugins/vcaching/
Description: WordPress Varnish Cache integration.
Version: 1.3
Author: Razvan Stanga
Author URI: http://git.razvi.ro/
License: http://www.apache.org/licenses/LICENSE-2.0
Text Domain: vcaching
Network: true

Copyright 2015: Razvan Stanga (email: varnish-caching@razvi.ro)
*/

class VCaching {
    protected $blogId;
    protected $plugin = 'vcaching';
    protected $prefix = 'varnish_caching_';
    protected $purgeUrls = array();
    protected $varnishIp = null;
    protected $varnishHost = null;
    protected $dynamicHost = null;
    protected $ipsToHosts = array();
    protected $purgeKey = null;
    protected $getParam = 'purge_varnish_cache';
    protected $postTypes = array('page', 'post');
    protected $override = 0;
    protected $customFields = array();
    protected $noticeMessage = '';
    protected $debug = 0;
    protected $varnistStats = array(
        "client_conn" => "Client connections accepted",
        "client_drop" => "Connection dropped, no sess/wrk",
        "client_req" => "Client requests received",
        "cache_hit" => "Cache hits",
        "cache_hitpass" => "Cache hits for pass",
        "cache_miss" => "Cache misses",
        "backend_conn" => "Backend conn. success",
        "backend_unhealthy" => "Backend conn. not attempted",
        "backend_busy" => "Backend conn. too many",
        "backend_fail" => "Backend conn. failures",
        "backend_reuse" => "Backend conn. reuses",
        "backend_toolate" => "Backend conn. was closed",
        "backend_recycle" => "Backend conn. recycles",
        "backend_retry" => "Backend conn. retry",
        "fetch_head" => "Fetch head",
        "fetch_length" => "Fetch with Length",
        "fetch_chunked" => "Fetch chunked",
        "fetch_eof" => "Fetch EOF",
        "fetch_bad" => "Fetch had bad headers",
        "fetch_close" => "Fetch wanted close",
        "fetch_oldhttp" => "Fetch pre HTTP/1.1 closed",
        "fetch_zero" => "Fetch zero len",
        "fetch_failed" => "Fetch failed",
        "fetch_1xx" => "Fetch no body (1xx)",
        "fetch_204" => "Fetch no body (204)",
        "fetch_304" => "Fetch no body (304)",
        "n_sess_mem" => "N struct sess_mem",
        "n_sess" => "N struct sess",
        "n_object" => "N struct object",
        "n_vampireobject" => "N unresurrected objects",
        "n_objectcore" => "N struct objectcore",
        "n_objecthead" => "N struct objecthead",
        "n_waitinglist" => "N struct waitinglist",
        "n_vbc" => "N struct vbc",
        "n_wrk" => "N worker threads",
        "n_wrk_create" => "N worker threads created",
        "n_wrk_failed" => "N worker threads not created",
        "n_wrk_max" => "N worker threads limited",
        "n_wrk_lqueue" => "work request queue length",
        "n_wrk_queued" => "N queued work requests",
        "n_wrk_drop" => "N dropped work requests",
        "n_backend" => "N backends",
        "n_expired" => "N expired objects",
        "n_lru_nuked" => "N LRU nuked objects",
        "n_lru_moved" => "N LRU moved objects",
        "losthdr" => "HTTP header overflows",
        "n_objsendfile" => "Objects sent with sendfile",
        "n_objwrite" => "Objects sent with write",
        "n_objoverflow" => "Objects overflowing workspace",
        "s_sess" => "Total Sessions",
        "s_req" => "Total Requests",
        "s_pipe" => "Total pipe",
        "s_pass" => "Total pass",
        "s_fetch" => "Total fetch",
        "s_hdrbytes" => "Total header bytes",
        "s_bodybytes" => "Total body bytes",
        "sess_closed" => "Session Closed",
        "sess_pipeline" => "Session Pipeline",
        "sess_readahead" => "Session Read Ahead",
        "sess_linger" => "Session Linger",
        "sess_herd" => "Session herd",
        "shm_records" => "SHM records",
        "shm_writes" => "SHM writes",
        "shm_flushes" => "SHM flushes due to overflow",
        "shm_cont" => "SHM MTX contention",
        "shm_cycles" => "SHM cycles through buffer",
        "sms_nreq" => "SMS allocator requests",
        "sms_nobj" => "SMS outstanding allocations",
        "sms_nbytes" => "SMS outstanding bytes",
        "sms_balloc" => "SMS bytes allocated",
        "sms_bfree" => "SMS bytes freed",
        "backend_req" => "Backend requests made",
        "n_vcl" => "N vcl total",
        "n_vcl_avail" => "N vcl available",
        "n_vcl_discard" => "N vcl discarded",
        "n_ban" => "N total active bans",
        "n_ban_gone" => "N total gone bans",
        "n_ban_add" => "N new bans added",
        "n_ban_retire" => "N old bans deleted",
        "n_ban_obj_test" => "N objects tested",
        "n_ban_re_test" => "N regexps tested against",
        "n_ban_dups" => "N duplicate bans removed",
        "hcb_nolock" => "HCB Lookups without lock",
        "hcb_lock" => "HCB Lookups with lock",
        "hcb_insert" => "HCB Inserts",
        "esi_errors" => "ESI parse errors (unlock)",
        "esi_warnings" => "ESI parse warnings (unlock)",
        "accept_fail" => "Accept failures",
        "client_drop_late" => "Connection dropped late",
        "uptime" => "Client uptime",
        "dir_dns_lookups" => "DNS director lookups",
        "dir_dns_failed" => "DNS director failed lookups",
        "dir_dns_hit" => "DNS director cached lookups hit",
        "dir_dns_cache_full" => "DNS director full dnscache",
        "vmods" => "Loaded VMODs",
        "n_gzip" => "Gzip operations",
        "n_gunzip" => "Gunzip operations",
        "sess_pipe_overflow" => "Dropped sessions due to session pipe overflow",
        "LCK.sms.creat" => "Created locks",
        "LCK.sms.destroy" => "Destroyed locks",
        "LCK.sms.locks" => "Lock Operations",
        "LCK.sms.colls" => "Collisions",
        "LCK.smp.creat" => "Created locks",
        "LCK.smp.destroy" => "Destroyed locks",
        "LCK.smp.locks" => "Lock Operations",
        "LCK.smp.colls" => "Collisions",
        "LCK.sma.creat" => "Created locks",
        "LCK.sma.destroy" => "Destroyed locks",
        "LCK.sma.locks" => "Lock Operations",
        "LCK.sma.colls" => "Collisions",
        "LCK.smf.creat" => "Created locks",
        "LCK.smf.destroy" => "Destroyed locks",
        "LCK.smf.locks" => "Lock Operations",
        "LCK.smf.colls" => "Collisions",
        "LCK.hsl.creat" => "Created locks",
        "LCK.hsl.destroy" => "Destroyed locks",
        "LCK.hsl.locks" => "Lock Operations",
        "LCK.hsl.colls" => "Collisions",
        "LCK.hcb.creat" => "Created locks",
        "LCK.hcb.destroy" => "Destroyed locks",
        "LCK.hcb.locks" => "Lock Operations",
        "LCK.hcb.colls" => "Collisions",
        "LCK.hcl.creat" => "Created locks",
        "LCK.hcl.destroy" => "Destroyed locks",
        "LCK.hcl.locks" => "Lock Operations",
        "LCK.hcl.colls" => "Collisions",
        "LCK.vcl.creat" => "Created locks",
        "LCK.vcl.destroy" => "Destroyed locks",
        "LCK.vcl.locks" => "Lock Operations",
        "LCK.vcl.colls" => "Collisions",
        "LCK.stat.creat" => "Created locks",
        "LCK.stat.destroy" => "Destroyed locks",
        "LCK.stat.locks" => "Lock Operations",
        "LCK.stat.colls" => "Collisions",
        "LCK.sessmem.creat" => "Created locks",
        "LCK.sessmem.destroy" => "Destroyed locks",
        "LCK.sessmem.locks" => "Lock Operations",
        "LCK.sessmem.colls" => "Collisions",
        "LCK.wstat.creat" => "Created locks",
        "LCK.wstat.destroy" => "Destroyed locks",
        "LCK.wstat.locks" => "Lock Operations",
        "LCK.wstat.colls" => "Collisions",
        "LCK.herder.creat" => "Created locks",
        "LCK.herder.destroy" => "Destroyed locks",
        "LCK.herder.locks" => "Lock Operations",
        "LCK.herder.colls" => "Collisions",
        "LCK.wq.creat" => "Created locks",
        "LCK.wq.destroy" => "Destroyed locks",
        "LCK.wq.locks" => "Lock Operations",
        "LCK.wq.colls" => "Collisions",
        "LCK.objhdr.creat" => "Created locks",
        "LCK.objhdr.destroy" => "Destroyed locks",
        "LCK.objhdr.locks" => "Lock Operations",
        "LCK.objhdr.colls" => "Collisions",
        "LCK.exp.creat" => "Created locks",
        "LCK.exp.destroy" => "Destroyed locks",
        "LCK.exp.locks" => "Lock Operations",
        "LCK.exp.colls" => "Collisions",
        "LCK.lru.creat" => "Created locks",
        "LCK.lru.destroy" => "Destroyed locks",
        "LCK.lru.locks" => "Lock Operations",
        "LCK.lru.colls" => "Collisions",
        "LCK.cli.creat" => "Created locks",
        "LCK.cli.destroy" => "Destroyed locks",
        "LCK.cli.locks" => "Lock Operations",
        "LCK.cli.colls" => "Collisions",
        "LCK.ban.creat" => "Created locks",
        "LCK.ban.destroy" => "Destroyed locks",
        "LCK.ban.locks" => "Lock Operations",
        "LCK.ban.colls" => "Collisions",
        "LCK.vbp.creat" => "Created locks",
        "LCK.vbp.destroy" => "Destroyed locks",
        "LCK.vbp.locks" => "Lock Operations",
        "LCK.vbp.colls" => "Collisions",
        "LCK.vbe.creat" => "Created locks",
        "LCK.vbe.destroy" => "Destroyed locks",
        "LCK.vbe.locks" => "Lock Operations",
        "LCK.vbe.colls" => "Collisions",
        "LCK.backend.creat" => "Created locks",
        "LCK.backend.destroy" => "Destroyed locks",
        "LCK.backend.locks" => "Lock Operations",
        "LCK.backend.colls" => "Collisions",
        "SMF.s0.c_req" => "Allocator requests",
        "SMF.s0.c_fail" => "Allocator failures",
        "SMF.s0.c_bytes" => "Bytes allocated",
        "SMF.s0.c_freed" => "Bytes freed",
        "SMF.s0.g_alloc" => "Allocations outstanding",
        "SMF.s0.g_bytes" => "Bytes outstanding",
        "SMF.s0.g_space" => "Bytes available",
        "SMF.s0.g_smf" => "N struct smf",
        "SMF.s0.g_smf_frag" => "N small free smf",
        "SMF.s0.g_smf_large" => "N large free smf",
        "SMA.Transient.c_req" => "Allocator requests",
        "SMA.Transient.c_fail" => "Allocator failures",
        "SMA.Transient.c_bytes" => "Bytes allocated",
        "SMA.Transient.c_freed" => "Bytes freed",
        "SMA.Transient.g_alloc" => "Allocations outstanding",
        "SMA.Transient.g_bytes" => "Bytes outstanding",
        "SMA.Transient.g_space" => "Bytes available",
        "VBE.default(192.168.0.2,,80).vcls" => "VCL references",
        "VBE.default(192.168.0.2,,80).happy" => "Happy health probes",
    );

    public function __construct()
    {
        global $blog_id;
        defined($this->plugin) || define($this->plugin, true);

        $this->blogId = $blog_id;
        add_action('init', array(&$this, 'init'));
        add_action('activity_box_end', array($this, 'varnish_glance'), 100);

        $this->customFields = array(
            array(
                'name'          => 'ttl',
                'title'         => 'TTL',
                'description'   => __('Not required. If filled in overrides default TTL of %s seconds. 0 means no caching.', $this->plugin),
                'type'          => 'text',
                'scope'         =>  array('post', 'page'),
                'capability'    => 'manage_options'
            )
        );

        $this->setupIpsToHosts();
        $this->purgeKey = ($purgeKey = trim(get_option($this->prefix . 'purge_key'))) ? $purgeKey : null;
        $this->admin_menu();
    }

    public function init()
    {
        load_plugin_textdomain($this->plugin, false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );

        add_action('wp', array($this, 'buffer_start'), 1000000);
        add_action('shutdown', array($this, 'buffer_end'), 1000000);

        $this->debug = get_option($this->prefix . 'debug');

        // send headers to varnish
        add_action('send_headers', array($this, 'send_headers'), 1000000);

        // register events to purge post
        foreach ($this->getRegisterEvents() as $event) {
            add_action($event, array($this, 'purge_post'), 10, 2);
        }

        // purge all cache
        if (isset($_GET[$this->getParam]) && check_admin_referer($this->plugin)) {
            if (get_option('permalink_structure') == '' && current_user_can('manage_options')) {
                add_action('admin_notices' , array($this, 'pretty_permalinks_message'));
            }
            if ($this->varnishIp == null) {
                add_action('admin_notices' , array($this, 'purge_message_no_ips'));
            } else {
                $this->purgeCache();
            }
        }

        if ($this->check_if_purgeable()) {
            add_action('admin_bar_menu', array($this, 'purge_varnish_cache_all_adminbar'), 100);
        }
        if ($this->override = get_option($this->prefix . 'override')) {
            add_action('admin_menu', array($this, 'createCustomFields'));
            add_action('save_post', array($this, 'saveCustomFields' ), 1, 2);
            add_action('wp_enqueue_scripts', array($this, 'override_ttl'), 1000);
        }
        add_action('wp_enqueue_scripts', array($this, 'override_homepage_ttl'), 1000);

        // console purge
        if (isset($_POST['varnish_caching_purge_url'])) {
            $this->purgeUrl(home_url() . $_POST['varnish_caching_purge_url']);
            add_action('admin_notices' , array($this, 'purge_message'));
        }
    }

    public function override_ttl($post)
    {
        $postId = isset($GLOBALS['wp_the_query']->post->ID) ? $GLOBALS['wp_the_query']->post->ID : 0;
        if ($postId && (is_page() || is_single())) {
            $ttl = get_post_meta($postId, $this->prefix . 'ttl', true);
            if (trim($ttl) != '') {
                Header('X-VC-TTL: ' . intval($ttl), true);
            }
        }
    }

    public function override_homepage_ttl()
    {
        if (is_home() || is_front_page()) {
            $this->homepage_ttl = get_option($this->prefix . 'homepage_ttl');
            Header('X-VC-TTL: ' . intval($this->homepage_ttl), true);
        }
    }

    public function buffer_callback($buffer)
    {
        return $buffer;
    }

    public function buffer_start()
    {
        ob_start(array($this, "buffer_callback"));
    }

    public function buffer_end()
    {
        ob_end_flush();
    }

    protected function setupIpsToHosts()
    {
        $this->varnishIp = get_option($this->prefix . 'ips');
        $this->varnishHost = get_option($this->prefix . 'hosts');
        $this->dynamicHost = get_option($this->prefix . 'dynamic_host');
        $varnishIp = explode(',', $this->varnishIp);
        $varnishHost = explode(',', $this->varnishHost);
        foreach ($varnishIp as $key => $ip) {
            $this->ipsToHosts[] = array(
                'ip' => $ip,
                'host' => $this->dynamicHost ? $_SERVER['HTTP_HOST'] : $varnishHost[$key]
            );
        }
    }

    public function createCustomFields()
    {
        if (function_exists('add_meta_box')) {
            foreach ($this->postTypes as $postType) {
                add_meta_box($this->plugin, 'Varnish', array($this, 'displayCustomFields'), $postType, 'side', 'high');
            }
        }
    }

    public function saveCustomFields($post_id, $post)
    {
        if (!isset($_POST['vc-custom-fields_wpnonce']) || !wp_verify_nonce($_POST['vc-custom-fields_wpnonce'], 'vc-custom-fields'))
            return;
        if (!current_user_can('edit_post', $post_id))
            return;
        if (!in_array($post->post_type, $this->postTypes))
            return;
        foreach ($this->customFields as $customField) {
            if (current_user_can($customField['capability'], $post_id)) {
                if (isset($_POST[$this->prefix . $customField['name']]) && trim($_POST[$this->prefix . $customField['name']]) != '') {
                    update_post_meta($post_id, $this->prefix . $customField['name'], $_POST[$this->prefix . $customField['name']]);
                } else {
                    delete_post_meta($post_id, $this->prefix . $customField['name']);
                }
            }
        }
    }

    public function displayCustomFields()
    {
        global $post;
        ?>
            <?php
            wp_nonce_field('vc-custom-fields', 'vc-custom-fields_wpnonce', false, true);
            foreach ($this->customFields as $customField) {
                // Check scope
                $scope = $customField['scope'];
                $output = false;
                foreach ($scope as $scopeItem) {
                    switch ($scopeItem) {
                        default: {
                            if ($post->post_type == $scopeItem)
                                $output = true;
                            break;
                        }
                    }
                    if ($output) break;
                }
                // Check capability
                if (!current_user_can($customField['capability'], $post->ID))
                    $output = false;
                // Output if allowed
                if ($output) { ?>
                        <?php
                        switch ($customField['type']) {
                            case "checkbox": {
                                // Checkbox
                                echo '<p><strong>' . $customField['title'] . '</strong></p>';
                                echo '<label class="screen-reader-text" for="' . $this->prefix . $customField['name'] . '">' . $customField['title'] . '</label>';
                                echo '<p><input type="checkbox" name="' . $this->prefix . $customField['name'] . '" id="' . $this->prefix . $customField['name'] . '" value="yes"';
                                if (get_post_meta( $post->ID, $this->prefix . $customField['name'], true ) == "yes")
                                    echo ' checked="checked"';
                                echo '" style="width: auto;" /></p>';
                                break;
                            }
                            default: {
                                // Plain text field
                                echo '<p><b>' . $customField['title'] . '</b></p>';
                                $value = get_post_meta($post->ID, $this->prefix . $customField[ 'name' ], true);
                                $default_ttl = get_option($this->prefix . 'ttl');
                                echo '<p><input type="text" name="' . $this->prefix . $customField['name'] . '" id="' . $this->prefix . $customField['name'] . '" value="' . $value . '" /></p>';
                                break;
                            }
                        }
                        ?>
                        <?php if ($customField['description']) echo '<p>' . sprintf($customField['description'], $default_ttl) . '</p>'; ?>
                <?php
                }
            } ?>
        <?php
    }

    public function check_if_purgeable()
    {
        return (!is_multisite() && current_user_can('activate_plugins')) || current_user_can('manage_network') || (is_multisite() && !current_user_can('manage_network') && (SUBDOMAIN_INSTALL || (!SUBDOMAIN_INSTALL && (BLOG_ID_CURRENT_SITE != $this->blogId))));
    }

    public function purge_message()
    {
        echo '<div id="message" class="updated fade"><p><strong>' . __('Varnish message:', $this->plugin) . '</strong><br />' . $this->noticeMessage . '</p></div>';
    }

    public function purge_message_no_ips()
    {
        echo '<div id="message" class="error fade"><p><strong>' . __('Please set the IPs for Varnish!', $this->plugin) . '</strong></p></div>';
    }

    public function pretty_permalinks_message()
    {
        echo '<div id="message" class="error"><p>' . __('Varnish Caching requires you to use custom permalinks. Please go to the <a href="options-permalink.php">Permalinks Options Page</a> to configure them.', $this->plugin) . '</p></div>';
    }

    public function purge_varnish_cache_all_adminbar($admin_bar)
    {
        $admin_bar->add_menu( array(
            'id'    => 'purge-all-varnish-cache',
            'title' => __('Purge ALL Varnish Cache', $this->plugin),
            'href'  => wp_nonce_url(add_query_arg($this->getParam, 1), $this->plugin),
            'meta'  => array(
                'title' => __('Purge ALL Varnish Cache', $this->plugin),
            ),
        ));
    }

    public function varnish_glance()
    {
        $url = wp_nonce_url(admin_url('?' . $this->getParam), $this->plugin);
        $button = '';
        $nopermission = '';
        if ($this->varnishIp == null) {
            $intro .= sprintf(__('Please setup Varnish IPs to be able to use <a href="%1$s">Varnish Caching</a>.', $this->plugin), 'http://wordpress.org/plugins/varnish-caching/');
        } else {
            $intro .= sprintf(__('<a href="%1$s">Varnish Caching</a> automatically purges your posts when published or updated. Sometimes you need a manual flush.', $this->plugin), 'http://wordpress.org/plugins/varnish-caching/');
            $button .=  __('Press the button below to force it to purge your entire cache.', $this->plugin);
            $button .= '</p><p><span class="button"><a href="' . $url . '"><strong>';
            $button .= __('Purge ALL Varnish Cache', $this->plugin);
            $button .= '</strong></a></span>';
            $nopermission .=  __('You do not have permission to purge the cache for the whole site. Please contact your adminstrator.', $this->plugin);
        }
        if ($this->check_if_purgeable()) {
            $text = $intro . ' ' . $button;
        } else {
            $text = $intro . ' ' . $nopermission;
        }
        echo '<p class="varnish-galce">' . $text . '</p>';
    }

    protected function getRegisterEvents() {
        $actions = array(
            'save_post',
            'deleted_post',
            'trashed_post',
            'edit_post',
            'delete_attachment',
            'switch_theme',
        );
        return apply_filters('vcaching_events', $actions);
    }

    public function purgeCache() {
        $purgeUrls = array_unique($this->purgeUrls);

        if (empty($purgeUrls)) {
            if (isset($_GET[$this->getParam]) && current_user_can('manage_options') && check_admin_referer($this->plugin)) {
                $this->purgeUrl(home_url() .'/?vc-regex');
            }
        } else {
            foreach($purgeUrls as $url) {
                $this->purgeUrl($url);
            }
        }
        add_action('admin_notices' , array($this, 'purge_message'));
    }

    protected function purgeUrl($url) {
        $p = parse_url($url);

        if (isset($p['query']) && ($p['query'] == 'vc-regex')) {
            $pregex = '.*';
            $purgemethod = 'regex';
        } else {
            $pregex = '';
            $purgemethod = 'default';
        }

        if (isset($p['path'])) {
            $path = $p['path'];
        } else {
            $path = '';
        }

        $schema = apply_filters('vcaching_schema', 'http://');

        foreach ($this->ipsToHosts as $key => $ipToHost) {
            $purgeme = $schema . $ipToHost['ip'] . $path . $pregex;
            $headers = array('host' => $ipToHost['host'], 'X-VC-Purge-Method' => $purgemethod, 'X-VC-Purge-Host' => $ipToHost['host']);
            if (!is_null($this->purgeKey)) {
                $headers['X-VC-Purge-Key'] = $this->purgeKey;
            }
            $response = wp_remote_request($purgeme, array('method' => 'PURGE', 'headers' => $headers));
            if ($response instanceof WP_Error) {
                foreach ($response->errors as $error => $errors) {
                    $this->noticeMessage .= '<br />Error ' . $error . '<br />';
                    foreach ($errors as $error => $description) {
                        $this->noticeMessage .= ' - ' . $description . '<br />';
                    }
                }
            } else {
                $this->noticeMessage .= '<br />' . __('Trying to purge URL :', $this->plugin) . $purgeme;
                $message = preg_match("/<title>(.*)<\/title>/i", $response['body'], $matches);
                $this->noticeMessage .= ' => <br /> ' . isset($matches[1]) ? " => " . $matches[1] : $response['body'];
                $this->noticeMessage .= '<br />';
                if ($this->debug) {
                    $this->noticeMessage .= $response['body'] . "<br />";
                }
            }
        }

        do_action('after_purge_url', $url, $purgeme);
    }

    public function purge_post($postId)
    {
        // If this is a valid post we want to purge the post, the home page and any associated tags & cats
        // If not, purge everything on the site.

        $validPostStatus = array('publish', 'trash');
        $thisPostStatus  = get_post_status($postId);

        // If this is a revision, stop.
        if(get_permalink($postId) !== true && !in_array($thisPostStatus, $validPostStatus)) {
            return;
        } else {
            // array to collect all our URLs
            $listofurls = array();

            // Category purge based on Donnacha's work in WP Super Cache
            $categories = get_the_category($postId);
            if ($categories) {
                foreach ($categories as $cat) {
                    array_push($listofurls, get_category_link( $cat->term_id));
                }
            }
            // Tag purge based on Donnacha's work in WP Super Cache
            $tags = get_the_tags($postId);
            if ($tags) {
                foreach ($tags as $tag) {
                    array_push($listofurls, get_tag_link( $tag->term_id));
                }
            }

            // Author URL
            array_push($listofurls,
                get_author_posts_url(get_post_field( 'post_author', $postId)),
                get_author_feed_link(get_post_field( 'post_author', $postId))
            );

            // Archives and their feeds
            $archiveurls = array();
            if ( get_post_type_archive_link(get_post_type($postId)) == true) {
                array_push($listofurls,
                    get_post_type_archive_link( get_post_type($postId)),
                    get_post_type_archive_feed_link( get_post_type($postId))
                );
            }

            // Post URL
            array_push($listofurls, get_permalink($postId));

            // Feeds
            array_push($listofurls,
                get_bloginfo_rss('rdf_url') ,
                get_bloginfo_rss('rss_url') ,
                get_bloginfo_rss('rss2_url'),
                get_bloginfo_rss('atom_url'),
                get_bloginfo_rss('comments_rss2_url'),
                get_post_comments_feed_link($postId)
            );

            // Home Page and (if used) posts page
            array_push($listofurls, home_url('/'));
            if ( get_option('show_on_front') == 'page') {
                array_push($listofurls, get_permalink(get_option('page_for_posts')));
            }

            // Now flush all the URLs we've collected
            foreach ($listofurls as $url) {
                array_push($this->purgeUrls, $url) ;
            }
        }
        // Filter to add or remove urls to the array of purged urls
        // @param array $purgeUrls the urls (paths) to be purged
        // @param int $postId the id of the new/edited post
        $this->purgeUrls = apply_filters('vcaching_purge_urls', $this->purgeUrls, $postId);
        $this->purgeCache();
    }

    public function send_headers()
    {
        $enable = get_option($this->prefix . 'enable');
        if ($enable) {
            Header('X-VC-Enabled: true', true);
            $ttl = get_option($this->prefix . 'ttl');
            Header('X-VC-TTL: ' . $ttl, true);
            if ($debug = get_option($this->prefix . 'debug')) {
                Header('X-VC-Debug: true', true);
            }
        } else {
            Header('X-VC-Enabled: false', true);
        }
    }

    public function admin_menu()
    {
        add_action('admin_menu', array($this, 'add_menu_item'));
        add_action('admin_init', array($this, 'options_page_fields'));
        add_action('admin_init', array($this, 'console_page_fields'));
    }

    public function add_menu_item()
    {
        if ($this->check_if_purgeable()) {
            add_menu_page(__('Varnish Caching', $this->plugin), __('Varnish Caching', $this->plugin), 'manage_options', $this->plugin . '-plugin', array($this, 'settings_page'), home_url() . '/wp-content/plugins/' . $this->plugin . '/icon.png', 99);
        }
    }

    public function settings_page()
    {
    ?>
        <div class="wrap">
        <h1><?=__('Varnish Caching', $this->plugin)?></h1>

        <h2 class="nav-tab-wrapper">
            <a class="nav-tab <?php if(!isset($_GET['tab']) || $_GET['tab'] == 'options'): ?>nav-tab-active<?php endif; ?>" href="<?php echo admin_url() ?>index.php?page=<?=$this->plugin?>-plugin&amp;tab=options"><?=__('Settings', $this->plugin)?></a>
            <?php if ($this->check_if_purgeable()): ?>
                <a class="nav-tab <?php if($_GET['tab'] == 'console'): ?>nav-tab-active<?php endif; ?>" href="<?php echo admin_url() ?>index.php?page=<?=$this->plugin?>-plugin&amp;tab=console"><?=__('Console', $this->plugin)?></a>
            <?php endif; ?>
            <a class="nav-tab <?php if($_GET['tab'] == 'stats'): ?>nav-tab-active<?php endif; ?>" href="<?php echo admin_url() ?>index.php?page=<?=$this->plugin?>-plugin&amp;tab=stats"><?=__('Statistics', $this->plugin)?></a>
        </h2>

        <?php if(!isset($_GET['tab']) || $_GET['tab'] == 'options'): ?>
            <form method="post" action="options.php">
                <?php
                    settings_fields($this->prefix . 'options');
                    do_settings_sections($this->prefix . 'options');
                    submit_button();
                ?>
            </form>
        <?php elseif($_GET['tab'] == 'console'): ?>
            <form method="post" action="index.php?page=<?=$this->plugin?>-plugin&amp;tab=console">
                <?php
                    settings_fields($this->prefix . 'console');
                    do_settings_sections($this->prefix . 'console');
                    submit_button(__('Purge', $this->plugin));
                ?>
            </form>
        <?php elseif($_GET['tab'] == 'stats'): ?>
            <?php
                $error = null;
                if (class_exists('VarnishStat')) {
                    $vs = new VarnishStat;
                    try {
                        $stats = $vs->getSnapshot();
                    } catch (VarnishException $e) {
                        $error = $e->getMessage();
                    }
                } else {
                    $error = __('Stats are available only with Varnish PECL extension installed. See <a href="https://pecl.php.net/package/varnish" target="_blank">https://pecl.php.net/package/varnish</a>.', $this->plugin);
                }
            ?>
            <h2><?= __('Statistics', $this->plugin) ?></h2>
            <?php if($error == null): ?>
            <table class="fixed">
                <?php if(count($stats)): ?>
                <thead>
                    <tr>
                        <td><strong><?= __('Key', $this->plugin) ?></strong></td>
                        <td><strong><?= __('Value', $this->plugin) ?></strong></td>
                        <td><strong><?= __('Description', $this->plugin) ?></strong></td>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats as $key => $value): ?>
                    <tr>
                        <td><?=$key?></td>
                        <td><?=$value?></td>
                        <td><?=isset($this->varnistStats[$key]) ? __($this->varnistStats[$key], $this->plugin) : ''?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <?php else: ?>
                    <tr>
                        <td colspan="3">
                            <?=$error?>
                        </td>
                    </tr>
                <?php endif; ?>
            </table>
            <?php else: ?>
                <?=$error?>
            <?php endif; ?>
        <?php endif; ?>
        </div>
    <?php
    }

    public function options_page_fields()
    {
        add_settings_section($this->prefix . 'options', __('Settings', $this->plugin), null, $this->prefix . 'options');

        add_settings_field($this->prefix . "enable", __("Enable" , $this->plugin), array($this, $this->prefix . "enable"), $this->prefix . 'options', $this->prefix . 'options');
        add_settings_field($this->prefix . "homepage_ttl", __("Homepage cache TTL", $this->plugin), array($this, $this->prefix . "homepage_ttl"), $this->prefix . 'options', $this->prefix . 'options');
        add_settings_field($this->prefix . "ttl", __("Cache TTL", $this->plugin), array($this, $this->prefix . "ttl"), $this->prefix . 'options', $this->prefix . 'options');
        add_settings_field($this->prefix . "ips", __("IPs", $this->plugin), array($this, $this->prefix . "ips"), $this->prefix . 'options', $this->prefix . 'options');
        add_settings_field($this->prefix . "dynamic_host", __("Dynamic host", $this->plugin), array($this, $this->prefix . "dynamic_host"), $this->prefix . 'options', $this->prefix . 'options');
        if (!get_option($this->prefix . 'dynamic_host')) {
            add_settings_field($this->prefix . "hosts", __("Hosts", $this->plugin), array($this, $this->prefix . "hosts"), $this->prefix . 'options', $this->prefix . 'options');
        }
        add_settings_field($this->prefix . "override", __("Override default TTL", $this->plugin), array($this, $this->prefix . "override"), $this->prefix . 'options', $this->prefix . 'options');
        add_settings_field($this->prefix . "purge_key", __("Purge key", $this->plugin), array($this, $this->prefix . "purge_key"), $this->prefix . 'options', $this->prefix . 'options');
        add_settings_field($this->prefix . "debug", __("Enable debug", $this->plugin), array($this, $this->prefix . "debug"), $this->prefix . 'options', $this->prefix . 'options');

        if($_POST['option_page'] == $this->prefix . 'options') {
            register_setting($this->prefix . 'options', $this->prefix . "enable");
            register_setting($this->prefix . 'options', $this->prefix . "ttl");
            register_setting($this->prefix . 'options', $this->prefix . "homepage_ttl");
            register_setting($this->prefix . 'options', $this->prefix . "ips");
            register_setting($this->prefix . 'options', $this->prefix . "dynamic_host");
            register_setting($this->prefix . 'options', $this->prefix . "hosts");
            register_setting($this->prefix . 'options', $this->prefix . "override");
            register_setting($this->prefix . 'options', $this->prefix . "purge_key");
            register_setting($this->prefix . 'options', $this->prefix . "debug");
        }
    }

    public function varnish_caching_enable()
    {
        ?>
            <input type="checkbox" name="varnish_caching_enable" value="1" <?php checked(1, get_option($this->prefix . 'enable'), true); ?> />
            <p class="description"><?=__('Enable Varnish Caching', $this->plugin)?></p>
        <?php
    }

    public function varnish_caching_homepage_ttl()
    {
        ?>
            <input type="text" name="varnish_caching_homepage_ttl" id="varnish_caching_homepage_ttl" value="<?php echo get_option($this->prefix . 'homepage_ttl'); ?>" />
            <p class="description"><?=__('Time to live in seconds in Varnish cache for homepage', $this->plugin)?></p>
        <?php
    }

    public function varnish_caching_ttl()
    {
        ?>
            <input type="text" name="varnish_caching_ttl" id="varnish_caching_ttl" value="<?php echo get_option($this->prefix . 'ttl'); ?>" />
            <p class="description"><?=__('Time to live in seconds in Varnish cache', $this->plugin)?></p>
        <?php
    }

    public function varnish_caching_ips()
    {
        ?>
            <input type="text" name="varnish_caching_ips" id="varnish_caching_ips" size="100" value="<?php echo get_option($this->prefix . 'ips'); ?>" />
            <p class="description"><?=__('Comma separated ip/ip:port. Example : 192.168.0.2,192.168.0.3:8080', $this->plugin)?></p>
        <?php
    }

    public function varnish_caching_dynamic_host()
    {
        ?>
            <input type="checkbox" name="varnish_caching_dynamic_host" value="1" <?php checked(1, get_option($this->prefix . 'dynamic_host'), true); ?> />
            <p class="description">
                <?=__('Uses the $_SERVER[\'HTTP_HOST\'] as hash for Varnish. This means the purge cache action will work on the domain you\'re on.<br />Use this option if you use only one domain.', $this->plugin)?>
            </p>
        <?php
    }

    public function varnish_caching_hosts()
    {
        ?>
            <input type="text" name="varnish_caching_hosts" id="varnish_caching_hosts" size="100" value="<?php echo get_option($this->prefix . 'hosts'); ?>" />
            <p class="description">
                <?=__('Comma separated hostnames. Varnish uses the hostname to create the cache hash. For each IP, you must set a hostname.<br />Use this option if you use multiple domains.', $this->plugin)?>
            </p>
        <?php
    }

    public function varnish_caching_override()
    {
        ?>
            <input type="checkbox" name="varnish_caching_override" value="1" <?php checked(1, get_option($this->prefix . 'override'), true); ?> />
            <p class="description"><?=__('Override default TTL on each post/page.', $this->plugin)?></p>
        <?php
    }

    public function varnish_caching_purge_key()
    {
        ?>
            <input type="text" name="varnish_caching_purge_key" id="varnish_caching_purge_key" size="100" value="<?php echo get_option($this->prefix . 'purge_key'); ?>" />
            <p class="description">
                <?=__('Key used to purge Varnish cache. It is sent to Varnish as X-VC-Purge-Key header. Use a SHA-256 hash.<br />If you can\'t use ACL\'s, use this option.', $this->plugin)?>
            </p>
        <?php
    }

    public function varnish_caching_debug()
    {
        ?>
            <input type="checkbox" name="varnish_caching_debug" value="1" <?php checked(1, get_option($this->prefix . 'debug'), true); ?> />
            <p class="description">
                <?=__('Send all debugging headers to the client. Also shows complete response from Varnish on purge all.', $this->plugin)?>
            </p>
        <?php
    }

    public function console_page_fields()
    {
        add_settings_section('console', __("Console", $this->plugin), null, $this->prefix . 'console');

        add_settings_field($this->prefix . "purge_url", __("URL", $this->plugin), array($this, $this->prefix . "purge_url"), $this->prefix . 'console', "console");
    }

    public function varnish_caching_purge_url()
    {
        ?>
            <input type="text" name="varnish_caching_purge_url" size="100" id="varnish_caching_purge_url" value="" />
            <p class="description"><?=__('Relative URL to purge. Example : /wp-content/uploads/.*', $this->plugin)?></p>
        <?php
    }
}

$vcaching = new VCaching();

// WP-CLI
if ( defined('WP_CLI') && WP_CLI ) {
    include('wp-cli.php');
}
