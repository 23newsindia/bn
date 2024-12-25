<?php
class MACP_Cache_Manager {
    private $html_cache;
    private $admin_bar;

    public function __construct(MACP_HTML_Cache $html_cache) {
        $this->html_cache = $html_cache;
        
        // Hook into WordPress actions
        add_action('init', [$this, 'init']);
        add_action('save_post', [$this, 'handle_post_update'], 10, 2);
        add_action('admin_post_macp_clear_all_cache', [$this, 'handle_clear_all_cache']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    public function init() {
        // Initialize admin bar after WordPress is fully loaded
        if (is_user_logged_in()) {
            require_once MACP_PLUGIN_DIR . 'includes/admin/class-macp-admin-bar.php';
            $this->admin_bar = new MACP_Admin_Bar($this->html_cache);
        }
    }

    public function handle_post_update($post_id, $post) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Clear cache only for this specific post
        $this->html_cache->clear_page_cache(get_permalink($post_id));
    }

    public function handle_clear_all_cache() {
        check_admin_referer('macp_clear_all_cache');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $this->html_cache->clear_cache();
        
        wp_redirect(wp_get_referer() ?: admin_url());
        exit;
    }

    public function enqueue_scripts() {
        if (!current_user_can('manage_options')) {
            return;
        }

        wp_enqueue_script(
            'macp-admin',
            plugins_url('assets/js/admin.js', MACP_PLUGIN_FILE),
            ['jquery'],
            MACP_VERSION,
            true
        );

        wp_localize_script('macp-admin', 'macpAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('macp_ajax_nonce'),
            'currentUrl' => esc_url(home_url($_SERVER['REQUEST_URI']))
        ]);
    }
}