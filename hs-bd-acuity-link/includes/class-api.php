<?php
/**
 * API class for interacting with Acuity Scheduling API
 */
class Acuity_Integration_API {
    /**
     * User ID
     */
    private $user_id;
    
    /**
     * API Key
     */
    private $api_key;
    
    /**
     * API base URL
     */
    private $api_base = 'https://acuityscheduling.com/api/v1/';
    
    /**
     * Rate limit
     */
    private $rate_limit = 100;
    
    /**
     * Rate window (in seconds)
     */
    private $rate_window = 60;
    
    /**
     * Options instance
     */
    private $options;

    /**
     * Constructor
     */
    public function __construct() {
        $this->options = new Acuity_Integration_Options();
        $this->logger = new Acuity_Integration_Logger();
        
        // Get credentials
        $credentials = $this->get_credentials();
        $this->user_id = $credentials['user_id'];
        $this->api_key = $credentials['api_key'];
    }
    
    /**
     * Make an API request
     *
     * @param string $endpoint API endpoint
     * @param array $args Request arguments
     * @return array|object Response data
     */
    public function request($endpoint, $args = array()) {
        // Check rate limit before making request
        $this->check_rate_limit();
        
        // Build request URL
        $url = $this->api_base . ltrim($endpoint, '/');
        
        // Add query parameters
        if (!empty($args['query'])) {
            $url = add_query_arg($args['query'], $url);
        }
        
        // Set up request arguments
        $request_args = array(
            'headers' => array(
                'Authorization' => $this->get_auth_header(),
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        );
        
        // Add body for POST/PUT requests
        if (!empty($args['body'])) {
            $request_args['body'] = json_encode($args['body']);
        }
        
        // Set request method
        $method = isset($args['method']) ? strtoupper($args['method']) : 'GET';
        
        // Try to get from cache for GET requests
        $cache_key = 'acuity_api_' . md5($url . json_encode($request_args));
        $cache_time = $this->options->get('cache_time', 3600); // Default 1 hour
        
        if ($method === 'GET' && $cache_time > 0) {
            $cached_response = get_transient($cache_key);
            if ($cached_response !== false) {
                return $cached_response;
            }
        }
        
        // Make request

        
        $response = wp_remote_request($url, array_merge($request_args, array('method' => $method)));
        
        // Check for WordPress error
        if (is_wp_error($response)) {
            throw Acuity_Integration_API_Exception::from_wp_error($response);
        }
        
        // Get response code and body
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        // Parse JSON response
        $data = json_decode($body, true);
        
        // Handle JSON parsing error
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Acuity_Integration_API_Exception(
                sprintf(__('Failed to parse API response: %s', 'acuity-integration'), json_last_error_msg()),
                json_last_error()
            );
        }
        
        // Check for API error
        if ($status_code >= 400) {
            $error_message = isset($data['message']) ? $data['message'] : __('Unknown API error', 'acuity-integration');
            
            // Log the error with sanitized response data
            $sanitized_data = $this->sanitize_response_data($data);

        }
        
        // Cache successful GET responses
        if ($method === 'GET' && $cache_time > 0) {
            set_transient($cache_key, $data, $cache_time);
        }
        
        return $data;
    }
    
    /**
     * Test API connection
     *
     * @return bool True if connection successful
     * @throws Acuity_Integration_API_Exception If connection fails
     */
    public function test_connection() {
        try {
            $response = $this->request('me');
            return isset($response['id']);
        } catch (Exception $e) {
            $this->logger->error('Connection test failed: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get classes
     *
     * @param array $args Query arguments
     * @return array Classes
     * @throws Acuity_Integration_API_Exception If API request fails
     */
    public function get_classes($args = array()) {
        $query = array();
        
        // Set default min/max date range
        if (!isset($args['min_date'])) {
            $query['minDate'] = date('Y-m-d');
        } else {
            $query['minDate'] = $args['min_date'];
        }
        
        if (isset($args['max_date'])) {
            $query['maxDate'] = $args['max_date'];
        }
        
        // Include canceled classes
        if (isset($args['include_canceled']) && $args['include_canceled']) {
            $query['includeCanceled'] = 'true';
        }
        
        try {
            return $this->request('classes', array('query' => $query));
        } catch (Exception $e) {
            $this->logger->error('Failed to get classes: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get encryption key
     *
     * @return string Encryption key
     */
    private function get_encryption_key() {
        // Use defined key if available
        if (defined('ACUITY_ENCRYPTION_KEY')) {
            return ACUITY_ENCRYPTION_KEY;
        }
        
        // Use WordPress auth key as fallback
        if (defined('AUTH_KEY')) {
            return AUTH_KEY;
        }
        
        // Last resort: use site URL with salt
        return hash('sha256', site_url() . 'acuity_integration_salt');
    }
    
    /**
     * Encrypt a value
     *
     * @param string $value Value to encrypt
     * @return string Encrypted value
     */
    public function encrypt($value) {
        if (empty($value)) {
            return '';
        }
        
        $key = $this->get_encryption_key();
        $method = 'aes-256-cbc';
        $iv_length = openssl_cipher_iv_length($method);
        $iv = openssl_random_pseudo_bytes($iv_length);
        
        $encrypted = openssl_encrypt(
            $value,
            $method,
            $key,
            0,
            $iv
        );
        
        if ($encrypted === false) {
            $this->logger->error('Encryption failed: ' . openssl_error_string());
            return '';
        }
        
        // Add HMAC for authentication
        $hmac = hash_hmac('sha256', $iv . $encrypted, $key, true);
        
        // Combine HMAC + IV + encrypted data
        return base64_encode($hmac . $iv . $encrypted);
    }
    
    /**
     * Decrypt a value
     *
     * @param string $encrypted_value Encrypted value
     * @return string Decrypted value
     */
    public function decrypt($encrypted_value) {
        if (empty($encrypted_value)) {
            return '';
        }
        
        $key = $this->get_encryption_key();
        $method = 'aes-256-cbc';
        $iv_length = openssl_cipher_iv_length($method);
        
        $decoded = base64_decode($encrypted_value);
        if ($decoded === false) {
            $this->logger->warning('Failed to base64 decode encrypted value');
            return '';
        }
        
        // Get HMAC length (SHA256 = 32 bytes)
        $hmac_length = 32;
        
        // Ensure decoded string is long enough
        $min_length = $hmac_length + $iv_length;
        if (strlen($decoded) < $min_length) {
            $this->logger->warning('Encrypted value is too short');
            return '';
        }
        
        // Extract HMAC, IV and encrypted data
        $hmac = substr($decoded, 0, $hmac_length);
        $iv = substr($decoded, $hmac_length, $iv_length);
        $encrypted_data = substr($decoded, $hmac_length + $iv_length);
        
        // Verify HMAC before decrypting
        $calculated_hmac = hash_hmac('sha256', $iv . $encrypted_data, $key, true);
        if (!hash_equals($hmac, $calculated_hmac)) {
            $this->logger->error('HMAC verification failed');
            return '';
        }
        
        $decrypted = openssl_decrypt(
            $encrypted_data,
            $method,
            $key,
            0,
            $iv
        );
        
        if ($decrypted === false) {
            $this->logger->error('Decryption failed: ' . openssl_error_string());
            return '';
        }
        
        return $decrypted;
    }
    
    /**
     * Get API credentials
     *
     * @return array API credentials
     */
    private function get_credentials() {
        // Check for constants first
        if (defined('ACUITY_USER_ID') && defined('ACUITY_API_KEY')) {
            return array(
                'user_id' => ACUITY_USER_ID,
                'api_key' => ACUITY_API_KEY
            );
        }
        
        // Get credentials from options
        return array(
            'user_id' => $this->options->get_encrypted('user_id'),
            'api_key' => $this->options->get_encrypted('api_key')
        );
    }
    
    /**
     * Get authentication header
     *
     * @return string Authentication header
     */
    private function get_auth_header() {
        return 'Basic ' . base64_encode($this->user_id . ':' . $this->api_key);
    }
    
    /**
     * Check rate limit
     *
     * @throws Acuity_Integration_API_Exception If rate limit exceeded
     */
    private function check_rate_limit() {
        $transient_key = 'acuity_rate_limit_' . md5($this->user_id);
        $requests = get_transient($transient_key);
        
        if ($requests === false) {
            set_transient($transient_key, 1, $this->rate_window);
            return;
        }
        
        if ($requests >= $this->rate_limit) {
            throw new Acuity_Integration_API_Exception(
                __('Rate limit exceeded. Please try again later.', 'acuity-integration'),
                429
            );
        }
        
        set_transient($transient_key, $requests + 1, $this->rate_window);
    }
    
    /**
     * Sanitize response data for logging
     *
     * @param array $data Response data
     * @return array Sanitized data
     */
    private function sanitize_response_data($data) {
        if (!is_array($data)) {
            return $data;
        }
        
        $sanitized = array();
        
        foreach ($data as $key => $value) {
            // Remove potentially sensitive fields
            if (in_array($key, array('api_key', 'apiKey', 'password', 'token', 'secret'))) {
                $sanitized[$key] = '***REDACTED***';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitize_response_data($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Clear API cache
     */
    public function clear_cache() {
        global $wpdb;
        
        // Clear all API transients
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $wpdb->options WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_acuity_api_%',
                '_transient_timeout_acuity_api_%'
            )
        );
    }
} 