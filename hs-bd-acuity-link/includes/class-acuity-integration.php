<?php
/**
 * Main plugin class
 */
class Acuity_Integration_Plugin {
    /**
     * Plugin instance
     */
    private static $instance = null;

    /**
     * Admin instance
     */
    private $admin;

    /**
     * Sync instance
     */
    private $sync;

    /**
     * Archive instance
     */
    private $archive;

    /**
     * ACF instance
     */
    private $acf;
    
    /**
     * Options instance
     */
    private $options;
    
    /**
     * Post types instance
     */
    private $post_types;
    
    /**
     * Cron instance
     */
    private $cron;
    


    /**
     * Get plugin instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get options instance
     */
    public function get_options() {
        return $this->options;
    }
    
    
    /**
     * Get ACF instance
     */
    public function get_acf() {
        return $this->acf;
    }
    
    /**
     * Get admin instance
     */
    public function get_admin() {
        return $this->admin;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Initialize core components first
        $this->options = new Acuity_Integration_Options();
        $this->post_types = new Acuity_Integration_Post_Types();
        $this->cron = new Acuity_Integration_Cron();
        
        // Initialize other components
        $this->admin = new Acuity_Integration_Admin();
        $this->sync = new Acuity_Integration_Sync();
        $this->archive = new Acuity_Integration_Archive();
        $this->acf = new Acuity_Integration_ACF();
        
        // Register hooks
        $this->register_hooks();
    }
    
    /**
     * Register hooks
     */
    private function register_hooks() {

        // Register post types and taxonomies
        add_action('init', array($this->post_types, 'register'));
        add_action('init', array($this->post_types, 'register_meta'));
        
        // Register cron schedules and events
        $this->cron->register();
        
        // Register admin hooks
        add_action('admin_menu', array($this->admin, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this->admin, 'enqueue_scripts'));
        
        // Register AJAX handlers
        add_action('wp_ajax_' . Acuity_Integration_Constants::prefixed('manual_sync'), array($this, 'handle_ajax_sync'));
        add_action('wp_ajax_' . Acuity_Integration_Constants::prefixed('manual_archive'), array($this, 'handle_ajax_archive'));
        
        // Register custom dashboard widgets
        add_action('wp_dashboard_setup', array($this->admin, 'add_dashboard_widgets'));
        
        // Register shortcodes
        add_shortcode('acuity_classes', array($this, 'classes_shortcode'));
    }
    
    /**
     * Handle AJAX sync request
     */
    public function handle_ajax_sync() {
        // Verify nonce and capabilities
        Acuity_Integration_Security::verify_request_or_die(
            Acuity_Integration_Constants::nonce_action('ajax_sync'),
            Acuity_Integration_Constants::CAPABILITY
        );
        
        try {
            $result = $this->sync->sync_classes();
            wp_send_json_success(array(
                'message' => sprintf(
                    /* translators: %d: number of classes synced */
                    _n(
                        '%d class synced successfully.',
                        '%d classes synced successfully.',
                        $result,
                        'acuity-integration'
                    ),
                    $result
                ),
                'count' => $result
            ));
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
        
        wp_die();
    }
    
    /**
     * Handle AJAX archive request
     */
    public function handle_ajax_archive() {
        // Verify nonce and capabilities
        Acuity_Integration_Security::verify_request_or_die(
            Acuity_Integration_Constants::nonce_action('ajax_archive'),
            Acuity_Integration_Constants::CAPABILITY
        );
        
        try {
            $result = $this->archive->archive_classes();
            wp_send_json_success(array(
                'message' => sprintf(
                    /* translators: %d: number of classes archived */
                    _n(
                        '%d class archived successfully.',
                        '%d classes archived successfully.',
                        $result,
                        'acuity-integration'
                    ),
                    $result
                ),
                'count' => $result
            ));
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
        
        wp_die();
    }
    

    
    /**
     * Plugin activation
     */
    public static function activate() {
        // Ensure options are initialized
        $options = new Acuity_Integration_Options();
        $defaults = $options->get_defaults();
        
        foreach ($defaults as $key => $value) {
            if (get_option(Acuity_Integration_Constants::option_name($key)) === false) {
                update_option(Acuity_Integration_Constants::option_name($key), $value);
            }
        }
        
        // Create custom post types and taxonomies for proper rewrite rules
        $post_types = new Acuity_Integration_Post_Types();
        $post_types->register();
        
        // Initialize cron tasks
        $cron = new Acuity_Integration_Cron();
        $cron->register();
        
        
        // Log activation
        $logger = new Acuity_Integration_Logger();
        $logger->info('Plugin activated', array(
            'version' => ACUITY_INTEGRATION_VERSION
        ));
    }
    
    /**
     * Plugin deactivation
     */
    public static function deactivate() {
        // Clear scheduled events
        Acuity_Integration_Cron::clear_scheduled_events();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Log deactivation
        $logger = new Acuity_Integration_Logger();
        $logger->info('Plugin deactivated');
    }
    


    /**
     * Get plugin info
     * 
     * @return array Plugin information
     */
    public function get_plugin_info() {
        return array(
            'version' => ACUITY_INTEGRATION_VERSION,
            'name' => 'Acuity Integration',
            'author' => 'Happy Sasquatch',
            'website' => 'https://happysasquatch.com/acuity-integration',
            'requirements' => array(
                'php' => '7.0',
                'wordpress' => '5.0'
            )
        );
    }
    

}