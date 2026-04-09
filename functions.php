<?php
// functions.php - Funciones auxiliares del sistema

// Funciones de Seguridad
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Funciones de Sesión y Autenticación
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect('login.php');
        exit;
    }
}

function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $userId = $_SESSION['user_id'];
    $user = db()->selectOne("SELECT * FROM users WHERE id = ?", [$userId]);
    
    if ($user) {
        $user['role'] = db()->selectOne("SELECT * FROM roles WHERE id = ?", [$user['role_id']]);
    }
    
    return $user;
}

function hasPermission($permission) {
    $user = getCurrentUser();
    if (!$user || !isset($user['role']['permissions'])) {
        return false;
    }
    
    $permissions = json_decode($user['role']['permissions'], true);
    return in_array($permission, $permissions);
}

function login($email, $password, $remember = false) {
    $user = db()->selectOne("SELECT * FROM users WHERE email = ? AND status = 'active'", [$email]);
    
    if (!$user || !verifyPassword($password, $user['password'])) {
        return false;
    }
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['user_role'] = $user['role_id'];
    
    // Cargar idioma del usuario
    if (isset($user['language'])) {
        $_SESSION['user_language'] = $user['language'];
    }
    
    // Actualizar último login
    db()->update('users', 
        ['last_login' => date('Y-m-d H:i:s')],
        'id = :id',
        ['id' => $user['id']]
    );
    
    // Remember me
    if ($remember) {
        $token = generateToken();
        setcookie('remember_token', $token, time() + REMEMBER_ME_LIFETIME, '/');
        // Guardar token en BD
    }
    
    return true;
}

function logout() {
    session_unset();
    session_destroy();
    
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/');
    }
    
    redirect('login.php');
}

// Funciones de Redirección y URLs
function redirect($url) {
    if (!headers_sent()) {
        header("Location: " . $url);
        exit;
    } else {
        echo "<script>window.location.href='{$url}';</script>";
        exit;
    }
}

function currentUrl() {
    return $_SERVER['REQUEST_URI'];
}

function baseUrl($path = '') {
    return SITE_URL . '/' . ltrim($path, '/');
}

function adminUrl($path = '') {
    return ADMIN_URL . '/' . ltrim($path, '/');
}

// Funciones de Formato
function formatPrice($price, $currency = 'USD') {
    $symbol = $currency === 'USD' ? '$' : '€';
    return $symbol . number_format($price, 2, '.', ',');
}

function formatDate($date, $format = 'd/m/Y') {
    if (empty($date)) return '';
    return date($format, strtotime($date));
}

function formatDateTime($datetime, $format = 'd/m/Y H:i') {
    if (empty($datetime)) return '';
    return date($format, strtotime($datetime));
}

function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $difference = time() - $timestamp;
    
    if ($difference < 60) {
        return 'hace unos segundos';
    } elseif ($difference < 3600) {
        $mins = floor($difference / 60);
        return "hace {$mins} " . ($mins == 1 ? 'minuto' : 'minutos');
    } elseif ($difference < 86400) {
        $hours = floor($difference / 3600);
        return "hace {$hours} " . ($hours == 1 ? 'hora' : 'horas');
    } elseif ($difference < 604800) {
        $days = floor($difference / 86400);
        return "hace {$days} " . ($days == 1 ? 'día' : 'días');
    } else {
        return formatDate($datetime);
    }
}

function truncate($text, $length = 100, $suffix = '...') {
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return mb_substr($text, 0, $length) . $suffix;
}

// Funciones de Mensajes Flash
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

function showFlashMessage() {
    $message = getFlashMessage();
    if ($message) {
        $alertClass = [
            'success' => 'alert-success',
            'error' => 'alert-danger',
            'warning' => 'alert-warning',
            'info' => 'alert-info'
        ];
        
        $class = $alertClass[$message['type']] ?? 'alert-info';
        
        return "<div class='alert {$class} alert-dismissible fade show' role='alert'>
                    {$message['message']}
                    <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                </div>";
    }
    return '';
}

// Funciones de Subida de Archivos
function uploadFile($file, $destination, $allowedTypes = null, $maxSize = null) {
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'message' => 'Error en el archivo'];
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Error al subir el archivo'];
    }
    
    $maxSize = $maxSize ?? MAX_FILE_SIZE;
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'El archivo es demasiado grande'];
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedTypes = $allowedTypes ?? ALLOWED_IMAGE_TYPES;
    
    if (!in_array($extension, $allowedTypes)) {
        return ['success' => false, 'message' => 'Tipo de archivo no permitido'];
    }
    
    $filename = uniqid() . '.' . $extension;
    $filepath = $destination . $filename;
    
    if (!is_dir($destination)) {
        mkdir($destination, 0755, true);
    }
    
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => false, 'message' => 'Error al mover el archivo'];
    }
    
    return ['success' => true, 'filename' => $filename, 'filepath' => $filepath];
}

function deleteFile($filepath) {
    if (file_exists($filepath)) {
        return unlink($filepath);
    }
    return false;
}

// Funciones de Paginación
function paginate($totalItems, $itemsPerPage, $currentPage = 1) {
    $totalPages = ceil($totalItems / $itemsPerPage);
    $currentPage = max(1, min($totalPages, $currentPage));
    $offset = ($currentPage - 1) * $itemsPerPage;
    
    return [
        'total_items' => $totalItems,
        'total_pages' => $totalPages,
        'current_page' => $currentPage,
        'items_per_page' => $itemsPerPage,
        'offset' => $offset,
        'has_previous' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages
    ];
}

function renderPagination($pagination, $url) {
    if ($pagination['total_pages'] <= 1) {
        return '';
    }
    
    $html = '<nav aria-label="Paginación"><ul class="pagination justify-content-center">';
    
    // Botón anterior
    if ($pagination['has_previous']) {
        $prevPage = $pagination['current_page'] - 1;
        $html .= "<li class='page-item'><a class='page-link' href='{$url}?page={$prevPage}'>Anterior</a></li>";
    } else {
        $html .= "<li class='page-item disabled'><span class='page-link'>Anterior</span></li>";
    }
    
    // Números de página
    for ($i = 1; $i <= $pagination['total_pages']; $i++) {
        $active = $i === $pagination['current_page'] ? 'active' : '';
        $html .= "<li class='page-item {$active}'><a class='page-link' href='{$url}?page={$i}'>{$i}</a></li>";
    }
    
    // Botón siguiente
    if ($pagination['has_next']) {
        $nextPage = $pagination['current_page'] + 1;
        $html .= "<li class='page-item'><a class='page-link' href='{$url}?page={$nextPage}'>Siguiente</a></li>";
    } else {
        $html .= "<li class='page-item disabled'><span class='page-link'>Siguiente</span></li>";
    }
    
    $html .= '</ul></nav>';
    
    return $html;
}

// Funciones de Validación
function validateRequired($value) {
    return !empty(trim($value));
}

function validateMinLength($value, $min) {
    return mb_strlen($value) >= $min;
}

function validateMaxLength($value, $max) {
    return mb_strlen($value) <= $max;
}

function validateNumeric($value) {
    return is_numeric($value);
}

function validatePhone($phone) {
    return preg_match('/^[0-9\-\+\(\)\s]+$/', $phone);
}

// Función de logging
function logActivity($action, $entityType, $entityId, $description = null) {
    $user = getCurrentUser();
    
    if (!$user) return;
    
    $data = [
        'user_id' => $user['id'],
        'action' => $action,
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'description' => $description,
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'],
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    db()->insert('activity_log', $data);
}

// Función para generar slug
function generateSlug($text) {
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    $text = trim($text, '-');
    return $text;
}

// Función para enviar emails
function sendEmail($to, $subject, $body, $templateId = null) {
    // Implementar con PHPMailer o similar
    // Por ahora retorna true
    return true;
}

// ============================================
// FUNCIONES PARA DOCUMENTOS
// ============================================

/**
 * Formatear bytes a formato legible
 */
if (!function_exists('formatBytes')) {
    function formatBytes($bytes, $decimals = 2) {
        if ($bytes == 0) return '0 Bytes';
        
        $k = 1024;
        $dm = $decimals < 0 ? 0 : $decimals;
        $sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        
        $i = floor(log($bytes) / log($k));
        
        return round($bytes / pow($k, $i), $dm) . ' ' . $sizes[$i];
    }
}

/**
 * Obtener tiempo transcurrido de forma legible
 */
if (!function_exists('timeAgo')) {
    function timeAgo($datetime) {
        $time = strtotime($datetime);
        $now = time();
        $diff = $now - $time;
        
        if ($diff < 60) {
            return 'Hace ' . $diff . ' segundos';
        } elseif ($diff < 3600) {
            $minutes = floor($diff / 60);
            return 'Hace ' . $minutes . ' minuto' . ($minutes > 1 ? 's' : '');
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return 'Hace ' . $hours . ' hora' . ($hours > 1 ? 's' : '');
        } elseif ($diff < 2592000) {
            $days = floor($diff / 86400);
            return 'Hace ' . $days . ' dia' . ($days > 1 ? 's' : '');
        } elseif ($diff < 31536000) {
            $months = floor($diff / 2592000);
            return 'Hace ' . $months . ' mes' . ($months > 1 ? 'es' : '');
        } else {
            $years = floor($diff / 31536000);
            return 'Hace ' . $years . ' ano' . ($years > 1 ? 's' : '');
        }
    }
}

// ============================================
// FUNCIONES DE TRADUCCIÓN Y MULTIIDIOMA
// AGREGADAS PARA EL SISTEMA BILINGÜE
// ============================================

/**
 * Cambiar el idioma del usuario actual
 * @param string $language - Código del idioma (en, es)
 * @return bool
 */
function changeUserLanguage($language) {
    if (!in_array($language, ['en', 'es'])) {
        return false;
    }
    
    // Cambiar en la sesión
    $_SESSION['user_language'] = $language;
    
    // Cambiar usando la clase Language si está disponible
    if (class_exists('Language')) {
        Language::getInstance()->setLanguage($language);
    }
    
    // Si el usuario está logueado, actualizar en la BD
    if (isset($_SESSION['user_id'])) {
        try {
            db()->update('users', 
                ['language' => $language],
                'id = ?',
                [$_SESSION['user_id']]
            );
            
            // Log de actividad
            db()->insert('activity_log', [
                'user_id' => $_SESSION['user_id'],
                'action' => 'language_changed',
                'entity_type' => 'user',
                'entity_id' => $_SESSION['user_id'],
                'description' => "Idioma cambiado a: {$language}",
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            return true;
        } catch (Exception $e) {
            error_log('Error changing user language: ' . $e->getMessage());
            return false;
        }
    }
    
    // Para visitantes sin sesión, guardar en cookie
    setcookie('site_language', $language, time() + (86400 * 365), '/');
    
    return true;
}

/**
 * Obtener el nombre del idioma actual
 * @return string
 */
function getCurrentLanguageName() {
    if (!class_exists('Language')) {
        return 'English';
    }
    
    $languages = Language::getAvailableLanguages();
    $current = currentLanguage();
    return $languages[$current]['native_name'] ?? 'English';
}

/**
 * Obtener la bandera del idioma actual
 * @return string
 */
function getCurrentLanguageFlag() {
    if (!class_exists('Language')) {
        return '🇺🇸';
    }
    
    $languages = Language::getAvailableLanguages();
    $current = currentLanguage();
    return $languages[$current]['flag'] ?? '🇺🇸';
}

/**
 * Generar URL con parámetro de idioma
 * @param string $url - URL base
 * @param string $language - Código del idioma (opcional)
 * @return string
 */
function urlWithLanguage($url, $language = null) {
    if ($language === null) {
        $language = currentLanguage();
    }
    
    $separator = (strpos($url, '?') !== false) ? '&' : '?';
    return $url . $separator . 'lang=' . $language;
}

/**
 * Traducir texto con fallback al texto original
 * Alias de la función __() para compatibilidad
 * @param string $key - Clave de traducción
 * @param array $params - Parámetros opcionales
 * @param string $default - Texto por defecto
 * @return string
 */
function trans($key, $params = [], $default = null) {
    if (function_exists('__')) {
        return __($key, $params, $default);
    }
    return $default ?? $key;
}

/**
 * Traducir array de opciones
 * Útil para dropdowns y selects
 * @param array $options - Array de opciones
 * @param string $prefix - Prefijo para las claves de traducción
 * @return array
 */
function transOptions($options, $prefix = '') {
    $translated = [];
    foreach ($options as $key => $value) {
        $translationKey = $prefix ? "{$prefix}.{$key}" : $key;
        if (function_exists('__')) {
            $translated[$key] = __($translationKey, [], $value);
        } else {
            $translated[$key] = $value;
        }
    }
    return $translated;
}

/**
 * Formatear fecha según el idioma actual
 * @param mixed $date - Fecha (timestamp o string)
 * @param string $format - Formato (short, medium, long)
 * @return string
 */
function formatDateByLanguage($date, $format = 'medium') {
    $timestamp = is_numeric($date) ? $date : strtotime($date);
    
    $currentLang = function_exists('currentLanguage') ? currentLanguage() : 'en';
    
    if ($currentLang === 'es') {
        // Formato español
        switch ($format) {
            case 'short':
                return date('d/m/Y', $timestamp);
            case 'medium':
                return date('d/m/Y H:i', $timestamp);
            case 'long':
                $months = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                          'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
                $month = $months[date('n', $timestamp) - 1];
                return date('d', $timestamp) . ' de ' . $month . ' de ' . date('Y', $timestamp);
            default:
                return date('d/m/Y H:i:s', $timestamp);
        }
    } else {
        // Formato inglés
        switch ($format) {
            case 'short':
                return date('m/d/Y', $timestamp);
            case 'medium':
                return date('m/d/Y H:i', $timestamp);
            case 'long':
                return date('F d, Y', $timestamp);
            default:
                return date('m/d/Y H:i:s', $timestamp);
        }
    }
}

/**
 * Formatear número según el idioma actual
 * @param float $number - Número a formatear
 * @param int $decimals - Cantidad de decimales
 * @return string
 */
function formatNumberByLanguage($number, $decimals = 0) {
    $currentLang = function_exists('currentLanguage') ? currentLanguage() : 'en';
    
    if ($currentLang === 'es') {
        return number_format($number, $decimals, ',', '.');
    } else {
        return number_format($number, $decimals, '.', ',');
    }
}

/**
 * Formatear moneda según el idioma actual
 * @param float $amount - Monto
 * @param string $currency - Código de moneda
 * @return string
 */
function formatCurrencyByLanguage($amount, $currency = 'USD') {
    $formatted = formatNumberByLanguage($amount, 2);
    
    $currentLang = function_exists('currentLanguage') ? currentLanguage() : 'en';
    
    if ($currentLang === 'es') {
        return $currency . ' ' . $formatted;
    } else {
        return $currency . ' ' . $formatted;
    }
}

/**
 * Obtener texto traducido para tiempo transcurrido
 * Versión traducible de timeAgo()
 * @param string $datetime - Fecha y hora
 * @return string
 */
function timeAgoTranslated($datetime) {
    $timestamp = strtotime($datetime);
    $difference = time() - $timestamp;
    
    $currentLang = function_exists('currentLanguage') ? currentLanguage() : 'en';
    
    if ($currentLang === 'es') {
        if ($difference < 60) {
            return 'hace unos segundos';
        } elseif ($difference < 3600) {
            $mins = floor($difference / 60);
            return "hace {$mins} " . ($mins == 1 ? 'minuto' : 'minutos');
        } elseif ($difference < 86400) {
            $hours = floor($difference / 3600);
            return "hace {$hours} " . ($hours == 1 ? 'hora' : 'horas');
        } elseif ($difference < 604800) {
            $days = floor($difference / 86400);
            return "hace {$days} " . ($days == 1 ? 'día' : 'días');
        } else {
            return formatDateByLanguage($datetime, 'short');
        }
    } else {
        if ($difference < 60) {
            return 'a few seconds ago';
        } elseif ($difference < 3600) {
            $mins = floor($difference / 60);
            return "{$mins} " . ($mins == 1 ? 'minute' : 'minutes') . ' ago';
        } elseif ($difference < 86400) {
            $hours = floor($difference / 3600);
            return "{$hours} " . ($hours == 1 ? 'hour' : 'hours') . ' ago';
        } elseif ($difference < 604800) {
            $days = floor($difference / 86400);
            return "{$days} " . ($days == 1 ? 'day' : 'days') . ' ago';
        } else {
            return formatDateByLanguage($datetime, 'short');
        }
    }
}

/**
 * Verificar si un archivo de idioma existe
 * @param string $language - Código del idioma
 * @return bool
 */
function languageFileExists($language) {
    if (!defined('LANG_PATH')) {
        return false;
    }
    return file_exists(LANG_PATH . $language . '.php');
}

/**
 * Obtener todos los idiomas disponibles con información
 * @return array
 */
function getAvailableLanguagesInfo() {
    if (class_exists('Language')) {
        return Language::getAvailableLanguages();
    }
    
    return [
        'en' => [
            'code' => 'en',
            'name' => 'English',
            'native_name' => 'English',
            'flag' => '🇺🇸'
        ],
        'es' => [
            'code' => 'es',
            'name' => 'Spanish',
            'native_name' => 'Español',
            'flag' => '🇪🇸'
        ]
    ];
    

}



// ============================================================
// FUNCIONES PARA PORTAL DE CLIENTES
// ============================================================

/**
 * Verificar si un cliente está autenticado
 */
function isClientLoggedIn() {
    return isset($_SESSION['client_id']) && !empty($_SESSION['client_id']);
}

/**
 * Login de clientes del portal
 */
function clientLogin($email, $password) {
    // Buscar cliente activo por email
    $client = db()->selectOne("
        SELECT * FROM clients 
        WHERE email = ? 
        AND portal_active = 1
    ", [$email]);
    
    // Verificar si el cliente existe
    if (!$client) {
        return false;
    }
    
    // Verificar si tiene contraseña configurada
    if (empty($client['password'])) {
        return false;
    }
    
    // Verificar contraseña
    if (!password_verify($password, $client['password'])) {
        return false;
    }
    
    // Crear sesión de cliente
    $_SESSION['client_id'] = $client['id'];
    $_SESSION['client_email'] = $client['email'];
    $_SESSION['client_name'] = $client['first_name'] . ' ' . $client['last_name'];
    
    // Actualizar último login
    db()->update('clients', 
        ['last_login' => date('Y-m-d H:i:s')],
        'id = ?',
        [$client['id']]
    );
    
    return true;
}

/**
 * Logout de clientes
 */
function clientLogout() {
    unset($_SESSION['client_id']);
    unset($_SESSION['client_email']);
    unset($_SESSION['client_name']);
    
    redirect('login-clientes.php');
}