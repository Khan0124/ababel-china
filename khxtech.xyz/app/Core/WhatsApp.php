<?php
// app/Core/WhatsApp.php
namespace App\Core;

class WhatsApp
{
    /**
     * Country codes mapping
     * Format: 'country_code' => ['digits' => expected_digits_without_country_code, 'name' => 'Country Name']
     */
    private static $countryCodes = [
        '249' => ['digits' => 9, 'name' => 'Sudan'],
        '20' => ['digits' => 10, 'name' => 'Egypt'],
        '971' => ['digits' => 9, 'name' => 'UAE'],
        '86' => ['digits' => 11, 'name' => 'China'],
        '255' => ['digits' => 9, 'name' => 'Tanzania'],
        '966' => ['digits' => 9, 'name' => 'Saudi Arabia'],
        '974' => ['digits' => 8, 'name' => 'Qatar'],
        '968' => ['digits' => 8, 'name' => 'Oman'],
        '965' => ['digits' => 8, 'name' => 'Kuwait'],
        '973' => ['digits' => 8, 'name' => 'Bahrain'],
        '218' => ['digits' => 9, 'name' => 'Libya'],
        '216' => ['digits' => 8, 'name' => 'Tunisia'],
        '212' => ['digits' => 9, 'name' => 'Morocco'],
        '213' => ['digits' => 9, 'name' => 'Algeria'],
        '963' => ['digits' => 9, 'name' => 'Syria'],
        '961' => ['digits' => 8, 'name' => 'Lebanon'],
        '962' => ['digits' => 9, 'name' => 'Jordan'],
        '964' => ['digits' => 10, 'name' => 'Iraq'],
        '967' => ['digits' => 9, 'name' => 'Yemen'],
        '254' => ['digits' => 9, 'name' => 'Kenya'],
        '256' => ['digits' => 9, 'name' => 'Uganda'],
        '251' => ['digits' => 9, 'name' => 'Ethiopia'],
        '252' => ['digits' => 8, 'name' => 'Somalia']
    ];
    
    /**
     * Detect country code from phone number
     */
    private static function detectCountryCode($phone)
    {
        // Check if phone already has a country code
        foreach (self::$countryCodes as $code => $info) {
            if (str_starts_with($phone, $code)) {
                return $code;
            }
        }
        
        // Try to detect based on phone length
        $phoneLength = strlen($phone);
        
        // If starts with 0, remove it and check length
        if (str_starts_with($phone, '0')) {
            $phoneWithoutZero = substr($phone, 1);
            $lengthWithoutZero = strlen($phoneWithoutZero);
            
            // Check which country matches this length
            foreach (self::$countryCodes as $code => $info) {
                if ($info['digits'] == $lengthWithoutZero) {
                    // Default to Sudan if multiple matches (can be customized)
                    if ($code == '249') {
                        return $code;
                    }
                }
            }
        }
        
        // Default to Sudan if cannot detect
        return '249';
    }
    
    /**
     * Get country name from code
     */
    public static function getCountryName($countryCode)
    {
        return self::$countryCodes[$countryCode]['name'] ?? 'Unknown';
    }
    
    /**
     * Send receipt via WhatsApp using WhatsApp Business API
     * You can use CallMeBot or WhatsApp Business API
     */
    public static function sendReceipt($phone, $message, $countryCode = null)
    {
        // Remove any non-numeric characters from phone
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // If country code is not provided, try to detect it
        if (!$countryCode) {
            $countryCode = self::detectCountryCode($phone);
        }
        
        // Handle phone number formatting based on country
        if (str_starts_with($phone, '0')) {
            // Remove leading 0
            $phone = substr($phone, 1);
        }
        
        // If phone doesn't already have country code, add it
        if (!str_starts_with($phone, $countryCode)) {
            $phone = $countryCode . $phone;
        }
        
        // Validate phone number length
        $expectedLength = strlen($countryCode) + (self::$countryCodes[$countryCode]['digits'] ?? 9);
        if (strlen($phone) != $expectedLength) {
            // Log warning but continue
            error_log("WhatsApp: Phone number length mismatch. Expected: $expectedLength, Got: " . strlen($phone));
        }
        
        // Method 1: Using WhatsApp URL Scheme (opens WhatsApp with pre-filled message)
        $whatsappUrl = "https://wa.me/{$phone}?text=" . urlencode($message);
        
        return $whatsappUrl;
    }
    
    /**
     * Format phone number for display
     */
    public static function formatPhoneNumber($phone, $includeCountryName = false)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        $countryCode = self::detectCountryCode($phone);
        
        if (!str_starts_with($phone, $countryCode)) {
            if (str_starts_with($phone, '0')) {
                $phone = $countryCode . substr($phone, 1);
            } else {
                $phone = $countryCode . $phone;
            }
        }
        
        $formatted = '+' . $phone;
        
        if ($includeCountryName) {
            $formatted .= ' (' . self::getCountryName($countryCode) . ')';
        }
        
        return $formatted;
    }
    
    /**
     * Format receipt message for WhatsApp
     */
    public static function formatReceiptMessage($transaction, $client, $company = [])
    {
        $companyName = $company['name'] ?? __('company_name');
        $date = date('Y-m-d', strtotime($transaction['transaction_date']));
        
        $message = "🧾 *{$companyName}*\n";
        $message .= "━━━━━━━━━━━━━━━\n";
        $message .= "📋 *" . __('transactions.transaction_no') . ":* {$transaction['transaction_no']}\n";
        $message .= "📅 *" . __('date') . ":* {$date}\n";
        $message .= "👤 *" . __('clients.name') . ":* {$client['name']}\n\n";
        
        if ($transaction['invoice_no']) {
            $message .= "🔢 *" . __('transactions.invoice_no') . ":* {$transaction['invoice_no']}\n";
        }
        
        $message .= "\n💰 *" . __('transactions.total_amount') . ":*\n";
        
        if ($transaction['total_amount_rmb'] > 0) {
            $message .= "• RMB: ¥" . number_format($transaction['total_amount_rmb'], 2) . "\n";
        }
        
        if ($transaction['shipping_usd'] > 0) {
            $message .= "• USD: $" . number_format($transaction['shipping_usd'], 2) . "\n";
        }
        
        $message .= "\n💳 *" . __('transactions.payment') . ":*\n";
        
        if ($transaction['payment_rmb'] > 0) {
            $message .= "• RMB: ¥" . number_format($transaction['payment_rmb'], 2) . "\n";
        }
        
        if ($transaction['payment_usd'] > 0) {
            $message .= "• USD: $" . number_format($transaction['payment_usd'], 2) . "\n";
        }
        
        if ($transaction['payment_sdg'] > 0) {
            $message .= "• SDG: " . number_format($transaction['payment_sdg'], 2) . "\n";
        }
        
        $message .= "\n📊 *" . __('balance') . ":*\n";
        
        if ($transaction['balance_rmb'] != 0) {
            $message .= "• RMB: ¥" . number_format($transaction['balance_rmb'], 2) . "\n";
        }
        
        if ($transaction['balance_usd'] != 0) {
            $message .= "• USD: $" . number_format($transaction['balance_usd'], 2) . "\n";
        }
        
        $message .= "\n━━━━━━━━━━━━━━━\n";
        $message .= "✅ " . __('messages.saved_successfully') . "\n";
        $message .= "📞 " . ($company['phone'] ?? '') . "\n";
        
        return $message;
    }
}

// Helper function for WhatsApp sharing
function whatsapp_share_url($phone, $message, $countryCode = null)
{
    return \App\Core\WhatsApp::sendReceipt($phone, $message, $countryCode);
}