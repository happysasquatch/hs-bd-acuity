<?php
/**
 * Settings class for managing plugin settings UI
 */
class Acuity_Integration_Settings {
    /**
     * Options instance
     *
     * @var Acuity_Integration_Options
     */
    private $options;
    
    /**
     * Current tab
     *
     * @var string
     */
    private $current_tab = 'general';
    
    /**
     * Available tabs
     *
     * @var array
     */
    private $tabs = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->options = new Acuity_Integration_Options();
        
        // Define tabs
        $this->tabs = array(
            'general' => __('General', 'acuity-integration'),
            'sync' => __('Synchronization', 'acuity-integration'),
            'display' => __('Display', 'acuity-integration'),
            'advanced' => __('Advanced', 'acuity-integration'),
            'logs' => __('Logs', 'acuity-integration')
        );
        
        // Get current tab
        if (isset($_GET['tab']) && array_key_exists($_GET['tab'], $this->tabs)) {
            $this->current_tab = sanitize_key($_GET['tab']);
        }
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        // General settings
        register_setting(
            'acuity_general_settings',
            'acuity_user_id',
            array('sanitize_callback' => 'sanitize_text_field')
        );
        
        register_setting(
            'acuity_general_settings',
            'acuity_api_key',
            array('sanitize_callback' => 'sanitize_text_field')
        );
        
        // Sync settings
        register_setting(
            'acuity_sync_settings',
            'acuity_sync_interval',
            array('sanitize_callback' => 'sanitize_text_field')
        );
        
        register_setting(
            'acuity_sync_settings',
            'acuity_cache_time',
            array('sanitize_callback' => 'absint')
        );
        
        register_setting(
            'acuity_sync_settings',
            'acuity_max_classes',
            array('sanitize_callback' => 'absint')
        );
        
        register_setting(
            'acuity_sync_settings',
            'acuity_auto_archive',
            array('sanitize_callback' => array($this, 'sanitize_checkbox'))
        );
        
        register_setting(
            'acuity_sync_settings',
            'acuity_archive_days',
            array('sanitize_callback' => 'absint')
        );
        
        // Display settings
        register_setting(
            'acuity_display_settings',
            'acuity_post_status',
            array('sanitize_callback' => 'sanitize_text_field')
        );
        
        // Advanced settings
        register_setting(
            'acuity_advanced_settings',
            'acuity_debug_mode',
            array('sanitize_callback' => array($this, 'sanitize_checkbox'))
        );
        
        register_setting(
            'acuity_advanced_settings',
            'acuity_log_retention',
            array('sanitize_callback' => 'absint')
        );
    }
    
    /**
     * Sanitize checkbox value
     *
     * @param mixed $value Checkbox value
     * @return string 'yes' or 'no'
     */
    public function sanitize_checkbox($value) {
        return $value ? 'yes' : 'no';
    }
    
    /**
     * Render settings page
     */
    public function render_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Acuity Integration Settings', 'acuity-integration'); ?></h1>
            
            <?php settings_errors(); ?>
            
            <h2 class="nav-tab-wrapper">
                <?php foreach ($this->tabs as $tab => $name) : ?>
                    <a href="?page=acuity-integration-settings&tab=<?php echo esc_attr($tab); ?>" 
                       class="nav-tab <?php echo $this->current_tab === $tab ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html($name); ?>
                    </a>
                <?php endforeach; ?>
            </h2>
            
            <form method="post" action="options.php">
                <?php 
                // Output security fields
                if ($this->current_tab === 'general') {
                    settings_fields('acuity_general_settings');
                    $this->render_general_settings();
                } elseif ($this->current_tab === 'sync') {
                    settings_fields('acuity_sync_settings');
                    $this->render_sync_settings();
                } elseif ($this->current_tab === 'display') {
                    settings_fields('acuity_display_settings');
                    $this->render_display_settings();
                } elseif ($this->current_tab === 'advanced') {
                    settings_fields('acuity_advanced_settings');
                    $this->render_advanced_settings();
                } elseif ($this->current_tab === 'logs') {
                    $this->render_logs_page();
                }
                
                // Don't show submit button on logs page
                if ($this->current_tab !== 'logs') {
                    submit_button();
                }
                ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render general settings
     */
    private function render_general_settings() {
        $user_id = $this->options->get_encrypted('user_id');
        $api_key = $this->options->get_encrypted('api_key');
        ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="acuity_user_id"><?php _e('User ID', 'acuity-integration'); ?></label>
                </th>
                <td>
                    <input type="text" id="acuity_user_id" name="acuity_user_id" 
                           value="<?php echo esc_attr($user_id); ?>" class="regular-text" />
                    <p class="description">
                        <?php _e('Your Acuity Scheduling User ID', 'acuity-integration'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="acuity_api_key"><?php _e('API Key', 'acuity-integration'); ?></label>
                </th>
                <td>
                    <input type="password" id="acuity_api_key" name="acuity_api_key" 
                           value="<?php echo esc_attr($api_key); ?>" class="regular-text" />
                    <p class="description">
                        <?php _e('Your Acuity Scheduling API Key', 'acuity-integration'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <?php _e('Connection Status', 'acuity-integration'); ?>
                </th>
                <td>
                    <?php $this->render_connection_status(); ?>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render sync settings
     */
    private function render_sync_settings() {
        $sync_interval = $this->options->get('sync_interval');
        $cache_time = $this->options->get('cache_time');
        $max_classes = $this->options->get('max_classes');
        $auto_archive = $this->options->get('auto_archive') === 'yes';
        $archive_days = $this->options->get('archive_days');
        ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="acuity_sync_interval"><?php _e('Sync Interval', 'acuity-integration'); ?></label>
                </th>
                <td>
                    <select id="acuity_sync_interval" name="acuity_sync_interval">
                        <option value="hourly" <?php selected($sync_interval, 'hourly'); ?>>
                            <?php _e('Hourly', 'acuity-integration'); ?>
                        </option>
                        <option value="twicedaily" <?php selected($sync_interval, 'twicedaily'); ?>>
                            <?php _e('Twice Daily', 'acuity-integration'); ?>
                        </option>
                        <option value="daily" <?php selected($sync_interval, 'daily'); ?>>
                            <?php _e('Daily', 'acuity-integration'); ?>
                        </option>
                    </select>
                    <p class="description">
                        <?php _e('How often to synchronize with Acuity Scheduling', 'acuity-integration'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="acuity_cache_time"><?php _e('Cache Time (seconds)', 'acuity-integration'); ?></label>
                </th>
                <td>
                    <input type="number" id="acuity_cache_time" name="acuity_cache_time" 
                           value="<?php echo esc_attr($cache_time); ?>" min="0" step="60" class="small-text" />
                    <p class="description">
                        <?php _e('How long to cache API responses (in seconds)', 'acuity-integration'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="acuity_max_classes"><?php _e('Maximum Classes', 'acuity-integration'); ?></label>
                </th>
                <td>
                    <input type="number" id="acuity_max_classes" name="acuity_max_classes" 
                           value="<?php echo esc_attr($max_classes); ?>" min="1" class="small-text" />
                    <p class="description">
                        <?php _e('Maximum number of classes to fetch from Acuity', 'acuity-integration'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <?php _e('Auto Archive', 'acuity-integration'); ?>
                </th>
                <td>
                    <label for="acuity_auto_archive">
                        <input type="checkbox" id="acuity_auto_archive" name="acuity_auto_archive" 
                               value="1" <?php checked($auto_archive); ?> />
                        <?php _e('Automatically archive past classes', 'acuity-integration'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="acuity_archive_days"><?php _e('Archive Days', 'acuity-integration'); ?></label>
                </th>
                <td>
                    <input type="number" id="acuity_archive_days" name="acuity_archive_days" 
                           value="<?php echo esc_attr($archive_days); ?>" min="1" class="small-text" />
                    <p class="description">
                        <?php _e('Archive classes older than this many days', 'acuity-integration'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render display settings
     */
    private function render_display_settings() {
        $post_status = $this->options->get('post_status');
        ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="acuity_post_status"><?php _e('Post Status', 'acuity-integration'); ?></label>
                </th>
                <td>
                    <select id="acuity_post_status" name="acuity_post_status">
                        <option value="publish" <?php selected($post_status, 'publish'); ?>>
                            <?php _e('Published', 'acuity-integration'); ?>
                        </option>
                        <option value="draft" <?php selected($post_status, 'draft'); ?>>
                            <?php _e('Draft', 'acuity-integration'); ?>
                        </option>
                        <option value="private" <?php selected($post_status, 'private'); ?>>
                            <?php _e('Private', 'acuity-integration'); ?>
                        </option>
                    </select>
                    <p class="description">
                        <?php _e('Default status for imported classes', 'acuity-integration'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render advanced settings
     */
    private function render_advanced_settings() {
        $debug_mode = $this->options->get('debug_mode') === 'yes';
        $log_retention = $this->options->get('log_retention');
        ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <?php _e('Debug Mode', 'acuity-integration'); ?>
                </th>
                <td>
                    <label for="acuity_debug_mode">
                        <input type="checkbox" id="acuity_debug_mode" name="acuity_debug_mode" 
                               value="1" <?php checked($debug_mode); ?> />
                        <?php _e('Enable debug logging', 'acuity-integration'); ?>
                    </label>
                    <p class="description">
                        <?php _e('This will log additional information for debugging purposes', 'acuity-integration'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="acuity_log_retention"><?php _e('Log Retention (days)', 'acuity-integration'); ?></label>
                </th>
                <td>
                    <input type="number" id="acuity_log_retention" name="acuity_log_retention" 
                           value="<?php echo esc_attr($log_retention); ?>" min="1" class="small-text" />
                    <p class="description">
                        <?php _e('Number of days to keep logs', 'acuity-integration'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <?php _e('Reset Settings', 'acuity-integration'); ?>
                </th>
                <td>
                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=acuity-integration-settings&action=reset'), 'acuity_reset_settings', 'acuity_nonce'); ?>" 
                       class="button" onclick="return confirm('<?php esc_attr_e('Are you sure you want to reset all settings to default values?', 'acuity-integration'); ?>')">
                        <?php _e('Reset to Defaults', 'acuity-integration'); ?>
                    </a>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <?php _e('Clear Cache', 'acuity-integration'); ?>
                </th>
                <td>
                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=acuity-integration-settings&action=clear_cache'), 'acuity_clear_cache', 'acuity_nonce'); ?>" 
                       class="button">
                        <?php _e('Clear Cache', 'acuity-integration'); ?>
                    </a>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render logs page
     */
    private function render_logs_page() {
        $logger = new Acuity_Integration_Logger();
        $errors = $logger->get_errors();
        
        // Handle clear logs action
        if (isset($_GET['action']) && $_GET['action'] === 'clear_logs' && isset($_GET['acuity_nonce'])) {
            if (wp_verify_nonce($_GET['acuity_nonce'], 'acuity_clear_logs')) {
                $logger->clear_errors();
                add_settings_error(
                    'acuity_logs',
                    'logs_cleared',
                    __('Logs cleared successfully.', 'acuity-integration'),
                    'updated'
                );
                $errors = array();
            }
        }
        ?>
        <div class="acuity-logs-wrapper">
            <div class="acuity-logs-header">
                <h2><?php _e('Error Logs', 'acuity-integration'); ?></h2>
                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=acuity-integration-settings&tab=logs&action=clear_logs'), 'acuity_clear_logs', 'acuity_nonce'); ?>" 
                   class="button">
                    <?php _e('Clear Logs', 'acuity-integration'); ?>
                </a>
            </div>
            
            <?php if (empty($errors)) : ?>
                <p><?php _e('No error logs found.', 'acuity-integration'); ?></p>
            <?php else : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e('Time', 'acuity-integration'); ?></th>
                            <th><?php _e('Message', 'acuity-integration'); ?></th>
                            <th><?php _e('Details', 'acuity-integration'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($errors as $error) : ?>
                            <tr>
                                <td>
                                    <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $error['timestamp'])); ?>
                                </td>
                                <td><?php echo esc_html($error['message']); ?></td>
                                <td>
                                    <?php if (!empty($error['data'])) : ?>
                                        <button type="button" class="button button-small acuity-toggle-details">
                                            <?php _e('Show Details', 'acuity-integration'); ?>
                                        </button>
                                        <div class="acuity-error-details" style="display:none;">
                                            <pre><?php echo esc_html(wp_json_encode($error['data'], JSON_PRETTY_PRINT)); ?></pre>
                                        </div>
                                    <?php else : ?>
                                        <?php _e('No additional details', 'acuity-integration'); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <script>
                jQuery(document).ready(function($) {
                    $('.acuity-toggle-details').on('click', function() {
                        var $details = $(this).next('.acuity-error-details');
                        $details.toggle();
                        
                        if ($details.is(':visible')) {
                            $(this).text('<?php esc_attr_e('Hide Details', 'acuity-integration'); ?>');
                        } else {
                            $(this).text('<?php esc_attr_e('Show Details', 'acuity-integration'); ?>');
                        }
                    });
                });
                </script>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render connection status
     */
    private function render_connection_status() {
        $api = new Acuity_Integration_API();
        try {
            $result = $api->test_connection();
            if ($result) {
                echo '<span class="acuity-status acuity-status-success">';
                esc_html_e('Connected', 'acuity-integration');
                echo '</span>';
            } else {
                echo '<span class="acuity-status acuity-status-error">';
                esc_html_e('Connection failed', 'acuity-integration');
                echo '</span>';
            }
        } catch (Exception $e) {
            echo '<span class="acuity-status acuity-status-error">';
            echo esc_html(sprintf(__('Error: %s', 'acuity-integration'), $e->getMessage()));
            echo '</span>';
        }
    }
    
    /**
     * Process admin actions
     */
    public function process_actions() {
        if (!isset($_GET['action']) || !isset($_GET['acuity_nonce'])) {
            return;
        }
        
        $action = sanitize_key($_GET['action']);
        
        if ($action === 'reset' && wp_verify_nonce($_GET['acuity_nonce'], 'acuity_reset_settings')) {
            $this->options->reset();
            add_settings_error(
                'acuity_settings',
                'settings_reset',
                __('Settings reset to defaults.', 'acuity-integration'),
                'updated'
            );
            wp_redirect(admin_url('admin.php?page=acuity-integration-settings'));
            exit;
        }
        
        if ($action === 'clear_cache' && wp_verify_nonce($_GET['acuity_nonce'], 'acuity_clear_cache')) {
            $this->clear_cache();
            add_settings_error(
                'acuity_settings',
                'cache_cleared',
                __('Cache cleared successfully.', 'acuity-integration'),
                'updated'
            );
            wp_redirect(admin_url('admin.php?page=acuity-integration-settings'));
            exit;
        }
    }
    
    /**
     * Clear all plugin cache
     */
    private function clear_cache() {
        global $wpdb;
        
        // Clear transients
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $wpdb->options WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_acuity_%',
                '_transient_timeout_acuity_%'
            )
        );
        
        // Clear any cached data
        wp_cache_flush();
    }
}