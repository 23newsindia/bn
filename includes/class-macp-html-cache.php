<?php
require_once MACP_PLUGIN_DIR . 'includes/class-macp-debug.php';
require_once MACP_PLUGIN_DIR . 'includes/class-macp-filesystem.php';

class MACP_HTML_Cache {
    private $cache_dir;
    private $excluded_urls;
    private $user_cache_dir;

    public function __construct() {
        $this->cache_dir = WP_CONTENT_DIR . '/cache/macp/';
        $this->user_cache_dir = $this->cache_dir . 'users/';
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
        
        // Create users cache directory
        if (!MACP_Filesystem::ensure_directory($this->user_cache_dir)) {
            MACP_Debug::log("Failed to create or access user cache directory: " . $this->user_cache_dir);
            return false;
        }
        
        // Create index.php files for security
        if (!file_exists($this->cache_dir . 'index.php')) {
            MACP_Filesystem::write_file($this->cache_dir . 'index.php', '<?php // Silence is golden');
        }
        if (!file_exists($this->user_cache_dir . 'index.php')) {
            MACP_Filesystem::write_file($this->user_cache_dir . 'index.php', '<?php // Silence is golden');
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

    private function get_cache_path($is_logged_in = false, $user_id = 0) {
        $url_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $cache_key = md5($url_path . '|' . $_SERVER['HTTP_HOST']);
        
        if ($is_logged_in && $user_id) {
            // Create user-specific directory if it doesn't exist
            $user_specific_dir = $this->user_cache_dir . $user_id . '/';
            if (!file_exists($user_specific_dir)) {
                MACP_Filesystem::ensure_directory($user_specific_dir);
            }
            return [
                'html' => $user_specific_dir . $cache_key . '.html',
                'gzip' => $user_specific_dir . $cache_key . '.html.gz'
            ];
        }
        
        return [
            'html' => $this->cache_dir . $cache_key . '.html',
            'gzip' => $this->cache_dir . $cache_key . '.html.gz'
        ];
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

        $is_logged_in = is_user_logged_in();
        $user_id = get_current_user_id();
        $cache_paths = $this->get_cache_path($is_logged_in, $user_id);

        MACP_Debug::log("Processing page for cache: " . $_SERVER['REQUEST_URI'] . 
            ($is_logged_in ? " (User ID: {$user_id})" : " (Guest)"));

        // Add cache creation time and user status comment
        $user_status = $is_logged_in ? "logged-in user (ID: {$user_id})" : "guest";
        $buffer = preg_replace(
            '/(<\/html>)/i',
            "<!-- Cached by MACP on " . current_time('mysql') . " for {$user_status} -->\n$1",
            $buffer
        );
      
        // Check if minification is enabled
        if (get_option('macp_minify_html', 0)) {
            MACP_Debug::log("Applying HTML minification");
            $minification = new MACP_Minification();
            $buffer = $minification->process_output($buffer);
        }

        // Save uncompressed version
        if (!MACP_Filesystem::write_file($cache_paths['html'], $buffer)) {
            MACP_Debug::log("Failed to write cache file: " . $cache_paths['html']);
            return $buffer;
        }
        
        MACP_Debug::log("Successfully wrote cache file: " . $cache_paths['html']);
        
        // Save gzipped version if enabled
        if (get_option('macp_enable_gzip', 1)) {
            $gzipped = gzencode($buffer, 9);
            if ($gzipped && MACP_Filesystem::write_file($cache_paths['gzip'], $gzipped)) {
                MACP_Debug::log("Successfully wrote gzipped cache file");
            }
        }

        return $buffer;
    }

      public function clear_page_cache($url) {
        if (empty($url)) {
            MACP_Debug::log("Invalid URL provided for cache clearing");
            return false;
        }

        $url_path = parse_url($url, PHP_URL_PATH);
        $cache_key = md5($url_path . '|' . $_SERVER['HTTP_HOST']);
        
        // Clear guest cache for this page
        $files_to_delete = glob($this->cache_dir . $cache_key . '*');
        foreach ($files_to_delete as $file) {
            if (is_file($file)) {
                unlink($file);
                MACP_Debug::log("Deleted guest cache file: " . $file);
            }
        }
        
        // Clear user-specific caches for this page
        $user_dirs = glob($this->user_cache_dir . '*', GLOB_ONLYDIR);
        foreach ($user_dirs as $user_dir) {
            $files_to_delete = glob($user_dir . '/' . $cache_key . '*');
            foreach ($files_to_delete as $file) {
                if (is_file($file)) {
                    unlink($file);
                    MACP_Debug::log("Deleted user cache file: " . $file);
                }
            }
        }

        return true;
    }

    public function clear_cache($post_id = null) {
        if ($post_id) {
            $url = get_permalink($post_id);
            return $this->clear_page_cache($url);
        }
        
        // Clear all cache if no post_id provided
        MACP_Debug::log("Clearing all cache");
        
        // Clear all guest cache files
        $files = glob($this->cache_dir . '*.{html,gz}', GLOB_BRACE);
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
                MACP_Debug::log("Deleted guest cache file: " . $file);
            }
        }
        
        // Clear all user-specific cache files
        $user_dirs = glob($this->user_cache_dir . '*', GLOB_ONLYDIR);
        foreach ($user_dirs as $user_dir) {
            $files = glob($user_dir . '/*.{html,gz}', GLOB_BRACE);
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                    MACP_Debug::log("Deleted user cache file: " . $file);
                }
            }
        }

        return true;
    }
}