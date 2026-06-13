<?php
/**
 * Validation Helper Functions
 * Real Estate Receivable System
 * 
 * Enhanced validation functions for data quality and security
 */

/**
 * Enhanced email validation with optional MX record check
 * 
 * @param string $email Email address to validate
 * @param bool $strict Enable MX record check (default: false)
 * @return bool True if valid, false otherwise
 */
function validate_email($email, $strict = false) {
    // Basic format validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    
    // Optional: Check if domain has MX records (only if $strict is true)
    if ($strict) {
        $domain = substr(strrchr($email, "@"), 1);
        
        // Check if domain has MX or A records
        if (!checkdnsrr($domain, "MX") && !checkdnsrr($domain, "A")) {
            return false;
        }
    }
    
    return true;
}

/**
 * Validate Philippine phone number format
 * Accepts: 09XXXXXXXXX or +639XXXXXXXXX
 * 
 * @param string $phone Phone number to validate
 * @param bool $required If false, allows empty values (default: true)
 * @return bool True if valid, false otherwise
 */
function validate_philippine_phone($phone, $required = true) {
    // Allow empty if not required
    if (!$required && empty($phone)) {
        return true;
    }
    
    // Remove all non-digit characters except +
    $cleaned = preg_replace('/[^0-9+]/', '', $phone);
    
    // Check valid Philippine mobile number formats
    // Format 1: 09XXXXXXXXX (11 digits starting with 09)
    // Format 2: +639XXXXXXXXX (13 chars starting with +639)
    $pattern = '/^(09\d{9}|\+639\d{9})$/';
    
    return preg_match($pattern, $cleaned) === 1;
}

/**
 * Normalize Philippine phone number to standard format
 * 
 * @param string $phone Phone number to normalize
 * @param string $format Output format: 'compact' (09XX), 'separated' (09XX-XXX-XXXX), 'international' (+639XX)
 * @return string|null Normalized phone number or null if invalid
 */
function normalize_phone($phone, $format = 'compact') {
    if (!validate_philippine_phone($phone, false)) {
        return null;
    }
    
    // Remove all non-digit characters except +
    $cleaned = preg_replace('/[^0-9+]/', '', $phone);
    
    // Convert to 09 format (remove +63 if present)
    if (substr($cleaned, 0, 3) === '+63') {
        $cleaned = '0' . substr($cleaned, 3);
    }
    
    // Apply format
    switch ($format) {
        case 'separated':
            // 09XX-XXX-XXXX
            return substr($cleaned, 0, 4) . '-' . substr($cleaned, 4, 3) . '-' . substr($cleaned, 7);
        case 'international':
            // +639XXXXXXXXX
            return '+63' . substr($cleaned, 1);
        case 'compact':
        default:
            // 09XXXXXXXXX
            return $cleaned;
    }
}

/**
 * Validate that date is not in the future
 * 
 * @param string $date Date string in Y-m-d format
 * @param string $field_name Field name for error message (optional)
 * @return array ['valid' => bool, 'error' => string|null]
 */
function validate_date_not_future($date, $field_name = 'Date') {
    $result = ['valid' => true, 'error' => null];
    
    // Parse date
    $date_obj = DateTime::createFromFormat('Y-m-d', $date);
    
    if (!$date_obj) {
        $result['valid'] = false;
        $result['error'] = "{$field_name} is not a valid date.";
        return $result;
    }
    
    // Check if future
    $today = new DateTime();
    if ($date_obj > $today) {
        $result['valid'] = false;
        $result['error'] = "{$field_name} cannot be in the future.";
        return $result;
    }
    
    return $result;
}

/**
 * Validate date range
 * 
 * @param string $start_date Start date in Y-m-d format
 * @param string $end_date End date in Y-m-d format
 * @param bool $allow_same Allow start and end to be same date (default: true)
 * @return array ['valid' => bool, 'error' => string|null]
 */
function validate_date_range($start_date, $end_date, $allow_same = true) {
    $result = ['valid' => true, 'error' => null];
    
    $start = DateTime::createFromFormat('Y-m-d', $start_date);
    $end = DateTime::createFromFormat('Y-m-d', $end_date);
    
    if (!$start || !$end) {
        $result['valid'] = false;
        $result['error'] = 'Invalid date format.';
        return $result;
    }
    
    if ($allow_same) {
        if ($start > $end) {
            $result['valid'] = false;
            $result['error'] = 'Start date must be before or equal to end date.';
        }
    } else {
        if ($start >= $end) {
            $result['valid'] = false;
            $result['error'] = 'Start date must be before end date.';
        }
    }
    
    return $result;
}

/**
 * Validate decimal/money amount
 * 
 * @param mixed $amount Amount to validate
 * @param float $min Minimum value (default: 0.01)
 * @param float $max Maximum value (default: 999999999.99)
 * @param int $decimals Number of decimal places allowed (default: 2)
 * @return array ['valid' => bool, 'error' => string|null, 'value' => float|null]
 */
function validate_amount($amount, $min = 0.01, $max = 999999999.99, $decimals = 2) {
    $result = ['valid' => true, 'error' => null, 'value' => null];
    
    // Check if numeric
    if (!is_numeric($amount)) {
        $result['valid'] = false;
        $result['error'] = 'Amount must be a valid number.';
        return $result;
    }
    
    $amount = floatval($amount);
    $result['value'] = $amount;
    
    // Check range
    if ($amount < $min) {
        $result['valid'] = false;
        $result['error'] = "Amount must be at least ₱" . number_format($min, 2) . ".";
        return $result;
    }
    
    if ($amount > $max) {
        $result['valid'] = false;
        $result['error'] = "Amount cannot exceed ₱" . number_format($max, 2) . ".";
        return $result;
    }
    
    // Check decimal places
    $parts = explode('.', (string)$amount);
    if (isset($parts[1]) && strlen($parts[1]) > $decimals) {
        $result['valid'] = false;
        $result['error'] = "Amount cannot have more than {$decimals} decimal places.";
        return $result;
    }
    
    return $result;
}

/**
 * Validate string length
 * 
 * @param string $value String to validate
 * @param int $min Minimum length
 * @param int $max Maximum length
 * @param string $field_name Field name for error message
 * @return array ['valid' => bool, 'error' => string|null]
 */
function validate_length($value, $min, $max, $field_name = 'Field') {
    $result = ['valid' => true, 'error' => null];
    
    $length = mb_strlen($value);
    
    if ($length < $min) {
        $result['valid'] = false;
        $result['error'] = "{$field_name} must be at least {$min} characters.";
    } elseif ($length > $max) {
        $result['valid'] = false;
        $result['error'] = "{$field_name} cannot exceed {$max} characters.";
    }
    
    return $result;
}

/**
 * Sanitize and validate integer within range
 * 
 * @param mixed $value Value to validate
 * @param int $min Minimum value
 * @param int $max Maximum value
 * @param string $field_name Field name for error message
 * @return array ['valid' => bool, 'error' => string|null, 'value' => int|null]
 */
function validate_integer($value, $min, $max, $field_name = 'Value') {
    $result = ['valid' => true, 'error' => null, 'value' => null];
    
    if (!is_numeric($value)) {
        $result['valid'] = false;
        $result['error'] = "{$field_name} must be a number.";
        return $result;
    }
    
    $int_value = intval($value);
    $result['value'] = $int_value;
    
    if ($int_value < $min || $int_value > $max) {
        $result['valid'] = false;
        $result['error'] = "{$field_name} must be between {$min} and {$max}.";
    }
    
    return $result;
}

/**
 * Check if value exists in allowed options
 * 
 * @param mixed $value Value to check
 * @param array $allowed_values Array of allowed values
 * @param string $field_name Field name for error message
 * @return array ['valid' => bool, 'error' => string|null]
 */
function validate_in_list($value, array $allowed_values, $field_name = 'Value') {
    $result = ['valid' => true, 'error' => null];
    
    if (!in_array($value, $allowed_values, true)) {
        $result['valid'] = false;
        $result['error'] = "{$field_name} is not a valid option.";
    }
    
    return $result;
}

/**
 * Validate required field
 * 
 * @param mixed $value Value to check
 * @param string $field_name Field name for error message
 * @return array ['valid' => bool, 'error' => string|null]
 */
function validate_required($value, $field_name = 'Field') {
    $result = ['valid' => true, 'error' => null];
    
    if (empty($value) && $value !== '0' && $value !== 0) {
        $result['valid'] = false;
        $result['error'] = "{$field_name} is required.";
    }
    
    return $result;
}
