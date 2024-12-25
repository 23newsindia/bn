<?php
require_once MACP_PLUGIN_DIR . 'includes/class-macp-debug.php';
require_once MACP_PLUGIN_DIR . 'includes/class-macp-filesystem.php';

class MACP_HTML_Cache {
    private $cache_dir;
    private $excluded_urls;

    public function __construct() {
        $this->cache_dir = WP_CONTENT_DIR . '/cache/macp/';
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
        if (!MACP_Filesystem::ensure_directory($this->cache_dir)) {
            MACP_Debug::log("Failed to create or access cache directory: " . $this->cache_dir);
            return false;
        }
        
        // Create index.php for security
        if (!file_exists($this->cache_dir . 'index.php')) {
            MACP_Filesystem::write_file($this->cache_dir . 'index.php', '<?php // Silence is golden');
        }
        
        return true;
    }

    public function should_cache_page() {
        if (!is_dir($this->cache_dir) || !is_writable($this->cache_dir)) {
            MACP_Debug::log("Cache directory not writable: " . $this->cache_dir);
            return false;
        }

        if (is_admin()) {
            MACP_Debug::log("Not caching: Admin page");
            return false;
        }

        if (is_user_logged_in()) {
            MACP_Debug::log("Not caching: User is logged in");
            return false;
        }

        if (is_search() || $_SERVER['REQUEST_METHOD'] !== 'GET' || is_preview()) {
            MACP_Debug::log("Not caching: Search/Non-GET/Preview");
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

        MACP_Debug::log("Processing page for cache: " . $url_path);

        // Add cache creation time comment
        $buffer = preg_replace('/(<\/html>)/i', '<!-- Cached by MACP on ' . current_time('mysql') . ' -->\n$1', $buffer);
      
        // Check if minification is enabled
        if (get_option('macp_minify_html', 0)) {
            MACP_Debug::log("Applying HTML minification");
            $minification = new MACP_Minification();
            $buffer = $minification->process_output($buffer);
        }

        // Save uncompressed version
        if (!MACP_Filesystem::write_file($cache_file, $buffer)) {
            MACP_Debug::log("Failed to write cache file: " . $cache_file);
            return $buffer;
        }
        
        MACP_Debug::log("Successfully wrote cache file: " . $cache_file);
        
        // Save gzipped version if enabled
        if (get_option('macp_enable_gzip', 1)) {
            $gzipped = gzencode($buffer, 9);
            if ($gzipped && MACP_Filesystem::write_file($cache_gzip, $gzipped)) {
                MACP_Debug::log("Successfully wrote gzipped cache file");
            }
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