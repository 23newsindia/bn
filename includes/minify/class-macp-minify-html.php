<?php
class MACP_Minify_HTML {
    private static $instance = null;
    private $options = [];

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $this->options = [
            'remove_comments' => true,
            'remove_whitespace' => true,
            'remove_linebreaks' => true,
            'preserve_conditional_comments' => true,
            'preserve_server_script' => true,
        ];
    }

    public function minify($html) {
        if (empty($html)) return $html;

        // Preserve conditional comments and server-side script
        if ($this->options['preserve_conditional_comments']) {
            $html = preg_replace_callback(
                '/<!--[^]><!\[endif\]-->/s',
                function($matches) { return '___CONDITIONAL_COMMENT___' . base64_encode($matches[0]) . '___CONDITIONAL_COMMENT___'; },
                $html
            );
        }

        if ($this->options['preserve_server_script']) {
            $html = preg_replace_callback(
                '/<\?php.*?\?>/s',
                function($matches) { return '___PHP___' . base64_encode($matches[0]) . '___PHP___'; },
                $html
            );
        }

        // Remove comments (not containing IE conditional)
        if ($this->options['remove_comments']) {
            $html = preg_replace('/<!--(?!\s*(?:\[if [^\]]+]|<!|>))(?:(?!-->).)*-->/s', '', $html);
        }

        // Remove whitespace
        if ($this->options['remove_whitespace']) {
            // Remove whitespace between HTML tags
            $html = preg_replace('/>\s+</', '><', $html);
            // Remove whitespace at the start of files
            $html = preg_replace('/^\s+/', '', $html);
            // Compress multiple spaces
            $html = preg_replace('/\s{2,}/', ' ', $html);
        }

        // Remove line breaks
        if ($this->options['remove_linebreaks']) {
            $html = str_replace(["\n", "\r", "\t"], '', $html);
        }

        // Restore preserved content
        if ($this->options['preserve_conditional_comments']) {
            $html = preg_replace_callback(
                '/___CONDITIONAL_COMMENT___(.+?)___CONDITIONAL_COMMENT___/s',
                function($matches) { return base64_decode($matches[1]); },
                $html
            );
        }

        if ($this->options['preserve_server_script']) {
            $html = preg_replace_callback(
                '/___PHP___(.+?)___PHP___/s',
                function($matches) { return base64_decode($matches[1]); },
                $html
            );
        }

        return $html;
    }
}