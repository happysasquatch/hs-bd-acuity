<?php

class Acuity_Integration_ACF {
    /**
     * ACF field keys
     */
    private $fields = array(
        'class_id',
        'class_name',
        'trainer',
        'duration',
        'series',
        'capacity',
        'available',
        'signups',
        'color',
        'price',
        'category',
        'description',
        'appointment_type_id',
        'datetime_full',
        'datetime_display',
        'weekday',
        'date_only',
        'time_only',
        'class_sessions',
        'location',
        'checkout_link',
        'edit_link'
    );


    /**
     * Get field key by name
     */
    public function get_field_key($field) {
        return isset($this->fields[$field]) ? $this->fields[$field] : null;
    }

    public function update_basic_class_fields($post_id, $class_data) {
        foreach ($this->fields as $field) {
            if (isset($class_data[$field])) {
                update_field($field, $class_data[$field], $post_id);
            }

        }
    }

        /**
     * Add a session to the sessions repeater field
     */
    public function add_session($post_id, $session_time) {
        add_row($this->fields['class_sessions'], $session_time, $post_id);
    }

    /**
     * Get number of sessions for a class
     */
    public function get_session_count($post_id) {
        $sessions = get_field($this->fields['class_sessions'], $post_id);
        return is_array($sessions) ? count($sessions) : 0;
    }
    

}