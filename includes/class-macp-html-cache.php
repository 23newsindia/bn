<?php
require_once MACP_PLUGIN_DIR . 'includes/class-macp-debug.php';
require_once MACP_PLUGIN_DIR . 'includes/class-macp-filesystem.php';

class MACP_HTML_Cache {
    private $cache_dir;
    private $excluded_urls;

    public function __construct() {
        $this->cache_dir = MACP_PLUGIN_DIR . 'cache/';
        $this->excluded_urls = [
            'wp-login.php',
            'wp-admin',
            'wp-cron.php',
            'wp-content',
            'wp-includes',
            'xmlrpc.php',
            'wp-api',
            '/cart/',
            '/checkout/',
            '/my-account/',
            'add-to-cart',
            'logout',
            'lost-password',
            'register'
        ];
        
        $this->ensure_cache_directory();
    }

    private function ensure_cache_directory() {
        if (MACP_Filesystem::ensure_directory($this->cache_dir)) {
            // Create .htaccess to protect cache directory
            $htaccess_file = $this->cache_dir . '.htaccess';
            if (!file_exists($htaccess_file)) {
                MACP_Filesystem::write_file($htaccess_file, "deny from all");
            }
        }
    }

    public function should_cache_page() {
        // Debug current request
        MACP_Debug::log("Checking if page should be cached: " . $_SERVER['REQUEST_URI']);

        if (is_admin()) {
            MACP_Debug::log("Not caching: Admin page");
            return false;
        }

        if (is_user_logged_in()) {
            MACP_Debug::log("Not caching: User is logged in");
            return false;
        }

        if (is_search() || $_SERVER['REQUEST_METHOD'] === 'POST' || is_preview()) {
            MACP_Debug::log("Not caching: Search/POST/Preview");
            return false;
        }

        if (function_exists('is_woocommerce') && (is_cart() || is_checkout() || is_account_page())) {
            MACP_Debug::log("Not caching: WooCommerce page");
            return false;
        }

        $current_url = $_SERVER['REQUEST_URI'];
        foreach ($this->excluded_urls as $excluded_url) {
            if (strpos($current_url, $excluded_url) !== false) {
                MACP_Debug::log("Not caching: Excluded URL pattern found - {$excluded_url}");
                return false;
            }
        }

        MACP_Debug::log("Page can be cached");
        return true;
    }

    public function start_buffer() {
        if ($this->should_cache_page()) {
            MACP_Debug::log("Starting output buffer");
            ob_start([$this, 'cache_output']);
        }
    }

    public function cache_output($buffer) {
        if (strlen($buffer) < 255) {
            MACP_Debug::log("Buffer too small to cache: " . strlen($buffer) . " bytes");
            return $buffer;
        }

        $url_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $cache_key = md5($url_path . '|' . $_SERVER['HTTP_HOST']);
        $cache_file = $this->cache_dir . $cache_key . '.html';
        $cache_gzip = $this->cache_dir . $cache_key . '.html.gz';

        MACP_Debug::log("Caching page to: " . $cache_file);

        // Add cache creation time comment
        $buffer = preg_replace('/(<\/html>)/i', '<!-- Cached by MACP on ' . current_time('mysql') . ' -->\n$1', $buffer);
      
        // Check if minification options are enabled and apply minification
    if (get_option('macp_minify_html', 0) || 
        get_option('macp_minify_css', 0) || 
        get_option('macp_minify_js', 0)) {
        $minification = new MACP_Minification();
        $buffer = $minification->process_output($buffer);
    }

        // Save uncompressed version
        if (!MACP_Filesystem::write_file($cache_file, $buffer)) {
            MACP_Debug::log("Failed to write cache file");
            return $buffer;
        }
        
        // Save gzipped version if enabled
        if (get_option('macp_enable_gzip', 1)) {
            MACP_Filesystem::write_file($cache_gzip, gzencode($buffer, 9));
        }

        return $buffer;
    }

    public function clear_cache($post_id = null) {
        MACP_Debug::log("Clearing cache" . ($post_id ? " for post ID: {$post_id}" : ""));
        
        if ($post_id) {
            $url = get_permalink($post_id);
            if ($url) {
                $url_path = parse_url($url, PHP_URL_PATH);
                $cache_key = md5($url_path . '|' . $_SERVER['HTTP_HOST']);
                $files_to_delete = glob($this->cache_dir . $cache_key . '*');
                
                foreach ($files_to_delete as $file) {
                    if (is_file($file)) {
                        unlink($file);
                        MACP_Debug::log("Deleted cache file: " . $file);
                    }
                }
            }
        } else {
            $files = glob($this->cache_dir . '*.{html,gz}', GLOB_BRACE);
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                    MACP_Debug::log("Deleted cache file: " . $file);
                }
            }
        }
    }
}