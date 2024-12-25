<?php
class MACP_Minify_CSS {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function minify($css) {
        if (empty($css)) return $css;

        // Remove comments
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);

        // Remove whitespace
        $css = preg_replace('/\s+/', ' ', $css);
        
        // Remove spaces before and after operators
        $css = preg_replace('/\s*([\{\};:,])\s*/', '$1', $css);
        
        // Remove unnecessary semicolons
        $css = preg_replace('/;}/', '}', $css);
        
        // Remove leading zeros
        $css = preg_replace('/(?<![\d.])\b0+(\.\d+)/', '$1', $css);
        
        // Convert RGB colors to hex
        $css = preg_replace_callback('/rgb\s*\(\s*([0-9,\s]+)\s*\)/', function($matches) {
            $rgb = explode(',', $matches[1]);
            return sprintf('#%02x%02x%02x', 
                trim($rgb[0]), 
                trim($rgb[1]), 
                trim($rgb[2])
            );
        }, $css);

        // Shorten hex colors
        $css = preg_replace('/\#([a-f0-9])\1([a-f0-9])\2([a-f0-9])\3/i', '#$1$2$3', $css);

        return trim($css);
    }
}