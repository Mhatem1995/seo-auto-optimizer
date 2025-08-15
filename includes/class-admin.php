<?php
/**
 * Admin Interface Class
 * Handles the WordPress admin dashboard interface
 */

if (!defined('ABSPATH')) {
    exit;
}

class SEO_Auto_Optimizer_Admin {
    
    private $settings;
    
    public function __construct() {
        $this->settings = get_option('seo_auto_optimizer_settings', array());
        $this->init();
    }
    
    /**
     * Initialize admin hooks
     */
    private function init() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Add admin menu page
     */
    public function add_admin_menu() {
        add_options_page(
            __('SEO Auto-Optimizer', 'seo-auto-optimizer'),
            __('SEO Auto-Optimizer', 'seo-auto-optimizer'),
            'manage_options',
            'seo-auto-optimizer',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if ($hook !== 'settings_page_seo-auto-optimizer') {
            return;
        }
        
        wp_enqueue_script(
            'seo-auto-optimizer-admin',
            SEO_AUTO_OPTIMIZER_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            SEO_AUTO_OPTIMIZER_VERSION,
            true
        );
        
        wp_enqueue_style(
            'seo-auto-optimizer-admin',
            SEO_AUTO_OPTIMIZER_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            SEO_AUTO_OPTIMIZER_VERSION
        );
        
        // Localize script for AJAX
        wp_localize_script('seo-auto-optimizer-admin', 'seoAutoOptimizer', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('seo_auto_optimizer_nonce'),
            'strings' => array(
                'processing' => __('Processing...', 'seo-auto-optimizer'),
                'completed' => __('Optimization completed!', 'seo-auto-optimizer'),
                'error' => __('An error occurred', 'seo-auto-optimizer'),
                'confirm_bulk' => __('This will optimize multiple posts. Continue?', 'seo-auto-optimizer')
            )
        ));
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('seo_auto_optimizer_settings', 'seo_auto_optimizer_settings');
    }
    
    /**
     * Main admin page
     */
    public function admin_page() {
        if (isset($_POST['save_settings'])) {
            $this->save_settings();
        }
        
        $bulk_processor = new SEO_Auto_Optimizer_Bulk_Processor();
        $stats = $bulk_processor->get_optimization_stats();
        
        ?>
        <div class="wrap">
            <h1><?php _e('SEO Auto-Optimizer', 'seo-auto-optimizer'); ?></h1>
            
            <div class="seo-auto-optimizer-header">
                <div class="plugin-info">
                    <div class="plugin-logo">
                        <span class="dashicons dashicons-search" style="font-size: 32px; color: #0073aa;"></span>
                    </div>
                    <div class="plugin-description">
                        <h2><?php _e('SEO Auto-Optimizer', 'seo-auto-optimizer'); ?></h2>
                        <p><?php _e('Optimize Rank Math SEO fields automatically for posts (and products in future updates).', 'seo-auto-optimizer'); ?></p>
                    </div>
                </div>
            </div>
            
            <?php $this->show_notices(); ?>
            
            <div class="seo-optimizer-dashboard">
                
                <!-- Bulk Optimization Section -->
                <div class="postbox">
                    <h3 class="hndle"><?php _e('Bulk Optimization', 'seo-auto-optimizer'); ?></h3>
                    <div class="inside">
                        <form id="bulk-optimization-form" method="post">
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php _e('Post Type', 'seo-auto-optimizer'); ?></th>
                                    <td>
                                        <select name="post_type" id="post_type">
                                            <option value="post"><?php _e('Posts', 'seo-auto-optimizer'); ?></option>
                                            <option value="page"><?php _e('Pages', 'seo-auto-optimizer'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Batch Size', 'seo-auto-optimizer'); ?></th>
                                    <td>
                                        <input type="number" name="batch_size" id="batch_size" value="20" min="1" max="100" />
                                        <p class="description"><?php _e('Number of posts to process at once (recommended: 20)', 'seo-auto-optimizer'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Date Range', 'seo-auto-optimizer'); ?></th>
                                    <td>
                                        <input type="date" name="start_date" id="start_date" placeholder="<?php _e('Start Date', 'seo-auto-optimizer'); ?>" />
                                        <input type="date" name="end_date" id="end_date" placeholder="<?php _e('End Date', 'seo-auto-optimizer'); ?>" />
                                        <p class="description"><?php _e('Leave empty to process all posts', 'seo-auto-optimizer'); ?></p>
                                    </td>
                                </tr>
                            </table>
                            
                            <div class="bulk-controls">
                                <button type="button" id="start-bulk-optimization" class="button button-primary button-large">
                                    <?php _e('Optimize Now', 'seo-auto-optimizer'); ?>
                                </button>
                            </div>
                        </form>
                        
                        <!-- Progress Section -->
                        <div id="bulk-progress" style="display: none;">
                            <h4><?php _e('Progress:', 'seo-auto-optimizer'); ?></h4>
                            <div class="progress-bar-container">
                                <div class="progress-bar">
                                    <div class="progress-fill" id="progress-fill" style="width: 0%"></div>
                                </div>
                                <span class="progress-text" id="progress-text">0%</span>
                            </div>
                            <div class="progress-stats" id="progress-stats">
                                <p><?php _e('Processed: <span id="processed-count">0</span> / <span id="total-count">0</span>', 'seo-auto-optimizer'); ?></p>
                                <p id="current-status"><?php _e('Initializing...', 'seo-auto-optimizer'); ?></p>
                            </div>
                        </div>
                        
                    </div>
                </div>
                
                <!-- Auto-Optimize on Save Section -->
                <div class="postbox">
                    <h3 class="hndle"><?php _e('Auto-Optimize on Save', 'seo-auto-optimizer'); ?></h3>
                    <div class="inside">
                        <form method="post">
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php _e('Enable Auto Optimization', 'seo-auto-optimizer'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="auto_optimize_on_save" value="1" <?php checked($this->settings['auto_optimize_on_save'], true); ?> />
                                            <?php _e('Enable auto optimization when saving posts', 'seo-auto-optimizer'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Overwrite Existing Fields', 'seo-auto-optimizer'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="overwrite_existing_empty" value="1" <?php checked($this->settings['overwrite_existing_empty'], true); ?> />
                                            <?php _e('Overwrite existing SEO fields if empty', 'seo-auto-optimizer'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Skip Already Optimized', 'seo-auto-optimizer'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="skip_already_optimized" value="1" <?php checked($this->settings['skip_already_optimized'], true); ?> />
                                            <?php _e('Skip posts already optimized', 'seo-auto-optimizer'); ?>
                                        </label>
                                    </td>
                                </tr>
                            </table>
                            
                            <?php submit_button(__('Save Auto-Optimization Settings', 'seo-auto-optimizer'), 'secondary', 'save_settings'); ?>
                        </form>
                    </div>
                </div>
                
                <!-- SEO Generation Settings -->
                <div class="postbox">
                    <h3 class="hndle"><?php _e('Generated SEO Settings', 'seo-auto-optimizer'); ?></h3>
                    <div class="inside">
                        <form method="post">
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php _e('Focus Keyword Strategy', 'seo-auto-optimizer'); ?></th>
                                    <td>
                                        <select name="focus_keyword_strategy">
                                            <option value="from_title" <?php selected($this->settings['focus_keyword_strategy'], 'from_title'); ?>>
                                                <?php _e('From Post Title', 'seo-auto-optimizer'); ?>
                                            </option>
                                            <option value="from_content" <?php selected($this->settings['focus_keyword_strategy'], 'from_content'); ?>>
                                                <?php _e('From Content Analysis', 'seo-auto-optimizer'); ?>
                                            </option>
                                            <option value="from_tags" <?php selected($this->settings['focus_keyword_strategy'], 'from_tags'); ?>>
                                                <?php _e('From Post Tags', 'seo-auto-optimizer'); ?>
                                            </option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('SEO Title Strategy', 'seo-auto-optimizer'); ?></th>
                                    <td>
                                        <select name="seo_title_strategy">
                                            <option value="post_title_site_name" <?php selected($this->settings['seo_title_strategy'], 'post_title_site_name'); ?>>
                                                <?php _e('{post_title} | {site_name}', 'seo-auto-optimizer'); ?>
                                            </option>
                                            <option value="keyword_post_title" <?php selected($this->settings['seo_title_strategy'], 'keyword_post_title'); ?>>
                                                <?php _e('{keyword} - {post_title}', 'seo-auto-optimizer'); ?>
                                            </option>
                                            <option value="post_title_only" <?php selected($this->settings['seo_title_strategy'], 'post_title_only'); ?>>
                                                <?php _e('{post_title} only', 'seo-auto-optimizer'); ?>
                                            </option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Meta Description', 'seo-auto-optimizer'); ?></th>
                                    <td>
                                        <select name="meta_description_strategy">
                                            <option value="first_160_chars" <?php selected($this->settings['meta_description_strategy'], 'first_160_chars'); ?>>
                                                <?php _e('First 160 characters of content', 'seo-auto-optimizer'); ?>
                                            </option>
                                            <option value="post_excerpt" <?php selected($this->settings['meta_description_strategy'], 'post_excerpt'); ?>>
                                                <?php _e('Post excerpt (fallback to content)', 'seo-auto-optimizer'); ?>
                                            </option>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                            
                            <?php submit_button(__('Save SEO Settings', 'seo-auto-optimizer'), 'secondary', 'save_settings'); ?>
                        </form>
                    </div>
                </div>
                
                <!-- Statistics Section -->
                <div class="postbox">
                    <h3 class="hndle"><?php _e('Optimization Statistics', 'seo-auto-optimizer'); ?></h3>
                    <div class="inside">
                        <div class="stats-grid">
                            <div class="stat-box">
                                <h4><?php _e('Total Optimizations', 'seo-auto-optimizer'); ?></h4>
                                <span class="stat-number"><?php echo number_format($stats['total']); ?></span>
                            </div>
                            <div class="stat-box">
                                <h4><?php _e('Last 30 Days', 'seo-auto-optimizer'); ?></h4>
                                <span class="stat-number"><?php echo number_format($stats['recent_30_days']); ?></span>
                            </div>
                        </div>
                        
                        <?php if (!empty($stats['by_post_type'])): ?>
                        <h4><?php _e('By Post Type', 'seo-auto-optimizer'); ?></h4>
                        <ul>
                            <?php foreach ($stats['by_post_type'] as $type_stat): ?>
                            <li><?php echo ucfirst($type_stat->post_type); ?>: <?php echo number_format($type_stat->count); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Logs Section -->
                <div class="postbox">
                    <h3 class="hndle"><?php _e('Logs', 'seo-auto-optimizer'); ?></h3>
                    <div class="inside">
                        <p>
                            <button type="button" id="view-logs" class="button">
                                <?php _e('View Optimization History', 'seo-auto-optimizer'); ?>
                            </button>
                            <button type="button" id="clear-logs" class="button">
                                <?php _e('Clear Old Logs (90+ days)', 'seo-auto-optimizer'); ?>
                            </button>
                        </p>
                        
                        <div id="logs-container" style="display: none;">
                            <div id="logs-content"></div>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
        
        <style>
            .seo-auto-optimizer-header {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                padding: 20px;
                margin: 20px 0;
            }
            .plugin-info {
                display: flex;
                align-items: center;
            }
            .plugin-logo {
                margin-right: 20px;
            }
            .plugin-description h2 {
                margin: 0 0 10px 0;
                color: #23282d;
            }
            .seo-optimizer-dashboard .postbox {
                margin-bottom: 20px;
            }
            .progress-bar-container {
                margin: 15px 0;
            }
            .progress-bar {
                width: 100%;
                height: 20px;
                background-color: #f1f1f1;
                border-radius: 10px;
                overflow: hidden;
                position: relative;
            }
            .progress-fill {
                height: 100%;
                background: linear-gradient(90deg, #0073aa 0%, #005177 100%);
                border-radius: 10px;
                transition: width 0.3s ease;
            }
            .progress-text {
                position: absolute;
                right: 10px;
                top: 50%;
                transform: translateY(-50%);
                font-weight: bold;
                color: #23282d;
            }
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 20px;
                margin-bottom: 20px;
            }
            .stat-box {
                text-align: center;
                padding: 20px;
                background: #f9f9f9;
                border-radius: 4px;
            }
            .stat-box h4 {
                margin: 0 0 10px 0;
                color: #666;
            }
            .stat-number {
                font-size: 32px;
                font-weight: bold;
                color: #0073aa;
            }
            .bulk-controls {
                margin-top: 20px;
            }
            .progress-stats {
                margin-top: 15px;
            }
            #bulk-progress {
                margin-top: 20px;
                padding: 20px;
                background: #f9f9f9;
                border-radius: 4px;
            }
        </style>
        <?php
    }
    
    /**
     * Show admin notices
     */
    private function show_notices() {
        if (isset($_GET['settings-updated'])) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e('Settings saved successfully!', 'seo-auto-optimizer'); ?></p>
            </div>
            <?php
        }
    }
    
    /**
     * Save plugin settings
     */
    private function save_settings() {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'seo_auto_optimizer_settings-options')) {
            return;
        }
        
        $settings = array(
            'auto_optimize_on_save' => isset($_POST['auto_optimize_on_save']),
            'overwrite_existing_empty' => isset($_POST['overwrite_existing_empty']),
            'skip_already_optimized' => isset($_POST['skip_already_optimized']),
            'focus_keyword_strategy' => sanitize_text_field($_POST['focus_keyword_strategy']),
            'seo_title_strategy' => sanitize_text_field($_POST['seo_title_strategy']),
            'meta_description_strategy' => sanitize_text_field($_POST['meta_description_strategy']),
            'version' => SEO_AUTO_OPTIMIZER_VERSION
        );
        
        update_option('seo_auto_optimizer_settings', $settings);
        $this->settings = $settings;
        
        wp_redirect(admin_url('options-general.php?page=seo-auto-optimizer&settings-updated=1'));
        exit;
    }
}