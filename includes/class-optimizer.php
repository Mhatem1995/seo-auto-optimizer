<?php
/**
 * SEO Optimizer Core Class
 * Handles the actual SEO field generation and optimization logic
 */

if (!defined('ABSPATH')) {
    exit;
}

class SEO_Auto_Optimizer_Core {
    
    private $settings;
    
    public function __construct() {
        $this->settings = get_option('seo_auto_optimizer_settings', array());
    }
    
    /**
     * Optimize a single post
     * @param int $post_id
     * @return array Results of optimization
     */
    public function optimize_post($post_id) {
        $post = get_post($post_id);
        
        if (!$post || $post->post_status !== 'publish') {
            return array(
                'success' => false,
                'message' => __('Post not found or not published', 'seo-auto-optimizer')
            );
        }
        
        // Check if we should skip already optimized posts
        if ($this->should_skip_post($post_id)) {
            return array(
                'success' => false,
                'message' => __('Post already optimized, skipping', 'seo-auto-optimizer'),
                'skipped' => true
            );
        }
        
        // Generate SEO data
        $seo_data = $this->generate_seo_data($post);
        
        // Save to Rank Math
        $result = $this->save_rank_math_data($post_id, $seo_data);
        
        if ($result) {
            // Log the optimization
            $this->log_optimization($post_id, $post->post_type, 'single', $seo_data);
            
            return array(
                'success' => true,
                'message' => __('Post optimized successfully', 'seo-auto-optimizer'),
                'data' => $seo_data
            );
        }
        
        return array(
            'success' => false,
            'message' => __('Failed to save SEO data', 'seo-auto-optimizer')
        );
    }
    
    /**
     * Check if post should be skipped
     * @param int $post_id
     * @return bool
     */
    private function should_skip_post($post_id) {
        if (empty($this->settings['skip_already_optimized'])) {
            return false;
        }
        
        // Check if post already has Rank Math data
        $existing_focus_keyword = get_post_meta($post_id, 'rank_math_focus_keyword', true);
        $existing_title = get_post_meta($post_id, 'rank_math_title', true);
        $existing_description = get_post_meta($post_id, 'rank_math_description', true);
        
        return !empty($existing_focus_keyword) || !empty($existing_title) || !empty($existing_description);
    }
    
    /**
     * Generate SEO data for a post
     * @param WP_Post $post
     * @return array
     */
    private function generate_seo_data($post) {
        $focus_keyword = $this->generate_focus_keyword($post);
        $seo_title = $this->generate_seo_title($post, $focus_keyword);
        $meta_description = $this->generate_meta_description($post);
        
        return array(
            'focus_keyword' => $focus_keyword,
            'seo_title' => $seo_title,
            'meta_description' => $meta_description
        );
    }
    
    /**
     * Generate focus keyword from post
     * @param WP_Post $post
     * @return string
     */
    private function generate_focus_keyword($post) {
        $strategy = isset($this->settings['focus_keyword_strategy']) ? $this->settings['focus_keyword_strategy'] : 'from_title';
        
        switch ($strategy) {
            case 'from_title':
                return $this->extract_keyword_from_title($post->post_title);
                
            case 'from_content':
                return $this->extract_keyword_from_content($post->post_content);
                
            case 'from_tags':
                return $this->extract_keyword_from_tags($post->ID);
                
            default:
                return $this->extract_keyword_from_title($post->post_title);
        }
    }
    
    /**
     * Extract keyword from post title
     * @param string $title
     * @return string
     */
    private function extract_keyword_from_title($title) {
        // Remove common words and get main keywords
        $title = strtolower($title);
        $title = preg_replace('/[^a-z0-9\s]/', '', $title);
        
        // Common stop words to remove
        $stop_words = array(
            'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 
            'of', 'with', 'by', 'is', 'are', 'was', 'were', 'be', 'been', 'have', 
            'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should',
            'may', 'might', 'must', 'can', 'this', 'that', 'these', 'those'
        );
        
        $words = explode(' ', $title);
        $keywords = array();
        
        foreach ($words as $word) {
            $word = trim($word);
            if (strlen($word) > 2 && !in_array($word, $stop_words)) {
                $keywords[] = $word;
            }
        }
        
        // Return first 2-3 meaningful words as focus keyword
        return implode(' ', array_slice($keywords, 0, 3));
    }
    
    /**
     * Extract keyword from content
     * @param string $content
     * @return string
     */
    private function extract_keyword_from_content($content) {
        // Strip HTML and get plain text
        $content = wp_strip_all_tags($content);
        $content = strtolower($content);
        
        // Simple word frequency analysis
        $words = str_word_count($content, 1);
        $word_count = array_count_values($words);
        
        // Remove common stop words
        $stop_words = array(
            'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 
            'of', 'with', 'by', 'is', 'are', 'was', 'were', 'be', 'been', 'have', 
            'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should'
        );
        
        foreach ($stop_words as $stop_word) {
            unset($word_count[$stop_word]);
        }
        
        // Filter words that are too short
        $word_count = array_filter($word_count, function($word) {
            return strlen($word) > 3;
        }, ARRAY_FILTER_USE_KEY);
        
        // Sort by frequency
        arsort($word_count);
        
        // Return top keyword
        $top_keywords = array_keys(array_slice($word_count, 0, 2));
        return implode(' ', $top_keywords);
    }
    
    /**
     * Extract keyword from post tags
     * @param int $post_id
     * @return string
     */
    private function extract_keyword_from_tags($post_id) {
        $tags = wp_get_post_tags($post_id);
        
        if (empty($tags)) {
            // Fallback to title if no tags
            $post = get_post($post_id);
            return $this->extract_keyword_from_title($post->post_title);
        }
        
        // Get first tag as focus keyword
        return $tags[0]->name;
    }
    
    /**
     * Generate SEO title
     * @param WP_Post $post
     * @param string $focus_keyword
     * @return string
     */
    private function generate_seo_title($post, $focus_keyword) {
        $strategy = isset($this->settings['seo_title_strategy']) ? $this->settings['seo_title_strategy'] : 'post_title_site_name';
        $site_name = get_bloginfo('name');
        
        switch ($strategy) {
            case 'post_title_site_name':
                return $post->post_title . ' | ' . $site_name;
                
            case 'keyword_post_title':
                return ucfirst($focus_keyword) . ' - ' . $post->post_title;
                
            case 'post_title_only':
                return $post->post_title;
                
            case 'custom_format':
                // Allow custom format like "{keyword} | {title} | {site}"
                $format = isset($this->settings['custom_title_format']) ? $this->settings['custom_title_format'] : '{title} | {site}';
                $title = str_replace(
                    array('{title}', '{site}', '{keyword}'),
                    array($post->post_title, $site_name, ucfirst($focus_keyword)),
                    $format
                );
                return $title;
                
            default:
                return $post->post_title . ' | ' . $site_name;
        }
    }
    
    /**
     * Generate meta description
     * @param WP_Post $post
     * @return string
     */
    private function generate_meta_description($post) {
        $strategy = isset($this->settings['meta_description_strategy']) ? $this->settings['meta_description_strategy'] : 'first_160_chars';
        
        switch ($strategy) {
            case 'first_160_chars':
                return $this->get_content_excerpt($post->post_content, 160);
                
            case 'post_excerpt':
                if (!empty($post->post_excerpt)) {
                    return substr($post->post_excerpt, 0, 160);
                }
                // Fallback to content excerpt
                return $this->get_content_excerpt($post->post_content, 160);
                
            case 'custom_format':
                // Custom format could include keyword, title, etc.
                return $this->get_content_excerpt($post->post_content, 160);
                
            default:
                return $this->get_content_excerpt($post->post_content, 160);
        }
    }
    
    /**
     * Get clean content excerpt
     * @param string $content
     * @param int $length
     * @return string
     */
    private function get_content_excerpt($content, $length = 160) {
        // Strip HTML tags and shortcodes
        $content = wp_strip_all_tags($content);
        $content = strip_shortcodes($content);
        
        // Clean up whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);
        
        // Truncate to specified length
        if (strlen($content) > $length) {
            $content = substr($content, 0, $length);
            // Find last complete word
            $last_space = strrpos($content, ' ');
            if ($last_space !== false) {
                $content = substr($content, 0, $last_space);
            }
            $content .= '...';
        }
        
        return $content;
    }
    
    /**
     * Save data to Rank Math meta fields
     * @param int $post_id
     * @param array $seo_data
     * @return bool
     */
    private function save_rank_math_data($post_id, $seo_data) {
        $success = true;
        
        // Check if we should overwrite existing data
        $overwrite_empty = isset($this->settings['overwrite_existing_empty']) ? $this->settings['overwrite_existing_empty'] : true;
        
        // Focus Keyword
        $existing_keyword = get_post_meta($post_id, 'rank_math_focus_keyword', true);
        if (empty($existing_keyword) || !$overwrite_empty) {
            $success &= update_post_meta($post_id, 'rank_math_focus_keyword', $seo_data['focus_keyword']);
        }
        
        // SEO Title
        $existing_title = get_post_meta($post_id, 'rank_math_title', true);
        if (empty($existing_title) || !$overwrite_empty) {
            $success &= update_post_meta($post_id, 'rank_math_title', $seo_data['seo_title']);
        }
        
        // Meta Description
        $existing_description = get_post_meta($post_id, 'rank_math_description', true);
        if (empty($existing_description) || !$overwrite_empty) {
            $success &= update_post_meta($post_id, 'rank_math_description', $seo_data['meta_description']);
        }
        
        return $success;
    }
    
    /**
     * Log optimization to database
     * @param int $post_id
     * @param string $post_type
     * @param string $optimization_type
     * @param array $seo_data
     */
    private function log_optimization($post_id, $post_type, $optimization_type, $seo_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'seo_auto_optimizer_logs';
        
        $wpdb->insert(
            $table_name,
            array(
                'post_id' => $post_id,
                'post_type' => $post_type,
                'optimization_type' => $optimization_type,
                'focus_keyword' => $seo_data['focus_keyword'],
                'seo_title' => $seo_data['seo_title'],
                'meta_description' => $seo_data['meta_description']
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s')
        );
    }
}