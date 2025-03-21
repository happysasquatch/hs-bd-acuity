<?php
/**
 * Admin class
 */
class Acuity_Integration_Admin {
    /**
     * Settings instance
     */
    private $settings;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = new Acuity_Integration_Settings();
        
        // Add admin actions
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_init', array($this->settings, 'process_actions'));
    }
    
    /**
     * Add admin menu items
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Acuity Integration', 'acuity-integration'),
            __('Acuity', 'acuity-integration'),
            'manage_options',
            'acuity-integration',
            array($this, 'display_main_page'),
            'dashicons-calendar-alt',
            30
        );
        
        add_submenu_page(
            'acuity-integration',
            __('Acuity Classes', 'acuity-integration'),
            __('Classes', 'acuity-integration'),
            'manage_options',
            'acuity-integration',
            array($this, 'display_main_page')
        );
        
        add_submenu_page(
            'acuity-integration',
            __('Acuity Settings', 'acuity-integration'),
            __('Settings', 'acuity-integration'),
            'manage_options',
            'acuity-integration-settings',
            array($this->settings, 'render_page')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_scripts($hook) {
        // Only enqueue on plugin pages
        if (strpos($hook, 'acuity-integration') === false) {
            return;
        }
        
        wp_enqueue_style(
            'acuity-admin-css',
            ACUITY_INTEGRATION_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            ACUITY_INTEGRATION_VERSION
        );
        
        wp_enqueue_script(
            'acuity-admin-js',
            ACUITY_INTEGRATION_PLUGIN_URL . 'admin/css/admin.js',
            array('jquery'),
            ACUITY_INTEGRATION_VERSION,
            true
        );
        
        wp_localize_script('acuity-admin-js', 'acuityAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('acuity_ajax_nonce'),
            'syncingText' => __('Syncing...', 'acuity-integration'),
            'doneText' => __('Done!', 'acuity-integration')
        ));
    }
    
    /**
     * Display main admin page
     */
    public function display_main_page() {
        // Verify user has proper permissions
        if (!Acuity_Integration_Security::current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'acuity-integration'));
        }
        
        // Include view
        include ACUITY_INTEGRATION_PLUGIN_DIR . 'admin/views/main-page.php';
    }
}
