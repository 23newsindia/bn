<?php
/*
Plugin Name: My Advanced Cache Plugin
Description: Integrates Redis for object caching and static HTML caching with WP Rocket-like interface
Version: 1.3
Author: Your Name
*/

if (!defined('ABSPATH')) exit;

define('MACP_PLUGIN_FILE', __FILE__);
define('MACP_PLUGIN_DIR', plugin_dir_path(__FILE__));

// Load Composer autoloader
if (file_exists(MACP_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once MACP_PLUGIN_DIR . 'vendor/autoload.php';
}

require_once MACP_PLUGIN_DIR . 'includes/class-macp-debug.php';
require_once MACP_PLUGIN_DIR . 'includes/class-macp-filesystem.php';
require_once MACP_PLUGIN_DIR . 'includes/class-macp-redis.php';
require_once MACP_PLUGIN_DIR . 'includes/class-macp-minification.php';
require_once MACP_PLUGIN_DIR . 'includes/class-macp-html-cache.php';
require_once MACP_PLUGIN_DIR . 'includes/class-macp-admin.php';
require_once MACP_PLUGIN_DIR . 'includes/class-macp-debug-utility.php';

class MACP_Plugin {
    private $redis;
    private $html_cache;
    private $admin;

    public function __construct() {
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        $this->redis = new MACP_Redis();
        $this->html_cache = new MACP_HTML_Cache();
        $this->admin = new MACP_Admin($this->redis);

        $this->init_hooks();
        
        MACP_Debug::log('Plugin initialized');
    }

    public function activate() {
        MACP_Debug::log('Plugin activated');
        
        // Create cache directory
        $cache_dir = WP_CONTENT_DIR . '/cache/macp';
        if (!file_exists($cache_dir)) {
            wp_mkdir_p($cache_dir);
        }
        
        // Set default options
        add_option('macp_enable_html_cache', 1);
        add_option('macp_enable_gzip', 1);
        add_option('macp_enable_redis', 1);
        add_option('macp_minify_html', 0);
    }

    public function deactivate() {
        if ($this->html_cache) {
            $this->html_cache->clear_cache();
        }
        MACP_Debug::log('Plugin deactivated');
    }

    private function init_hooks() {
        if (get_option('macp_enable_html_cache', 1)) {
            add_action('template_redirect', [$this->html_cache, 'start_buffer'], -9999);
            add_action('save_post', [$this->html_cache, 'clear_cache']);
            add_action('comment_post', [$this->html_cache, 'clear_cache']);
            add_action('wp_trash_post', [$this->html_cache, 'clear_cache']);
            add_action('switch_theme', [$this->html_cache, 'clear_cache']);
        }

        if (get_option('macp_enable_redis', 1)) {
            add_action('init', [$this->redis, 'prime_cache']);
        }
    }
}

// Initialize the plugin
new MACP_Plugin();