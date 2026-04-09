<?php
/**
 * Clase Language - Sistema de Traducción Multiidioma
 * Soporta inglés (en) y español (es)
 * 
 * Uso:
 * - __('menu.dashboard') - Traduce una clave
 * - __('welcome_message', ['name' => 'John']) - Traduce con parámetros
 * - currentLanguage() - Obtiene el idioma actual
 * - setLanguage('es') - Cambia el idioma
 */

class Language {
    private static $instance = null;
    private $language = 'en';
    private $translations = [];
    private $fallbackTranslations = [];
    
    /**
     * Constructor privado (Singleton)
     */
    private function __construct() {
        $this->detectLanguage();
        $this->loadTranslations();
    }
    
    /**
     * Singleton - obtener instancia única
     * @return Language
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Detectar idioma del usuario
     * Prioridad: 1) Usuario logueado 2) Cookie 3) GET param 4) Default
     */
    private function detectLanguage() {
        // 1. Si el usuario está logueado, usar su idioma de preferencia
        if (isset($_SESSION['user_id']) && isset($_SESSION['user_language'])) {
            $lang = $_SESSION['user_language'];
            if (in_array($lang, ['en', 'es'])) {
                $this->language = $lang;
                return;
            }
        }
        
        // 2. Si hay un idioma en la cookie (para visitantes del website)
        if (isset($_COOKIE['site_language'])) {
            $lang = $_COOKIE['site_language'];
            if (in_array($lang, ['en', 'es'])) {
                $this->language = $lang;
                return;
            }
        }
        
        // 3. Si se está cambiando el idioma vía GET
        if (isset($_GET['lang'])) {
            $lang = $_GET['lang'];
            if (in_array($lang, ['en', 'es'])) {
                $this->language = $lang;
                // Guardar en cookie para visitantes
                setcookie('site_language', $this->language, time() + (86400 * 365), '/');
                return;
            }
        }
        
        // 4. Por defecto: inglés
        $this->language = defined('DEFAULT_LANGUAGE') ? DEFAULT_LANGUAGE : 'en';
    }
    
    /**
     * Cargar archivos de traducción
     */
    private function loadTranslations() {
        $langPath = defined('LANG_PATH') ? LANG_PATH : dirname(__DIR__) . '/lang/';
        $langFile = $langPath . $this->language . '.php';
        
        if (file_exists($langFile)) {
            $this->translations = include($langFile);
        } else {
            // Si no existe el archivo, usar array vacío
            $this->translations = [];
            
            // Intentar cargar inglés como fallback
            if ($this->language !== 'en') {
                $enFile = $langPath . 'en.php';
                if (file_exists($enFile)) {
                    $this->fallbackTranslations = include($enFile);
                }
            }
        }
    }
    
    /**
     * Obtener traducción
     * @param string $key - Clave de traducción (puede usar notación punto: 'menu.dashboard')
     * @param array $params - Parámetros para reemplazar (opcional) ['name' => 'John']
     * @param string $default - Texto por defecto si no existe traducción
     * @return string
     */
    public function get($key, $params = [], $default = null) {
        // Buscar en traducciones cargadas
        $translation = $this->getNestedValue($this->translations, $key);
        
        // Si no existe, buscar en fallback
        if ($translation === null && !empty($this->fallbackTranslations)) {
            $translation = $this->getNestedValue($this->fallbackTranslations, $key);
        }
        
        // Si aún no existe, usar el default o la misma key
        if ($translation === null) {
            $translation = $default ?? $key;
        }
        
        // Reemplazar parámetros si existen
        if (!empty($params) && is_array($params)) {
            foreach ($params as $paramKey => $paramValue) {
                $translation = str_replace(':' . $paramKey, $paramValue, $translation);
            }
        }
        
        return $translation;
    }
    
    /**
     * Obtener valor anidado de un array usando notación de punto
     * Ejemplo: 'menu.dashboard' busca $array['menu']['dashboard']
     * @param array $array
     * @param string $key
     * @return mixed|null
     */
    private function getNestedValue($array, $key) {
        // Si la clave existe directamente, retornarla
        if (isset($array[$key])) {
            return $array[$key];
        }
        
        // Si contiene punto, buscar anidado
        if (strpos($key, '.') === false) {
            return null;
        }
        
        $keys = explode('.', $key);
        $value = $array;
        
        foreach ($keys as $k) {
            if (!is_array($value) || !isset($value[$k])) {
                return null;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
    
    /**
     * Obtener idioma actual
     * @return string
     */
    public function getCurrentLanguage() {
        return $this->language;
    }
    
    /**
     * Cambiar idioma
     * @param string $language - Código del idioma (en, es)
     * @return bool
     */
    public function setLanguage($language) {
        if (!in_array($language, ['en', 'es'])) {
            return false;
        }
        
        $this->language = $language;
        $this->loadTranslations();
        
        // Guardar en sesión si el usuario está logueado
        if (isset($_SESSION['user_id'])) {
            $_SESSION['user_language'] = $language;
            
            // Actualizar en la base de datos
            if (function_exists('db')) {
                try {
                    db()->update('users', 
                        ['language' => $language],
                        'id = ?',
                        [$_SESSION['user_id']]
                    );
                } catch (Exception $e) {
                    if (defined('DEBUG_MODE') && DEBUG_MODE) {
                        error_log('Error updating user language: ' . $e->getMessage());
                    }
                }
            }
        }
        
        // Guardar en cookie para visitantes
        setcookie('site_language', $language, time() + (86400 * 365), '/');
        
        return true;
    }
    
    /**
     * Obtener todos los idiomas disponibles
     * @return array
     */
    public static function getAvailableLanguages() {
        return [
            'en' => [
                'code' => 'en',
                'name' => 'English',
                'native_name' => 'English',
                'flag' => '🇺🇸',
                'flag_icon' => 'us'
            ],
            'es' => [
                'code' => 'es',
                'name' => 'Spanish',
                'native_name' => 'Español',
                'flag' => '🇪🇸',
                'flag_icon' => 'es'
            ]
        ];
    }
    
    /**
     * Verificar si un idioma está disponible
     * @param string $language
     * @return bool
     */
    public static function isAvailable($language) {
        return in_array($language, ['en', 'es']);
    }
    
    /**
     * Obtener información de un idioma específico
     * @param string $language
     * @return array|null
     */
    public static function getLanguageInfo($language) {
        $languages = self::getAvailableLanguages();
        return $languages[$language] ?? null;
    }
    
    /**
     * Resetear instancia (útil para testing)
     */
    public static function reset() {
        self::$instance = null;
    }
}

// ============================================
// FUNCIONES HELPER GLOBALES
// ============================================

/**
 * Función helper global para traducción
 * Uso: __('menu.dashboard') o __('welcome', ['name' => 'John'])
 * 
 * @param string $key - Clave de traducción
 * @param array $params - Parámetros opcionales
 * @param string $default - Texto por defecto
 * @return string
 */
if (!function_exists('__')) {
    function __($key, $params = [], $default = null) {
        return Language::getInstance()->get($key, $params, $default);
    }
}

/**
 * Función helper para obtener idioma actual
 * Uso: currentLanguage()
 * 
 * @return string
 */
if (!function_exists('currentLanguage')) {
    function currentLanguage() {
        return Language::getInstance()->getCurrentLanguage();
    }
}

/**
 * Función helper para cambiar idioma
 * Uso: setLanguage('es')
 * 
 * @param string $language
 * @return bool
 */
if (!function_exists('setLanguage')) {
    function setLanguage($language) {
        return Language::getInstance()->setLanguage($language);
    }
}

/**
 * Función helper para verificar si es un idioma específico
 * Uso: isLanguage('en')
 * 
 * @param string $language
 * @return bool
 */
if (!function_exists('isLanguage')) {
    function isLanguage($language) {
        return currentLanguage() === $language;
    }
}