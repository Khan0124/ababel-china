<?php
// app/Controllers/LanguageController.php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Language;

class LanguageController extends Controller
{
    public function change()
    {
        $lang = $_GET['lang'] ?? 'ar';
        $validLanguages = ['ar', 'en'];
        
        if (in_array($lang, $validLanguages)) {
            Language::getInstance()->setLanguage($lang);
        }
        
        // Redirect back to previous page or home
        $referrer = $_SERVER['HTTP_REFERER'] ?? '/';
        $this->redirect($referrer);
    }
}