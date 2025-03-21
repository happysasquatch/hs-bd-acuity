<?php
/**
 * Options class for managing plugin settings
 */
class Acuity_Integration_Options {
    /**
     * Option prefix
     */
    const PREFIX = 'acuity_';
    
    /**
     * Default options
     *
     * @var array
     */
    private $defaults = array(
        'user_id' => '',
        'api_key' => '',
        'sync_interval' => 'hourly',
        'cache_time' => 3600, // 1 hour in seconds
        'debug_mode' => 'no',
        'log_retention' => 30, // Days
        'auto_archive' => 'yes',
        'archive_days' => 7,
        'max_classes' => 100,
        'post_status' => 'publish'
    );
    
    /**
     * Get option
     *
     * @param string $key Option key without prefix
     * @param mixed $default Default value if option not found
     * @return mixed Option value
     */
    public function get($key, $default = null) {
        $option_name = self::PREFIX . $key;
        
        // If default is null, use the class default
        if ($default === null && isset($this->defaults[$key])) {
            $default = $this->defaults[$key];
        }
        
        return get_option($option_name, $default);
    }
    
    /**
     * Update option
     *
     * @param string $key Option key without prefix
     * @param mixed $value Option value
     * @return bool Whether option was updated
     */
    public function update($key, $value) {
        $option_name = self::PREFIX . $key;
        return update_option($option_name, $value);
    }
    
    /**
     * Delete option
     *
     * @param string $key Option key without prefix
     * @return bool Whether option was deleted
     */
    public function delete($key) {
        $option_name = self::PREFIX . $key;
        return delete_option($option_name);
    }
    
    /**
     * Get encrypted option
     *
     * @param string $key Option key without prefix
     * @return string Decrypted option value
     */
    public function get_encrypted($key) {
        $api = new Acuity_Integration_API();
        $encrypted_value = $this->get($key, '');
        return $api->decrypt($encrypted_value);
    }
    
    /**
     * Update encrypted option
     *
     * @param string $key Option key without prefix
     * @param string $value Value to encrypt and store
     * @return bool Whether option was updated
     */
    public function update_encrypted($key, $value) {
        $api = new Acuity_Integration_API();
        $encrypted_value = $api->encrypt($value);
        return $this->update($key, $encrypted_value);
    }
    
    /**
     * Get all options
     *
     * @return array All options
     */
    public function get_all() {
        $options = array();
        
        foreach (array_keys($this->defaults) as $key) {
            $options[$key] = $this->get($key);
        }
        
        return $options;
    }
    
    /**
     * Reset options to defaults
     *
     * @return void
     */
    public function reset() {
        foreach ($this->defaults as $key => $value) {
            $this->update($key, $value);
        }
    }
    
    /**
     * Get default value for an option
     *
     * @param string $key Option key
     * @return mixed Default value
     */
    public function get_default($key) {
        return isset($this->defaults[$key]) ? $this->defaults[$key] : null;
    }
    
    /**
     * Get all default options
     *
     * @return array Default options
     */
    public function get_defaults() {
        return $this->defaults;
    }
}