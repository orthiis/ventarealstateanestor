<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

// Manejo del cambio de idioma antes del login
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'es'])) {
    $_SESSION['temp_language'] = $_GET['lang'];
    setcookie('site_language', $_GET['lang'], time() + (86400 * 365), '/');
    
    // Si existe la clase Language, cambiar el idioma
    if (class_exists('Language')) {
        Language::getInstance()->setLanguage($_GET['lang']);
    }
    
    // Redirigir sin el parámetro lang en la URL
    redirect('login.php');
}

// Establecer idioma predeterminado en INGLÉS si no hay uno seleccionado
if (!isset($_SESSION['temp_language']) && !isset($_COOKIE['site_language'])) {
    $_SESSION['temp_language'] = 'en'; // INGLÉS POR DEFECTO
    setcookie('site_language', 'en', time() + (86400 * 365), '/');
    
    if (class_exists('Language')) {
        Language::getInstance()->setLanguage('en');
    }
} else {
    // Usar el idioma de la sesión temporal o cookie
    $lang = $_SESSION['temp_language'] ?? $_COOKIE['site_language'] ?? 'en';
    if (class_exists('Language')) {
        Language::getInstance()->setLanguage($lang);
    }
}

// Si ya está logueado, redirigir al dashboard
if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($email) || empty($password)) {
        $error = __('fill_all_fields', [], 'Please complete all fields');
    } else {
        if (login($email, $password, $remember)) {
            // Limpiar idioma temporal después del login exitoso
            if (isset($_SESSION['temp_language'])) {
                unset($_SESSION['temp_language']);
            }
            redirect('dashboard.php');
        } else {
            $error = __('invalid_credentials', [], 'Invalid credentials. Please check your email and password.');
        }
    }
}

// Obtener idioma actual
$currentLang = currentLanguage();
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('login_title', [], 'Sign In'); ?> - <?php echo SITE_NAME; ?></title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        .logo-img {
            max-width: 220px;
            height: auto;
            display: block;
            margin: 0 auto 20px auto;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        /* Animated Background */
        body::before {
            content: '';
            position: absolute;
            width: 600px;
            height: 600px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            top: -300px;
            right: -300px;
            animation: float 6s ease-in-out infinite;
        }

        body::after {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            bottom: -200px;
            left: -200px;
            animation: float 8s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            max-width: 1000px;
            width: 100%;
            display: flex;
            position: relative;
            z-index: 1;
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Language Selector */
        .language-selector {
            position: absolute;
            top: 20px;
            right: 20px;
            z-index: 100;
        }

        .language-dropdown {
            position: relative;
        }

        .language-btn {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 10px 16px;
            border-radius: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .language-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
        }

        .language-btn .flag {
            font-size: 18px;
        }

        .language-menu {
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 8px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
            min-width: 160px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
        }

        .language-menu.active {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .language-option {
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            transition: background 0.2s;
            border-bottom: 1px solid #f1f5f9;
        }

        .language-option:first-child {
            border-radius: 12px 12px 0 0;
        }

        .language-option:last-child {
            border-radius: 0 0 12px 12px;
            border-bottom: none;
        }

        .language-option:hover {
            background: #f8fafc;
        }

        .language-option.active {
            background: #f0f4ff;
            color: #667eea;
        }

        .language-option .flag {
            font-size: 20px;
        }

        .language-option .lang-name {
            flex: 1;
            font-weight: 500;
            font-size: 14px;
        }

        .language-option .check-icon {
            color: #667eea;
            font-size: 16px;
        }

        /* Left Side - Image & Content */
        .login-image {
            flex: 1;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 60px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        /* Logo Side - Left */
        .login-image {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .logo-side {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
        }
        
        .logo-img-main {
            max-width: 85%;
            height: auto;
            display: block;
            filter: drop-shadow(0 10px 30px rgba(0, 0, 0, 0.3));
        }
        
        /* Right Side Decorative Elements */
        .floating-elements-right {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            pointer-events: none;
        }
        
        .floating-icon-right {
            position: absolute;
            color: rgba(102, 126, 234, 0.15);
            animation: floatIcon 6s ease-in-out infinite;
        }
        
        .floating-icon-right:nth-child(1) {
            top: 15%;
            right: 10%;
            animation-delay: 0s;
        }
        
        .floating-icon-right:nth-child(2) {
            top: 50%;
            left: 8%;
            animation-delay: 2s;
        }
        
        .floating-icon-right:nth-child(3) {
            bottom: 20%;
            right: 15%;
            animation-delay: 4s;
        }
        
        /* Welcome Section */
        .welcome-section {
            text-align: center;
            margin-bottom: 40px;
            position: relative;
            z-index: 2;
        }
        
        .welcome-icon {
            font-size: 60px;
            color: #667eea;
            margin-bottom: 20px;
            display: block;
        }
        
        .welcome-title {
            font-size: 36px;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 12px;
            line-height: 1.2;
        }
        
        .welcome-subtitle {
            font-size: 16px;
            color: #667eea;
            font-weight: 500;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .welcome-section {
                display: none;
            }
            
            .floating-elements-right {
                display: none;
            }
        }

        .floating-elements {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
        }

        .floating-icon {
            position: absolute;
            opacity: 0.2;
            animation: floatIcon 6s ease-in-out infinite;
        }

        .floating-icon:nth-child(1) {
            top: 20%;
            left: 15%;
            animation-delay: 0s;
        }

        .floating-icon:nth-child(2) {
            top: 60%;
            right: 20%;
            animation-delay: 2s;
        }

        .floating-icon:nth-child(3) {
            bottom: 25%;
            left: 25%;
            animation-delay: 4s;
        }

        @keyframes floatIcon {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(10deg); }
        }

        .image-content {
            position: relative;
            z-index: 2;
        }

        .image-content h2 {
            font-size: 42px;
            font-weight: 800;
            margin-bottom: 20px;
            line-height: 1.2;
            text-shadow: 0 2px 20px rgba(0, 0, 0, 0.2);
        }

        .image-content p {
            font-size: 18px;
            opacity: 0.95;
            line-height: 1.6;
        }

        /* Right Side - Login Form */
        .login-form-container {
            flex: 1;
            padding: 60px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .logo {
            text-align: center;
            margin-bottom: 40px;
        }

        .logo i {
            font-size: 48px;
            color: #667eea;
            margin-bottom: 16px;
        }

        .logo h1 {
            font-size: 32px;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .logo p {
            color: #94a3b8;
            font-size: 14px;
        }

        .login-form h2 {
            font-size: 28px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .login-form > p {
            color: #64748b;
            margin-bottom: 32px;
            font-size: 15px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #334155;
            font-size: 14px;
        }

        .input-group {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-group i {
            position: absolute;
            left: 16px;
            color: #94a3b8;
            font-size: 16px;
            z-index: 1;
        }
        
        .input-group i.password-toggle {
            left: auto;
            right: 16px;
        }

        .input-group .form-control {
            width: 100%;
            padding: 14px 16px 14px 48px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s;
            background: #f8fafc;
        }

        .input-group.password-group {
            position: relative;
        }

        .input-group.password-group .form-control {
            padding-right: 48px;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
            background: white;
        }

        .form-control::placeholder {
            color: #cbd5e1;
        }

        .error-message {
            background: #fee2e2;
            color: #dc2626;
            padding: 14px 16px;
            border-radius: 10px;
            margin-bottom: 24px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-left: 4px solid #dc2626;
            animation: shake 0.5s;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        .error-message i {
            font-size: 18px;
        }

        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: #667eea;
        }

        .checkbox-group label {
            font-size: 14px;
            color: #64748b;
            cursor: pointer;
            margin: 0;
            font-weight: 500;
            user-select: none;
        }

        .forgot-link {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: color 0.3s;
        }

        .forgot-link:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .password-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #94a3b8;
            transition: color 0.3s;
            font-size: 16px;
            pointer-events: auto;
            z-index: 10;
        }
        
        .password-toggle:hover {
            color: #667eea;
        }

        .divider {
            margin: 32px 0;
            text-align: center;
            position: relative;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #e2e8f0;
        }

        .divider span {
            background: white;
            padding: 0 16px;
            color: #94a3b8;
            font-size: 13px;
            font-weight: 600;
            position: relative;
            z-index: 1;
        }

        .demo-credentials {
            background: linear-gradient(135deg, #f0f4ff 0%, #f8fafc 100%);
            padding: 20px;
            border-radius: 12px;
            border: 2px solid #e0e7ff;
        }

        .demo-title {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
            color: #667eea;
            font-weight: 700;
            font-size: 14px;
        }

        .demo-credentials p {
            margin: 0;
            color: #475569;
            font-size: 13px;
            line-height: 1.6;
        }

        .demo-credentials strong {
            color: #334155;
        }

        .login-footer {
            margin-top: 32px;
            text-align: center;
            padding-top: 24px;
            border-top: 1px solid #e2e8f0;
        }

        .login-footer p {
            color: #94a3b8;
            font-size: 13px;
        }

        /* Loading Animation */
        .btn-login.loading {
            pointer-events: none;
            opacity: 0.7;
        }

        .btn-login.loading::after {
            content: '';
            width: 16px;
            height: 16px;
            border: 2px solid white;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
            margin-left: 10px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive */
        @media (max-width: 768px) {
            body {
                padding: 0;
            }

            .login-container {
                border-radius: 0;
                max-width: 100%;
                min-height: 100vh;
            }

            .login-image {
                display: none;
            }

            .login-form-container {
                padding: 40px 24px;
            }

            .logo h1 {
                font-size: 28px;
            }

            .login-form h2 {
                font-size: 24px;
            }

            .remember-forgot {
                flex-direction: column;
                align-items: flex-start;
            }

            .language-selector {
                top: 10px;
                right: 10px;
            }
        }

        @media (max-width: 480px) {
            .login-form-container {
                padding: 30px 20px;
            }

            .logo h1 {
                font-size: 24px;
            }

            .logo i {
                font-size: 40px;
            }
        }
    </style>
</head>
<body>
    <!-- Language Selector -->
    <div class="language-selector">
        <div class="language-dropdown">
            <button class="language-btn" onclick="toggleLanguageMenu()">
                <span class="flag"><?php echo $currentLang === 'es' ? '🇪🇸' : '🇺🇸'; ?></span>
                <span><?php echo $currentLang === 'es' ? 'ES' : 'EN'; ?></span>
                <i class="fas fa-chevron-down"></i>
            </button>
            <div class="language-menu" id="languageMenu">
                <a href="?lang=en" class="language-option <?php echo $currentLang === 'en' ? 'active' : ''; ?>">
                    <span class="flag">🇺🇸</span>
                    <span class="lang-name">English</span>
                    <?php if ($currentLang === 'en'): ?>
                    <i class="fas fa-check check-icon"></i>
                    <?php endif; ?>
                </a>
                <a href="?lang=es" class="language-option <?php echo $currentLang === 'es' ? 'active' : ''; ?>">
                    <span class="flag">🇪🇸</span>
                    <span class="lang-name">Español</span>
                    <?php if ($currentLang === 'es'): ?>
                    <i class="fas fa-check check-icon"></i>
                    <?php endif; ?>
                </a>
            </div>
        </div>
    </div>

    <div class="login-container">
        <!-- Left Side - Logo -->
        <div class="login-image">
            <div class="logo-side">
                <img src="assets/images/logo.png" alt="<?php echo SITE_NAME; ?>" class="logo-img-main">
            </div>
        </div>

        <!-- Right Side - Content & Form -->
        <div class="login-form-container">
            <!-- Decorative Icons -->
            <div class="floating-elements-right">
                <div class="floating-icon-right">
                    <i class="fas fa-home" style="font-size: 32px;"></i>
                </div>
                <div class="floating-icon-right">
                    <i class="fas fa-key" style="font-size: 32px;"></i>
                </div>
                <div class="floating-icon-right">
                    <i class="fas fa-chart-line" style="font-size: 32px;"></i>
                </div>
            </div>

            <!-- Welcome Text -->
            <div class="welcome-section">
                <i class="fas fa-building welcome-icon"></i>
                <h2 class="welcome-title"><?php echo __('welcome_to', [], 'Welcome to'); ?><br><?php echo SITE_NAME; ?></h2>
                <p class="welcome-subtitle"><?php echo __('login_subtitle', [], 'Professional Real Estate Management System'); ?></p>
            </div>

            <!-- Login Form -->
            <div class="login-form">
                <h2><?php echo __('sign_in', [], 'Sign In'); ?></h2>
                <p><?php echo __('login_description', [], 'Enter your credentials to access the dashboard'); ?></p>

                <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
                <?php endif; ?>

                <form method="POST" action="" id="loginForm">
                    <div class="form-group">
                        <label for="email"><?php echo __('email', [], 'Email Address'); ?></label>
                        <div class="input-group">
                            <i class="fas fa-envelope"></i>
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                class="form-control" 
                                placeholder="<?php echo __('email_placeholder', [], 'your@email.com'); ?>" 
                                required 
                                autocomplete="email"
                                value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                            >
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password"><?php echo __('password', [], 'Password'); ?></label>
                        <div class="input-group password-group">
                            <i class="fas fa-lock"></i>
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                class="form-control" 
                                placeholder="••••••••" 
                                required
                                autocomplete="current-password"
                            >
                            <i class="fas fa-eye password-toggle" id="togglePassword"></i>
                        </div>
                    </div>

                    <div class="remember-forgot">
                        <div class="checkbox-group">
                            <input type="checkbox" id="remember" name="remember">
                            <label for="remember"><?php echo __('remember_me', [], 'Remember me'); ?></label>
                        </div>
                        <a href="recuperar-password.php" class="forgot-link">
                            <?php echo __('forgot_password', [], 'Forgot your password?'); ?>
                        </a>
                    </div>

                    <button type="submit" class="btn-login" id="loginBtn">
                        <i class="fas fa-sign-in-alt"></i>
                        <span><?php echo __('sign_in', [], 'Sign In'); ?></span>
                    </button>
                </form>

                <div class="divider">
                    <span><?php echo __('demo_credentials', [], 'Demo Credentials'); ?></span>
                </div>

                <div class="demo-credentials">
                    <div class="demo-title">
                        <i class="fas fa-info-circle"></i>
                        <span><?php echo __('demo_access', [], 'Demo Access'); ?></span>
                    </div>
                    <p>
                        <strong><?php echo __('email', [], 'Email'); ?>:</strong> mahlerlb@gmail.com<br>
                        <strong><?php echo __('password', [], 'Password'); ?>:</strong> 123456
                    </p>
                </div>

                <div class="login-footer">
                    <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. <?php echo __('all_rights_reserved', [], 'All rights reserved'); ?>.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle language menu
        function toggleLanguageMenu() {
            const menu = document.getElementById('languageMenu');
            menu.classList.toggle('active');
        }

        // Close language menu when clicking outside
        document.addEventListener('click', function(e) {
            const languageDropdown = document.querySelector('.language-dropdown');
            if (!languageDropdown.contains(e.target)) {
                document.getElementById('languageMenu').classList.remove('active');
            }
        });

        // Toggle password visibility
        const togglePassword = document.getElementById('togglePassword');
        const passwordField = document.getElementById('password');

        togglePassword.addEventListener('click', function() {
            const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordField.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });

        // Form submission loading state
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('loginBtn');
            btn.classList.add('loading');
            btn.querySelector('span').textContent = '<?php echo __('signing_in', [], 'Signing in'); ?>...';
        });
    </script>
</body>
</html>