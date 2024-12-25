<?php
require_once MACP_PLUGIN_DIR . 'includes/minify/class-macp-minify-html.php';
require_once MACP_PLUGIN_DIR . 'includes/minify/class-macp-minify-css.php';
require_once MACP_PLUGIN_DIR . 'includes/minify/class-macp-minify-js.php';

class MACP_Minification {
    private $html_minifier;
    private $css_minifier;
    private $js_minifier;

    public function __construct() {
        $this->html_minifier = MACP_Minify_HTML::get_instance();
        $this->css_minifier = MACP_Minify_CSS::get_instance();
        $this->js_minifier = MACP_Minify_JS::get_instance();
    }

    public function process_output($buffer) {
        if (get_option('macp_minify_html', 0)) {
            $buffer = $this->html_minifier->minify($buffer);
        }

        if (get_option('macp_minify_css', 0)) {
            // Extract and minify inline CSS
            $buffer = preg_replace_callback('/<style[^>]*>(.*?)<\/style>/is', function($matches) {
                return '<style>' . $this->css_minifier->minify($matches[1]) . '</style>';
            }, $buffer);
        }

        if (get_option('macp_minify_js', 0)) {
            // Extract and minify inline JavaScript
            $buffer = preg_replace_callback('/<script[^>]*>(.*?)<\/script>/is', function($matches) {
                // Skip if it's an external script
                if (strpos($matches[0], 'src=') !== false) {
                    return $matches[0];
                }
                return '<script>' . $this->js_minifier->minify($matches[1]) . '</script>';
            }, $buffer);
        }

        return $buffer;
    }
}