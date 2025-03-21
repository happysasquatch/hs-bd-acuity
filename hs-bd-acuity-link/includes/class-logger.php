<?php
/**
 * Logger class for handling debug and error logging
 */
class Acuity_Integration_Logger {
    /**
     * Log levels
     */
    const LEVEL_ERROR = 'error';
    const LEVEL_WARNING = 'warning';
    const LEVEL_INFO = 'info';
    const LEVEL_DEBUG = 'debug';
    
    /**
     * Whether debug mode is enabled
     *
     * @var bool
     */
    private $debug_mode;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->debug_mode = $this->is_debug_mode_enabled();
    }
    
    /**
     * Check if debug mode is enabled
     *
     * @return bool
     */
    private function is_debug_mode_enabled() {
        return (defined('WP_DEBUG') && WP_DEBUG) || 
               (get_option('acuity_debug_mode', false) === 'yes');
    }
    
    /**
     * Log a message
     *
     * @param string $message Message to log
     * @param string $level Log level
     * @param mixed $data Additional data to log (will be sanitized)
     * @return void
     */
    public function log($message, $level = self::LEVEL_INFO, $data = null) {
        // For non-debug levels, always log
        $should_log = true;
        
        // For debug level, only log if debug mode is enabled
        if ($level === self::LEVEL_DEBUG && !$this->debug_mode) {
            $should_log = false;
        }
        
        if (!$should_log) {
            return;
        }
        
        $log_entry = sprintf(
            '[%s][Acuity Integration][%s] %s',
            current_time('mysql'),
            strtoupper($level),
            $message
        );
        
        // If additional data is provided, sanitize and add it
        if ($data !== null) {
            // Deep sanitization of data for logging
            $sanitized_data = $this->sanitize_data_for_log($data);
            $log_entry .= PHP_EOL . 'Data: ' . wp_json_encode($sanitized_data, JSON_PRETTY_PRINT);
        }
        
        // Use WordPress logging method or fall back to error_log
        if (function_exists('error_log')) {
            error_log($log_entry);
        }
        
        // For critical errors, store in WordPress database for admin viewing
        if ($level === self::LEVEL_ERROR) {
            $this->store_error_in_db($message, $data);
        }
    }
    
    /**
     * Log an error message
     *
     * @param string $message Error message
     * @param mixed $data Additional data
     * @return void
     */
    public function error($message, $data = null) {
        $this->log($message, self::LEVEL_ERROR, $data);
    }
    
    /**
     * Log a warning message
     *
     * @param string $message Warning message
     * @param mixed $data Additional data
     * @return void
     */
    public function warning($message, $data = null) {
        $this->log($message, self::LEVEL_WARNING, $data);
    }
    
    /**
     * Log an info message
     *
     * @param string $message Info message
     * @param mixed $data Additional data
     * @return void
     */
    public function info($message, $data = null) {
        $this->log($message, self::LEVEL_INFO, $data);
    }
    
    /**
     * Log a debug message
     *
     * @param string $message Debug message
     * @param mixed $data Additional data
     * @return void
     */
    public function debug($message, $data = null) {
        $this->log($message, self::LEVEL_DEBUG, $data);
    }
    
    /**
     * Sanitize data for logging
     *
     * @param mixed $data Data to sanitize
     * @return mixed Sanitized data
     */
    private function sanitize_data_for_log($data) {
        if (is_array($data)) {
            $sanitized = array();
            foreach ($data as $key => $value) {
                // Sanitize sensitive fields
                if (in_array($key, array('api_key', 'password', 'token', 'secret'))) {
                    $sanitized[$key] = '***REDACTED***';
                } else {
                    $sanitized[$key] = $this->sanitize_data_for_log($value);
                }
            }
            return $sanitized;
        } elseif (is_object($data)) {
            // Convert object to array and sanitize
            return $this->sanitize_data_for_log((array) $data);
        } else {
            return $data;
        }
    }
    
    /**
     * Store error in database for admin viewing
     *
     * @param string $message Error message
     * @param mixed $data Additional data
     * @return void
     */
    private function store_error_in_db($message, $data = null) {
        $errors = get_option('acuity_integration_errors', array());
        
        // Limit to 50 most recent errors
        if (count($errors) >= 50) {
            array_shift($errors);
        }
        
        $errors[] = array(
            'timestamp' => current_time('timestamp'),
            'message' => $message,
            'data' => $this->sanitize_data_for_log($data)
        );
        
        update_option('acuity_integration_errors', $errors);
    }
    
    /**
     * Get stored errors
     *
     * @param int $count Number of errors to retrieve
     * @return array Errors
     */
    public function get_errors($count = 10) {
        $errors = get_option('acuity_integration_errors', array());
        
        // Sort by timestamp descending (newest first)
        usort($errors, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });
        
        // Return requested number of errors
        return array_slice($errors, 0, $count);
    }
    
    /**
     * Clear stored errors
     *
     * @return void
     */
    public function clear_errors() {
        delete_option('acuity_integration_errors');
    }
}