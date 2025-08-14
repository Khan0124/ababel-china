<?php
// Get current language
$currentLang = $_SESSION['lang'] ?? 'ar';
$isRTL = in_array($currentLang, ['ar', 'fa', 'he', 'ur']);
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>" dir="<?= $isRTL ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('login.title') ?> - <?= __('app_name') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f0f2f5;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            direction: <?= $isRTL ? 'rtl' : 'ltr' ?>;
        }
        .language-switch {
            position: absolute;
            top: 20px;
            <?= $isRTL ? 'left' : 'right' ?>: 20px;
        }
        .login-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 40px;
            width: 100%;
            max-width: 400px;
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo i {
            font-size: 60px;
            color: #0d6efd;
        }
        .logo h3 {
            color: #333;
            margin-top: 10px;
        }
        .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13,110,253,.25);
        }
        .btn-login {
            background-color: #0d6efd;
            border: none;
            padding: 12px;
            font-weight: 500;
            width: 100%;
        }
        .btn-login:hover {
            background-color: #0b5ed7;
        }
        .alert {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <!-- Language Switch Button -->
    <div class="language-switch">
        <div class="dropdown">
            <button class="btn btn-light dropdown-toggle" type="button" id="languageDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-translate"></i> <?= $currentLang == 'ar' ? 'العربية' : 'English' ?>
            </button>
            <ul class="dropdown-menu" aria-labelledby="languageDropdown">
                <li>
                    <a class="dropdown-item <?= $currentLang == 'ar' ? 'active' : '' ?>" href="/change-language?lang=ar">
                        <i class="bi bi-check-circle<?= $currentLang == 'ar' ? '-fill' : '' ?>"></i> العربية
                    </a>
                </li>
                <li>
                    <a class="dropdown-item <?= $currentLang == 'en' ? 'active' : '' ?>" href="/change-language?lang=en">
                        <i class="bi bi-check-circle<?= $currentLang == 'en' ? '-fill' : '' ?>"></i> English
                    </a>
                </li>
            </ul>
        </div>
    </div>
    
    <div class="login-container">
        <div class="logo">
            <i class="bi bi-building"></i>
            <h3><?= __('company_name') ?></h3>
            <p class="text-muted"><?= __('app_name') ?></p>
        </div>
        
        <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="/login">
            <div class="mb-3">
                <label for="username" class="form-label"><?= __('login.username') ?></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" class="form-control" id="username" name="username" required autofocus>
                </div>
            </div>
            
            <div class="mb-4">
                <label for="password" class="form-label"><?= __('login.password') ?></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
            </div>
            
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="remember">
                <label class="form-check-label" for="remember">
                    <?= __('login.remember_me') ?>
                </label>
            </div>
            
            <button type="submit" class="btn btn-primary btn-login">
                <i class="bi bi-box-arrow-in-<?= $isRTL ? 'left' : 'right' ?> me-2"></i>
                <?= __('login.login_button') ?>
            </button>
        </form>
        
        <div class="text-center mt-4">
            <a href="/forgot-password" class="text-decoration-none"><?= __('login.forgot_password') ?></a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>