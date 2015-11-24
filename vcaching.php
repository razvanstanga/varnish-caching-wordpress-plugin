<?php
/*
Plugin Name: VCaching
Plugin URI: http://wordpress.org/extend/plugins/vcaching/
Description: WordPress Varnish Cache integration.
Version: 1.1
Author: Razvan Stanga
Author URI: http://git.razvi.ro/
License: http://www.apache.org/licenses/LICENSE-2.0
Text Domain: vcaching
Network: true

Copyright 2015: Razvan Stanga (email: varnish-caching@razvi.ro)
*/

class VCaching {
    protected $blogId;
    protected $plugin = 'varnish-caching';
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
        load_plugin_textdomain($this->plugin);

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
        }
        add_action('wp_enqueue_scripts', array($this, 'override_ttl'), 1000);
        add_action('wp_enqueue_scripts', array($this, 'override_homepage_ttl'), 1000);
    }

    public function override_ttl($post)
    {
        $postId = isset($GLOBALS['wp_the_query']->post->ID) ? $GLOBALS['wp_the_query']->post->ID : 0;
        if ($postId && (is_page() || is_single())) {
            $ttl = get_post_meta($postId, $this->prefix . 'ttl', true);
            Header('X-VC-TTL: ' . $ttl, true);
        }
    }

    public function override_homepage_ttl()
    {
        if (is_home() || is_front_page()) {
            $this->homepage_ttl = get_option($this->prefix . 'homepage_ttl');
            Header('X-VC-TTL: ' . $this->homepage_ttl, true);
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
                if (isset($_POST[$this->prefix . $customField['name']]) && trim($_POST[$this->prefix . $customField['name']])) {
                    $value = $_POST[$this->prefix . $customField['name']];
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
                                $value = intval(get_post_meta($post->ID, $this->prefix . $customField[ 'name' ], true));
                                $default_ttl = get_option($this->prefix . 'ttl');
                                $value = $value ? $value : $default;
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
        return array(
            'save_post',
            'deleted_post',
            'trashed_post',
            'edit_post',
            'delete_attachment',
            'switch_theme',
        );
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

        $schema = apply_filters('varnish_http_purge_schema', 'http://');

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
                $this->noticeMessage .= '<br />Trying to purge URL : ' . $purgeme;
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
                array_push($listofurls, get_permalink( get_option('page_for_posts')));
            }

            // Now flush all the URLs we've collected
            foreach ($listofurls as $url) {
                array_push($this->purgeUrls, $url) ;
            }

        }

        // Filter to add or remove urls to the array of purged urls
        // @param array $purgeUrls the urls (paths) to be purged
        // @param int $postId the id of the new/edited post
        $this->purgeUrls = apply_filters('vc_purge_urls', $this->purgeUrls, $postId);
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
        add_action('admin_init', array($this, 'setting_page_fields'));
    }

    public function add_menu_item()
    {
        add_menu_page(__('Varnish Caching', $this->plugin), __('Varnish Caching', $this->plugin), 'manage_options', $this->plugin . '-options', array($this, 'settings_page'), home_url() . '/wp-content/plugins/' . $this->plugin . '/icon.png', 99);
    }

    public function settings_page()
    {
    ?>
        <div class="wrap">
        <h1><?=__('Varnish Caching Options', $this->plugin)?></h1>
        <form method="post" action="options.php">
            <?php
                settings_fields('section');
                do_settings_sections($this->plugin . '-options');
                submit_button();
            ?>
        </form>
        </div>
    <?php
    }

    public function setting_page_fields()
    {
        add_settings_section('section', 'Settings', null, $this->plugin . '-options');

        add_settings_field($this->prefix . "enable", __("Enable" , $this->plugin), array($this, $this->prefix . "enable"), $this->plugin . '-options', "section");
        add_settings_field($this->prefix . "homepage_ttl", __("Homepage cache TTL", $this->plugin), array($this, $this->prefix . "homepage_ttl"), $this->plugin . '-options', "section");
        add_settings_field($this->prefix . "ttl", __("Cache TTL", $this->plugin), array($this, $this->prefix . "ttl"), $this->plugin . '-options', "section");
        add_settings_field($this->prefix . "ips", __("IPs", $this->plugin), array($this, $this->prefix . "ips"), $this->plugin . '-options', "section");
        add_settings_field($this->prefix . "dynamic_host", __("Dynamic host", $this->plugin), array($this, $this->prefix . "dynamic_host"), $this->plugin . '-options', "section");
        if (!get_option($this->prefix . 'dynamic_host')) {
            add_settings_field($this->prefix . "hosts", __("Hosts", $this->plugin), array($this, $this->prefix . "hosts"), $this->plugin . '-options', "section");
        }
        add_settings_field($this->prefix . "override", __("Override default TTL", $this->plugin), array($this, $this->prefix . "override"), $this->plugin . '-options', "section");
        add_settings_field($this->prefix . "purge_key", __("Purge key", $this->plugin), array($this, $this->prefix . "purge_key"), $this->plugin . '-options', "section");
        add_settings_field($this->prefix . "debug", __("Enable debug", $this->plugin), array($this, $this->prefix . "debug"), $this->plugin . '-options', "section");

        register_setting("section", $this->prefix . "enable");
        register_setting("section", $this->prefix . "ttl");
        register_setting("section", $this->prefix . "homepage_ttl");
        register_setting("section", $this->prefix . "ips");
        register_setting("section", $this->prefix . "dynamic_host");
        register_setting("section", $this->prefix . "hosts");
        register_setting("section", $this->prefix . "override");
        register_setting("section", $this->prefix . "purge_key");
        register_setting("section", $this->prefix . "debug");
    }

    public function varnish_caching_enable()
    {
        ?>
            <input type="checkbox" name="varnish_caching_enable" value="1" <?php checked(1, get_option($this->prefix . 'enable'), true); ?> />
            <p class="description"><?=__('Enable Varnish caching', $this->plugin)?></p>
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
}

$vcaching = new VCaching();