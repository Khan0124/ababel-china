<?php
// app/Views/layouts/header.php
// Load Language class if not already loaded
if (!class_exists('App\Core\Language')) {
    require_once BASE_PATH . '/app/Core/Language.php';
}

// Load helpers if not already loaded
if (!function_exists('__')) {
    $helpersFile = BASE_PATH . '/app/Core/helpers.php';
    if (file_exists($helpersFile)) {
        require_once $helpersFile;
    } else {
        // Define minimal functions if helpers file doesn't exist
        function __($key, $params = []) { return $key; }
        function lang() { return $_SESSION['lang'] ?? 'ar'; }
        function isRTL() { return in_array(lang(), ['ar', 'fa', 'he', 'ur']); }
    }
}

$lang = \App\Core\Language::getInstance();
$isRTL = isRTL(); // Use the global function
$currentLang = lang(); // Use the global function
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>" dir="<?= $isRTL ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? __('app_name') ?> - <?= __('company_name') ?></title>
    
    <!-- Bootstrap CSS - RTL or LTR based on language -->
    <?php if ($isRTL): ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <?php else: ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <?php endif; ?>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    
    <!-- Language-specific styling -->
    <style>
        :root {
            --font-family: <?= $isRTL ? "'Segoe UI', Tahoma, 'Arial Unicode MS', Arial" : "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif" ?>;
        }
        
        body {
            font-family: var(--font-family);
            direction: <?= $isRTL ? 'rtl' : 'ltr' ?>;
        }
        
        /* Adjust icons for RTL */
        <?php if ($isRTL): ?>
        .bi-arrow-left::before { content: "\f12f"; }
        .bi-arrow-right::before { content: "\f130"; }
        .dropdown-menu { text-align: right; }
        <?php endif; ?>
        
        /* Notification badge style */
        .navbar-nav .badge {
            position: absolute;
            top: 5px;
            <?= $isRTL ? 'left' : 'right' ?>: 5px;
            font-size: 0.75rem;
            padding: 0.25rem 0.4rem;
        }
        
        .nav-item {
            position: relative;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="/">
                <i class="bi bi-building"></i> <?= __('app_name') ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav <?= $isRTL ? 'me-auto' : 'ms-auto' ?>">
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/dashboard') !== false ? 'active' : '' ?>" href="/dashboard">
                            <i class="bi bi-speedometer2"></i> <?= __('nav.dashboard') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/clients') !== false ? 'active' : '' ?>" href="/clients">
                            <i class="bi bi-people"></i> <?= __('nav.clients') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/transactions') !== false ? 'active' : '' ?>" href="/transactions">
                            <i class="bi bi-receipt"></i> <?= __('nav.transactions') ?>
                        </a>
                    </li>
                    <li class="nav-item position-relative">
                        <a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/loadings') !== false ? 'active' : '' ?>" href="/loadings">
                            <i class="bi bi-box-seam"></i> <?= __('loadings.title') ?>
                            <?php 
                            // Show notification badge for new containers if user is assigned to an office
                            if (isset($_SESSION['user_office']) && $_SESSION['user_office']) {
                                $db = \App\Core\Database::getInstance();
                                $stmt = $db->query("SELECT COUNT(*) as unread FROM office_notifications 
                                                   WHERE office = ? AND is_read = 0", [$_SESSION['user_office']]);
                                $result = $stmt->fetch();
                                $unread = $result ? $result['unread'] : 0;
                                if ($unread > 0):
                            ?>
                                <span class="badge bg-danger rounded-pill"><?= $unread ?></span>
                            <?php endif; } ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/cashbox') !== false ? 'active' : '' ?>" href="/cashbox">
                            <i class="bi bi-cash-stack"></i> <?= __('nav.cashbox') ?>
                        </a>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?= strpos($_SERVER['REQUEST_URI'], '/reports') !== false ? 'active' : '' ?>" href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-file-earmark-text"></i> <?= __('nav.reports') ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="/reports/daily"><?= __('reports.daily_report') ?></a></li>
                            <li><a class="dropdown-item" href="/reports/monthly"><?= __('reports.monthly_report') ?></a></li>
                            <li><a class="dropdown-item" href="/reports/clients"><?= __('reports.client_report') ?></a></li>
                            <li><a class="dropdown-item" href="/reports/cashbox"><?= __('reports.cashbox_report') ?></a></li>
                        </ul>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <!-- Language Switcher -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-globe"></i> <?= $lang->getAvailableLanguages()[$currentLang] ?? $currentLang ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <?php foreach ($lang->getAvailableLanguages() as $code => $name): ?>
                            <li>
                                <a class="dropdown-item <?= $code === $currentLang ? 'active' : '' ?>" 
                                   href="/change-language?lang=<?= $code ?>">
                                    <?= $name ?>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                    
                    <!-- User Menu -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?= $_SESSION['user_name'] ?? 'User' ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="/profile"><?= __('nav.profile') ?></a></li>
                            <li><a class="dropdown-item" href="/settings"><?= __('nav.settings') ?></a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/logout"><?= __('login.logout') ?></a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <div class="container-fluid">
        <div class="row">