<?php
class MACP_Admin_Bar {
    private $html_cache;

    public function __construct(MACP_HTML_Cache $html_cache) {
        $this->html_cache = $html_cache;
        add_action('admin_bar_menu', [$this, 'add_cache_controls'], 100);
        add_action('wp_ajax_macp_clear_page_cache', [$this, 'clear_page_cache']);
    }

    public function add_cache_controls($wp_admin_bar) {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Add main menu item
        $wp_admin_bar->add_node([
            'id'    => 'macp-cache',
            'title' => 'Cache',
            'href'  => admin_url('admin.php?page=macp_settings')
        ]);

        // Add clear current page cache option
        if (!is_admin()) {
            $wp_admin_bar->add_node([
                'id'     => 'macp-clear-page-cache',
                'parent' => 'macp-cache',
                'title'  => 'Clear This Page Cache',
                'href'   => '#',
                'meta'   => [
                    'onclick' => 'macpClearPageCache(event)',
                ]
            ]);
        }

        // Add clear all cache option
        $wp_admin_bar->add_node([
            'id'     => 'macp-clear-all-cache',
            'parent' => 'macp-cache',
            'title'  => 'Clear All Cache',
            'href'   => wp_nonce_url(admin_url('admin-post.php?action=macp_clear_all_cache'), 'macp_clear_all_cache')
        ]);
    }

    public function clear_page_cache() {
        check_ajax_referer('macp_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';
        if (empty($url)) {
            wp_send_json_error('Invalid URL');
        }

        $this->html_cache->clear_page_cache($url);
        wp_send_json_success('Cache cleared for this page');
    }
}