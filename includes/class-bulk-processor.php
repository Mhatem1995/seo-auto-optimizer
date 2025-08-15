<?php
/**
 * Bulk Processor Class
 * Handles bulk optimization of posts in batches
 */

if (!defined('ABSPATH')) {
    exit;
}

class SEO_Auto_Optimizer_Bulk_Processor {
    
    private $optimizer;
    private $settings;
    
    public function __construct() {
        $this->optimizer = new SEO_Auto_Optimizer_Core();
        $this->settings = get_option('seo_auto_optimizer_settings', array());
    }
    
    /**
     * Start bulk optimization process
     * @param array $args Processing arguments
     * @return array
     */
    public function start_bulk_optimization($args = array()) {
        $defaults = array(
            'post_type' => 'post',
            'batch_size' => 20,
            'offset' => 0,
            'start_date' => '',
            'end_date' => '',
            'post_status' => 'publish'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Get total count first
        $total_posts = $this->get_total_posts($args);
        
        if ($total_posts === 0) {
            return array(
                'success' => false,
                'message' => __('No posts found to optimize', 'seo-auto-optimizer'),
                'total' => 0,
                'processed' => 0
            );
        }
        
        // Process current batch
        $batch_result = $this->process_batch($args);
        
        return array(
            'success' => true,
            'total' => $total_posts,
            'processed' => $args['offset'] + $batch_result['processed'],
            'batch_processed' => $batch_result['processed'],
            'batch_skipped' => $batch_result['skipped'],
            'batch_errors' => $batch_result['errors'],
            'progress' => min(100, round((($args['offset'] + $batch_result['processed']) / $total_posts) * 100)),
            'completed' => ($args['offset'] + $batch_result['processed']) >= $total_posts,
            'message' => $batch_result['message']
        );
    }
    
    /**
     * Get total number of posts to process
     * @param array $args
     * @return int
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
        if (!empty($args['start_date'])) {
            $query_args['date_query']['after'] = $args['start_date'];
        }
        
        if (!empty($args['end_date'])) {
            $query_args['date_query']['before'] = $args['end_date'];
        }
        
        $query = new WP_Query($query_args);
        return $query->found_posts;
    }
    
    /**
     * Process a batch of posts
     * @param array $args
     * @return array
     */
    private function process_batch($args) {
        $query_args = array(
            'post_type' => $args['post_type'],
            'post_status' => $args['post_status'],
            'posts_per_page' => $args['batch_size'],
            'offset' => $args['offset'],
            'orderby' => 'ID',
            'order' => 'ASC'
        );
        
        // Add date filters
        if (!empty($args['start_date'])) {
            $query_args['date_query']['after'] = $args['start_date'];
        }
        
        if (!empty($args['end_date'])) {
            $query_args['date_query']['before'] = $args['end_date'];
        }
        
        $posts = get_posts($query_args);
        
        $processed = 0;
        $skipped = 0;
        $errors = 0;
        $results = array();
        
        foreach ($posts as $post) {
            $result = $this->optimizer->optimize_post($post->ID);
            
            if ($result['success']) {
                $processed++;
                $results[] = array(
                    'post_id' => $post->ID,
                    'title' => $post->post_title,
                    'status' => 'success'
                );
            } elseif (isset($result['skipped']) && $result['skipped']) {
                $skipped++;
                $results[] = array(
                    'post_id' => $post->ID,
                    'title' => $post->post_title,
                    'status' => 'skipped'
                );
            } else {
                $errors++;
                $results[] = array(
                    'post_id' => $post->ID,
                    'title' => $post->post_title,
                    'status' => 'error',
                    'message' => $result['message']
                );
            }
            
            // Prevent timeout on large batches
            if ($processed % 5 === 0) {
                // Brief pause every 5 posts
                usleep(100000); // 0.1 second
            }
        }
        
        $message = sprintf(
            __('Processed: %d, Skipped: %d, Errors: %d', 'seo-auto-optimizer'),
            $processed,
            $skipped,
            $errors
        );
        
        return array(
            'processed' => $processed,
            'skipped' => $skipped,
            'errors' => $errors,
            'results' => $results,
            'message' => $message
        );
    }
    
    /**
     * Get optimization statistics
     * @return array
     */
    public function get_optimization_stats() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'seo_auto_optimizer_logs';
        
        // Total optimizations
        $total = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        // Optimizations by type
        $by_type = $wpdb->get_results(
            "SELECT optimization_type, COUNT(*) as count 
             FROM $table_name 
             GROUP BY optimization_type"
        );
        
        // Recent optimizations (last 30 days)
        $recent = $wpdb->get_var(
            "SELECT COUNT(*) 
             FROM $table_name 
             WHERE optimized_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        
        // Optimizations by post type
        $by_post_type = $wpdb->get_results(
            "SELECT post_type, COUNT(*) as count 
             FROM $table_name 
             GROUP BY post_type"
        );
        
        return array(
            'total' => (int) $total,
            'recent_30_days' => (int) $recent,
            'by_type' => $by_type,
            'by_post_type' => $by_post_type
        );
    }
    
    /**
     * Get recent optimization logs
     * @param int $limit
     * @return array
     */
    public function get_recent_logs($limit = 50) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'seo_auto_optimizer_logs';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT l.*, p.post_title 
             FROM $table_name l 
             LEFT JOIN {$wpdb->posts} p ON l.post_id = p.ID 
             ORDER BY l.optimized_at DESC 
             LIMIT %d",
            $limit
        ));
        
        return $results;
    }
    
    /**
     * Clear optimization logs older than specified days
     * @param int $days
     * @return int Number of deleted records
     */
    public function clear_old_logs($days = 90) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'seo_auto_optimizer_logs';
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name 
             WHERE optimized_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
        
        return $deleted;
    }
    
    /**
     * Estimate processing time based on post count and batch size
     * @param int $total_posts
     * @param int $batch_size
     * @return array
     */
    public function estimate_processing_time($total_posts, $batch_size = 20) {
        // Rough estimates based on typical processing times
        $seconds_per_post = 0.5; // Half second per post average
        $batch_overhead = 2; // 2 seconds overhead per batch
        
        $total_batches = ceil($total_posts / $batch_size);
        $estimated_seconds = ($total_posts * $seconds_per_post) + ($total_batches * $batch_overhead);
        
        $minutes = floor($estimated_seconds / 60);
        $seconds = $estimated_seconds % 60;
        
        return array(
            'total_seconds' => $estimated_seconds,
            'minutes' => $minutes,
            'seconds' => $seconds,
            'formatted' => sprintf('%d minutes %d seconds', $minutes, $seconds),
            'total_batches' => $total_batches
        );
    }
}