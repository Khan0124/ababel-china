<?php
// app/Core/helpers.php
// This file contains global helper functions

use App\Core\Language;

if (!function_exists('__')) {
    /**
     * Get translation for a key
     * @param string $key Translation key
     * @param array $params Parameters to replace
     * @return string
     */
    function __($key, $params = [])
    {
        return Language::getInstance()->get($key, $params);
    }
}

if (!function_exists('lang')) {
    /**
     * Get current language code
     * @return string
     */
    function lang()
    {
        return Language::getInstance()->getCurrentLanguage();
    }
}

if (!function_exists('isRTL')) {
    /**
     * Check if current language is RTL
     * @return bool
     */
    function isRTL()
    {
        return Language::getInstance()->isRTL();
    }
}

if (!function_exists('formatBalance')) {
    /**
     * Format balance for display - show debt as positive amount
     * @param float $balance Raw balance value from database
     * @param string $currency Currency code (RMB, USD, etc.)
     * @param bool $showCurrency Whether to show currency symbol
     * @return string Formatted balance string
     */
    function formatBalance($balance, $currency = 'RMB', $showCurrency = true)
    {
        $isDebt = $balance < 0; // Negative value = debt to company
        $amount = abs($balance); // Get absolute amount
        
        $symbol = '';
        if ($showCurrency) {
            switch (strtoupper($currency)) {
                case 'RMB': $symbol = '¥'; break;
                case 'USD': $symbol = '$'; break;
                case 'SDG': $symbol = 'SDG '; break;
                case 'AED': $symbol = 'AED '; break;
                default: $symbol = strtoupper($currency) . ' ';
            }
        }
        
        // Format the amount
        $formatted = $symbol . number_format($amount, 2);
        
        // Add clear debt/credit indicator
        if ($amount > 0) {
            if ($isDebt) {
                // العميل يدين للشركة - إظهار المبلغ مع كلمة "مستحق"
                $formatted = $formatted . ' ' . __('outstanding_debt');
            } else {
                // العميل له رصيد فائض - إظهار المبلغ مع كلمة "رصيد فائض"
                $formatted = $formatted . ' ' . __('credit');
            }
        } else {
            // الرصيد صفر
            $formatted = $symbol . '0.00';
        }
        
        return $formatted;
    }
}

if (!function_exists('getBalanceType')) {
    /**
     * Get balance type (debt, credit, or zero)
     * @param float $balance Raw balance value
     * @return string 'debt', 'credit', or 'zero'
     */
    function getBalanceType($balance)
    {
        if ($balance < 0) {
            return 'debt'; // Client owes money (negative balance = debt)
        } elseif ($balance > 0) {
            return 'credit'; // Client has credit (positive balance = credit)
        } else {
            return 'zero';
        }
    }
}

if (!function_exists('getOutstandingAmount')) {
    /**
     * Get outstanding amount for payment (always positive)
     * @param float $balance Raw balance value
     * @return float Outstanding amount (positive)
     */
    function getOutstandingAmount($balance)
    {
        return $balance < 0 ? abs($balance) : 0; // Only return debt amounts as positive
    }
}

if (!function_exists('canMakePayment')) {
    /**
     * Check if payment can be made for a balance
     * @param float $balance Raw balance value
     * @param float $paymentAmount Payment amount to check
     * @return bool Whether payment is valid
     */
    function canMakePayment($balance, $paymentAmount)
    {
        $outstanding = getOutstandingAmount($balance);
        return $outstanding >= $paymentAmount && $paymentAmount > 0;
    }
}

if (!function_exists('config')) {
    /**
     * Get configuration value
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function config($key, $default = null)
    {
        static $config = null;
        
        if ($config === null) {
            $config = require BASE_PATH . '/config/app.php';
        }
        
        return $config[$key] ?? $default;
    }
}

if (!function_exists('asset')) {
    /**
     * Generate asset URL
     * @param string $path
     * @return string
     */
    function asset($path)
    {
        return '/' . ltrim($path, '/');
    }
}

if (!function_exists('url')) {
    /**
     * Generate URL
     * @param string $path
     * @return string
     */
    function url($path = '')
    {
        $baseUrl = config('url', '');
        return $baseUrl . '/' . ltrim($path, '/');
    }
}

// Logging helper functions
if (!function_exists('logger')) {
    /**
     * Get logger instance
     */
    function logger() {
        return \App\Core\Logger::getInstance();
    }
}

if (!function_exists('log_info')) {
    function log_info($message, array $context = []) {
        logger()->info($message, $context);
    }
}

if (!function_exists('log_error')) {
    function log_error($message, array $context = []) {
        logger()->error($message, $context);
    }
}

if (!function_exists('log_warning')) {
    function log_warning($message, array $context = []) {
        logger()->warning($message, $context);
    }
}

if (!function_exists('log_debug')) {
    function log_debug($message, array $context = []) {
        logger()->debug($message, $context);
    }
}

if (!function_exists('log_activity')) {
    function log_activity($action, $userId = null, array $details = []) {
        logger()->activity($action, $userId, $details);
    }
}

if (!function_exists('log_security')) {
    function log_security($event, array $context = []) {
        logger()->security($event, $context);
    }
}