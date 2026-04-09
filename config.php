<?php
// config.php - Configuración general del sistema

// Detectar automáticamente la ruta base
$scriptPath = dirname($_SERVER['SCRIPT_NAME']);
$scriptPath = str_replace('\\', '/', $scriptPath); // Windows compatibility
$scriptPath = rtrim($scriptPath, '/');

// Si estamos en una subcarpeta de /clientes/, subir un nivel
if (strpos($scriptPath, '/clientes') !== true) {
    $scriptPath = dirname($scriptPath);
}

// Definir BASE_URL dinámicamente
define('BASE_URL', $scriptPath);

// Función helper para generar URLs
function url($path = '') {
    $path = ltrim($path, '/');
    return BASE_URL . '/' . $path;
}

// Configuración de Base de Datos
define('DB_HOST', 'localhost');
define('DB_USER', 'xygfyvca_jaf');
define('DB_PASS', '*Camil7172*');
define('DB_NAME', 'xygfyvca_jaf');
define('DB_CHARSET', 'utf8mb4');

// Configuración de la Aplicación
define('SITE_NAME', 'Jaf Investments');
define('SITE_URL', 'https://orthiismusic.net/jaf');
define('ADMIN_URL', SITE_URL . '/admin');

// Rutas del sistema
define('ROOT_PATH', dirname(__FILE__));
define('UPLOAD_PATH', ROOT_PATH . '/uploads/');
define('PROPERTY_IMAGES_PATH', UPLOAD_PATH . 'properties/');
define('USER_IMAGES_PATH', UPLOAD_PATH . 'users/');
define('DOCUMENTS_PATH', UPLOAD_PATH . 'documents/');

// URLs públicas de uploads
define('UPLOAD_URL', SITE_URL . '/uploads/');
define('PROPERTY_IMAGES_URL', UPLOAD_URL . 'properties/');
define('USER_IMAGES_URL', UPLOAD_URL . 'users/');

// Configuración de sesión
define('SESSION_NAME', 'JAF_SESSION');
define('SESSION_LIFETIME', 3600); // 1 hora
define('REMEMBER_ME_LIFETIME', 2592000); // 30 días

// Configuración de seguridad
define('ENCRYPTION_KEY', 'your-secret-key-here-change-this');
define('PASSWORD_MIN_LENGTH', 8);
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutos

// Configuración de paginación
define('PROPERTIES_PER_PAGE', 12);
define('CLIENTS_PER_PAGE', 25);
define('USERS_PER_PAGE', 25);

// Configuración de archivos
define('MAX_FILE_SIZE', 10485760); // 10MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'webp']);
define('ALLOWED_DOCUMENT_TYPES', ['pdf', 'doc', 'docx', 'xls', 'xlsx']);

// Zona horaria
date_default_timezone_set('America/Santo_Domingo');

// Configuración de errores (cambiar en producción)
if ($_SERVER['SERVER_NAME'] === 'localhost') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    define('DEBUG_MODE', true);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    define('DEBUG_MODE', false);
}

// Iniciar sesión
session_name(SESSION_NAME);
session_start();

// Configuración regional
setlocale(LC_TIME, 'es_DO.UTF-8', 'es_DO', 'spanish');

// ============================================
// NUEVAS CONFIGURACIONES PARA MULTIIDIOMA
// ============================================

// Rutas para archivos de idioma
define('LANG_PATH', ROOT_PATH . '/lang/');
define('INCLUDES_PATH', ROOT_PATH . '/includes/');

// Idiomas disponibles
define('AVAILABLE_LANGUAGES', ['en', 'es']);
define('DEFAULT_LANGUAGE', 'en');

// Incluir sistema de idiomas
if (file_exists(INCLUDES_PATH . 'Language.php')) {
    require_once INCLUDES_PATH . 'Language.php';
    
    // Inicializar el sistema de idiomas
    $language = Language::getInstance();
    
    // Si el usuario está logueado, cargar su idioma preferido
    if (isset($_SESSION['user_id'])) {
        // Cargar database y functions solo si aún no están cargados
        if (!function_exists('db')) {
            require_once 'database.php';
        }
        if (!function_exists('getCurrentUser')) {
            require_once 'functions.php';
        }
        
        try {
            $userLang = db()->selectValue(
                "SELECT language FROM users WHERE id = ?",
                [$_SESSION['user_id']]
            );
            
            if ($userLang && in_array($userLang, AVAILABLE_LANGUAGES)) {
                $_SESSION['user_language'] = $userLang;
                $language->setLanguage($userLang);
            }
        } catch (Exception $e) {
            // Si hay error, continuar con idioma por defecto
            if (DEBUG_MODE) {
                error_log('Error loading user language: ' . $e->getMessage());
            }
        }
    }
    
    // Definir constantes de idioma
    define('CURRENT_LANGUAGE', currentLanguage());
    define('IS_ENGLISH', CURRENT_LANGUAGE === 'en');
    define('IS_SPANISH', CURRENT_LANGUAGE === 'es');
}