<?php
/**
 * Helper Functions
 * Utility functions used throughout the plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get plugin settings with defaults
 * @return array
 */
function seo_auto_optimizer_get_settings() {
    $defaults = array(
        'auto_optimize_on_save' => true,
        'overwrite_existing_empty' => true,
        'skip_already_optimized' => false,
        'focus_keyword_strategy' => 'from_title',
        'seo_title_strategy' => 'post_title_site_name',
        'meta_description_strategy' => 'first_160_chars',
        'batch_size' => 20,
        'version' => SEO_AUTO_OPTIMIZER_VERSION
    );
    
    $settings = get_option('seo_auto_optimizer_settings', array());
    return wp_parse_args($settings, $defaults);
}

/**
 * Check if Rank Math is active and functional
 * @return bool
 */
function seo_auto_optimizer_is_rank_math_active() {
    return class_exists('RankMath') || function_exists('rank_math');
}

/**
 * Get supported post types for optimization
 * @return array
 */
function seo_auto_optimizer_get_supported_post_types() {
    $post_types = get_post_types(array(
        'public' => true,
        'show_ui' => true
    ), 'objects');
    
    // Remove unsupported types
    $excluded = array('attachment');
    
    foreach ($excluded as $exclude) {
        unset($post_types[$exclude]);
    }
    
    return apply_filters('seo_auto_optimizer_supported_post_types', $post_types);
}

/**
 * Clean and prepare text for SEO fields
 * @param string $text
 * @param int $max_length
 * @return string
 */
function seo_auto_optimizer_clean_text($text, $max_length = 0) {
    // Remove HTML tags and shortcodes
    $text = wp_strip_all_tags($text);
    $text = strip_shortcodes($text);
    
    // Clean up whitespace
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);
    
    // Remove special characters but keep basic punctuation
    $text = preg_replace('/[^\w\s\-\.\,\!\?]/', '', $text);
    
    // Truncate if needed
    if ($max_length > 0 && strlen($text) > $max_length) {
        $text = substr($text, 0, $max_length);
        // Find last complete word
        $last_space = strrpos($text, ' ');
        if ($last_space !== false && $last_space > ($max_length * 0.8)) {
            $text = substr($text, 0, $last_space);
        }
        $text = rtrim($text, '.,!?') . '...';
    }
    
    return $text;
}

/**
 * Extract meaningful keywords from text
 * @param string $text
 * @param int $max_keywords
 * @return array
 */
function seo_auto_optimizer_extract_keywords($text, $max_keywords = 5) {
    $text = strtolower($text);
    $text = seo_auto_optimizer_clean_text($text);
    
    // Common stop words to remove
    $stop_words = array(
        'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 
        'of', 'with', 'by', 'is', 'are', 'was', 'were', 'be', 'been', 'have', 
        'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should',
        'may', 'might', 'must', 'can', 'this', 'that', 'these', 'those',
        'i', 'you', 'he', 'she', 'it', 'we', 'they', 'me', 'him', 'her', 'us', 'them',
        'my', 'your', 'his', 'its', 'our', 'their', 'myself', 'yourself', 'himself',
        'herself', 'itself', 'ourselves', 'yourselves', 'themselves', 'what', 'which',
        'who', 'whom', 'whose', 'where', 'when', 'why', 'how', 'all', 'any', 'both',
        'each', 'few', 'more', 'most', 'other', 'some', 'such', 'no', 'nor', 'not',
        'only', 'own', 'same', 'so', 'than', 'too', 'very'
    );
    
    // Split into words
    $words = str_word_count($text, 1);
    $keywords = array();
    
    foreach ($words as $word) {
        $word = trim($word);
        // Skip if too short or is a stop word
        if (strlen($word) > 2 && !in_array($word, $stop_words)) {
            $keywords[] = $word;
        }
    }
    
    // Count word frequency
    $word_counts = array_count_values($keywords);
    
    // Sort by frequency
    arsort($word_counts);
    
    // Return top keywords
    return array_slice(array_keys($word_counts), 0, $max_keywords);
}

/**
 * Generate focus keyword variations
 * @param string $base_keyword
 * @return array
 */
function seo_auto_optimizer_generate_keyword_variations($base_keyword) {
    $variations = array($base_keyword);
    
    // Add plural form
    if (!empty($base_keyword) && !preg_match('/s$/', $base_keyword)) {
        $variations[] = $base_keyword . 's';
    }
    
    // Add question formats
    $variations[] = 'what is ' . $base_keyword;
    $variations[] = 'how to ' . $base_keyword;
    $variations[] = 'best ' . $base_keyword;
    
    return apply_filters('seo_auto_optimizer_keyword_variations', $variations, $base_keyword);
}

/**
 * Validate SEO title length
 * @param string $title
 * @return array
 */
function seo_auto_optimizer_validate_title($title) {
    $length = strlen($title);
    $ideal_min = 30;
    $ideal_max = 60;
    $absolute_max = 70;
    
    $status = 'good';
    $message = '';
    
    if ($length < $ideal_min) {
        $status = 'warning';
        $message = sprintf(__('Title is too short. Consider adding %d more characters.', 'seo-auto-optimizer'), $ideal_min - $length);
    } elseif ($length > $absolute_max) {
        $status = 'error';
        $message = sprintf(__('Title is too long. Consider removing %d characters.', 'seo-auto-optimizer'), $length - $absolute_max);
    } elseif ($length > $ideal_max) {
        $status = 'warning';
        $message = __('Title is slightly long but acceptable.', 'seo-auto-optimizer');
    } else {
        $message = __('Title length is optimal.', 'seo-auto-optimizer');
    }
    
    return array(
        'length' => $length,
        'status' => $status,
        'message' => $message
    );
}

/**
 * Validate meta description length
 * @param string $description
 * @return array
 */
function seo_auto_optimizer_validate_description($description) {
    $length = strlen($description);
    $ideal_min = 120;
    $ideal_max = 160;
    $absolute_max = 170;
    
    $status = 'good';
    $message = '';
    
    if ($length < $ideal_min) {
        $status = 'warning';
        $message = sprintf(__('Description is too short. Consider adding %d more characters.', 'seo-auto-optimizer'), $ideal_min - $length);
    } elseif ($length > $absolute_max) {
        $status = 'error';
        $message = sprintf(__('Description is too long. Consider removing %d characters.', 'seo-auto-optimizer'), $length - $absolute_max);
    } elseif ($length > $ideal_max) {
        $status = 'warning';
        $message = __('Description is slightly long but acceptable.', 'seo-auto-optimizer');
    } else {
        $message = __('Description length is optimal.', 'seo-auto-optimizer');
    }
    
    return array(
        'length' => $length,
        'status' => $status,
        'message' => $message
    );
}

/**
 * Get post content without shortcodes and HTML
 * @param int|WP_Post $post
 * @return string
 */
function seo_auto_optimizer_get_clean_content($post) {
    if (is_numeric($post)) {
        $post = get_post($post);
    }
    
    if (!$post) {
        return '';
    }
    
    $content = $post->post_content;
    
    // Apply WordPress content filters but strip HTML
    $content = apply_filters('the_content', $content);
    $content = wp_strip_all_tags($content);
    $content = strip_shortcodes($content);
    
    return $content;
}

/**
 * Check if post should be excluded from optimization
 * @param int|WP_Post $post
 * @return bool
 */
function seo_auto_optimizer_should_exclude_post($post) {
    if (is_numeric($post)) {
        $post = get_post($post);
    }
    
    if (!$post) {
        return true;
    }
    
    // Exclude certain post types
    $excluded_types = apply_filters('seo_auto_optimizer_excluded_post_types', array(
        'attachment',
        'revision',
        'nav_menu_item',
        'custom_css',
        'customize_changeset',
        'oembed_cache',
        'user_request',
        'wp_block'
    ));
    
    if (in_array($post->post_type, $excluded_types)) {
        return true;
    }
    
    // Exclude certain post statuses
    $excluded_statuses = apply_filters('seo_auto_optimizer_excluded_post_statuses', array(
        'auto-draft',
        'trash',
        'inherit'
    ));
    
    if (in_array($post->post_status, $excluded_statuses)) {
        return true;
    }
    
    // Check if post has no-index meta
    $noindex = get_post_meta($post->ID, 'rank_math_robots', true);
    if (is_array($noindex) && in_array('noindex', $noindex)) {
        return true;
    }
    
    return false;
}

/**
 * Log optimization activity
 * @param string $message
 * @param array $context
 */
function seo_auto_optimizer_log($message, $context = array()) {
    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        $log_message = 'SEO Auto-Optimizer: ' . $message;
        
        if (!empty($context)) {
            $log_message .= ' | Context: ' . wp_json_encode($context);
        }
        
        error_log($log_message);
    }
}

/**
 * Get plugin version
 * @return string
 */
function seo_auto_optimizer_get_version() {
    return SEO_AUTO_OPTIMIZER_VERSION;
}

/**
 * Check if plugin needs update
 * @return bool
 */
function seo_auto_optimizer_needs_update() {
    $settings = get_option('seo_auto_optimizer_settings', array());
    $current_version = isset($settings['version']) ? $settings['version'] : '0.0.0';
    
    return version_compare($current_version, SEO_AUTO_OPTIMIZER_VERSION, '<');
}

/**
 * Format processing time for display
 * @param int $seconds
 * @return string
 */
function seo_auto_optimizer_format_time($seconds) {
    if ($seconds < 60) {
        return sprintf(__('%d seconds', 'seo-auto-optimizer'), $seconds);
    }
    
    $minutes = floor($seconds / 60);
    $remaining_seconds = $seconds % 60;
    
    if ($minutes < 60) {
        if ($remaining_seconds > 0) {
            return sprintf(__('%d minutes %d seconds', 'seo-auto-optimizer'), $minutes, $remaining_seconds);
        } else {
            return sprintf(__('%d minutes', 'seo-auto-optimizer'), $minutes);
        }
    }
    
    $hours = floor($minutes / 60);
    $remaining_minutes = $minutes % 60;
    
    return sprintf(__('%d hours %d minutes', 'seo-auto-optimizer'), $hours, $remaining_minutes);
}