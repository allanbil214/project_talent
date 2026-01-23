<?php
// classes/Validator.php

class Validator {
    private $errors = [];
    private $data = [];
    
    public function __construct($data = []) {
        $this->data = $data;
    }
    
    /**
     * Validate data against rules
     */
    public function validate($rules) {
        foreach ($rules as $field => $rule_set) {
            $rules_array = is_array($rule_set) ? $rule_set : explode('|', $rule_set);
            
            foreach ($rules_array as $rule) {
                $this->applyRule($field, $rule);
            }
        }
        
        return empty($this->errors);
    }
    
    /**
     * Apply single rule
     */
    private function applyRule($field, $rule) {
        $value = $this->data[$field] ?? null;
        
        // Required
        if ($rule === 'required' && empty($value)) {
            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . ' is required');
            return;
        }
        
        // Skip other validations if field is empty and not required
        if (empty($value)) {
            return;
        }
        
        // Email
        if ($rule === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, 'Invalid email format');
        }
        
        // Min length
        if (strpos($rule, 'min:') === 0) {
            $min = (int) substr($rule, 4);
            if (strlen($value) < $min) {
                $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . " must be at least {$min} characters");
            }
        }
        
        // Max length
        if (strpos($rule, 'max:') === 0) {
            $max = (int) substr($rule, 4);
            if (strlen($value) > $max) {
                $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . " must not exceed {$max} characters");
            }
        }
        
        // Numeric
        if ($rule === 'numeric' && !is_numeric($value)) {
            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . ' must be a number');
        }
        
        // Integer
        if ($rule === 'integer' && !filter_var($value, FILTER_VALIDATE_INT)) {
            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . ' must be an integer');
        }
        
        // Alpha
        if ($rule === 'alpha' && !ctype_alpha(str_replace(' ', '', $value))) {
            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . ' must contain only letters');
        }
        
        // Alphanumeric
        if ($rule === 'alphanumeric' && !ctype_alnum(str_replace(' ', '', $value))) {
            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . ' must contain only letters and numbers');
        }
        
        // URL
        if ($rule === 'url' && !filter_var($value, FILTER_VALIDATE_URL)) {
            $this->addError($field, 'Invalid URL format');
        }
        
        // Date
        if ($rule === 'date') {
            $d = \DateTime::createFromFormat('Y-m-d', $value);
            if (!$d || $d->format('Y-m-d') !== $value) {
                $this->addError($field, 'Invalid date format (YYYY-MM-DD required)');
            }
        }
        
        // Confirmed (password confirmation)
        if ($rule === 'confirmed') {
            $confirmation_field = $field . '_confirmation';
            if (!isset($this->data[$confirmation_field]) || $value !== $this->data[$confirmation_field]) {
                $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . ' confirmation does not match');
            }
        }
        
        // In array
        if (strpos($rule, 'in:') === 0) {
            $allowed = explode(',', substr($rule, 3));
            if (!in_array($value, $allowed)) {
                $this->addError($field, 'Invalid value for ' . str_replace('_', ' ', $field));
            }
        }
        
        // Between
        if (strpos($rule, 'between:') === 0) {
            list($min, $max) = explode(',', substr($rule, 8));
            if ($value < $min || $value > $max) {
                $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . " must be between {$min} and {$max}");
            }
        }
    }
    
    /**
     * Add custom validation rule
     */
    public function custom($field, $callback, $message) {
        $value = $this->data[$field] ?? null;
        
        if (!$callback($value)) {
            $this->addError($field, $message);
        }
        
        return $this;
    }
    
    /**
     * Add error
     */
    private function addError($field, $message) {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }
    
    /**
     * Check if validation passed
     */
    public function passes() {
        return empty($this->errors);
    }
    
    /**
     * Check if validation failed
     */
    public function fails() {
        return !empty($this->errors);
    }
    
    /**
     * Get all errors
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * Get first error for a field
     */
    public function getError($field) {
        return isset($this->errors[$field]) ? $this->errors[$field][0] : null;
    }
    
    /**
     * Get all error messages as flat array
     */
    public function getErrorMessages() {
        $messages = [];
        foreach ($this->errors as $field => $field_errors) {
            $messages = array_merge($messages, $field_errors);
        }
        return $messages;
    }
}