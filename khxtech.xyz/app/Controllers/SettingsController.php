<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\User;

class SettingsController extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }
    
    public function index()
    {
        // Get current settings
        $db = \App\Core\Database::getInstance();
        $stmt = $db->query("SELECT * FROM settings");
        $settingsRaw = $stmt->fetchAll();
        
        $settings = [];
        foreach ($settingsRaw as $setting) {
            $settings[$setting['setting_key']] = $setting['setting_value'];
        }
        
        // Get exchange rates
        $exchangeRates = [
            'USD_RMB' => $settings['exchange_rate_usd_rmb'] ?? '7.20',
            'SDG_RMB' => $settings['exchange_rate_sdg_rmb'] ?? '0.012',
            'AED_RMB' => $settings['exchange_rate_aed_rmb'] ?? '1.96'
        ];
        
        $this->view('settings/index', [
            'title' => __('settings.title'),
            'settings' => $settings,
            'exchangeRates' => $exchangeRates
        ]);
    }
    
    public function save()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/settings');
        }
        
        $db = \App\Core\Database::getInstance();
        
        try {
            // Update exchange rates
            $this->updateSetting($db, 'exchange_rate_usd_rmb', $_POST['exchange_rate_usd_rmb'] ?? '7.20');
            $this->updateSetting($db, 'exchange_rate_sdg_rmb', $_POST['exchange_rate_sdg_rmb'] ?? '0.012');
            $this->updateSetting($db, 'exchange_rate_aed_rmb', $_POST['exchange_rate_aed_rmb'] ?? '1.96');
            
            // Update company settings if provided
            if (isset($_POST['company_name'])) {
                $this->updateSetting($db, 'company_name', $_POST['company_name']);
            }
            
            if (isset($_POST['company_address'])) {
                $this->updateSetting($db, 'company_address', $_POST['company_address']);
            }
            
            if (isset($_POST['company_phone'])) {
                $this->updateSetting($db, 'company_phone', $_POST['company_phone']);
            }
            
            // Change password if requested
            if (!empty($_POST['current_password']) && !empty($_POST['new_password'])) {
                $userModel = new User();
                $user = $userModel->find($_SESSION['user_id']);
                
                if (password_verify($_POST['current_password'], $user['password'])) {
                    if ($_POST['new_password'] === $_POST['confirm_password']) {
                        $userModel->changePassword($_SESSION['user_id'], $_POST['new_password']);
                        $message = __('settings.password_changed');
                    } else {
                        throw new \Exception(__('validation.password_mismatch'));
                    }
                } else {
                    throw new \Exception(__('validation.invalid_password'));
                }
            }
            
            $this->redirect('/settings?success=' . urlencode(__('messages.saved_successfully')));
            
        } catch (\Exception $e) {
            $this->redirect('/settings?error=' . urlencode($e->getMessage()));
        }
    }
    
    private function updateSetting($db, $key, $value)
    {
        $sql = "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
        $db->query($sql, [$key, $value]);
    }
    
    public function backup()
    {
        // Check if user is admin
        if ($_SESSION['user_role'] !== 'admin') {
            $this->redirect('/settings?error=' . urlencode(__('messages.access_denied')));
        }
        
        $db = \App\Core\Database::getInstance();
        $tables = ['clients', 'transactions', 'cashbox_movements', 'users', 'settings'];
        
        $backup = "-- China Office Accounting System Backup\n";
        $backup .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
        
        foreach ($tables as $table) {
            $backup .= "-- Table: $table\n";
            $backup .= "DELETE FROM $table;\n";
            
            $stmt = $db->query("SELECT * FROM $table");
            $rows = $stmt->fetchAll();
            
            foreach ($rows as $row) {
                $columns = array_keys($row);
                $values = array_map(function($val) use ($db) {
                    return $db->getConnection()->quote($val);
                }, array_values($row));
                
                $backup .= "INSERT INTO $table (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ");\n";
            }
            $backup .= "\n";
        }
        
        $filename = 'backup_' . date('Y-m-d_His') . '.sql';
        
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $backup;
        exit;
    }
}