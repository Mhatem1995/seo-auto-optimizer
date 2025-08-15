<?php
/**
 * License Management Class
 * Handles plugin licensing and subscription validation (Phase 2)
 * Currently a placeholder - will be implemented after core plugin is complete
 */

if (!defined('ABSPATH')) {
    exit;
}

class SEO_Auto_Optimizer_License {
    
    private $license_server_url = 'https://your-license-server.com/api/v1/';
    private $product_id = 'seo-auto-optimizer';
    private $settings_key = 'seo_auto_optimizer_license';
    
    public function __construct() {
        // Only initialize if licensing is enabled
        if (defined('SEO_AUTO_OPTIMIZER_LICENSING_ENABLED') && SEO_AUTO_OPTIMIZER_LICENSING_ENABLED) {
            $this->init();
        }
    }
    
    /**
     * Initialize license management
     */
    private function init() {
        add_action('admin_init', array($this, 'check_license_status'));
        add_action('wp_ajax_seo_auto_optimizer_activate_license', array($this, 'ajax_activate_license'));
        add_action('wp_ajax_seo_auto_optimizer_deactivate_license', array($this, 'ajax_deactivate_license'));
        
        // Schedule daily license check
        if (!wp_next_scheduled('seo_auto_optimizer_daily_license_check')) {
            wp_schedule_event(time(), 'daily', 'seo_auto_optimizer_daily_license_check');
        }
        
        add_action('seo_auto_optimizer_daily_license_check', array($this, 'daily_license_check'));
        
        // Add license settings to admin page
        add_action('seo_auto_optimizer_admin_settings', array($this, 'license_settings_section'));
    }
    
    /**
     * Check if license is valid
     * @return bool
     */
    public function is_license_valid() {
        if (!defined('SEO_AUTO_OPTIMIZER_LICENSING_ENABLED') || !SEO_AUTO_OPTIMIZER_LICENSING_ENABLED) {
            return true; // License not required in development
        }
        
        $license_data = get_option($this->settings_key, array());
        
        if (empty($license_data['license_key']) || empty($license_data['status'])) {
            return false;
        }
        
        // Check if license is active and not expired
        if ($license_data['status'] !== 'active') {
            return false;
        }
        
        // Check expiration date
        if (!empty($license_data['expires']) && $license_data['expires'] !== 'lifetime') {
            $expires = strtotime($license_data['expires']);
            if ($expires && $expires < time()) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get license status
     * @return array
     */
    public function get_license_status() {
        $license_data = get_option($this->settings_key, array());
        
        $defaults = array(
            'license_key' => '',
            'status' => 'inactive',
            'expires' => '',
            'sites_allowed' => 1,
            'sites_used' => 0,
            'last_checked' => '',
            'error' => ''
        );
        
        return wp_parse_args($license_data, $defaults);
    }
    
    /**
     * Activate license
     * @param string $license_key
     * @return array
     */
    public function activate_license($license_key) {
        $license_key = sanitize_text_field(trim($license_key));
        
        if (empty($license_key)) {
            return array(
                'success' => false,
                'message' => __('License key is required', 'seo-auto-optimizer')
            );
        }
        
        // Make API request to license server
        $response = $this->make_license_request('activate', array(
            'license_key' => $license_key,
            'domain' => $this->get_site_domain(),
            'product_id' => $this->product_id
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data || !isset($data['success'])) {
            return array(
                'success' => false,
                'message' => __('Invalid response from license server', 'seo-auto-optimizer')
            );
        }
        
        if ($data['success']) {
            // Save license data
            $license_data = array(
                'license_key' => $license_key,
                'status' => $data['license']['status'],
                'expires' => $data['license']['expires'],
                'sites_allowed' => $data['license']['sites_allowed'],
                'sites_used' => $data['license']['sites_used'],
                'last_checked' => current_time('mysql'),
                'error' => ''
            );
            
            update_option($this->settings_key, $license_data);
            
            return array(
                'success' => true,
                'message' => __('License activated successfully', 'seo-auto-optimizer'),
                'data' => $license_data
            );
        } else {
            return array(
                'success' => false,
                'message' => isset($data['message']) ? $data['message'] : __('License activation failed', 'seo-auto-optimizer')
            );
        }
    }
    
    /**
     * Deactivate license
     * @return array
     */
    public function deactivate_license() {
        $license_data = get_option($this->settings_key, array());
        
        if (empty($license_data['license_key'])) {
            return array(
                'success' => false,
                'message' => __('No license to deactivate', 'seo-auto-optimizer')
            );
        }
        
        // Make API request to license server
        $response = $this->make_license_request('deactivate', array(
            'license_key' => $license_data['license_key'],
            'domain' => $this->get_site_domain(),
            'product_id' => $this->product_id
        ));
        
        // Clear local license data regardless of API response
        delete_option($this->settings_key);
        
        if (is_wp_error($response)) {
            return array(
                'success' => true, // Still success locally
                'message' => __('License deactivated locally. Server error: ', 'seo-auto-optimizer') . $response->get_error_message()
            );
        }
        
        return array(
            'success' => true,
            'message' => __('License deactivated successfully', 'seo-auto-optimizer')
        );
    }
    
    /**
     * Daily license check via cron
     */
    public function daily_license_check() {
        $license_data = get_option($this->settings_key, array());
        
        if (empty($license_data['license_key']) || $license_data['status'] !== 'active') {
            return;
        }
        
        // Check license with server
        $response = $this->make_license_request('check', array(
            'license_key' => $license_data['license_key'],
            'domain' => $this->get_site_domain(),
            'product_id' => $this->product_id
        ));
        
        if (!is_wp_error($response)) {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if ($data && isset($data['success']) && $data['success']) {
                // Update license data
                $license_data['status'] = $data['license']['status'];
                $license_data['expires'] = $data['license']['expires'];
                $license_data['sites_used'] = $data['license']['sites_used'];
                $license_data['last_checked'] = current_time('mysql');
                $license_data['error'] = '';
                
                update_option($this->settings_key, $license_data);
            } else {
                // Mark license as invalid
                $license_data['status'] = 'invalid';
                $license_data['error'] = isset($data['message']) ? $data['message'] : __('License check failed', 'seo-auto-optimizer');
                $license_data['last_checked'] = current_time('mysql');
                
                update_option($this->settings_key, $license_data);
            }
        }
    }
    
    /**
     * Check license status on admin pages
     */
    public function check_license_status() {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }
        
        $license_data = get_option($this->settings_key, array());
        
        // Show admin notices for license issues
        if (!$this->is_license_valid()) {
            add_action('admin_notices', array($this, 'license_invalid_notice'));
        }
    }
    
    /**
     * Show license invalid notice
     */
    public function license_invalid_notice() {
        $license_data = $this->get_license_status();
        
        $message = __('SEO Auto-Optimizer license is invalid or expired. Some features may be disabled.', 'seo-auto-optimizer');
        
        if (!empty($license_data['error'])) {
            $message .= ' ' . sprintf(__('Error: %s', 'seo-auto-optimizer'), $license_data['error']);
        }
        
        $settings_url = admin_url('options-general.php?page=seo-auto-optimizer');
        
        echo '<div class="notice notice-error">';
        echo '<p>' . $message . '</p>';
        echo '<p><a href="' . $settings_url . '" class="button">' . __('Manage License', 'seo-auto-optimizer') . '</a></p>';
        echo '</div>';
    }
    
    /**
     * License settings section for admin page
     */
    public function license_settings_section() {
        $license_data = $this->get_license_status();
        
        ?>
        <div class="postbox">
            <h3 class="hndle"><?php _e('License & Subscription', 'seo-auto-optimizer'); ?></h3>
            <div class="inside">
                
                <?php if ($license_data['status'] === 'active'): ?>
                    <div class="license-status active">
                        <p><span class="dashicons dashicons-yes-alt"></span> <?php _e('Active License', 'seo-auto-optimizer'); ?></p>
                        
                        <?php if ($license_data['expires'] !== 'lifetime'): ?>
                        <p><?php printf(__('Expires: %s', 'seo-auto-optimizer'), date('Y-m-d', strtotime($license_data['expires']))); ?></p>
                        <?php endif; ?>
                        
                        <p><?php printf(__('Sites: %d / %d', 'seo-auto-optimizer'), $license_data['sites_used'], $license_data['sites_allowed']); ?></p>
                        
                        <p>
                            <button type="button" id="deactivate-license" class="button">
                                <?php _e('Deactivate License', 'seo-auto-optimizer'); ?>
                            </button>
                        </p>
                    </div>
                
                <?php else: ?>
                    <div class="license-status inactive">
                        <p><span class="dashicons dashicons-warning"></span> <?php _e('No Active License', 'seo-auto-optimizer'); ?></p>
                        
                        <?php if (!empty($license_data['error'])): ?>
                        <p class="error"><?php echo esc_html($license_data['error']); ?></p>
                        <?php endif; ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('License Key', 'seo-auto-optimizer'); ?></th>
                                <td>
                                    <input type="text" id="license-key" class="regular-text" placeholder="<?php _e('Enter your license key', 'seo-auto-optimizer'); ?>" />
                                    <button type="button" id="activate-license" class="button button-primary">
                                        <?php _e('Activate License', 'seo-auto-optimizer'); ?>
                                    </button>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="description">
                            <?php _e('Enter your license key to unlock all features.', 'seo-auto-optimizer'); ?>
                            <a href="https://your-site.com/purchase" target="_blank"><?php _e('Purchase a license', 'seo-auto-optimizer'); ?></a>
                        </p>
                    </div>
                <?php endif; ?>
                
            </div>
        </div>
        
        <style>
            .license-status {
                padding: 15px;
                border-radius: 4px;
                margin-bottom: 15px;
            }
            .license-status.active {
                background: #d4edda;
                border: 1px solid #c3e6cb;
                color: #155724;
            }
            .license-status.inactive {
                background: #fff3cd;
                border: 1px solid #ffeaa7;
                color: #856404;
            }
            .license-status .dashicons {
                margin-right: 5px;
            }
            .license-status .error {
                color: #dc3545;
                font-weight: bold;
            }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('#activate-license').on('click', function() {
                var $button = $(this);
                var licenseKey = $('#license-key').val().trim();
                
                if (!licenseKey) {
                    alert('<?php _e('Please enter a license key', 'seo-auto-optimizer'); ?>');
                    return;
                }
                
                $button.prop('disabled', true).text('<?php _e('Activating...', 'seo-auto-optimizer'); ?>');
                
                $.post(ajaxurl, {
                    action: 'seo_auto_optimizer_activate_license',
                    license_key: licenseKey,
                    nonce: '<?php echo wp_create_nonce('seo_auto_optimizer_license'); ?>'
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message);
                        $button.prop('disabled', false).text('<?php _e('Activate License', 'seo-auto-optimizer'); ?>');
                    }
                }).fail(function() {
                    alert('<?php _e('Request failed. Please try again.', 'seo-auto-optimizer'); ?>');
                    $button.prop('disabled', false).text('<?php _e('Activate License', 'seo-auto-optimizer'); ?>');
                });
            });
            
            $('#deactivate-license').on('click', function() {
                if (!confirm('<?php _e('Are you sure you want to deactivate this license?', 'seo-auto-optimizer'); ?>')) {
                    return;
                }
                
                var $button = $(this);
                $button.prop('disabled', true).text('<?php _e('Deactivating...', 'seo-auto-optimizer'); ?>');
                
                $.post(ajaxurl, {
                    action: 'seo_auto_optimizer_deactivate_license',
                    nonce: '<?php echo wp_create_nonce('seo_auto_optimizer_license'); ?>'
                }, function(response) {
                    location.reload();
                }).fail(function() {
                    alert('<?php _e('Request failed. Please try again.', 'seo-auto-optimizer'); ?>');
                    $button.prop('disabled', false).text('<?php _e('Deactivate License', 'seo-auto-optimizer'); ?>');
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * AJAX handler for license activation
     */
    public function ajax_activate_license() {
        if (!wp_verify_nonce($_POST['nonce'], 'seo_auto_optimizer_license')) {
            wp_die(__('Security check failed', 'seo-auto-optimizer'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'seo-auto-optimizer'));
        }
        
        $license_key = sanitize_text_field($_POST['license_key']);
        $result = $this->activate_license($license_key);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX handler for license deactivation
     */
    public function ajax_deactivate_license() {
        if (!wp_verify_nonce($_POST['nonce'], 'seo_auto_optimizer_license')) {
            wp_die(__('Security check failed', 'seo-auto-optimizer'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'seo-auto-optimizer'));
        }
        
        $result = $this->deactivate_license();
        wp_send_json_success($result);
    }
    
    /**
     * Make license API request
     * @param string $action
     * @param array $data
     * @return array|WP_Error
     */
    private function make_license_request($action, $data) {
        $url = $this->license_server_url . $action;
        
        $response = wp_remote_post($url, array(
            'timeout' => 30,
            'body' => $data,
            'headers' => array(
                'User-Agent' => 'SEO Auto-Optimizer/' . SEO_AUTO_OPTIMIZER_VERSION . '; ' . get_site_url()
            )
        ));
        
        return $response;
    }
    
    /**
     * Get current site domain
     * @return string
     */
    private function get_site_domain() {
        return parse_url(get_site_url(), PHP_URL_HOST);
    }
    
    /**
     * Check if optimization should be blocked due to invalid license
     * @return bool
     */
    public function should_block_optimization() {
        if (!defined('SEO_AUTO_OPTIMIZER_LICENSING_ENABLED') || !SEO_AUTO_OPTIMIZER_LICENSING_ENABLED) {
            return false; // Don't block during development
        }
        
        return !$this->is_license_valid();
    }
}