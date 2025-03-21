<?php
/**
 * Exception Handler class
 */
class Acuity_Integration_Exception_Handler {
    /**
     * Logger instance
     *
     * @var Acuity_Integration_Logger
     */
    private $logger;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = new Acuity_Integration_Logger();
        
        // Register global exception handler
        set_exception_handler(array($this, 'handle_uncaught_exception'));
    }
    
    /**
     * Handle uncaught exceptions
     *
     * @param Throwable $exception The exception
     * @return void
     */
    public function handle_uncaught_exception($exception) {
        $this->log_exception($exception);
        
        // Only show detailed error messages to administrators
        if (current_user_can('manage_options') && WP_DEBUG) {
            $message = $this->get_formatted_exception($exception);
        } else {
            $message = __('An unexpected error occurred.', 'acuity-integration');
        }
        
        // If this is an AJAX request, send JSON response
        if (wp_doing_ajax()) {
            wp_send_json_error(array(
                'message' => $message
            ));
            exit;
        }
        
        // For admin pages, show admin notice
        if (is_admin()) {
            add_action('admin_notices', function() use ($message) {
                echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
            });
            return;
        }
        
        // For frontend, show graceful error
        wp_die($message, __('Error', 'acuity-integration'), array('response' => 500));
    }
    
    /**
     * Log an exception
     *
     * @param Throwable $exception The exception
     * @param bool $is_critical Whether this is a critical error
     * @return void
     */
    public function log_exception($exception, $is_critical = true) {
        $message = $this->get_formatted_exception($exception);
        
        // Get more details for logging
        $data = array(
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $this->get_sanitized_trace($exception),
            'type' => get_class($exception)
        );
        
        if ($is_critical) {
            $this->logger->error($message, $data);
        } else {
            $this->logger->warning($message, $data);
        }
    }
    
    /**
     * Format exception for display
     *
     * @param Throwable $exception The exception
     * @return string Formatted exception message
     */
    private function get_formatted_exception($exception) {
        $message = $exception->getMessage();
        
        // Add file and line information for admins when debugging
        if (current_user_can('manage_options') && WP_DEBUG) {
            $message .= ' in ' . $exception->getFile() . ' on line ' . $exception->getLine();
        }
        
        return $message;
    }
    
    /**
     * Get sanitized stack trace
     *
     * @param Throwable $exception The exception
     * @return array Sanitized stack trace
     */
    private function get_sanitized_trace($exception) {
        $trace = $exception->getTrace();
        $sanitized_trace = array();
        
        foreach ($trace as $i => $frame) {
            // Include only essential information
            $sanitized_frame = array(
                'file' => isset($frame['file']) ? $frame['file'] : '(unknown file)',
                'line' => isset($frame['line']) ? $frame['line'] : '(unknown line)',
                'function' => isset($frame['function']) ? $frame['function'] : '(unknown function)'
            );
            
            // Include class if available
            if (isset($frame['class'])) {
                $sanitized_frame['class'] = $frame['class'];
            }
            
            // Sanitize arguments to prevent sensitive data exposure
            if (isset($frame['args'])) {
                $sanitized_args = array();
                foreach ($frame['args'] as $j => $arg) {
                    if (is_scalar($arg)) {
                        $sanitized_args[$j] = $arg;
                    } elseif (is_array($arg)) {
                        $sanitized_args[$j] = '(array)';
                    } elseif (is_object($arg)) {
                        $sanitized_args[$j] = '(object) ' . get_class($arg);
                    } elseif (is_resource($arg)) {
                        $sanitized_args[$j] = '(resource)';
                    } else {
                        $sanitized_args[$j] = '(unknown)';
                    }
                }
                $sanitized_frame['args'] = $sanitized_args;
            }
            
            $sanitized_trace[$i] = $sanitized_frame;
        }
        
        return $sanitized_trace;
    }
}