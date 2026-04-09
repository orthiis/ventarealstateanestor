<?php
/**
 * Componente: Selector de Idioma
 * Language Switcher Component
 * 
 * Uso en CRM: include 'includes/language-switcher.php';
 * Uso en Website: include '../includes/language-switcher.php';
 */

$currentLang = function_exists('currentLanguage') ? currentLanguage() : 'en';
$availableLanguages = class_exists('Language') ? Language::getAvailableLanguages() : [
    'en' => ['code' => 'en', 'native_name' => 'English', 'flag' => '🇺🇸'],
    'es' => ['code' => 'es', 'native_name' => 'Español', 'flag' => '🇪🇸']
];
$currentUrl = $_SERVER['REQUEST_URI'];
$currentLangName = function_exists('getCurrentLanguageName') ? getCurrentLanguageName() : 'English';
$currentLangFlag = function_exists('getCurrentLanguageFlag') ? getCurrentLanguageFlag() : '🇺🇸';
?>

<div class="language-switcher">
    <div class="dropdown">
        <button class="btn btn-language dropdown-toggle" type="button" id="languageDropdown" data-bs-toggle="dropdown" aria-expanded="false">
            <span class="flag"><?php echo $currentLangFlag; ?></span>
            <span class="lang-name"><?php echo $currentLangName; ?></span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="languageDropdown">
            <?php foreach ($availableLanguages as $code => $lang): ?>
                <li>
                    <a class="dropdown-item <?php echo $currentLang === $code ? 'active' : ''; ?>" 
                       href="#"
                       data-lang="<?php echo $code; ?>"
                       onclick="changeLanguage('<?php echo $code; ?>'); return false;">
                        <span class="flag"><?php echo $lang['flag']; ?></span>
                        <span class="lang-text"><?php echo $lang['native_name']; ?></span>
                        <?php if ($currentLang === $code): ?>
                            <i class="fas fa-check ms-auto"></i>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<style>
.language-switcher {
    display: inline-block;
    position: relative;
}

.btn-language {
    background: transparent;
    border: 1px solid #e5e7eb;
    color: #4b5563;
    padding: 8px 16px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.3s;
    cursor: pointer;
}

.btn-language:hover {
    background: #f9fafb;
    border-color: #667eea;
    color: #667eea;
}

.btn-language:focus {
    outline: none;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.btn-language .flag {
    font-size: 18px;
    line-height: 1;
}

.btn-language .lang-name {
    display: inline-block;
}

.language-switcher .dropdown-menu {
    min-width: 180px;
    padding: 8px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    border-radius: 12px;
    margin-top: 8px;
}

.language-switcher .dropdown-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    border-radius: 8px;
    transition: all 0.2s;
    cursor: pointer;
    color: #4b5563;
    text-decoration: none;
}

.language-switcher .dropdown-item:hover {
    background: #f3f4f6;
    color: #1f2937;
}

.language-switcher .dropdown-item.active {
    background: #eff6ff;
    color: #667eea;
    font-weight: 600;
}

.language-switcher .dropdown-item .flag {
    font-size: 18px;
    line-height: 1;
}

.language-switcher .dropdown-item .lang-text {
    flex: 1;
}

.language-switcher .dropdown-item i.fa-check {
    color: #667eea;
    font-size: 12px;
}

/* Responsive */
@media (max-width: 768px) {
    .btn-language .lang-name {
        display: none;
    }
    
    .btn-language {
        padding: 8px 12px;
    }
}

/* Loading state */
.btn-language.loading {
    opacity: 0.6;
    pointer-events: none;
}
</style>

<script>
/**
 * Cambiar idioma del sistema
 * @param {string} lang - Código del idioma (en, es)
 */
function changeLanguage(lang) {
    // Mostrar loading
    const btn = document.querySelector('.btn-language');
    if (!btn) return;
    
    const originalContent = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    btn.disabled = true;
    btn.classList.add('loading');
    
    // Determinar la ruta correcta del endpoint
    const isInRoot = window.location.pathname.indexOf('/ajax/') === -1 && 
                     window.location.pathname.indexOf('/includes/') === -1;
    const ajaxPath = isInRoot ? 'ajax/change-language.php' : '../ajax/change-language.php';
    
    // Hacer petición AJAX para cambiar idioma
    fetch(ajaxPath, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ language: lang })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Recargar la página para aplicar el nuevo idioma
            window.location.reload();
        } else {
            // Mostrar error
            alert('<?php echo function_exists('__') ? __('error') : 'Error'; ?>: ' + (data.message || 'Unknown error'));
            btn.innerHTML = originalContent;
            btn.disabled = false;
            btn.classList.remove('loading');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('<?php echo function_exists('__') ? __('error') : 'Error'; ?>: ' + error.message);
        btn.innerHTML = originalContent;
        btn.disabled = false;
        btn.classList.remove('loading');
    });
}

// Cerrar dropdown al hacer clic fuera
document.addEventListener('click', function(event) {
    const languageSwitcher = document.querySelector('.language-switcher');
    if (languageSwitcher && !languageSwitcher.contains(event.target)) {
        const dropdown = languageSwitcher.querySelector('.dropdown-menu');
        if (dropdown && dropdown.classList.contains('show')) {
            dropdown.classList.remove('show');
        }
    }
});
</script>