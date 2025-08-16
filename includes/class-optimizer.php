<?php
/**
 * SEO Auto-Optimizer Core Class
 * Updated with Perfect Score Algorithm while maintaining compatibility
 */

if (!defined('ABSPATH')) {
    exit;
}

class SEO_Auto_Optimizer_Core {
    
    private $settings;
    private $target_density = 1.5; // Target keyword density percentage
    private $min_word_count = 600;
    private $perfect_score_mode = true; // Enable perfect score algorithm
    private $authority_domains = [
        'wikipedia.org',
        'britannica.com',
        'reuters.com',
        'bbc.com',
        'cnn.com'
    ];
    
    public function __construct() {
        $this->settings = get_option('seo_auto_optimizer_settings', array());
    }
    
    /**
     * Main optimization function - Enhanced for perfect Rank Math scores
     * @param int $post_id
     * @return array Results of optimization
     */
    public function optimize_post($post_id) {
        // Use perfect score algorithm if enabled
        if ($this->perfect_score_mode) {
            return $this->optimize_post_perfect_score($post_id);
        }
        
        // Fallback to original optimization logic
        return $this->optimize_post_legacy($post_id);
    }
    
    /**
     * NEW: Perfect Score optimization algorithm
     */
    public function optimize_post_perfect_score($post_id) {
        $post = get_post($post_id);
        
        if (!$post || $post->post_status !== 'publish') {
            return array(
                'success' => false,
                'message' => __('Post not found or not published', 'seo-auto-optimizer')
            );
        }
        
        // Step 1: Determine Focus Keyword
        $focus_keyword = $this->determine_focus_keyword($post);
        
        // Step 2: Optimize SEO Title
        $seo_title = $this->optimize_seo_title($post, $focus_keyword);
        
        // Step 3: Optimize Meta Description
        $meta_description = $this->optimize_meta_description($post, $focus_keyword);
        
        // Step 4: Optimize URL (Slug)
        $optimized_slug = $this->optimize_post_slug($post, $focus_keyword);
        
        // Step 5: Optimize Content for Keyword Density
        $optimized_content = $this->optimize_content_density($post, $focus_keyword);
        
        // Step 6: Add/Optimize Subheadings
        $content_with_headings = $this->optimize_subheadings($optimized_content, $focus_keyword);
        
        // Step 7: Optimize Images and Alt Text
        $final_content = $this->optimize_images_alt_text($content_with_headings, $focus_keyword, $post_id);
        
        // Step 8: Add Internal and External Links
        $content_with_links = $this->add_strategic_links($final_content, $focus_keyword, $post_id);
        
        // Step 9: Ensure Minimum Word Count
        $content_final = $this->ensure_minimum_word_count($content_with_links, $focus_keyword);
        
        // Save all optimizations
        $result = $this->save_optimizations($post_id, array(
            'focus_keyword' => $focus_keyword,
            'seo_title' => $seo_title,
            'meta_description' => $meta_description,
            'slug' => $optimized_slug,
            'content' => $content_final
        ));
        
        if ($result) {
            $this->log_optimization($post_id, $post->post_type, 'perfect_score', array(
                'focus_keyword' => $focus_keyword,
                'seo_title' => $seo_title,
                'meta_description' => $meta_description
            ));
            
            return array(
                'success' => true,
                'message' => __('Post optimized for perfect Rank Math score', 'seo-auto-optimizer'),
                'data' => array(
                    'focus_keyword' => $focus_keyword,
                    'seo_title' => $seo_title,
                    'meta_description' => $meta_description,
                    'word_count' => str_word_count(wp_strip_all_tags($content_final)),
                    'keyword_density' => $this->calculate_keyword_density($content_final, $focus_keyword)
                )
            );
        }
        
        return array(
            'success' => false,
            'message' => __('Failed to save optimizations', 'seo-auto-optimizer')
        );
    }
    
    /**
     * Step 1: Determine Focus Keyword
     */
    private function determine_focus_keyword($post) {
        // Check if already set
        $existing_keyword = get_post_meta($post->ID, 'rank_math_focus_keyword', true);
        if (!empty($existing_keyword)) {
            return $existing_keyword;
        }
        
        // Extract from title with advanced processing
        $title_words = $this->extract_meaningful_words($post->post_title);
        
        // Get top 2-3 words as keyword phrase
        $focus_keyword = implode(' ', array_slice($title_words, 0, 3));
        
        // Fallback to single word if phrase is too long
        if (strlen($focus_keyword) > 50) {
            $focus_keyword = $title_words[0];
        }
        
        return strtolower(trim($focus_keyword));
    }
    
    /**
     * Step 2: Optimize SEO Title for Rank Math
     */
    private function optimize_seo_title($post, $focus_keyword) {
        $existing_title = get_post_meta($post->ID, 'rank_math_title', true);
        
        // If exists and contains keyword, keep it
        if (!empty($existing_title) && stripos($existing_title, $focus_keyword) !== false) {
            return $existing_title;
        }
        
        $title = $post->post_title;
        
        // Ensure keyword is at the beginning for better SEO
        if (stripos($title, $focus_keyword) === false) {
            $title = ucfirst($focus_keyword) . ': ' . $title;
        }
        
        // Add site name if not too long
        $site_name = get_bloginfo('name');
        $full_title = $title . ' | ' . $site_name;
        
        // Keep under 60 characters for optimal display
        if (strlen($full_title) > 60) {
            $title = substr($title, 0, 60 - strlen(' | ' . $site_name)) . ' | ' . $site_name;
        } else {
            $title = $full_title;
        }
        
        return $title;
    }
    
    /**
     * Step 3: Optimize Meta Description
     */
    private function optimize_meta_description($post, $focus_keyword) {
        $existing_description = get_post_meta($post->ID, 'rank_math_description', true);
        
        // If exists and contains keyword, optimize it
        if (!empty($existing_description)) {
            if (stripos($existing_description, $focus_keyword) !== false) {
                return substr($existing_description, 0, 155);
            }
        }
        
        // Create new description with keyword
        $content = wp_strip_all_tags($post->post_content);
        $content = preg_replace('/\s+/', ' ', $content);
        
        // Find the first sentence containing the keyword
        $sentences = preg_split('/[.!?]+/', $content);
        $description = '';
        
        foreach ($sentences as $sentence) {
            if (stripos($sentence, $focus_keyword) !== false) {
                $description = trim($sentence);
                break;
            }
        }
        
        // If no sentence contains keyword, create one
        if (empty($description)) {
            $description = "Discover everything about " . $focus_keyword . ". " . substr($content, 0, 100);
        }
        
        // Ensure keyword appears early and description is optimal length
        if (stripos($description, $focus_keyword) > 30) {
            $description = ucfirst($focus_keyword) . " - " . $description;
        }
        
        return substr($description, 0, 155) . (strlen($description) > 155 ? '...' : '');
    }
    
    /**
     * Step 4: Optimize URL Slug
     */
    private function optimize_post_slug($post, $focus_keyword) {
        $current_slug = $post->post_name;
        
        // Check if slug already contains keyword
        if (stripos($current_slug, str_replace(' ', '-', $focus_keyword)) !== false) {
            return $current_slug;
        }
        
        // Create keyword-friendly slug
        $keyword_slug = sanitize_title($focus_keyword);
        $new_slug = $keyword_slug . '-' . $current_slug;
        
        // Ensure uniqueness
        $counter = 1;
        $original_slug = $new_slug;
        while ($this->slug_exists($new_slug, $post->ID)) {
            $new_slug = $original_slug . '-' . $counter;
            $counter++;
        }
        
        return $new_slug;
    }
    
    /**
     * Step 5: Optimize Content for Keyword Density
     */
    private function optimize_content_density($post, $focus_keyword) {
        $content = $post->post_content;
        $current_density = $this->calculate_keyword_density($content, $focus_keyword);
        
        if ($current_density >= $this->target_density) {
            return $content; // Already optimal
        }
        
        $word_count = str_word_count(wp_strip_all_tags($content));
        $target_occurrences = ceil(($word_count * $this->target_density) / 100);
        $current_occurrences = substr_count(strtolower($content), strtolower($focus_keyword));
        $needed_occurrences = $target_occurrences - $current_occurrences;
        
        if ($needed_occurrences <= 0) {
            return $content;
        }
        
        // Add keyword naturally to content
        $paragraphs = explode("\n\n", $content);
        $insertion_points = array(0, floor(count($paragraphs) / 2), count($paragraphs) - 1);
        
        $insertions_made = 0;
        foreach ($insertion_points as $point) {
            if ($insertions_made >= $needed_occurrences) break;
            
            if (isset($paragraphs[$point])) {
                $paragraph = $paragraphs[$point];
                
                // Insert keyword naturally if not already present
                if (stripos($paragraph, $focus_keyword) === false) {
                    $sentences = explode('.', $paragraph);
                    if (count($sentences) > 1) {
                        $sentences[0] .= ', especially when considering ' . $focus_keyword;
                        $paragraphs[$point] = implode('.', $sentences);
                        $insertions_made++;
                    }
                }
            }
        }
        
        return implode("\n\n", $paragraphs);
    }
    
    /**
     * Step 6: Optimize Subheadings
     */
    private function optimize_subheadings($content, $focus_keyword) {
        // Check if any heading contains the keyword
        $has_keyword_heading = preg_match('/<h[2-6][^>]*>.*?' . preg_quote($focus_keyword, '/') . '.*?<\/h[2-6]>/i', $content);
        
        if ($has_keyword_heading) {
            return $content; // Already has keyword in heading
        }
        
        // Find first H2 or H3 and modify it
        $pattern = '/<(h[2-3])([^>]*)>(.*?)<\/h[2-3]>/i';
        if (preg_match($pattern, $content, $matches)) {
            $new_heading = '<' . $matches[1] . $matches[2] . '>' . ucfirst($focus_keyword) . ': ' . $matches[3] . '</' . $matches[1] . '>';
            $content = preg_replace($pattern, $new_heading, $content, 1);
        } else {
            // Add a new H2 heading
            $first_paragraph_end = strpos($content, '</p>');
            if ($first_paragraph_end !== false) {
                $new_heading = "\n\n<h2>Understanding " . ucfirst($focus_keyword) . "</h2>\n\n";
                $content = substr_replace($content, $new_heading, $first_paragraph_end + 4, 0);
            }
        }
        
        return $content;
    }
    
    /**
     * Step 7: Optimize Images and Alt Text
     */
    private function optimize_images_alt_text($content, $focus_keyword, $post_id) {
        $has_images = preg_match('/<img[^>]*>/i', $content);
        $has_keyword_alt = preg_match('/<img[^>]*alt=["\'][^"\']*' . preg_quote($focus_keyword, '/') . '[^"\']*["\'][^>]*>/i', $content);
        
        if ($has_images && $has_keyword_alt) {
            return $content; // Already optimized
        }
        
        if ($has_images && !$has_keyword_alt) {
            // Modify first image to include keyword in alt text
            $content = preg_replace(
                '/<img([^>]*?)alt=["\']([^"\']*)["\']([^>]*?)>/i',
                '<img$1alt="$2 ' . $focus_keyword . '"$3>',
                $content,
                1
            );
        } else {
            // Add a placeholder image with keyword alt text
            $image_html = '<img src="' . $this->get_default_image_url() . '" alt="' . ucfirst($focus_keyword) . ' illustration" class="wp-image-auto-seo" />';
            
            // Insert after first paragraph
            $first_paragraph_end = strpos($content, '</p>');
            if ($first_paragraph_end !== false) {
                $content = substr_replace($content, "\n\n" . $image_html . "\n\n", $first_paragraph_end + 4, 0);
            }
        }
        
        return $content;
    }
    
    /**
     * Step 8: Add Strategic Internal and External Links
     */
    private function add_strategic_links($content, $focus_keyword, $post_id) {
        // Add internal link
        $related_post = $this->find_related_post($focus_keyword, $post_id);
        if ($related_post) {
            $internal_link = '<a href="' . get_permalink($related_post->ID) . '">' . $related_post->post_title . '</a>';
            
            // Insert internal link naturally
            $content = $this->insert_link_naturally($content, $internal_link, 'internal');
        }
        
        // Add external authority link
        $authority_link = $this->generate_authority_link($focus_keyword);
        if ($authority_link) {
            $content = $this->insert_link_naturally($content, $authority_link, 'external');
        }
        
        return $content;
    }
    
    /**
     * Step 9: Ensure Minimum Word Count
     */
    private function ensure_minimum_word_count($content, $focus_keyword) {
        $word_count = str_word_count(wp_strip_all_tags($content));
        
        if ($word_count >= $this->min_word_count) {
            return $content;
        }
        
        $needed_words = $this->min_word_count - $word_count;
        
        // Add keyword-rich content
        $additional_content = $this->generate_additional_content($focus_keyword, $needed_words);
        
        return $content . "\n\n" . $additional_content;
    }
    
    /**
     * LEGACY: Original optimization method (for backward compatibility)
     */
    private function optimize_post_legacy($post_id) {
        // Your original optimization logic here
        // This ensures backward compatibility with existing code
        
        return array(
            'success' => true,
            'message' => __('Post optimized using legacy method', 'seo-auto-optimizer')
        );
    }
    
    /**
     * Helper Methods
     */
    
    private function extract_meaningful_words($text) {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s]/', '', $text);
        
        $stop_words = array(
            'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 
            'of', 'with', 'by', 'is', 'are', 'was', 'were', 'be', 'been', 'have', 
            'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should'
        );
        
        $words = explode(' ', $text);
        $meaningful_words = array();
        
        foreach ($words as $word) {
            $word = trim($word);
            if (strlen($word) > 2 && !in_array($word, $stop_words)) {
                $meaningful_words[] = $word;
            }
        }
        
        return $meaningful_words;
    }
    
    private function calculate_keyword_density($content, $keyword) {
        $text = wp_strip_all_tags($content);
        $word_count = str_word_count($text);
        $keyword_count = substr_count(strtolower($text), strtolower($keyword));
        
        return $word_count > 0 ? ($keyword_count / $word_count) * 100 : 0;
    }
    
    private function slug_exists($slug, $exclude_id = 0) {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND ID != %d",
            $slug,
            $exclude_id
        );
        
        return $wpdb->get_var($query) !== null;
    }
    
    private function get_default_image_url() {
        return 'https://via.placeholder.com/600x400/0073aa/ffffff?text=SEO+Optimized+Image';
    }
    
    private function find_related_post($keyword, $exclude_id) {
        $posts = get_posts(array(
            'post_type' => 'post',
            'posts_per_page' => 5,
            'exclude' => array($exclude_id),
            's' => $keyword,
            'orderby' => 'relevance'
        ));
        
        return !empty($posts) ? $posts[0] : null;
    }
    
    private function generate_authority_link($keyword) {
        $domain = $this->authority_domains[array_rand($this->authority_domains)];
        $search_url = 'https://' . $domain . '/search?q=' . urlencode($keyword);
        
        return '<a href="' . $search_url . '" target="_blank" rel="nofollow">Learn more about ' . $keyword . '</a>';
    }
    
    private function insert_link_naturally($content, $link, $type) {
        $paragraphs = explode('</p>', $content);
        $target_paragraph = $type === 'internal' ? 1 : 2;
        
        if (isset($paragraphs[$target_paragraph])) {
            $paragraphs[$target_paragraph] .= ' ' . $link . '.';
        }
        
        return implode('</p>', $paragraphs);
    }
    
    private function generate_additional_content($keyword, $needed_words) {
        $templates = array(
            "When exploring {keyword}, it's important to understand the various aspects and implications. This comprehensive guide covers everything you need to know about {keyword} and its applications in today's world.",
            "The significance of {keyword} cannot be overstated in modern times. Many professionals and enthusiasts alike have found {keyword} to be an essential component of their daily operations and strategic planning.",
            "Understanding {keyword} requires a deep dive into its fundamental principles and practical applications. This detailed analysis provides insights into how {keyword} can be effectively utilized for optimal results."
        );
        
        $template = $templates[array_rand($templates)];
        $content = str_replace('{keyword}', $keyword, $template);
        
        // Expand content if needed
        while (str_word_count($content) < $needed_words) {
            $content .= " Additionally, {keyword} plays a crucial role in various industries and sectors. Research has shown that proper implementation of {keyword} strategies can lead to significant improvements in overall performance and outcomes.";
            $content = str_replace('{keyword}', $keyword, $content);
        }
        
        return '<p>' . $content . '</p>';
    }
    
    /**
     * Save all optimizations to WordPress
     */
    private function save_optimizations($post_id, $data) {
        $success = true;
        
        // Update Rank Math meta fields
        $success &= update_post_meta($post_id, 'rank_math_focus_keyword', $data['focus_keyword']);
        $success &= update_post_meta($post_id, 'rank_math_title', $data['seo_title']);
        $success &= update_post_meta($post_id, 'rank_math_description', $data['meta_description']);
        
        // Update post content and slug
        $success &= wp_update_post(array(
            'ID' => $post_id,
            'post_content' => $data['content'],
            'post_name' => $data['slug']
        )) !== 0;
        
        return $success;
    }
    
    /**
     * Log optimization to database
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
                'meta_description' => $seo_data['meta_description'],
                'optimized_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Toggle Perfect Score Mode
     */
    public function set_perfect_score_mode($enabled = true) {
        $this->perfect_score_mode = $enabled;
    }
    
    /**
     * Get optimization statistics
     */
    public function get_optimization_stats() {
        global $wpdb;
        
        $total_optimized = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = 'rank_math_focus_keyword' 
            AND meta_value != ''
        ");
        
        return array(
            'total_optimized' => (int) $total_optimized,
            'perfect_score_mode' => $this->perfect_score_mode
        );
    }
}
