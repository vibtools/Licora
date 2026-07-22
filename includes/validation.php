<?php
class Validation {
    private $errors = [];
    
    // লাইসেন্স ডেটা ভ্যালিডেশন
    public function validateLicenseData($data) {
        $this->errors = [];
        
        if (!isset($data['hours']) || $data['hours'] < 1 || $data['hours'] > 8760) {
            $this->errors['hours'] = 'Hours must be between 1 and 8760';
        }
        
        if (!isset($data['device_limit']) || $data['device_limit'] < 1 || $data['device_limit'] > 100) {
            $this->errors['device_limit'] = 'Device limit must be between 1 and 100';
        }
        
        return empty($this->errors);
    }
    
    // লগইন ডেটা ভ্যালিডেশন
    public function validateLoginData($data) {
        $this->errors = [];
        
        if (empty($data['username'])) {
            $this->errors['username'] = 'Username is required';
        }
        
        if (empty($data['password'])) {
            $this->errors['password'] = 'Password is required';
        }
        
        if (strlen($data['password'] ?? '') < 6) {
            $this->errors['password'] = 'Password must be at least 6 characters';
        }
        
        return empty($this->errors);
    }
    
    // API রিকোয়েস্ট ভ্যালিডেশন
    public function validateAPIRequest($data) {
        $this->errors = [];
        
        if (empty($data['license_key'])) {
            $this->errors['license_key'] = 'License key is required';
        } elseif (!Security::validateLicenseFormat($data['license_key'])) {
            $this->errors['license_key'] = 'Invalid license key format';
        }
        
        return empty($this->errors);
    }
    
    // এরর গুলো রিটার্ন
    public function getErrors() {
        return $this->errors;
    }
    
    // সিঙ্গেল এরর গেট
    public function getError($field) {
        return $this->errors[$field] ?? null;
    }
    
    // হিউম্যান ফ্রেন্ডলি এরর মেসেজ
    public function getFormattedErrors() {
        $messages = [];
        foreach ($this->errors as $field => $error) {
            $messages[] = ucfirst($field) . ': ' . $error;
        }
        return implode('<br>', $messages);
    }
}
?>