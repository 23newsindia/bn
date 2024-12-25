<?php
class MACP_Redis {
    private $redis;

    public function __construct() {
        if (class_exists('Redis')) {
            try {
                $this->redis = new Redis();
                $this->redis->connect('127.0.0.1', 6379);
            } catch (Exception $e) {
                error_log('Redis connection failed: ' . $e->getMessage());
            }
        }
    }

    public function check_status() {
        if (!$this->redis) {
            return "Redis is not installed or unavailable.";
        }

        try {
            if ($this->redis->ping()) {
                return "Redis is connected and working.";
            }
        } catch (Exception $e) {
            return "Redis connection failed: " . $e->getMessage();
        }
    }

    public function batch_prefetch($keys) {
        if (empty($keys) || !$this->redis) {
            return [];
        }

        $compressed_values = $this->redis->mget($keys);
        $values = [];
        
        foreach ($compressed_values as $index => $compressed_value) {
            if ($compressed_value !== false) {
                $values[$keys[$index]] = unserialize(lzf_decompress($compressed_value));
            }
        }

        return $values;
    }

    public function prime_cache() {
        if (!$this->redis) return;

        // Cache recent posts
        $recent_posts = get_posts([
            'numberposts' => 10,
            'post_status' => 'publish',
            'fields' => ['ID', 'post_title', 'post_date']
        ]);
        $this->redis->setex('recent_posts', 3600, lzf_compress(serialize($recent_posts)));

        // Cache menu
        $menu_items = wp_get_nav_menu_items('primary');
        if ($menu_items) {
            $this->redis->setex('primary_menu', 7200, lzf_compress(serialize($menu_items)));
        }

        // Cache homepage data
        $homepage_data = [
            'title' => get_bloginfo('name'),
            'description' => get_bloginfo('description'),
            'featured_posts' => get_posts([
                'numberposts' => 5,
                'post_status' => 'publish',
                'category_name' => 'featured',
                'fields' => ['ID', 'post_title', 'post_date']
            ]),
        ];
        $this->redis->setex('homepage_data', 1800, lzf_compress(serialize($homepage_data)));
    }
}