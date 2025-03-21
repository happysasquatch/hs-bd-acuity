<?php
/**
 * Sync class for handling data synchronization with Acuity
 */
class Acuity_Integration_Sync {
    /**
     * API instance
     */
    private $api;
    
    /**
     * Logger instance
     */
    private $logger;
    
    /**
     * Options instance
     */
    private $options;
    
    /**
     * ACF instance
     */
    private $acf;
    
    /**
     * Exception handler
     */
    private $exception_handler;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->api = new Acuity_Integration_API();
        $this->logger = new Acuity_Integration_Logger();
        $this->options = new Acuity_Integration_Options();
        $this->acf = new Acuity_Integration_ACF();
        $this->exception_handler = new Acuity_Integration_Exception_Handler();
    }
    
    /**
     * Sync classes
     *
     * @return int Number of classes synced
     * @throws Exception If sync fails
     */
    public function sync_classes() {
        try {
            $this->logger->info('Starting class sync');
            
            // Get classes from API
            $classes = $this->get_classes_from_api();
            
            if (empty($classes)) {
                $this->logger->info('No classes found to sync');
                return 0;
            }
            
            $this->logger->info('Retrieved ' . count($classes) . ' classes from API');
            
            // Process classes
            $synced = $this->process_classes($classes);
            
            $this->logger->info('Completed class sync. Synced ' . $synced . ' classes');
            
            return $synced;
        } catch (Exception $e) {
            // Log the exception
            $this->exception_handler->log_exception($e);
            
            // Rethrow for caller to handle
            throw $e;
        }
    }
    
    /**
     * Get classes from the API
     *
     * @return array Classes from the API
     * @throws Acuity_Integration_API_Exception If API request fails
     */
    private function get_classes_from_api() {
        try {
            // Get max classes setting
            $max_classes = $this->options->get('max_classes', 100);
            
            // Set up query args
            $args = array(
                'min_date' => date('Y-m-d'),
                'max_date' => date('Y-m-d', strtotime('+3 months'))
            );
            
            // Get classes from API
            $classes = $this->api->get_classes($args);
            
            // Limit to max classes
            if (count($classes) > $max_classes) {
                $classes = array_slice($classes, 0, $max_classes);
            }
            
            return $classes;
        } catch (Exception $e) {
            $this->logger->error('Failed to get classes from API: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Process classes
     *
     * @param array $classes Classes to process
     * @return int Number of classes processed
     */
    private function process_classes($classes) {
        $count = 0;
        $errors = array();
        
        foreach ($classes as $class) {
            try {
                // Map class data to our format
                $mapped_class = $this->map_class_data($class);
                
                // Validate class data
                $this->validate_class_data($mapped_class);
                
                // Process the class
                $result = $this->process_single_class($mapped_class);
                
                if ($result) {
                    $count++;
                }
            } catch (Exception $e) {
                // Log the error but continue processing other classes
                $this->logger->warning('Error processing class: ' . $e->getMessage(), array(
                    'class_id' => isset($class['id']) ? $class['id'] : 'unknown'
                ));
                
                $errors[] = array(
                    'class_id' => isset($class['id']) ? $class['id'] : 'unknown',
                    'message' => $e->getMessage()
                );
            }
        }
        
        // If there were errors, log a summary
        if (!empty($errors)) {
            $this->logger->warning('Completed with errors', array(
                'error_count' => count($errors),
                'total' => count($classes),
                'errors' => $errors
            ));
        }
        
        return $count;
    }
    
    /**
     * Process a single class
     *
     * @param array $class Class data
     * @return bool Whether the class was processed successfully
     * @throws Exception If class processing fails
     */
    private function process_single_class($class) {
        try {
            // Check if class already exists
            $existing_class = $this->get_existing_class($class['class_id']);
            
            if ($existing_class) {
                // Update existing class
                $this->update_class($existing_class->ID, $class);
                $this->logger->debug('Updated class: ' . $class['class_id']);
                return true;
            } else {
                // Create new class
                $post_id = $this->create_class($class);
                $this->logger->debug('Created new class: ' . $class['class_id']);
                return true;
            }
        } catch (Exception $e) {
            $this->logger->error('Failed to process class: ' . $e->getMessage(), array(
                'class_id' => $class['class_id']
            ));
            throw $e;
        }
    }
    
    /**
     * Map class data from API to our format
     *
     * @param array $class Class data from API
     * @return array Mapped class data
     */
    private function map_class_data($class) {
        // Basic validation
        if (!isset($class['id'])) {
            throw new Acuity_Integration_Validation_Exception(
                __('Missing class ID', 'acuity-integration')
            );
        }
        
        // Build mapped class data
        $mapped_class = array(
            'class_id' => $class['id'],
            'class_name' => isset($class['name']) ? $class['name'] : '',
            'trainer' => isset($class['calendar']) ? $class['calendar'] : '',
            'duration' => isset($class['duration']) ? (int) $class['duration'] : 0,
            'series' => isset($class['isSeries']) ? (bool) $class['isSeries'] : false,
            'capacity' => isset($class['slots']) ? (int) $class['slots'] : 0,
            'available' => isset($class['slotsAvailable']) ? (int) $class['slotsAvailable'] : 0,
            'signups' => isset($class['slots']) && isset($class['slotsAvailable']) 
                ? ((int) $class['slots'] - (int) $class['slotsAvailable']) 
                : 0,
            'color' => isset($class['color']) ? $class['color'] : '',
            'price' => isset($class['price']) ? $class['price'] : '',
            'category' => isset($class['category']) ? $class['category'] : '',
            'description' => isset($class['description']) ? $class['description'] : '',
            'appointment_type_id' => isset($class['appointmentTypeID']) ? $class['appointmentTypeID'] : '',
            'datetime_full' => isset($class['time']) ? $class['time'] : '',
            'datetime_display' => isset($class['localeTime']) ? $class['localeTime'] : '',
        );
        
        // Process date and time
        if (!empty($mapped_class['datetime_full'])) {
            $datetime = new DateTime($mapped_class['datetime_full']);
            $mapped_class['weekday'] = $datetime->format('l');
            $mapped_class['date_only'] = $datetime->format('Y-m-d');
            $mapped_class['time_only'] = $datetime->format('g:i a');
        }
        
        // Build checkout and edit links
        $mapped_class['checkout_link'] = sprintf(
            'https://app.acuityscheduling.com/schedule.php?owner=%s&appointmentType=%s',
            $this->options->get_encrypted('user_id'),
            $mapped_class['appointment_type_id']
        );
        
        $mapped_class['edit_link'] = sprintf(
            'https://app.acuityscheduling.com/app/appointment.php?action=manage&id=%s',
            $mapped_class['class_id']
        );
        
        return $mapped_class;
    }
    
    /**
     * Validate class data
     *
     * @param array $class Class data
     * @throws Acuity_Integration_Validation_Exception If validation fails
     */
    private function validate_class_data($class) {
        $errors = array();
        
        // Required fields
        $required_fields = array(
            'class_id' => __('Class ID', 'acuity-integration'),
            'class_name' => __('Class Name', 'acuity-integration'),
            'datetime_full' => __('Date and Time', 'acuity-integration')
        );
        
        foreach ($required_fields as $field => $label) {
            if (empty($class[$field])) {
                $errors[$field] = sprintf(
                    __('%s is required', 'acuity-integration'),
                    $label
                );
            }
        }
        
        // If there are validation errors, throw exception
        if (!empty($errors)) {
            throw new Acuity_Integration_Validation_Exception(
                __('Class data validation failed', 'acuity-integration'),
                $errors
            );
        }
        
        return true;
    }
    
    /**
     * Get existing class by Acuity ID
     *
     * @param int $class_id Acuity class ID
     * @return WP_Post|null Existing class post or null
     */
    private function get_existing_class($class_id) {
        $args = array(
            'post_type' => 'acuity-class',
            'meta_key' => 'class_id',
            'meta_value' => $class_id,
            'posts_per_page' => 1
        );
        
        $query = new WP_Query($args);
        
        if (is_wp_error($query)) {
            $this->logger->error('Error querying for existing class: ' . $query->get_error_message());
            return null;
        }
        
        return $query->have_posts() ? $query->posts[0] : null;
    }
    
    /**
     * Create new class
     *
     * @param array $class Class data
     * @return int Post ID
     * @throws Exception If class creation fails
     */
    private function create_class($class) {
        try {
            // Get post status from options
            $post_status = $this->options->get('post_status', 'publish');
            
            // Process name based on category
            $name = $this->generate_post_title($class);
            
            $post_data = array(
                'post_title' => $name,
                'post_status' => $post_status,
                'post_type' => 'acuity-class',
                'post_date' => current_time('mysql')
            );
            
            // Add post content if available
            if (!empty($class['description'])) {
                $post_data['post_content'] = wp_kses_post($class['description']);
            }
    
            $post_id = wp_insert_post($post_data, true);
            
            if (is_wp_error($post_id)) {
                throw new Exception($post_id->get_error_message());
            }
    
            // Update class meta
            $this->update_class_meta($post_id, $class);
    
            // Set class type taxonomy
            $this->set_class_type_taxonomy($post_id, $class);
            
            // Add first session if it's a series
            if (isset($class['series']) && $class['series']) {
                $this->acf->add_session($post_id, $class['datetime_full']);
            }
            
            // Run action after class creation
            do_action('acuity_after_class_created', $post_id, $class);
            
            return $post_id;
        } catch (Exception $e) {
            $this->logger->error('Failed to create class: ' . $e->getMessage(), array(
                'class_data' => $this->sanitize_class_data($class)
            ));
            throw $e;
        }
    }
    
    /**
     * Update existing class
     *
     * @param int $post_id Post ID
     * @param array $class Class data
     * @return bool Whether update was successful
     * @throws Exception If class update fails
     */
    private function update_class($post_id, $class) {
        try {
            $post_data = array(
                'ID' => $post_id,
            );
            
            // Update title if changed
            $new_title = $this->generate_post_title($class);
            $current_title = get_the_title($post_id);
            
            if ($new_title !== $current_title) {
                $post_data['post_title'] = $new_title;
            }
            
            // Update content if changed
            if (!empty($class['description'])) {
                $current_content = get_post_field('post_content', $post_id);
                $new_content = wp_kses_post($class['description']);
                
                if ($new_content !== $current_content) {
                    $post_data['post_content'] = $new_content;
                }
            }
            
            // Only update post if there are changes
            if (count($post_data) > 1) {
                $result = wp_update_post($post_data, true);
                
                if (is_wp_error($result)) {
                    throw new Exception($result->get_error_message());
                }
            }
    
            // Always update meta
            $this->update_class_meta($post_id, $class);
    
            // Set class type taxonomy
            $this->set_class_type_taxonomy($post_id, $class);
            
            // Run action after class update
            do_action('acuity_after_class_updated', $post_id, $class);
            
            return true;
        } catch (Exception $e) {
            $this->logger->error('Failed to update class: ' . $e->getMessage(), array(
                'post_id' => $post_id,
                'class_data' => $this->sanitize_class_data($class)
            ));
            throw $e;
        }
    }
    
    /**
     * Update class meta
     *
     * @param int $post_id Post ID
     * @param array $class Class data
     * @return void
     */
    private function update_class_meta($post_id, $class) {
        // Update core meta field for ID reference
        update_post_meta($post_id, 'class_id', $class['class_id']);
        
        // Update ACF fields
        $this->acf->update_basic_class_fields($post_id, $class);
    }
    
    /**
     * Set class type taxonomy
     *
     * @param int $post_id Post ID
     * @param array $class Class data
     * @return void
     */
    private function set_class_type_taxonomy($post_id, $class) {
        // Skip if no category
        if (empty($class['category'])) {
            return;
        }
        
        // Check if term exists
        $term = term_exists($class['category'], 'acuity-class-type');
        
        // Create term if it doesn't exist
        if (empty($term)) {
            $term = wp_insert_term($class['category'], 'acuity-class-type');
            
            if (is_wp_error($term)) {
                $this->logger->warning('Failed to create class type term: ' . $term->get_error_message(), array(
                    'category' => $class['category']
                ));
                return;
            }
            
            $term_id = $term['term_id'];
        } else {
            $term_id = is_array($term) ? $term['term_id'] : $term;
        }
        
        // Set the term
        $result = wp_set_post_terms($post_id, array($term_id), 'acuity-class-type');
        
        if (is_wp_error($result)) {
            $this->logger->warning('Failed to set class type term: ' . $result->get_error_message(), array(
                'post_id' => $post_id,
                'term_id' => $term_id
            ));
        }
    }
    
    /**
     * Generate post title
     *
     * @param array $class Class data
     * @return string Post title
     */
    private function generate_post_title($class) {
        // Default to class name
        $title = $class['class_name'];
        
        // Add date if available
        if (!empty($class['datetime_display'])) {
            $title .= ' - ' . $class['datetime_display'];
        }
        
        return $title;
    }
    
    /**
     * Sanitize class data for logging
     *
     * @param array $class Class data
     * @return array Sanitized class data
     */
    private function sanitize_class_data($class) {
        // Create a copy to avoid modifying original
        $sanitized = $class;
        
        // Remove potentially sensitive fields or large data
        $sensitive_fields = array('checkout_link', 'edit_link');
        
        foreach ($sensitive_fields as $field) {
            if (isset($sanitized[$field])) {
                unset($sanitized[$field]);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Clear sync cache
     *
     * @return void
     */
    public function clear_cache() {
        $this->api->clear_cache();
    }
}