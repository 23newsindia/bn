<?php
class MACP_Admin {
    private $redis;

    public function __construct(MACP_Redis $redis) {
        $this->redis = $redis;
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_styles']);
    }

    public function add_admin_menu() {
        add_menu_page(
            'Advanced Cache Settings',
            'Advanced Cache',
            'manage_options',
            'macp_settings',
            [$this, 'render_settings_page'],
            'dashicons-performance',
            20
        );
    }

    public function enqueue_admin_styles($hook) {
        if ($hook !== 'toplevel_page_macp_settings') return;
        wp_enqueue_style('macp-admin-styles', plugins_url('assets/css/admin.css', MACP_PLUGIN_FILE));
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) return;

        if (isset($_POST['macp_save_settings'])) {
            check_admin_referer('macp_save_settings_nonce');
            
            $options = [
                'macp_enable_redis',
                'macp_enable_html_cache',
                'macp_enable_gzip',
                'macp_minify_html',
                'macp_minify_css',
                'macp_minify_js'
            ];

            foreach ($options as $option) {
                update_option($option, isset($_POST[$option]) ? 1 : 0);
            }
        }

        $settings = [
            'redis' => get_option('macp_enable_redis', 1),
            'html_cache' => get_option('macp_enable_html_cache', 1),
            'gzip' => get_option('macp_enable_gzip', 1),
            'minify_html' => get_option('macp_minify_html', 0),
            'minify_css' => get_option('macp_minify_css', 0),
            'minify_js' => get_option('macp_minify_js', 0)
        ];

        include MACP_PLUGIN_DIR . 'templates/admin-page.php';
    }
}