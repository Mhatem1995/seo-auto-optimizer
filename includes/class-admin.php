<?php
/**
 * Perfect Score Admin Interface
 * Enhanced dashboard for Rank Math perfect score optimization
 */

if (!defined('ABSPATH')) {
    exit;
}

class SEO_Auto_Optimizer_Perfect_Score_Admin {
    
    private $bulk_processor;
    private $settings;
    
    public function __construct() {
        $this->bulk_processor = new SEO_Auto_Optimizer_Enhanced_Bulk_Processor();
        $this->settings = get_option('seo_auto_optimizer_settings', array());
        
        add_action('admin_menu', array($this, 'add_perfect_score_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_perfect_score_scripts'));
        add_action('wp_ajax_start_perfect_score_optimization', array($this, 'ajax_start_perfect_score_optimization'));
        add_action('wp_ajax_get_perfect_score_progress', array($this, 'ajax_get_perfect_score_progress'));
        add_action('wp_ajax_stop_perfect_score_optimization', array($this, 'ajax_stop_perfect_score_optimization'));
        add_action('wp_ajax_get_perfect_score_stats', array($this, 'ajax_get_perfect_score_stats'));
    }
    
    /**
     * Add Perfect Score submenu
     */
    public function add_perfect_score_menu() {
        add_submenu_page(
            'seo-auto-optimizer',
            __('Perfect Score Mode', 'seo-auto-optimizer'),
            __('Perfect Score', 'seo-auto-optimizer'),
            'manage_options',
            'seo-auto-optimizer-perfect-score',
            array($this, 'render_perfect_score_page')
        );
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_perfect_score_scripts($hook) {
        if (strpos($hook, 'seo-auto-optimizer-perfect-score') === false) {
            return;
        }
        
        wp_enqueue_script(
            'seo-auto-optimizer-perfect-score',
            plugin_dir_url(__FILE__) . 'assets/js/perfect-score.js',
            array('jquery'),
            '1.0.0',
            true
        );
        
        wp_enqueue_style(
            'seo-auto-optimizer-perfect-score',
            plugin_dir_url(__FILE__) . 'assets/css/perfect-score.css',
            array(),
            '1.0.0'
        );
        
        wp_localize_script('seo-auto-optimizer-perfect-score', 'seoOptimizerPerfectScore', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('seo_optimizer_perfect_score_nonce'),
            'strings' => array(
                'starting' => __('Starting optimization...', 'seo-auto-optimizer'),
                'processing' => __('Processing posts...', 'seo-auto-optimizer'),
                'completed' => __('Optimization completed!', 'seo-auto-optimizer'),
                'error' => __('An error occurred', 'seo-auto-optimizer'),
                'confirm_stop' => __('Are you sure you want to stop the optimization?', 'seo-auto-optimizer')
            )
        ));
    }
    
    /**
     * Render Perfect Score page
     */
    public function render_perfect_score_page() {
        $stats = $this->bulk_processor->get_perfect_score_statistics();
        $bulk_status = get_option('seo_auto_optimizer_bulk_status', array());
        ?>
        <div class="wrap seo-optimizer-perfect-score">
            <h1><?php _e('SEO Auto-Optimizer - Perfect Score Mode', 'seo-auto-optimizer'); ?></h1>
            
            <div class="seo-optimizer-perfect-score-header">
                <div class="perfect-score-hero">
                    <div class="hero-content">
                        <h2><?php _e('Achieve Perfect Rank Math Scores', 'seo-auto-optimizer'); ?></h2>
                        <p><?php _e('Advanced algorithm designed to achieve 100/100 Rank Math SEO scores on all your content.', 'seo-auto-optimizer'); ?></p>
                    </div>
                    <div class="hero-stats">
                        <div class="stat-card">
                            <div class="stat-number"><?php echo esc_html($stats['perfect_scores']); ?></div>
                            <div class="stat-label"><?php _e('Perfect Scores', 'seo-auto-optimizer'); ?></div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo esc_html($stats['success_rate']); ?>%</div>
                            <div class="stat-label"><?php _e('Success Rate', 'seo-auto-optimizer'); ?></div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo esc_html($stats['average_score']); ?></div>
                            <div class="stat-label"><?php _e('Avg Score', 'seo-auto-optimizer'); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="seo-optimizer-content">
                <div class="content-left">
                    <!-- Optimization Control Panel -->
                    <div class="optimization-panel">
                        <h3><?php _e('Perfect Score Optimization', 'seo-auto-optimizer'); ?></h3>
                        
                        <form id="perfect-score-form">
                            <?php wp_nonce_field('seo_optimizer_perfect_score_nonce', 'perfect_score_nonce'); ?>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php _e('Post Type', 'seo-auto-optimizer'); ?></th>
                                    <td>
                                        <select name="post_type" id="post_type">
                                            <option value="post"><?php _e('Posts', 'seo-auto-optimizer'); ?></option>
                                            <option value="page"><?php _e('Pages', 'seo-auto-optimizer'); ?></option>
                                            <?php if (class_exists('WooCommerce')): ?>
                                                <option value="product"><?php _e('Products (WooCommerce)', 'seo-auto-optimizer'); ?></option>
                                            <?php endif; ?>
                                            <?php
                                            $custom_post_types = get_post_types(array('public' => true, '_builtin' => false), 'objects');
                                            foreach ($custom_post_types as $post_type) {
                                                echo '<option value="' . esc_attr($post_type->name) . '">' . esc_html($post_type->label) . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row"><?php _e('Target Score', 'seo-auto-optimizer'); ?></th>
                                    <td>
                                        <input type="number" name="target_score" id="target_score" value="100" min="80" max="100" />
                                        <p class="description"><?php _e('Minimum Rank Math score to achieve (80-100)', 'seo-auto-optimizer'); ?></p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row"><?php _e('Batch Size', 'seo-auto-optimizer'); ?></th>
                                    <td>
                                        <select name="batch_size" id="batch_size">
                                            <option value="5"><?php _e('5 posts (Recommended)', 'seo-auto-optimizer'); ?></option>
                                            <option value="10"><?php _e('10 posts', 'seo-auto-optimizer'); ?></option>
                                            <option value="15"><?php _e('15 posts (Fast server)', 'seo-auto-optimizer'); ?></option>
                                        </select>
                                        <p class="description"><?php _e('Posts to process per batch. Smaller batches are more reliable.', 'seo-auto-optimizer'); ?></p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row"><?php _e('Date Range', 'seo-auto-optimizer'); ?></th>
                                    <td>
                                        <input type="date" name="start_date" id="start_date" />
                                        <span><?php _e('to', 'seo-auto-optimizer'); ?></span>
                                        <input type="date" name="end_date" id="end_date" />
                                        <p class="description"><?php _e('Leave empty to process all posts', 'seo-auto-optimizer'); ?></p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row"><?php _e('Options', 'seo-auto-optimizer'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="force_optimization" id="force_optimization" />
                                            <?php _e('Force re-optimization of already optimized posts', 'seo-auto-optimizer'); ?>
                                        </label>
                                        <br />
                                        <label>
                                            <input type="checkbox" name="schedule_optimization" id="schedule_optimization" />
                                            <?php _e('Run in background (recommended for large sites)', 'seo-auto-optimizer'); ?>
                                        </label>
                                    </td>
                                </tr>
                            </table>
                            
                            <div class="optimization-actions">
                                <button type="submit" class="button button-primary button-large" id="start-optimization">
                                    <span class="dashicons dashicons-performance"></span>
                                    <?php _e('Start Perfect Score Optimization', 'seo-auto-optimizer'); ?>
                                </button>
                                
                                <button type="button" class="button button-secondary" id="stop-optimization" style="display: none;">
                                    <span class="dashicons dashicons-no"></span>
                                    <?php _e('Stop Optimization', 'seo-auto-optimizer'); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Progress Panel -->
                    <div class="progress-panel" id="progress-panel" style="display: none;">
                        <h3><?php _e('Optimization Progress', 'seo-auto-optimizer'); ?></h3>
                        
                        <div class="progress-bar-container">
                            <div class="progress-bar">
                                <div class="progress-fill" id="progress-fill"></div>
                            </div>
                            <div class="progress-text" id="progress-text">0%</div>
                        </div>
                        
                        <div class="progress-stats">
                            <div class="progress-stat">
                                <span class="stat-label"><?php _e('Total:', 'seo-auto-optimizer'); ?></span>
                                <span class="stat-value" id="total-posts">0</span>
                            </div>
                            <div class="progress-stat">
                                <span class="stat-label"><?php _e('Processed:', 'seo-auto-optimizer'); ?></span>
                                <span class="stat-value" id="processed-posts">0</span>
                            </div>
                            <div class="progress-stat">
                                <span class="stat-label"><?php _e('Perfect Scores:', 'seo-auto-optimizer'); ?></span>
                                <span class="stat-value" id="perfect-scores">0</span>
                            </div>
                            <div class="progress-stat">
                                <span class="stat-label"><?php _e('Average Score:', 'seo-auto-optimizer'); ?></span>
                                <span class="stat-value" id="average-score">0</span>
                            </div>
                        </div>
                        
                        <div class="progress-messages">
                            <div id="progress-message" class="progress-message"></div>
                        </div>
                    </div>
                    
                    <!-- Results Panel -->
                    <div class="results-panel" id="results-panel" style="display: none;">
                        <h3><?php _e('Optimization Results', 'seo-auto-optimizer'); ?></h3>
                        <div id="results-content"></div>
                    </div>
                </div>
                
                <div class="content-right">
                    <!-- Perfect Score Algorithm Info -->
                    <div class="info-panel">
                        <h3><?php _e('Perfect Score Algorithm', 'seo-auto-optimizer'); ?></h3>
                        <div class="algorithm-steps">
                            <div class="step">
                                <div class="step-number">1</div>
                                <div class="step-content">
                                    <h4><?php _e('Focus Keyword Detection', 'seo-auto-optimizer'); ?></h4>
                                    <p><?php _e('Intelligently extracts or generates optimal focus keywords from content', 'seo-auto-optimizer'); ?></p>
                                </div>
                            </div>
                            
                            <div class="step">
                                <div class="step-number">2</div>
                                <div class="step-content">
                                    <h4><?php _e('SEO Title Optimization', 'seo-auto-optimizer'); ?></h4>
                                    <p><?php _e('Creates compelling titles with perfect keyword placement', 'seo-auto-optimizer'); ?></p>
                                </div>
                            </div>
                            
                            <div class="step">
                                <div class="step-number">3</div>
                                <div class="step-content">
                                    <h4><?php _e('Meta Description Enhancement', 'seo-auto-optimizer'); ?></h4>
                                    <p><?php _e('Generates engaging descriptions with optimal keyword density', 'seo-auto-optimizer'); ?></p>
                                </div>
                            </div>
                            
                            <div class="step">
                                <div class="step-number">4</div>
                                <div class="step-content">
                                    <h4><?php _e('URL Structure Optimization', 'seo-auto-optimizer'); ?></h4>
                                    <p><?php _e('Creates SEO-friendly URLs with proper keyword integration', 'seo-auto-optimizer'); ?></p>
                                </div>
                            </div>
                            
                            <div class="step">
                                <div class="step-number">5</div>
                                <div class="step-content">
                                    <h4><?php _e('Content Density Balancing', 'seo-auto-optimizer'); ?></h4>
                                    <p><?php _e('Achieves perfect 1.5% keyword density throughout content', 'seo-auto-optimizer'); ?></p>
                                </div>
                            </div>
                            
                            <div class="step">
                                <div class="step-number">6</div>
                                <div class="step-content">
                                    <h4><?php _e('Strategic Link Building', 'seo-auto-optimizer'); ?></h4>
                                    <p><?php _e('Adds relevant internal and authority external links', 'seo-auto-optimizer'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Optimizations -->
                    <div class="recent-optimizations-panel">
                        <h3><?php _e('Recent Optimizations', 'seo-auto-optimizer'); ?></h3>
                        <div id="recent-optimizations">
                            <?php if (!empty($stats['recent_optimizations'])): ?>
                                <div class="optimizations-list">
                                    <?php foreach ($stats['recent_optimizations'] as $optimization): ?>
                                        <div class="optimization-item">
                                            <div class="optimization-title">
                                                <a href="<?php echo get_edit_post_link($optimization->ID); ?>" target="_blank">
                                                    <?php echo esc_html($optimization->post_title); ?>
                                                </a>
                                            </div>
                                            <div class="optimization-score">
                                                <span class="score-badge score-<?php echo $optimization->score >= 100 ? 'perfect' : ($optimization->score >= 90 ? 'good' : 'needs-work'); ?>">
                                                    <?php echo esc_html($optimization->score); ?>/100
                                                </span>
                                            </div>
                                            <div class="optimization-date">
                                                <?php echo human_time_diff(strtotime($optimization->optimized_date), current_time('timestamp')); ?> <?php _e('ago', 'seo-auto-optimizer'); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="no-optimizations"><?php _e('No optimizations yet. Start your first perfect score optimization!', 'seo-auto-optimizer'); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Performance Tips -->
                    <div class="tips-panel">
                        <h3><?php _e('Perfect Score Tips', 'seo-auto-optimizer'); ?></h3>
                        <div class="tips-list">
                            <div class="tip">
                                <span class="dashicons dashicons-lightbulb"></span>
                                <p><?php _e('Use smaller batch sizes (5-10) for better reliability and server performance.', 'seo-auto-optimizer'); ?></p>
                            </div>
                            <div class="tip">
                                <span class="dashicons dashicons-clock"></span>
                                <p><?php _e('Schedule optimizations during low-traffic hours for best results.', 'seo-auto-optimizer'); ?></p>
                            </div>
                            <div class="tip">
                                <span class="dashicons dashicons-backup"></span>
                                <p><?php _e('Always backup your site before running bulk optimizations.', 'seo-auto-optimizer'); ?></p>
                            </div>
                            <div class="tip">
                                <span class="dashicons dashicons-chart-line"></span>
                                <p><?php _e('Monitor your search rankings after optimization for performance insights.', 'seo-auto-optimizer'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .seo-optimizer-perfect-score {
            max-width: 1200px;
        }
        
        .seo-optimizer-perfect-score-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .perfect-score-hero {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .hero-content h2 {
            font-size: 2.5em;
            margin: 0 0 10px 0;
            color: white;
        }
        
        .hero-content p {
            font-size: 1.2em;
            opacity: 0.9;
            margin: 0;
        }
        
        .hero-stats {
            display: flex;
            gap: 20px;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.15);
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            min-width: 120px;
        }
        
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.9em;
            opacity: 0.8;
        }
        
        .seo-optimizer-content {
            display: flex;
            gap: 30px;
        }
        
        .content-left {
            flex: 2;
        }
        
        .content-right {
            flex: 1;
        }
        
        .optimization-panel,
        .progress-panel,
        .results-panel,
        .info-panel,
        .recent-optimizations-panel,
        .tips-panel {
            background: white;
            border: 1px solid #e1e1e1;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .optimization-panel h3,
        .progress-panel h3,
        .results-panel h3,
        .info-panel h3,
        .recent-optimizations-panel h3,
        .tips-panel h3 {
            margin-top: 0;
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        
        .optimization-actions {
            margin-top: 20px;
        }
        
        .button-large {
            padding: 12px 24px;
            font-size: 16px;
        }
        
        .progress-bar-container {
            margin: 20px 0;
        }
        
        .progress-bar {
            background: #f0f0f0;
            height: 20px;
            border-radius: 10px;
            overflow: hidden;
            position: relative;
        }
        
        .progress-fill {
            background: linear-gradient(90deg, #667eea, #764ba2);
            height: 100%;
            width: 0%;
            transition: width 0.3s ease;
        }
        
        .progress-text {
            text-align: center;
            margin-top: 10px;
            font-weight: bold;
        }
        
        .progress-stats {
            display: flex;
            justify-content: space-between;
            margin: 20px 0;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
        }
        
        .progress-stat {
            text-align: center;
        }
        
        .progress-stat .stat-label {
            display: block;
            font-size: 0.9em;
            color: #666;
        }
        
        .progress-stat .stat-value {
            display: block;
            font-size: 1.4em;
            font-weight: bold;
            color: #333;
        }
        
        .algorithm-steps {
            space-y: 15px;
        }
        
        .step {
            display: flex;
            align-items: flex-start;
            margin-bottom: 20px;
        }
        
        .step-number {
            background: #667eea;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
            flex-shrink: 0;
        }
        
        .step-content h4 {
            margin: 0 0 5px 0;
            color: #333;
        }
        
        .step-content p {
            margin: 0;
            color: #666;
            font-size: 0.9em;
        }
        
        .optimizations-list {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .optimization-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .optimization-item:last-child {
            border-bottom: none;
        }
        
        .optimization-title a {
            text-decoration: none;
            color: #333;
            font-weight: 500;
        }
        
        .optimization-title a:hover {
            color: #667eea;
        }
        
        .score-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
        }
        
        .score-perfect {
            background: #d4edda;
            color: #155724;
        }
        
        .score-good {
            background: #fff3cd;
            color: #856404;
        }
        
        .score-needs-work {
            background: #f8d7da;
            color: #721c24;
        }
        
        .optimization-date {
            font-size: 0.8em;
            color: #666;
        }
        
        .tips-list {
            space-y: 15px;
        }
        
        .tip {
            display: flex;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .tip .dashicons {
            color: #667eea;
            margin-right: 10px;
            margin-top: 2px;
        }
        
        .tip p {
            margin: 0;
            font-size: 0.9em;
            color: #666;
        }
        
        .no-optimizations {
            text-align: center;
            color: #999;
            font-style: italic;
            padding: 20px;
        }
        
        @media (max-width: 768px) {
            .seo-optimizer-content {
                flex-direction: column;
            }
            
            .perfect-score-hero {
                flex-direction: column;
                text-align: center;
            }
            
            .hero-stats {
                margin-top: 20px;
                justify-content: center;
            }
            
            .progress-stats {
                flex-direction: column;
                gap: 10px;
            }
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            var optimizationInProgress = false;
            var progressInterval;
            
            // Start optimization
            $('#perfect-score-form').on('submit', function(e) {
                e.preventDefault();
                
                if (optimizationInProgress) {
                    return;
                }
                
                var formData = {
                    action: 'start_perfect_score_optimization',
                    nonce: $('#perfect_score_nonce').val(),
                    post_type: $('#post_type').val(),
                    target_score: $('#target_score').val(),
                    batch_size: $('#batch_size').val(),
                    start_date: $('#start_date').val(),
                    end_date: $('#end_date').val(),
                    force_optimization: $('#force_optimization').is(':checked'),
                    schedule_optimization: $('#schedule_optimization').is(':checked')
                };
                
                startOptimization(formData);
            });
            
            // Stop optimization
            $('#stop-optimization').on('click', function() {
                if (confirm(seoOptimizerPerfectScore.strings.confirm_stop)) {
                    stopOptimization();
                }
            });
            
            function startOptimization(formData) {
                optimizationInProgress = true;
                $('#start-optimization').prop('disabled', true).text(seoOptimizerPerfectScore.strings.starting);
                $('#progress-panel').show();
                $('#results-panel').hide();
                
                $.post(ajaxurl, formData, function(response) {
                    if (response.success) {
                        $('#start-optimization').hide();
                        $('#stop-optimization').show();
                        
                        // Start progress monitoring
                        progressInterval = setInterval(checkProgress, 2000);
                        updateProgress(response.data);
                    } else {
                        alert(response.data.message || seoOptimizerPerfectScore.strings.error);
                        resetOptimizationState();
                    }
                }).fail(function() {
                    alert(seoOptimizerPerfectScore.strings.error);
                    resetOptimizationState();
                });
            }
            
            function checkProgress() {
                $.post(ajaxurl, {
                    action: 'get_perfect_score_progress',
                    nonce: seoOptimizerPerfectScore.nonce
                }, function(response) {
                    if (response.success) {
                        updateProgress(response.data);
                        
                        if (response.data.completed) {
                            completeOptimization(response.data);
                        }
                    }
                });
            }
            
            function updateProgress(data) {
                var progress = data.progress || 0;
                $('#progress-fill').css('width', progress + '%');
                $('#progress-text').text(progress + '%');
                
                $('#total-posts').text(data.total || 0);
                $('#processed-posts').text(data.processed || 0);
                $('#perfect-scores').text(data.perfect_scores || 0);
                $('#average-score').text(data.average_score || 0);
                
                if (data.message) {
                    $('#progress-message').text(data.message);
                }
            }
            
            function completeOptimization(data) {
                clearInterval(progressInterval);
                optimizationInProgress = false;
                
                $('#progress-message').text(seoOptimizerPerfectScore.strings.completed);
                $('#stop-optimization').hide();
                $('#start-optimization').show().prop('disabled', false).text('Start Perfect Score Optimization');
                
                // Show results
                $('#results-panel').show();
                displayResults(data);
                
                // Refresh recent optimizations
                refreshRecentOptimizations();
            }
            
            function stopOptimization() {
                $.post(ajaxurl, {
                    action: 'stop_perfect_score_optimization',
                    nonce: seoOptimizerPerfectScore.nonce
                }, function(response) {
                    if (response.success) {
                        clearInterval(progressInterval);
                        resetOptimizationState();
                        $('#progress-message').text('Optimization stopped.');
                    }
                });
            }
            
            function resetOptimizationState() {
                optimizationInProgress = false;
                $('#start-optimization').prop('disabled', false).text('Start Perfect Score Optimization').show();
                $('#stop-optimization').hide();
            }
            
            function displayResults(data) {
                var html = '<div class="results-summary">';
                html += '<h4>Optimization Complete!</h4>';
                html += '<p><strong>Total Processed:</strong> ' + data.processed + '</p>';
                html += '<p><strong>Perfect Scores:</strong> ' + data.perfect_scores + '</p>';
                html += '<p><strong>Success Rate:</strong> ' + data.success_rate + '%</p>';
                html += '<p><strong>Average Score:</strong> ' + data.average_score + '</p>';
                html += '</div>';
                
                $('#results-content').html(html);
            }
            
            function refreshRecentOptimizations() {
                $.post(ajaxurl, {
                    action: 'get_perfect_score_stats',
                    nonce: seoOptimizerPerfectScore.nonce
                }, function(response) {
                    if (response.success && response.data.recent_optimizations) {
                        // Update recent optimizations panel
                        // Implementation would update the recent optimizations section
                    }
                });
            }
        });
        </script>
        <?php
    }
    
    /**
     * AJAX: Start perfect score optimization
     */
    public function ajax_start_perfect_score_optimization() {
        check_ajax_referer('seo_optimizer_perfect_score_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'seo-auto-optimizer'));
        }
        
        $args = array(
            'post_type' => sanitize_text_field($_POST['post_type']),
            'target_score' => intval($_POST['target_score']),
            'batch_size' => intval($_POST['batch_size']),
            'start_date' => sanitize_text_field($_POST['start_date']),
            'end_date' => sanitize_text_field($_POST['end_date']),
            'force_optimization' => isset($_POST['force_optimization']),
            'schedule_optimization' => isset($_POST['schedule_optimization'])
        );
        
        if ($args['schedule_optimization']) {
            $result = $this->bulk_processor->schedule_perfect_score_optimization($args);
        } else {
            $result = $this->bulk_processor->start_perfect_score_bulk_optimization($args);
        }
        
        wp_send_json($result);
    }
    
    /**
     * AJAX: Get optimization progress
     */
    public function ajax_get_perfect_score_progress() {
        check_ajax_referer('seo_optimizer_perfect_score_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'seo-auto-optimizer'));
        }
        
        $status = get_option('seo_auto_optimizer_bulk_status', array());
        
        wp_send_json_success($status);
    }
    
    /**
     * AJAX: Stop optimization
     */
    public function ajax_stop_perfect_score_optimization() {
        check_ajax_referer('seo_optimizer_perfect_score_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'seo-auto-optimizer'));
        }
        
        // Clear scheduled event
        wp_clear_scheduled_hook('seo_auto_optimizer_perfect_score_cron');
        
        // Update status
        update_option('seo_auto_optimizer_bulk_status', array(
            'status' => 'stopped',
            'stopped_at' => current_time('mysql')
        ));
        
        wp_send_json_success(array(
            'message' => __('Optimization stopped successfully', 'seo-auto-optimizer')
        ));
    }
    
    /**
     * AJAX: Get perfect score statistics
     */
    public function ajax_get_perfect_score_stats() {
        check_ajax_referer('seo_optimizer_perfect_score_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'seo-auto-optimizer'));
        }
        
        $stats = $this->bulk_processor->get_perfect_score_statistics();
        
        wp_send_json_success($stats);
    }
}

// Initialize the admin interface
new SEO_Auto_Optimizer_Perfect_Score_Admin();
