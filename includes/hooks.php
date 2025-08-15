<?php
/**
 * Hooks and AJAX Handler
 * Manages WordPress hooks and AJAX endpoints
 */

if (!defined('ABSPATH')) {
    exit;
}

class SEO_Auto_Optimizer_Hooks {
    
    private $optimizer;
    private $settings;
    
    public function __construct() {
        $this->optimizer = new SEO_Auto_Optimizer_Core();
        $this->settings = get_option('seo_auto_optimizer_settings', array());
        $this->init();
    }
    
    /**
     * Initialize hooks
     */
    private function init() {
        // Auto-optimize on post save
        add_action('save_post', array($this, 'auto_optimize_on_save'), 20, 3);
        
        // AJAX endpoints
        add_action('wp_ajax_seo_auto_optimizer_bulk_process', array($this, 'ajax_bulk_process'));
        add_action('wp_ajax_seo_auto_optimizer_get_logs', array($this, 'ajax_get_logs'));
        add_action('wp_ajax_seo_auto_optimizer_clear_logs', array($this, 'ajax_clear_logs'));
        add_action('wp_ajax_seo_auto_optimizer_optimize_single', array($this, 'ajax_optimize_single'));
        
        // Add meta box to post edit screen
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
    }
    
    /**
     * Auto-optimize post on save
     */
    public function auto_optimize_on_save($post_id, $post, $update) {
        // Skip if auto-optimization is disabled
        if (empty($this->settings['auto_optimize_on_save'])) {
            return;
        }
        
        // Skip autosaves and revisions
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }
        
        // Skip if not a published post
        if ($post->post_status !== 'publish') {
            return;
        }
        
        // Skip certain post types
        $excluded_types = apply_filters('seo_auto_optimizer_excluded_post_types', array(
            'attachment',
            'revision',
            'nav_menu_item',
            'custom_css',
            'customize_changeset'
        ));
        
        if (in_array($post->post_type, $excluded_types)) {
            return;
        }
        
        // Optimize the post
        $result = $this->optimizer->optimize_post($post_id);
        
        // Optionally log result for debugging
        if (defined('WP_DEBUG') && WP_DEBUG && !$result['success']) {
            error_log('SEO Auto-Optimizer: Failed to optimize post ' . $post_id . ' - ' . $result['message']);
        }
    }
    
    /**
     * AJAX handler for bulk processing
     */
    public function ajax_bulk_process() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'seo_auto_optimizer_nonce')) {
            wp_die(__('Security check failed', 'seo-auto-optimizer'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'seo-auto-optimizer'));
        }
        
        $bulk_processor = new SEO_Auto_Optimizer_Bulk_Processor();
        
        $args = array(
            'post_type' => sanitize_text_field($_POST['post_type']),
            'batch_size' => intval($_POST['batch_size']),
            'offset' => intval($_POST['offset']),
            'start_date' => sanitize_text_field($_POST['start_date']),
            'end_date' => sanitize_text_field($_POST['end_date'])
        );
        
        // Validate batch size
        $args['batch_size'] = max(1, min(100, $args['batch_size']));
        
        $result = $bulk_processor->start_bulk_optimization($args);
        
        wp_send_json($result);
    }
    
    /**
     * AJAX handler for getting logs
     */
    public function ajax_get_logs() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'seo_auto_optimizer_nonce')) {
            wp_die(__('Security check failed', 'seo-auto-optimizer'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'seo-auto-optimizer'));
        }
        
        $bulk_processor = new SEO_Auto_Optimizer_Bulk_Processor();
        $logs = $bulk_processor->get_recent_logs(100);
        
        ob_start();
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Post', 'seo-auto-optimizer'); ?></th>
                    <th><?php _e('Type', 'seo-auto-optimizer'); ?></th>
                    <th><?php _e('Focus Keyword', 'seo-auto-optimizer'); ?></th>
                    <th><?php _e('SEO Title', 'seo-auto-optimizer'); ?></th>
                    <th><?php _e('Date', 'seo-auto-optimizer'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="5"><?php _e('No optimization logs found.', 'seo-auto-optimizer'); ?></td>
                </tr>
                <?php else: ?>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html($log->post_title ?: 'Post #' . $log->post_id); ?></strong>
                        <br>
                        <small>ID: <?php echo $log->post_id; ?></small>
                    </td>
                    <td><?php echo esc_html(ucfirst($log->optimization_type)); ?></td>
                    <td><?php echo esc_html($log->focus_keyword); ?></td>
                    <td><?php echo esc_html(wp_trim_words($log->seo_title, 10)); ?></td>
                    <td><?php echo date('Y-m-d H:i:s', strtotime($log->optimized_at)); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
        $html = ob_get_clean();
        
        wp_send_json_success(array('html' => $html));
    }
    
    /**
     * AJAX handler for clearing logs
     */
    public function ajax_clear_logs() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'seo_auto_optimizer_nonce')) {
            wp_die(__('Security check failed', 'seo-auto-optimizer'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'seo-auto-optimizer'));
        }
        
        $bulk_processor = new SEO_Auto_Optimizer_Bulk_Processor();
        $deleted = $bulk_processor->clear_old_logs(90);
        
        wp_send_json_success(array(
            'message' => sprintf(__('Deleted %d old log entries.', 'seo-auto-optimizer'), $deleted)
        ));
    }
    
    /**
     * AJAX handler for optimizing single post
     */
    public function ajax_optimize_single() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'seo_auto_optimizer_nonce')) {
            wp_die(__('Security check failed', 'seo-auto-optimizer'));
        }
        
        // Check permissions
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions', 'seo-auto-optimizer'));
        }
        
        $post_id = intval($_POST['post_id']);
        
        if (!$post_id) {
            wp_send_json_error(array('message' => __('Invalid post ID', 'seo-auto-optimizer')));
        }
        
        $result = $this->optimizer->optimize_post($post_id);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * Add meta box to post edit screen
     */
    public function add_meta_box() {
        $post_types = get_post_types(array('public' => true), 'names');
        
        foreach ($post_types as $post_type) {
            add_meta_box(
                'seo-auto-optimizer-meta-box',
                __('SEO Auto-Optimizer', 'seo-auto-optimizer'),
                array($this, 'meta_box_content'),
                $post_type,
                'side',
                'default'
            );
        }
    }
    
    /**
     * Meta box content
     */
    public function meta_box_content($post) {
        // Check if post has been optimized
        $focus_keyword = get_post_meta($post->ID, 'rank_math_focus_keyword', true);
        $seo_title = get_post_meta($post->ID, 'rank_math_title', true);
        $meta_description = get_post_meta($post->ID, 'rank_math_description', true);
        
        $is_optimized = !empty($focus_keyword) || !empty($seo_title) || !empty($meta_description);
        
        wp_nonce_field('seo_auto_optimizer_meta_box', 'seo_auto_optimizer_meta_box_nonce');
        ?>
        <div class="seo-auto-optimizer-meta-box">
            <?php if ($is_optimized): ?>
                <p class="seo-status optimized">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php _e('This post has been optimized', 'seo-auto-optimizer'); ?>
                </p>
                
                <?php if (!empty($focus_keyword)): ?>
                <p><strong><?php _e('Focus Keyword:', 'seo-auto-optimizer'); ?></strong><br>
                <?php echo esc_html($focus_keyword); ?></p>
                <?php endif; ?>
                
                <?php if (!empty($seo_title)): ?>
                <p><strong><?php _e('SEO Title:', 'seo-auto-optimizer'); ?></strong><br>
                <?php echo esc_html(wp_trim_words($seo_title, 8)); ?></p>
                <?php endif; ?>
                
            <?php else: ?>
                <p class="seo-status not-optimized">
                    <span class="dashicons dashicons-warning"></span>
                    <?php _e('This post has not been optimized', 'seo-auto-optimizer'); ?>
                </p>
            <?php endif; ?>
            
            <p>
                <button type="button" id="optimize-single-post" class="button button-secondary" data-post-id="<?php echo $post->ID; ?>">
                    <?php _e('Optimize This Post', 'seo-auto-optimizer'); ?>
                </button>
            </p>
            
            <div id="single-optimization-result" style="display: none;"></div>
        </div>
        
        <style>
            .seo-auto-optimizer-meta-box .seo-status {
                padding: 8px;
                border-radius: 4px;
                margin: 10px 0;
            }
            .seo-status.optimized {
                background: #d4edda;
                color: #155724;
                border: 1px solid #c3e6cb;
            }
            .seo-status.not-optimized {
                background: #fff3cd;
                color: #856404;
                border: 1px solid #ffeaa7;
            }
            .seo-status .dashicons {
                margin-right: 5px;
            }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('#optimize-single-post').on('click', function() {
                var $button = $(this);
                var postId = $button.data('post-id');
                var $result = $('#single-optimization-result');
                
                $button.prop('disabled', true).text('<?php _e('Optimizing...', 'seo-auto-optimizer'); ?>');
                $result.hide();
                
                $.post(ajaxurl, {
                    action: 'seo_auto_optimizer_optimize_single',
                    post_id: postId,
                    nonce: '<?php echo wp_create_nonce('seo_auto_optimizer_nonce'); ?>'
                }, function(response) {
                    $button.prop('disabled', false).text('<?php _e('Optimize This Post', 'seo-auto-optimizer'); ?>');
                    
                    if (response.success) {
                        $result.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>').show();
                        // Reload the page to show updated status
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        $result.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>').show();
                    }
                }).fail(function() {
                    $button.prop('disabled', false).text('<?php _e('Optimize This Post', 'seo-auto-optimizer'); ?>');
                    $result.html('<div class="notice notice-error"><p><?php _e('An error occurred', 'seo-auto-optimizer'); ?></p></div>').show();
                });
            });
        });
        </script>
        <?php
    }
}