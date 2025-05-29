<?php
/**
 * Sprachkonfiguration für PHP
 * Dateipfad: /image-compressor/config/lang_config.php
 */

// Session starten falls noch nicht aktiv
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verfügbare Sprachen
define('AVAILABLE_LANGUAGES', ['de', 'en']);
define('DEFAULT_LANGUAGE', 'de');

// Cache für Übersetzungen
$GLOBALS['translations_cache'] = null;
$GLOBALS['current_language'] = null;

/**
 * Erkennt die bevorzugte Sprache
 */
function detectLanguage() {
    // Prüfe GET-Parameter (für Tests)
    if (isset($_GET['lang']) && in_array($_GET['lang'], AVAILABLE_LANGUAGES)) {
        setLanguage($_GET['lang']);
        return $_GET['lang'];
    }
    
    // Prüfe Session
    if (isset($_SESSION['language']) && in_array($_SESSION['language'], AVAILABLE_LANGUAGES)) {
        $GLOBALS['current_language'] = $_SESSION['language'];
        return $_SESSION['language'];
    }
    
    // Prüfe Cookie
    if (isset($_COOKIE['language']) && in_array($_COOKIE['language'], AVAILABLE_LANGUAGES)) {
        $_SESSION['language'] = $_COOKIE['language'];
        $GLOBALS['current_language'] = $_COOKIE['language'];
        return $_COOKIE['language'];
    }
    
    // Prüfe Browser-Sprache
    if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $browserLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
        if (in_array($browserLang, AVAILABLE_LANGUAGES)) {
            setLanguage($browserLang);
            return $browserLang;
        }
    }
    
    // Standard-Sprache
    setLanguage(DEFAULT_LANGUAGE);
    return DEFAULT_LANGUAGE;
}

/**
 * Lädt Übersetzungen für eine Sprache
 */
function loadTranslations($lang = null) {
    if ($lang === null) {
        $lang = $GLOBALS['current_language'] ?? detectLanguage();
    }
    
    // Prüfe Cache
    if ($GLOBALS['translations_cache'] !== null && $GLOBALS['current_language'] === $lang) {
        return $GLOBALS['translations_cache'];
    }
    
    $langFile = dirname(__DIR__) . '/assets/lang/' . $lang . '.json';
    
    if (file_exists($langFile)) {
        $content = file_get_contents($langFile);
        if ($content !== false) {
            $translations = json_decode($content, true);
            if ($translations !== null) {
                $GLOBALS['translations_cache'] = $translations;
                $GLOBALS['current_language'] = $lang;
                return $translations;
            }
        }
    }
    
    // Fallback zu Deutsch
    if ($lang !== DEFAULT_LANGUAGE) {
        return loadTranslations(DEFAULT_LANGUAGE);
    }
    
    // Notfall-Fallback
    return [];
}

/**
 * Holt eine Übersetzung
 */
function __($key, $replacements = []) {
    static $translations = null;
    static $loadedLang = null;
    
    // Lade Übersetzungen neu wenn Sprache geändert wurde
    $currentLang = $GLOBALS['current_language'] ?? detectLanguage();
    if ($translations === null || $loadedLang !== $currentLang) {
        $translations = loadTranslations($currentLang);
        $loadedLang = $currentLang;
    }
    
    // Navigiere durch verschachtelte Arrays
    $keys = explode('.', $key);
    $value = $translations;
    
    foreach ($keys as $k) {
        if (isset($value[$k])) {
            $value = $value[$k];
        } else {
            // Entwicklermodus: Zeige fehlende Übersetzungen
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                error_log("Missing translation: $key");
            }
            return $key; // Gebe Schlüssel zurück wenn Übersetzung fehlt
        }
    }
    
    // Ersetze Platzhalter
    if (is_string($value)) {
        foreach ($replacements as $placeholder => $replacement) {
            $value = str_replace('{' . $placeholder . '}', $replacement, $value);
        }
    }
    
    return $value;
}

/**
 * Setzt die Sprache
 */
function setLanguage($lang) {
    if (in_array($lang, AVAILABLE_LANGUAGES)) {
        $_SESSION['language'] = $lang;
        setcookie('language', $lang, time() + (86400 * 30), '/');
        $GLOBALS['current_language'] = $lang;
        $GLOBALS['translations_cache'] = null; // Cache leeren
        return true;
    }
    return false;
}

/**
 * Holt die aktuelle Sprache
 */
function getCurrentLanguage() {
    return $GLOBALS['current_language'] ?? detectLanguage();
}

/**
 * Initialisiert Sprache beim ersten Laden
 */
if ($GLOBALS['current_language'] === null) {
    detectLanguage();
}