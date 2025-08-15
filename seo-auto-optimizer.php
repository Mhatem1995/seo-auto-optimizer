<?php
/**
 * Plugin Name: SEO Auto-Optimizer
 * Plugin URI: https://www.linkedin.com/in/marwan-hatem-713269211
 * Description: Automatically optimize Rank Math SEO fields for posts and products with bulk processing capabilities.
 * Version: 1.0.0
 * Author: Marwan Hatem Mohamed
 * Author URI: https://www.linkedin.com/in/marwan-hatem-713269211
 * License: GPL v2 or later
 * Text Domain: seo-auto-optimizer
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SEO_AUTO_OPTIMIZER_VERSION', '1.0.0');
define('SEO_AUTO_OPTIMIZER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SEO_AUTO_OPTIMIZER_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('SEO_AUTO_OPTIMIZER_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main SEO Auto-Optimizer Plugin Class
 */
class SEO_Auto_Optimizer {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Initialize the plugin
     */
    private function init() {
        // Load text domain for translations
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Check if Rank Math is active
        add_action('admin_init', array($this, 'check_rank_math_dependency'));
        
        // Include required files
        $this->include_files();
        
        // Initialize components
        add_action('init', array($this, 'init_components'));
        
        // Plugin activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Load plugin text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain('seo-auto-optimizer', false, dirname(SEO_AUTO_OPTIMIZER_PLUGIN_BASENAME) . '/languages/');
    }
    
    /**
     * Check if Rank Math is active
     */
    public function check_rank_math_dependency() {
        if (!is_plugin_active('seo-by-rank-math/rank-math.php') && !class_exists('RankMath')) {
            add_action('admin_notices', array($this, 'rank_math_dependency_notice'));
        }
    }
    
    /**
     * Show dependency notice if Rank Math is not active
     */
    public function rank_math_dependency_notice() {
        $message = sprintf(
            __('SEO Auto-Optimizer requires %s to be installed and active.', 'seo-auto-optimizer'),
            '<strong>Rank Math SEO</strong>'
        );
        
        echo '<div class="notice notice-error"><p>' . $message . '</p></div>';
    }
    
    /**
     * Include required files
     */
    private function include_files() {
        require_once SEO_AUTO_OPTIMIZER_PLUGIN_PATH . 'includes/helpers.php';
        require_once SEO_AUTO_OPTIMIZER_PLUGIN_PATH . 'includes/class-optimizer.php';
        require_once SEO_AUTO_OPTIMIZER_PLUGIN_PATH . 'includes/class-bulk-processor.php';
        require_once SEO_AUTO_OPTIMIZER_PLUGIN_PATH . 'includes/class-admin.php';
        require_once SEO_AUTO_OPTIMIZER_PLUGIN_PATH . 'includes/class-license.php';
        require_once SEO_AUTO_OPTIMIZER_PLUGIN_PATH . 'includes/hooks.php';
    }
    
    /**
     * Initialize plugin components
     */
    public function init_components() {
        // Initialize admin interface
        if (is_admin()) {
            new SEO_Auto_Optimizer_Admin();
        }
        
        // Initialize hooks
        new SEO_Auto_Optimizer_Hooks();
        
        // Initialize license (placeholder for now)
        // new SEO_Auto_Optimizer_License();
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create default options
        $default_options = array(
            'auto_optimize_on_save' => true,
            'overwrite_existing_empty' => true,
            'skip_already_optimized' => false,
            'focus_keyword_strategy' => 'from_title',
            'seo_title_strategy' => 'post_title_site_name',
            'meta_description_strategy' => 'first_160_chars',
            'batch_size' => 20,
            'version' => SEO_AUTO_OPTIMIZER_VERSION
        );
        
        add_option('seo_auto_optimizer_settings', $default_options);
        
        // Create optimization log table
        $this->create_log_table();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear any scheduled events
        wp_clear_scheduled_hook('seo_auto_optimizer_bulk_process');
    }
    
    /**
     * Create log table for tracking optimizations
     */
    private function create_log_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'seo_auto_optimizer_logs';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            post_id int(11) NOT NULL,
            post_type varchar(20) NOT NULL,
            optimization_type varchar(20) NOT NULL,
            focus_keyword varchar(255),
            seo_title text,
            meta_description text,
            optimized_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY post_type (post_type),
            KEY optimization_type (optimization_type)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

// Initialize the plugin
SEO_Auto_Optimizer::get_instance();