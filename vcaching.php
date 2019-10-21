<?php
/*
Plugin Name: Varnish Caching
Plugin URI: http://wordpress.org/extend/plugins/vcaching/
Description: WordPress Varnish Cache integration.
Version: 1.8.3
Author: Razvan Stanga
Author URI: http://git.razvi.ro/
License: GPL-3.0-or-later
Text Domain: vcaching
Network: true

Copyright 2019: Razvan Stanga (email: varnish-caching@razvi.ro)
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
    protected $statsJsons = array();
    protected $purgeKey = null;
    protected $getParam = 'purge_varnish_cache';
    protected $postTypes = array('page', 'post');
    protected $override = 0;
    protected $customFields = array();
    protected $noticeMessage = '';
    protected $truncateNotice = false;
    protected $truncateNoticeShown = false;
    protected $truncateCount = 0;
    protected $debug = 0;
    protected $vclGeneratorTab = true;
    protected $purgeOnMenuSave = false;
    protected $currentTab;
    protected $useSsl = false;

    public function __construct()
    {
        global $blog_id;
        defined($this->plugin) || define($this->plugin, true);

        $this->blogId = $blog_id;
        add_action('init', array(&$this, 'init'), 11);
        add_action('activity_box_end', array($this, 'varnish_glance'), 100);
    }

    public function init()
    {
        load_plugin_textdomain($this->plugin, false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );

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
        $this->useSsl = get_option($this->prefix . 'ssl');

        $this->postTypes = get_post_types(array('show_in_rest' => true));

        $this->setup_ips_to_hosts();
        $this->purgeKey = ($purgeKey = trim(get_option($this->prefix . 'purge_key'))) ? $purgeKey : null;
        $this->admin_menu();

        add_action('wp', array($this, 'buffer_start'), 1000000);
        add_action('shutdown', array($this, 'buffer_end'), 1000000);

        $this->truncateNotice = get_option($this->prefix . 'truncate_notice');
        $this->debug = get_option($this->prefix . 'debug');

        // send headers to varnish
        add_action('send_headers', array($this, 'send_headers'), 1000000);

        // logged in cookie
        add_action('wp_login', array($this, 'wp_login'), 1000000);
        add_action('wp_logout', array($this, 'wp_logout'), 1000000);

        // register events to purge post
        foreach ($this->get_register_events() as $event) {
            add_action($event, array($this, 'purge_post'), 10, 2);
        }

        // purge all cache from admin bar
        if ($this->check_if_purgeable()) {
            add_action('admin_bar_menu', array($this, 'purge_varnish_cache_all_adminbar'), 100);
            if (isset($_GET[$this->getParam]) && check_admin_referer($this->plugin)) {
                if (get_option('permalink_structure') == '' && current_user_can('manage_options')) {
                    add_action('admin_notices' , array($this, 'pretty_permalinks_message'));
                }
                if ($this->varnishIp == null) {
                    add_action('admin_notices' , array($this, 'purge_message_no_ips'));
                } else {
                    $this->purge_cache();
                }
            }
        }

        // purge post/page cache from post/page actions
        if ($this->check_if_purgeable()) {
            if(!session_id()) {
                session_start();
            }
            add_filter('post_row_actions', array(
                &$this,
                'post_row_actions'
            ), 0, 2);
            add_filter('page_row_actions', array(
                &$this,
                'page_row_actions'
            ), 0, 2);
            if (isset($_GET['action']) && isset($_GET['post_id']) && ($_GET['action'] == 'purge_post' || $_GET['action'] == 'purge_page') && check_admin_referer($this->plugin)) {
                $this->purge_post($_GET['post_id']);
                $_SESSION['vcaching_note'] = $this->noticeMessage;
                $referer = str_replace('purge_varnish_cache=1', '', wp_get_referer());
                wp_redirect($referer . (strpos($referer, '?') ? '&' : '?') . 'vcaching_note=' . $_GET['action']);
            }
            if (isset($_GET['vcaching_note']) && ($_GET['vcaching_note'] == 'purge_post' || $_GET['vcaching_note'] == 'purge_page')) {
                add_action('admin_notices' , array($this, 'purge_post_page'));
            }
        }

        if ($this->override = get_option($this->prefix . 'override')) {
            add_action('admin_menu', array($this, 'create_custom_fields'));
            add_action('save_post', array($this, 'save_custom_fields' ), 1, 2);
            add_action('wp_enqueue_scripts', array($this, 'override_ttl'), 1000);
        }
        add_action('wp_enqueue_scripts', array($this, 'override_homepage_ttl'), 1000);

        // console purge
        if ($this->check_if_purgeable() && isset($_POST['varnish_caching_purge_url'])) {
            $this->purge_url(home_url() . $_POST['varnish_caching_purge_url']);
            add_action('admin_notices' , array($this, 'purge_message'));
        }
        $this->currentTab = isset($_GET['tab']) ? $_GET['tab'] : 'options';
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
        if (ob_get_level() > 0) {
            ob_end_flush();
        }
    }

    protected function setup_ips_to_hosts()
    {
        $this->varnishIp = get_option($this->prefix . 'ips');
        $this->varnishHost = get_option($this->prefix . 'hosts');
        $this->dynamicHost = get_option($this->prefix . 'dynamic_host');
        $this->statsJsons = get_option($this->prefix . 'stats_json_file');
        $this->purgeOnMenuSave = get_option($this->prefix . 'purge_menu_save');
        $varnishIp = explode(',', $this->varnishIp);
        $varnishIp = apply_filters('vcaching_varnish_ips', $varnishIp);
        $varnishHost = explode(',', $this->varnishHost);
        $varnishHost = apply_filters('vcaching_varnish_hosts', $varnishHost);
        $statsJsons = explode(',', $this->statsJsons);
        foreach ($varnishIp as $key => $ip) {
            $this->ipsToHosts[] = array(
                'ip' => $ip,
                'host' => $this->dynamicHost ? $_SERVER['HTTP_HOST'] : $varnishHost[$key],
                'statsJson' => isset($statsJsons[$key]) ? $statsJsons[$key] : null
            );
        }
    }

    public function create_custom_fields()
    {
        if (function_exists('add_meta_box')) {
            foreach ($this->postTypes as $postType) {
                add_meta_box($this->plugin, __('Varnish Caching', $this->plugin), array($this, 'display_custom_fields'), $postType, 'side', 'high');
            }
        }
    }

    public function save_custom_fields($post_id, $post)
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

    public function display_custom_fields()
    {
        global $post;
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
            if ($output) {
                switch ($customField['type']) {
                    case "checkbox": {
                        // Checkbox
                        echo '<p><strong>' . $customField['title'] . '</strong></p>';
                        echo '<label class="screen-reader-text" for="' . $this->prefix . $customField['name'] . '">' . $customField['title'] . '</label>';
                        echo '<p><input type="checkbox" name="' . $this->prefix . $customField['name'] . '" id="' . $this->prefix . $customField['name'] . '" value="yes"';
                        if (get_post_meta($post->ID, $this->prefix . $customField['name'], true ) == "yes")
                            echo ' checked="checked"';
                        echo '" style="width: auto;" /></p>';
                        break;
                    }
                    default: {
                        // Plain text field
                        echo '<p><strong>' . $customField['title'] . '</strong></p>';
                        $value = get_post_meta($post->ID, $this->prefix . $customField[ 'name' ], true);
                        echo '<p><input type="text" name="' . $this->prefix . $customField['name'] . '" id="' . $this->prefix . $customField['name'] . '" value="' . $value . '" /></p>';
                        break;
                    }
                }
            } else {
                echo '<p><strong>' . $customField['title'] . '</strong></p>';
                $value = get_post_meta($post->ID, $this->prefix . $customField[ 'name' ], true);
                echo '<p><input type="text" name="' . $this->prefix . $customField['name'] . '" id="' . $this->prefix . $customField['name'] . '" value="' . $value . '" disabled /></p>';
            }
            $default_ttl = get_option($this->prefix . 'ttl');
            if ($customField['description']) echo '<p>' . sprintf($customField['description'], $default_ttl) . '</p>';
        }
    }

    public function check_if_purgeable()
    {
        return (!is_multisite() && current_user_can('activate_plugins')) || current_user_can('manage_network') || (is_multisite() && !current_user_can('manage_network') && (SUBDOMAIN_INSTALL || (!SUBDOMAIN_INSTALL && (BLOG_ID_CURRENT_SITE != $this->blogId))));
    }

    public function purge_message()
    {
        echo '<div id="message" class="updated fade"><p><strong>' . __('Varnish Caching', $this->plugin) . '</strong><br /><br />' . $this->noticeMessage . '</p></div>';
    }

    public function purge_message_no_ips()
    {
        echo '<div id="message" class="error fade"><p><strong>' . __('Please set the IPs for Varnish!', $this->plugin) . '</strong></p></div>';
    }

    public function purge_post_page()
    {
        if (isset($_SESSION['vcaching_note'])) {
            echo '<div id="message" class="updated fade"><p><strong>' . __('Varnish Caching', $this->plugin) . '</strong><br /><br />' . $_SESSION['vcaching_note'] . '</p></div>';
            unset ($_SESSION['vcaching_note']);
        }
    }

    public function pretty_permalinks_message()
    {
        echo '<div id="message" class="error"><p>' . __('Varnish Caching requires you to use custom permalinks. Please go to the <a href="options-permalink.php">Permalinks Options Page</a> to configure them.', $this->plugin) . '</p></div>';
    }

    public function purge_varnish_cache_all_adminbar($admin_bar)
    {
        $admin_bar->add_menu(array(
            'id'    => 'purge-all-varnish-cache',
            'title' => __('Purge ALL Varnish Cache', $this->plugin),
            'href'  => wp_nonce_url(add_query_arg($this->getParam, 1), $this->plugin),
            'meta'  => array(
                'title' => __('Purge ALL Varnish Cache', $this->plugin),
            )
        ));
    }

    public function varnish_glance()
    {
        $url = wp_nonce_url(admin_url('?' . $this->getParam), $this->plugin);
        $button = '';
        $nopermission = '';
        $intro = '';
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
        echo '<p class="varnish-glance">' . $text . '</p>';
    }

    protected function get_register_events()
    {
        $actions = array(
            'publish_future_post',
            'save_post',
            'deleted_post',
            'trashed_post',
            'edit_post',
            'delete_attachment',
            'switch_theme',
        );
        return apply_filters('vcaching_events', $actions);
    }

    public function purge_cache()
    {
        $purgeUrls = array_unique($this->purgeUrls);

        if (empty($purgeUrls)) {
            if (isset($_GET[$this->getParam]) && $this->check_if_purgeable() && check_admin_referer($this->plugin)) {
                $this->purge_url(home_url() .'/?vc-regex');
            }
        } else {
            foreach($purgeUrls as $url) {
                $this->purge_url($url);
            }
        }
        if ($this->truncateNotice && $this->truncateNoticeShown == false) {
            $this->truncateNoticeShown = true;
            $this->noticeMessage .= '<br />' . __('Truncate message activated. Showing only first 3 messages.', $this->plugin);
        }
        add_action('admin_notices' , array($this, 'purge_message'));
    }

    public function purge_url($url)
    {
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

        $schema = apply_filters('vcaching_schema', ($this->useSsl ? 'https://' : 'http://'));

        foreach ($this->ipsToHosts as $key => $ipToHost) {
            $purgeme = $schema . $ipToHost['ip'] . $path . $pregex;
            $headers = array('host' => $ipToHost['host'], 'X-VC-Purge-Method' => $purgemethod, 'X-VC-Purge-Host' => $ipToHost['host']);
            if (!is_null($this->purgeKey)) {
                $headers['X-VC-Purge-Key'] = $this->purgeKey;
            }
            $response = wp_remote_request($purgeme, array('method' => 'PURGE', 'headers' => $headers, "sslverify" => false));
            if ($response instanceof WP_Error) {
                foreach ($response->errors as $error => $errors) {
                    $this->noticeMessage .= '<br />Error ' . $error . '<br />';
                    foreach ($errors as $error => $description) {
                        $this->noticeMessage .= ' - ' . $description . '<br />';
                    }
                }
            } else {
                if ($this->truncateNotice && $this->truncateCount <= 2 || $this->truncateNotice == false) {
                    $this->noticeMessage .= '' . __('Trying to purge URL : ', $this->plugin) . $purgeme;
                    preg_match("/<title>(.*)<\/title>/i", $response['body'], $matches);
                    $this->noticeMessage .= ' => <br /> ' . isset($matches[1]) ? " => " . $matches[1] : $response['body'];
                    $this->noticeMessage .= '<br />';
                    if ($this->debug) {
                        $this->noticeMessage .= $response['body'] . "<br />";
                    }
                }
                $this->truncateCount++;
            }
        }

        do_action('vcaching_after_purge_url', $url, $purgeme);
    }

    public function purge_post($postId, $post=null)
    {
        // Do not purge menu items
        if (get_post_type($post) == 'nav_menu_item' && $this->purgeOnMenuSave == false) {
            return;
        }

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
                    array_push($listofurls, get_category_link($cat->term_id));
                }
            }
            // Tag purge based on Donnacha's work in WP Super Cache
            $tags = get_the_tags($postId);
            if ($tags) {
                foreach ($tags as $tag) {
                    array_push($listofurls, get_tag_link($tag->term_id));
                }
            }

            // Author URL
            array_push($listofurls,
                get_author_posts_url(get_post_field('post_author', $postId)),
                get_author_feed_link(get_post_field('post_author', $postId))
            );

            // Archives and their feeds
            $archiveurls = array();
            if (get_post_type_archive_link(get_post_type($postId)) == true) {
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
            if (get_option('show_on_front') == 'page') {
                array_push($listofurls, get_permalink(get_option('page_for_posts')));
            }

            // If Automattic's AMP is installed, add AMP permalink
            if (function_exists('amp_get_permalink')) {
                array_push($listofurls, amp_get_permalink($postId));
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
        $this->purge_cache();
    }

    public function send_headers()
    {
        $enable = get_option($this->prefix . 'enable');
        if ($enable) {
            Header('X-VC-Enabled: true', true);
            if (is_user_logged_in()) {
                Header('X-VC-Cacheable: NO:User is logged in', true);
                $ttl = 0;
            } else {
                $ttl = get_option($this->prefix . 'ttl');
            }
            Header('X-VC-TTL: ' . $ttl, true);
            if ($this->debug) {
                Header('X-VC-Debug: true', true);
            }
        } else {
            Header('X-VC-Enabled: false', true);
        }
    }

    public function wp_login()
    {
        $cookie = get_option($this->prefix . 'cookie');
        if (!empty($cookie)) {
            setcookie($cookie, 1, time()+3600*24*100, COOKIEPATH, COOKIE_DOMAIN, false, true);
        }
    }

    public function wp_logout()
    {
        $cookie = get_option($this->prefix . 'cookie');
        if (!empty($cookie)) {
            setcookie($cookie, null, time()-3600*24*100, COOKIEPATH, COOKIE_DOMAIN, false, true);
        }
    }

    public function admin_menu()
    {
        add_action('admin_menu', array($this, 'add_menu_item'));
        add_action('admin_init', array($this, 'options_page_fields'));
        add_action('admin_init', array($this, 'console_page_fields'));
        if ($this->vclGeneratorTab) {
            add_action('admin_init', array($this, 'conf_page_fields'));
        }
    }

    public function add_menu_item()
    {
        if ($this->check_if_purgeable()) {
            add_menu_page(__('Varnish Caching', $this->plugin), __('Varnish Caching', $this->plugin), 'manage_options', $this->plugin . '-plugin', array($this, 'settings_page'), plugins_url() . '/' . $this->plugin . '/icon.png', 99);
        }
    }

    public function settings_page()
    {
    ?>
        <div class="wrap">
        <h1><?=__('Varnish Caching', $this->plugin)?></h1>

        <h2 class="nav-tab-wrapper">
            <a class="nav-tab <?php if($this->currentTab == 'options'): ?>nav-tab-active<?php endif; ?>" href="<?php echo admin_url() ?>index.php?page=<?=$this->plugin?>-plugin&amp;tab=options"><?=__('Settings', $this->plugin)?></a>
            <?php if ($this->check_if_purgeable()): ?>
                <a class="nav-tab <?php if($this->currentTab == 'console'): ?>nav-tab-active<?php endif; ?>" href="<?php echo admin_url() ?>index.php?page=<?=$this->plugin?>-plugin&amp;tab=console"><?=__('Console', $this->plugin)?></a>
            <?php endif; ?>
            <a class="nav-tab <?php if($this->currentTab == 'stats'): ?>nav-tab-active<?php endif; ?>" href="<?php echo admin_url() ?>index.php?page=<?=$this->plugin?>-plugin&amp;tab=stats"><?=__('Statistics', $this->plugin)?></a>
            <?php if ($this->vclGeneratorTab): ?>
                <a class="nav-tab <?php if($this->currentTab == 'conf'): ?>nav-tab-active<?php endif; ?>" href="<?php echo admin_url() ?>index.php?page=<?=$this->plugin?>-plugin&amp;tab=conf"><?=__('VCLs Generator', $this->plugin)?></a>
            <?php endif; ?>
        </h2>

        <?php if($this->currentTab == 'options'): ?>
            <form method="post" action="options.php">
                <?php
                    settings_fields($this->prefix . 'options');
                    do_settings_sections($this->prefix . 'options');
                    submit_button();
                ?>
            </form>
            <script type="text/javascript">
                function generateHash(length, bits, id) {
                    bits = bits || 36;
                    var outStr = "", newStr;
                    while (outStr.length < length)
                    {
                        newStr = Math.random().toString(bits).slice(2);
                        outStr += newStr.slice(0, Math.min(newStr.length, (length - outStr.length)));
                    }
                    jQuery('#' + id).val(outStr);
                }
            </script>
        <?php elseif($this->currentTab == 'console'): ?>
            <form method="post" action="index.php?page=<?=$this->plugin?>-plugin&amp;tab=console">
                <?php
                    settings_fields($this->prefix . 'console');
                    do_settings_sections($this->prefix . 'console');
                    submit_button(__('Purge', $this->plugin));
                ?>
            </form>
        <?php elseif($this->currentTab == 'stats'): ?>
            <h2><?= __('Statistics', $this->plugin) ?></h2>

            <div class="wrap">
                <?php if ($_GET['info'] == 1 || trim($this->statsJsons) == ""): ?>
                    <div class="fade">
                        <h4><?=__('Setup information', $this->plugin)?></h4>
                        <?= __('<strong>Short story</strong><br />You must generate by cronjob the JSON stats file. The generated files must be copied on the backend servers in the wordpress root folder.', $this->plugin) ?>
                        <br /><br />
                        <?=sprintf(__('<strong>Long story</strong><br />On every Varnish Cache server setup a cronjob that generates the JSON stats file :<br /> %1$s /path/to/be/set/filename.json # every 3 minutes.', $this->plugin), '*/3 * * * *     root   /usr/bin/varnishstat -1j >')?>
                        <br />
                        <?= __('The generated files must be copied on the backend servers in the wordpress root folder.', $this->plugin) ?>
                        <br />
                        <?=__("Use a different filename for each Varnish Cache server. After this is done, fill in the relative path to the files in Statistics JSONs on the Settings tab.", $this->plugin)?>
                        <br /><br />
                        <?= __('Example 1 <br />If you have a single server, both Varnish Cache and the backend on it, use the folowing cronjob:', $this->plugin) ?>
                        <br />
                        <?=sprintf(__('%1$s /path/to/the/wordpress/root/varnishstat.json # every 3 minutes.', $this->plugin), '*/3 * * * *     root   /usr/bin/varnishstat -1j >')?>
                        <br />
                        <?=__("Then fill in the relative path to the files in Statistics JSONs on the Settings tab :", $this->plugin)?>
                        <br />
                        <input type="text" size="100" value="<?=__("/varnishstat.json", $this->plugin)?>" />

                        <br /><br />
                        <?=__("Example 2 <br />You have 2 Varnish Cache Servers, and 3 backend servers. Setup the cronjob :", $this->plugin)?>
                        <br />
                        <?=sprintf(__('VC Server 1 : %1$s # every 3 minutes.', $this->plugin), '*/3 * * * *     root   /usr/bin/varnishstat -1j > /root/varnishstat/server1_3389398cd359cfa443f85ca040da069a.json')?>
                        <br />
                        <?=sprintf(__('VC Server 2 : %1$s # every 3 minutes.', $this->plugin), '*/3 * * * *     root   /usr/bin/varnishstat -1j > /root/varnishstat/server2_3389398cd359cfa443f85ca040da069a.json')?>
                        <br />
                        <?=__("Copy the files on the backend servers in /path/to/wordpress/root/varnishstat/ folder. Then fill in the relative path to the files in Statistics JSONs on the Settings tab :", $this->plugin)?>
                        <br />

                        <input type="text" size="100" value="<?=__("/varnishstat/server1_3389398cd359cfa443f85ca040da069a.json,/varnishstat/server2_3389398cd359cfa443f85ca040da069a.json", $this->plugin)?>" />
                    </div>
                <?php endif; ?>

                <?php if(trim($this->statsJsons)): ?>
                    <h2 class="nav-tab-wrapper">
                        <?php foreach ($this->ipsToHosts as $server => $ipToHost): ?>
                            <a class="server nav-tab <?php if($server == 0) echo "nav-tab-active"; ?>" href="#" server="<?=$server?>"><?= sprintf(__('Server %1$s', $this->plugin), $ipToHost['ip'])?></a>
                        <?php endforeach; ?>
                    </h2>

                    <?php foreach ($this->ipsToHosts as $server => $ipToHost): ?>
                        <div id="server_<?=$server?>" class="servers" style="display:<?php if($server == 0) {echo 'block';} else {echo 'none';} ?>">
                            <?= sprintf(__('Fetching stats for server %1$s', $this->plugin), $ipToHost['ip']) ?>
                        </div>
                        <script type="text/javascript">
                            jQuery.getJSON("<?=$ipToHost['statsJson']?>", function(data) {
                                var server = '#server_<?=$server?>';
                                jQuery(server).html('');
                                jQuery(server).append('<p><?= __('Stats generated on', $this->plugin) ?> ' + data.timestamp + '</p>');
                                jQuery(server).append('<table class="wp-list-table widefat fixed striped server_<?=$server?>"><thead><tr><td class="manage-column"><strong><?= __('Description', $this->plugin) ?></strong></td><td class="manage-column"><strong><?= __('Value', $this->plugin) ?></strong></td><td class="manage-column"><strong><?= __('Key', $this->plugin) ?></strong></td></tr></thead><tbody id="varnishstats_<?=$server?>"></tbody></table>');
                                delete data.timestamp;
                                jQuery.each(data, function(key, val) {
                                    jQuery('#varnishstats_<?=$server?>').append('<tr><td>'+val.description+'</td><td>'+val.value+'</td><td>'+key+'</td></tr>');
                                });
                            });
                        </script>
                    <?php endforeach; ?>
                    <script type="text/javascript">
                        jQuery('.nav-tab-wrapper > a.server').click(function(e){
                            e.preventDefault();
                            jQuery('.nav-tab-wrapper > a.server').removeClass('nav-tab-active');
                            jQuery(this).addClass('nav-tab-active');
                            jQuery(".servers").hide();
                            jQuery("#server_" + jQuery(this).attr('server')).show();
                        });
                    </script>
                <?php endif; ?>
            </div>
        <?php elseif($this->currentTab == 'conf'): ?>
            <form method="post" action="options.php">
                <?php
                    settings_fields($this->prefix . 'conf');
                    do_settings_sections($this->prefix . 'conf');
                    submit_button();
                ?>
            </form>
            <?php if (class_exists('ZipArchive')): ?>
                <form method="post" action="index.php?page=<?=$this->plugin?>-plugin&amp;tab=conf">
                    <?php
                        settings_fields($this->prefix . 'download');
                        do_settings_sections($this->prefix . 'download');
                        submit_button(__('Download'));
                    ?>
                </form>
            <?php else: ?>
                <?php
                    do_settings_sections($this->prefix . 'download_error');
                    echo sprintf(__('You server\'s PHP configuration is missing the ZIP extension (ZipArchive class is used by VCaching). Please enable the ZIP extension. For more information click <a href="%1$s" target="_blank">here</a>.', $this->plugin), 'http://www.php.net/manual/en/book.zip.php');
                    echo "<br />";
                    echo __('If you cannot enable the ZIP extension, please use the provided Varnish Cache configuration files located in /wp-content/plugins/vcaching/varnish-conf folder', $this->plugin);
                ?>
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
        add_settings_field($this->prefix . "cookie", __("Logged in cookie", $this->plugin), array($this, $this->prefix . "cookie"), $this->prefix . 'options', $this->prefix . 'options');
        add_settings_field($this->prefix . "stats_json_file", __("Statistics JSONs", $this->plugin), array($this, $this->prefix . "stats_json_file"), $this->prefix . 'options', $this->prefix . 'options');
        add_settings_field($this->prefix . "truncate_notice", __("Truncate notice message", $this->plugin), array($this, $this->prefix . "truncate_notice"), $this->prefix . 'options', $this->prefix . 'options');
        add_settings_field($this->prefix . "purge_menu_save", __("Purge on save menu", $this->plugin), array($this, $this->prefix . "purge_menu_save"), $this->prefix . 'options', $this->prefix . 'options');
        add_settings_field($this->prefix . "ssl", __("Use SSL on purge requests", $this->plugin), array($this, $this->prefix . "ssl"), $this->prefix . 'options', $this->prefix . 'options');
        add_settings_field($this->prefix . "debug", __("Enable debug", $this->plugin), array($this, $this->prefix . "debug"), $this->prefix . 'options', $this->prefix . 'options');

        if(isset($_POST['option_page']) && $_POST['option_page'] == $this->prefix . 'options') {
            register_setting($this->prefix . 'options', $this->prefix . "enable");
            register_setting($this->prefix . 'options', $this->prefix . "ttl");
            register_setting($this->prefix . 'options', $this->prefix . "homepage_ttl");
            register_setting($this->prefix . 'options', $this->prefix . "ips");
            register_setting($this->prefix . 'options', $this->prefix . "dynamic_host");
            register_setting($this->prefix . 'options', $this->prefix . "hosts");
            register_setting($this->prefix . 'options', $this->prefix . "override");
            register_setting($this->prefix . 'options', $this->prefix . "purge_key");
            register_setting($this->prefix . 'options', $this->prefix . "cookie");
            register_setting($this->prefix . 'options', $this->prefix . "stats_json_file");
            register_setting($this->prefix . 'options', $this->prefix . "truncate_notice");
            register_setting($this->prefix . 'options', $this->prefix . "purge_menu_save");
            register_setting($this->prefix . 'options', $this->prefix . "ssl");
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
            <input type="text" name="varnish_caching_purge_key" id="varnish_caching_purge_key" size="100" maxlength="64" value="<?php echo get_option($this->prefix . 'purge_key'); ?>" />
            <span onclick="generateHash(64, 0, 'varnish_caching_purge_key'); return false;" class="dashicons dashicons-image-rotate" title="<?=__('Generate')?>"></span>
            <p class="description">
                <?=__('Key used to purge Varnish cache. It is sent to Varnish as X-VC-Purge-Key header. Use a SHA-256 hash.<br />If you can\'t use ACL\'s, use this option. You can set the `purge key` in lib/purge.vcl.<br />Search the default value ff93c3cb929cee86901c7eefc8088e9511c005492c6502a930360c02221cf8f4 to find where to replace it.', $this->plugin)?>
            </p>
        <?php
    }

    public function varnish_caching_cookie()
    {
        ?>
            <input type="text" name="varnish_caching_cookie" id="varnish_caching_cookie" size="100" maxlength="64" value="<?php echo get_option($this->prefix . 'cookie'); ?>" />
            <span onclick="generateHash(64, 0, 'varnish_caching_cookie'); return false;" class="dashicons dashicons-image-rotate" title="<?=__('Generate')?>"></span>
            <p class="description">
                <?=__('This module sets a special cookie to tell Varnish that the user is logged in. Use a SHA-256 hash. You can set the `logged in cookie` in default.vcl.<br />Search the default value <i>flxn34napje9kwbwr4bjwz5miiv9dhgj87dct4ep0x3arr7ldif73ovpxcgm88vs</i> to find where to replace it.', $this->plugin)?>
            </p>
        <?php
    }

    public function varnish_caching_stats_json_file()
    {
        ?>
            <input type="text" name="varnish_caching_stats_json_file" id="varnish_caching_stats_json_file" size="100" value="<?php echo get_option($this->prefix . 'stats_json_file'); ?>" />
            <p class="description">
                <?=sprintf(__('Comma separated relative URLs. One for each IP. <a href="%1$s/wp-admin/index.php?page=vcaching-plugin&tab=stats&info=1">Click here</a> for more info on how to set this up.', $this->plugin), home_url())?>
            </p>
        <?php
    }

    public function varnish_caching_truncate_notice()
    {
        ?>
            <input type="checkbox" name="varnish_caching_truncate_notice" value="1" <?php checked(1, get_option($this->prefix . 'truncate_notice'), true); ?> />
            <p class="description">
                <?=__('When using multiple Varnish Cache servers, VCaching shows too many `Trying to purge URL` messages. Check this option to truncate that message.', $this->plugin)?>
            </p>
        <?php
    }

    public function varnish_caching_purge_menu_save()
    {
        ?>
            <input type="checkbox" name="varnish_caching_purge_menu_save" value="1" <?php checked(1, get_option($this->prefix . 'purge_menu_save'), true); ?> />
            <p class="description">
                <?=__('Purge menu related pages when a menu is saved.', $this->plugin)?>
            </p>
        <?php
    }

    public function varnish_caching_ssl()
    {
        ?>
            <input type="checkbox" name="varnish_caching_ssl" value="1" <?php checked(1, get_option($this->prefix . 'ssl'), true); ?> />
            <p class="description">
                <?=__('Use SSL (https://) for purge requests.', $this->plugin)?>
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

    public function conf_page_fields()
    {
        add_settings_section('conf', __("Varnish configuration", $this->plugin), null, $this->prefix . 'conf');

        add_settings_field($this->prefix . "varnish_backends", __("Backends", $this->plugin), array($this, $this->prefix . "varnish_backends"), $this->prefix . 'conf', "conf");
        add_settings_field($this->prefix . "varnish_acls", __("ACLs", $this->plugin), array($this, $this->prefix . "varnish_acls"), $this->prefix . 'conf', "conf");

        if(isset($_POST['option_page']) && $_POST['option_page'] == $this->prefix . 'conf') {
            register_setting($this->prefix . 'conf', $this->prefix . "varnish_backends");
            register_setting($this->prefix . 'conf', $this->prefix . "varnish_acls");
        }

        add_settings_section('download', __("Get configuration files", $this->plugin), null, $this->prefix . 'download');
        if (!class_exists('ZipArchive')) {
            add_settings_section('download_error', __("You cannot download the configuration files", $this->plugin), null, $this->prefix . 'download_error');
        }

        add_settings_field($this->prefix . "varnish_version", __("Version", $this->plugin), array($this, $this->prefix . "varnish_version"), $this->prefix . 'download', "download");

        if(isset($_POST['option_page']) && $_POST['option_page'] == $this->prefix . 'download') {
            $version = in_array($_POST['varnish_caching_varnish_version'], array(3,4,5)) ? $_POST['varnish_caching_varnish_version'] : 3;
            $tmpfile = tempnam(sys_get_temp_dir(), "zip");
            $zip = new ZipArchive();
            $zip->open($tmpfile, ZipArchive::OVERWRITE);
            $files = array(
                'default.vcl' => true,
                'LICENSE' => false,
                'README.rst' => false,
                'conf/acl.vcl' => true,
                'conf/backend.vcl' => true,
                'lib/bigfiles.vcl' => false,
                'lib/bigfiles_pipe.vcl' => false,
                'lib/cloudflare.vcl' => false,
                'lib/mobile_cache.vcl' => false,
                'lib/mobile_pass.vcl' => false,
                'lib/purge.vcl' => true,
                'lib/static.vcl' => false,
                'lib/xforward.vcl' => false,
            );
            foreach ($files as $file => $parse) {
                $filepath = __DIR__ . '/varnish-conf/v' . $version . '/' . $file;
                if ($parse) {
                    $content = $this->_parse_conf_file($version, $file, file_get_contents($filepath));
                } else {
                    $content = file_get_contents($filepath);
                }
                $zip->addFromString($file, $content);
            }
            $zip->close();
            header('Content-Type: application/zip');
            header('Content-Length: ' . filesize($tmpfile));
            header('Content-Disposition: attachment; filename="varnish_v' . $version . '_conf.zip"');
            readfile($tmpfile);
            unlink($tmpfile);
            exit();
        }
    }

    public function varnish_caching_varnish_version()
    {
        ?>
            <select name="varnish_caching_varnish_version" id="varnish_caching_varnish_version">
                <option value="3">3</option>
                <option value="4">4</option>
                <option value="5">5</option>
            </select>
            <p class="description"><?=__('Varnish Cache version', $this->plugin)?></p>
        <?php
    }

    public function varnish_caching_varnish_backends()
    {
        ?>
            <input type="text" name="varnish_caching_varnish_backends" id="varnish_caching_varnish_backends" size="100" value="<?php echo get_option($this->prefix . 'varnish_backends'); ?>" />
            <p class="description"><?=__('Comma separated ip/ip:port. Example : 192.168.0.2,192.168.0.3:8080', $this->plugin)?></p>
        <?php
    }

    public function varnish_caching_varnish_acls()
    {
        ?>
            <input type="text" name="varnish_caching_varnish_acls" id="varnish_caching_varnish_acls" size="100" value="<?php echo get_option($this->prefix . 'varnish_acls'); ?>" />
            <p class="description"><?=__('Comma separated ip/ip range. Example : 192.168.0.2,192.168.1.1/24', $this->plugin)?></p>
        <?php
    }

    private function _parse_conf_file($version, $file, $content)
    {
        if ($file == 'default.vcl') {
            $logged_in_cookie = get_option($this->prefix . 'cookie');
            $content = str_replace('flxn34napje9kwbwr4bjwz5miiv9dhgj87dct4ep0x3arr7ldif73ovpxcgm88vs', $logged_in_cookie, $content);
        } else if ($file == 'conf/backend.vcl') {
            if ($version == 3) {
                $content = "";
            } else if ($version == 4 || $version == 5) {
                $content = "import directors;\n\n";
            }
            $backend = array(3 => "", 4 => "");
            $ips = get_option($this->prefix . 'varnish_backends');
            $ips = explode(',', $ips);
            $id = 1;
            foreach ($ips as $ip) {
                if (strstr($ip, ":")) {
                    $_ip = explode(':', $ip);
                    $ip = $_ip[0];
                    $port = $_ip[1];
                } else {
                    $port = 80;
                }
                $content .= "backend backend" . $id . " {\n";
                $content .= "\t.host = \"" . $ip . "\";\n";
                $content .= "\t.port = \"" . $port . "\";\n";
                $content .= "}\n";
                $backend[3] .= "\t{\n";
                $backend[3] .= "\t\t.backend = backend" . $id . ";\n";
                $backend[3] .= "\t}\n";
                $backend[4] .= "\tbackends.add_backend(backend" . $id . ");\n";
                $id++;
            }
            if ($version == 3) {
                $content .= "\ndirector backends round-robin {\n";
                $content .= $backend[3];
                $content .= "}\n";
                $content .= "\nsub vcl_recv {\n";
                $content .= "\tset req.backend = backends;\n";
                $content .= "}\n";
            } elseif ($version == 4 || $version == 5) {
                $content .= "\nsub vcl_init {\n";
                $content .= "\tnew backends = directors.round_robin();\n";
                $content .= $backend[4];
                $content .= "}\n";
                $content .= "\nsub vcl_recv {\n";
                $content .= "\tset req.backend_hint = backends.backend();\n";
                $content .= "}\n";
            }
        } else if ($file == 'conf/acl.vcl') {
            $acls = get_option($this->prefix . 'varnish_acls');
            $acls = explode(',', $acls);
            $content = "acl cloudflare {\n";
            $content .= "\t# set this ip to your Railgun IP (if applicable)\n";
            $content .= "\t# \"1.2.3.4\";\n";
            $content .= "}\n";
            $content .= "\nacl purge {\n";
            $content .= "\t\"localhost\";\n";
            $content .= "\t\"127.0.0.1\";\n";
            foreach ($acls as $acl) {
                $content .= "\t\"" . $acl . "\";\n";
            }
            $content .= "}\n";
        } else if ($file == 'lib/purge.vcl') {
            $purge_key = get_option($this->prefix . 'purge_key');
            $content = str_replace('ff93c3cb929cee86901c7eefc8088e9511c005492c6502a930360c02221cf8f4', $purge_key, $content);
        }
        return $content;
    }

    public function post_row_actions($actions, $post)
    {
        if ($this->check_if_purgeable()) {
            $actions = array_merge($actions, array(
                'vcaching_purge_post' => sprintf('<a href="%s">' . __('Purge from Varnish', $this->plugin) . '</a>', wp_nonce_url(sprintf('admin.php?page=vcaching-plugin&tab=console&action=purge_post&post_id=%d', $post->ID), $this->plugin))
            ));
        }
        return $actions;
    }

    public function page_row_actions($actions, $post)
    {
        if ($this->check_if_purgeable()) {
            $actions = array_merge($actions, array(
                'vcaching_purge_page' => sprintf('<a href="%s">' . __('Purge from Varnish', $this->plugin) . '</a>', wp_nonce_url(sprintf('admin.php?page=vcaching-plugin&tab=console&action=purge_page&post_id=%d', $post->ID), $this->plugin))
            ));
        }
        return $actions;
    }
}

$vcaching = new VCaching();

// WP-CLI
if (defined('WP_CLI') && WP_CLI) {
    include('wp-cli.php');
}
