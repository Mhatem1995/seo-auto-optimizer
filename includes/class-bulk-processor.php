<?php
/**
 * Compatible Enhanced Bulk Processor Class
 * Works with existing SEO_Auto_Optimizer_Core class
 */

if (!defined('ABSPATH')) {
    exit;
}

class SEO_Auto_Optimizer_Enhanced_Bulk_Processor {
    
    private $optimizer;
    private $settings;
    private $perfect_score_mode = true;
    
    public function __construct() {
        // Use the existing core class, not the enhanced one
        $this->optimizer = new SEO_Auto_Optimizer_Core();
        // Enable perfect score mode
        $this->optimizer->set_perfect_score_mode(true);
        
        $this->settings = get_option('seo_auto_optimizer_settings', array());
    }
    
    /**
     * Start enhanced bulk optimization for perfect scores
     * @param array $args Processing arguments
     * @return array
     */
    public function start_perfect_score_bulk_optimization($args = array()) {
        $defaults = array(
            'post_type' => 'post',
            'batch_size' => 10, // Smaller batches for intensive processing
            'offset' => 0,
            'start_date' => '',
            'end_date' => '',
            'post_status' => 'publish',
            'force_optimization' => false, // Override skip settings
            'target_score' => 100 // Target Rank Math score
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Get posts to process
        $posts = $this->get_posts_for_optimization($args);
        
        if (empty($posts)) {
            return array(
                'success' => false,
                'message' => __('No posts found for optimization', 'seo-auto-optimizer'),
                'total' => 0,
                'processed' => 0,
                'completed' => true
            );
        }
        
        // Process current batch with perfect score algorithm
        $batch_result = $this->process_perfect_score_batch($posts, $args);
        
        // Calculate progress
        $total_posts = $this->get_total_posts($args);
        $processed_total = $args['offset'] + $batch_result['processed'];
        $progress = min(100, round(($processed_total / $total_posts) * 100));
        
        return array(
            'success' => true,
            'total' => $total_posts,
            'processed' => $processed_total,
            'batch_processed' => $batch_result['processed'],
            'batch_optimized' => $batch_result['optimized'],
            'batch_skipped' => $batch_result['skipped'],
            'batch_errors' => $batch_result['errors'],
            'perfect_scores' => $batch_result['perfect_scores'],
            'average_score' => $batch_result['average_score'],
            'progress' => $progress,
            'completed' => $processed_total >= $total_posts,
            'message' => $batch_result['message'],
            'details' => $batch_result['details']
        );
    }
    
    /**
     * Get posts for optimization with advanced filtering
     */
    private function get_posts_for_optimization($args) {
        $query_args = array(
            'post_type' => $args['post_type'],
            'post_status' => $args['post_status'],
            'posts_per_page' => $args['batch_size'],
            'offset' => $args['offset'],
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => array()
        );
        
        // Add date filters
        if (!empty($args['start_date']) || !empty($args['end_date'])) {
            $query_args['date_query'] = array();
            
            if (!empty($args['start_date'])) {
                $query_args['date_query']['after'] = $args['start_date'];
            }
            
            if (!empty($args['end_date'])) {
                $query_args['date_query']['before'] = $args['end_date'];
            }
        }
        
        // Skip already optimized posts unless forced
        if (!$args['force_optimization']) {
            $query_args['meta_query'] = array(
                'relation' => 'OR',
                array(
                    'key' => 'rank_math_focus_keyword',
                    'compare' => 'NOT EXISTS'
                ),
                array(
                    'key' => 'rank_math_focus_keyword',
                    'value' => '',
                    'compare' => '='
                ),
                array(
                    'key' => '_seo_auto_optimizer_score',
                    'value' => $args['target_score'],
                    'compare' => '<',
                    'type' => 'NUMERIC'
                )
            );
        }
        
        return get_posts($query_args);
    }
    
    /**
     * Process batch with perfect score algorithm
     */
    private function process_perfect_score_batch($posts, $args) {
        $processed = 0;
        $optimized = 0;
        $skipped = 0;
        $errors = 0;
        $perfect_scores = 0;
        $total_score = 0;
        $results = array();
        
        foreach ($posts as $post) {
            // Pre-optimization score check
            $pre_score = $this->calculate_rank_math_score($post->ID);
            
            if ($pre_score >= $args['target_score'] && !$args['force_optimization']) {
                $skipped++;
                $results[] = array(
                    'post_id' => $post->ID,
                    'title' => $post->post_title,
                    'status' => 'skipped',
                    'pre_score' => $pre_score,
                    'post_score' => $pre_score,
                    'message' => 'Already at target score'
                );
                continue;
            }
            
            // Apply perfect score optimization using the existing core class
            $result = $this->optimizer->optimize_post($post->ID);
            $processed++;
            
            if ($result['success']) {
                // Calculate post-optimization score
                $post_score = $this->calculate_rank_math_score($post->ID);
                $total_score += $post_score;
                
                if ($post_score >= $args['target_score']) {
                    $perfect_scores++;
                }
                
                $optimized++;
                
                // Save optimization score
                update_post_meta($post->ID, '_seo_auto_optimizer_score', $post_score);
                update_post_meta($post->ID, '_seo_auto_optimizer_optimized_date', current_time('mysql'));
                
                $results[] = array(
                    'post_id' => $post->ID,
                    'title' => $post->post_title,
                    'status' => 'optimized',
                    'pre_score' => $pre_score,
                    'post_score' => $post_score,
                    'improvement' => $post_score - $pre_score,
                    'data' => isset($result['data']) ? $result['data'] : array()
                );
                
            } else {
                $errors++;
                $results[] = array(
                    'post_id' => $post->ID,
                    'title' => $post->post_title,
                    'status' => 'error',
                    'pre_score' => $pre_score,
                    'post_score' => $pre_score,
                    'message' => $result['message']
                );
            }
            
            // Memory management and prevent timeouts
            if ($processed % 3 === 0) {
                $this->cleanup_memory();
                usleep(200000); // 0.2 second pause
            }
        }
        
        $average_score = $optimized > 0 ? round($total_score / $optimized, 1) : 0;
        
        $message = sprintf(
            __('Perfect Score Mode: %d optimized, %d perfect scores (%.1f%%), average score: %.1f', 'seo-auto-optimizer'),
            $optimized,
            $perfect_scores,
            $optimized > 0 ? ($perfect_scores / $optimized) * 100 : 0,
            $average_score
        );
        
        return array(
            'processed' => $processed,
            'optimized' => $optimized,
            'skipped' => $skipped,
            'errors' => $errors,
            'perfect_scores' => $perfect_scores,
            'average_score' => $average_score,
            'message' => $message,
            'details' => $results
        );
    }
    
    /**
     * Calculate Rank Math score simulation for perfect optimization
     */
    private function calculate_rank_math_score($post_id) {
        $score = 0;
        $max_score = 100;
        
        $post = get_post($post_id);
        if (!$post) return 0;
        
        // 1. Focus Keyword Set (10 points)
        $focus_keyword = get_post_meta($post_id, 'rank_math_focus_keyword', true);
        if (!empty($focus_keyword)) {
            $score += 10;
        }
        
        // 2. SEO Title optimized (15 points)
        $seo_title = get_post_meta($post_id, 'rank_math_title', true);
        if (!empty($seo_title)) {
            $score += 8;
            if (!empty($focus_keyword) && stripos($seo_title, $focus_keyword) !== false) {
                $score += 7; // Keyword in title
            }
        }
        
        // 3. Meta Description (15 points)
        $meta_desc = get_post_meta($post_id, 'rank_math_description', true);
        if (!empty($meta_desc)) {
            $score += 8;
            if (!empty($focus_keyword) && stripos($meta_desc, $focus_keyword) !== false) {
                $score += 7; // Keyword in description
            }
        }
        
        // 4. URL Structure (10 points)
        if (!empty($focus_keyword) && stripos($post->post_name, str_replace(' ', '-', $focus_keyword)) !== false) {
            $score += 10;
        }
        
        // 5. Content Analysis (20 points)
        $content = $post->post_content;
        if (!empty($focus_keyword)) {
            // Keyword density check
            $density = $this->calculate_keyword_density($content, $focus_keyword);
            if ($density >= 0.5 && $density <= 2.5) {
                $score += 8;
            }
            
            // Keyword in first paragraph
            $first_paragraph = $this->get_first_paragraph($content);
            if (stripos($first_paragraph, $focus_keyword) !== false) {
                $score += 6;
            }
            
            // Keyword in subheadings
            if (preg_match('/<h[2-6][^>]*>.*?' . preg_quote($focus_keyword, '/') . '.*?<\/h[2-6]>/i', $content)) {
                $score += 6;
            }
        }
        
        // 6. Content Length (10 points)
        $word_count = str_word_count(wp_strip_all_tags($content));
        if ($word_count >= 600) {
            $score += 10;
        } elseif ($word_count >= 300) {
            $score += 5;
        }
        
        // 7. Images with Alt Text (10 points)
        if (preg_match('/<img[^>]*alt=["\'][^"\']*' . preg_quote($focus_keyword, '/') . '[^"\']*["\'][^>]*>/i', $content)) {
            $score += 10;
        } elseif (preg_match('/<img[^>]*alt=["\'][^"\']+["\'][^>]*>/i', $content)) {
            $score += 5;
        }
        
        // 8. Internal Links (5 points)
        if (preg_match_all('/<a[^>]*href=["\'][^"\']*' . preg_quote(home_url(), '/') . '[^"\']*["\'][^>]*>/i', $content) >= 1) {
            $score += 5;
        }
        
        // 9. External Links (5 points)
        if (preg_match('/<a[^>]*href=["\']https?:\/\/(?!' . preg_quote(parse_url(home_url(), PHP_URL_HOST), '/') . ')[^"\']*["\'][^>]*>/i', $content)) {
            $score += 5;
        }
        
        return min($score, $max_score);
    }
    
    /**
     * Get total posts count for progress calculation
     */
    private function get_total_posts($args) {
        $query_args = array(
            'post_type' => $args['post_type'],
            'post_status' => $args['post_status'],
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => array()
        );
        
        // Add date filters
        if (!empty($args['start_date']) || !empty($args['end_date'])) {
            $query_args['date_query'] = array();
            
            if (!empty($args['start_date'])) {
                $query_args['date_query']['after'] = $args['start_date'];
            }
            
            if (!empty($args['end_date'])) {
                $query_args['date_query']['before'] = $args['end_date'];
            }
        }
        
        // Skip already optimized posts unless forced
        if (!$args['force_optimization']) {
            $query_args['meta_query'] = array(
                'relation' => 'OR',
                array(
                    'key' => 'rank_math_focus_keyword',
                    'compare' => 'NOT EXISTS'
                ),
                array(
                    'key' => 'rank_math_focus_keyword',
                    'value' => '',
                    'compare' => '='
                ),
                array(
                    'key' => '_seo_auto_optimizer_score',
                    'value' => $args['target_score'],
                    'compare' => '<',
                    'type' => 'NUMERIC'
                )
            );
        }
        
        $posts = get_posts($query_args);
        return count($posts);
    }
    
    /**
     * Schedule bulk optimization in background
     */
    public function schedule_perfect_score_optimization($args = array()) {
        $defaults = array(
            'post_type' => 'post',
            'batch_size' => 5, // Smaller batches for background processing
            'schedule_interval' => 300, // 5 minutes between batches
            'max_execution_time' => 120, // 2 minutes per batch
            'priority' => 10
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Clear any existing scheduled optimization
        wp_clear_scheduled_hook('seo_auto_optimizer_perfect_score_cron');
        
        // Schedule the optimization
        wp_schedule_event(time(), 'seo_auto_optimizer_interval', 'seo_auto_optimizer_perfect_score_cron', array($args));
        
        // Update status
        update_option('seo_auto_optimizer_bulk_status', array(
            'status' => 'scheduled',
            'args' => $args,
            'scheduled_at' => current_time('mysql'),
            'total_processed' => 0,
            'perfect_scores' => 0
        ));
        
        return array(
            'success' => true,
            'message' => __('Perfect score optimization scheduled successfully', 'seo-auto-optimizer'),
            'next_run' => date('Y-m-d H:i:s', wp_next_scheduled('seo_auto_optimizer_perfect_score_cron'))
        );
    }
    
    /**
     * Process scheduled perfect score optimization
     */
    public function process_scheduled_perfect_score_optimization($args) {
        $status = get_option('seo_auto_optimizer_bulk_status', array());
        
        if (empty($status) || $status['status'] !== 'scheduled') {
            return;
        }
        
        // Update status to running
        $status['status'] = 'running';
        $status['current_batch_start'] = current_time('mysql');
        update_option('seo_auto_optimizer_bulk_status', $status);
        
        // Set execution time limit
        set_time_limit($args['max_execution_time']);
        
        // Calculate current offset
        $offset = isset($status['total_processed']) ? $status['total_processed'] : 0;
        $args['offset'] = $offset;
        
        // Process batch
        $result = $this->start_perfect_score_bulk_optimization($args);
        
        // Update status
        $status['total_processed'] += $result['batch_processed'];
        $status['perfect_scores'] = isset($status['perfect_scores']) ? $status['perfect_scores'] + $result['perfect_scores'] : $result['perfect_scores'];
        $status['last_batch_result'] = $result;
        $status['last_run'] = current_time('mysql');
        
        if ($result['completed']) {
            $status['status'] = 'completed';
            $status['completed_at'] = current_time('mysql');
            
            // Clear scheduled event
            wp_clear_scheduled_hook('seo_auto_optimizer_perfect_score_cron');
            
            // Send completion notification if enabled
            $this->send_completion_notification($status);
        }
        
        update_option('seo_auto_optimizer_bulk_status', $status);
    }
    
    /**
     * Auto-optimization on post publish/update
     */
    public function auto_optimize_on_save($post_id, $post, $update) {
        // Skip if not enabled
        if (!$this->is_auto_optimization_enabled()) {
            return;
        }
        
        // Skip for revisions, autosaves, etc.
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        
        // Skip if not published
        if ($post->post_status !== 'publish') {
            return;
        }
        
        // Skip if already optimized recently (within 1 hour)
        $last_optimized = get_post_meta($post_id, '_seo_auto_optimizer_optimized_date', true);
        if (!empty($last_optimized)) {
            $last_time = strtotime($last_optimized);
            if ((time() - $last_time) < 3600) { // 1 hour
                return;
            }
        }
        
        // Run perfect score optimization using existing core class
        $result = $this->optimizer->optimize_post($post_id);
        
        if ($result['success']) {
            // Calculate and save score
            $score = $this->calculate_rank_math_score($post_id);
            update_post_meta($post_id, '_seo_auto_optimizer_score', $score);
            update_post_meta($post_id, '_seo_auto_optimizer_auto_optimized', 1);
        }
    }
    
    /**
     * WooCommerce product optimization support
     */
    public function optimize_woocommerce_product($product_id) {
        $product = wc_get_product($product_id);
        
        if (!$product) {
            return array(
                'success' => false,
                'message' => __('Product not found', 'seo-auto-optimizer')
            );
        }
        
        // Run optimization using existing core class
        $result = $this->optimizer->optimize_post($product_id);
        
        if ($result['success']) {
            // Update product-specific fields
            $focus_keyword = get_post_meta($product_id, 'rank_math_focus_keyword', true);
            if (!empty($focus_keyword)) {
                $product->set_short_description($this->optimize_product_short_description(
                    $product->get_short_description(),
                    $focus_keyword
                ));
                
                $product->save();
            }
        }
        
        return $result;
    }
    
    /**
     * Helper Methods
     */
    
    private function calculate_keyword_density($content, $keyword) {
        $text = wp_strip_all_tags($content);
        $word_count = str_word_count($text);
        $keyword_count = substr_count(strtolower($text), strtolower($keyword));
        
        return $word_count > 0 ? ($keyword_count / $word_count) * 100 : 0;
    }
    
    private function get_first_paragraph($content) {
        // Remove HTML tags and get first paragraph
        $text = wp_strip_all_tags($content);
        $paragraphs = explode("\n\n", $text);
        return isset($paragraphs[0]) ? $paragraphs[0] : '';
    }
    
    private function cleanup_memory() {
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }
    
    private function is_auto_optimization_enabled() {
        return isset($this->settings['auto_optimize']) && $this->settings['auto_optimize'] === 'yes';
    }
    
    private function send_completion_notification($status) {
        if (!isset($this->settings['email_notifications']) || $this->settings['email_notifications'] !== 'yes') {
            return;
        }
        
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        
        $subject = sprintf(__('[%s] SEO Perfect Score Optimization Completed', 'seo-auto-optimizer'), $site_name);
        
        $message = sprintf(
            __("SEO Perfect Score optimization has been completed on %s.\n\nResults:\n- Total processed: %d\n- Perfect scores achieved: %d\n- Success rate: %.1f%%\n\nThank you!", 'seo-auto-optimizer'),
            $site_name,
            $status['total_processed'],
            $status['perfect_scores'],
            $status['total_processed'] > 0 ? ($status['perfect_scores'] / $status['total_processed']) * 100 : 0
        );
        
        wp_mail($admin_email, $subject, $message);
    }
    
    private function optimize_product_short_description($short_description, $focus_keyword) {
        if (empty($short_description)) {
            return "Discover our premium " . $focus_keyword . " with exceptional quality and value.";
        }
        
        // Add keyword if not present
        if (stripos($short_description, $focus_keyword) === false) {
            $short_description = ucfirst($focus_keyword) . " - " . $short_description;
        }
        
        return $short_description;
    }
    
    /**
     * Get optimization statistics for dashboard
     */
    public function get_perfect_score_statistics() {
        global $wpdb;
        
        // Get posts with perfect scores
        $perfect_scores = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = '_seo_auto_optimizer_score' 
            AND pm.meta_value >= 100
            AND p.post_status = 'publish'
        ");
        
        // Get total optimized posts
        $total_optimized = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = '_seo_auto_optimizer_score'
            AND p.post_status = 'publish'
        ");
        
        // Get average score
        $average_score = $wpdb->get_var("
            SELECT AVG(CAST(pm.meta_value AS DECIMAL(5,2)))
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = '_seo_auto_optimizer_score'
            AND p.post_status = 'publish'
        ");
        
        // Get recent optimizations
        $recent_optimizations = $wpdb->get_results("
            SELECT p.ID, p.post_title, pm1.meta_value as score, pm2.meta_value as optimized_date
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_seo_auto_optimizer_score'
            INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_seo_auto_optimizer_optimized_date'
            WHERE p.post_status = 'publish'
            ORDER BY pm2.meta_value DESC
            LIMIT 10
        ");
        
        return array(
            'perfect_scores' => (int) $perfect_scores,
            'total_optimized' => (int) $total_optimized,
            'average_score' => round((float) $average_score, 1),
            'success_rate' => $total_optimized > 0 ? round(($perfect_scores / $total_optimized) * 100, 1) : 0,
            'recent_optimizations' => $recent_optimizations
        );
    }
}

// Initialize hooks for auto-optimization
if (class_exists('SEO_Auto_Optimizer_Enhanced_Bulk_Processor')) {
    $bulk_processor = new SEO_Auto_Optimizer_Enhanced_Bulk_Processor();
    add_action('save_post', array($bulk_processor, 'auto_optimize_on_save'), 10, 3);
    add_action('seo_auto_optimizer_perfect_score_cron', array($bulk_processor, 'process_scheduled_perfect_score_optimization'));
}
