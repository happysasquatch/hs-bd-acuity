<?php
/**
 * Plugin Name: Acuity Integration
 * Plugin URI: https://happysasquatch.com/acuity-integration
 * Description: Integrates Acuity Scheduling with WordPress for class management
 * Version: 1.0.0
 * Author: Happy Sasquatch
 * Author URI: https://happysasquatch.com
 * Text Domain: acuity-integration
 * Domain Path: /languages
 */

if (!defined('WPINC')) {
    exit;
}

// Define plugin constants
define('ACUITY_INTEGRATION_VERSION', '1.0.0');
define('ACUITY_INTEGRATION_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ACUITY_INTEGRATION_PLUGIN_URL', plugin_dir_url(__FILE__));


// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'Acuity_Integration_';
    $base_dir = ACUITY_INTEGRATION_PLUGIN_DIR . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    
   
    $file = $base_dir . 'class-' . strtolower(str_replace('_', '-', $relative_class)) . '.php';
    

    if (file_exists($file)) {
        require $file;
    }
});

// Initialize plugin
function acuity_integration_init() {
    $plugin = Acuity_Integration_Plugin::get_instance();
}
add_action('plugins_loaded', 'acuity_integration_init');

// Activation hook
register_activation_hook(__FILE__, array('Acuity_Integration_Plugin', 'activate'));

// Deactivation hook
register_deactivation_hook(__FILE__, array('Acuity_Integration_Plugin', 'deactivate'));

// Uninstall hook (for complete removal)
register_uninstall_hook(__FILE__, 'acuity_integration_uninstall');

/**
 * Uninstall plugin callback
 */
function acuity_integration_uninstall() {
    // The uninstall hook runs in a separate process so we need to load classes
    require_once plugin_dir_path(__FILE__) . 'includes/class-plugin.php';
    
    // Call the uninstall method with delete_data=true
    Acuity_Integration_Plugin::uninstall(true);
} 