<?php
/**
 * Konfigurationsdatei
 * Dateipfad: /image-compressor/config/config.php
 * 
 * Zentrale Konfigurationseinstellungen für die Bildkomprimierung
 */

// Fehlerberichterstattung (für Produktion deaktivieren)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Zeitzone
date_default_timezone_set('Europe/Berlin');

// Pfad-Konstanten
define('BASE_PATH', realpath(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR);
define('UPLOAD_PATH', BASE_PATH . 'uploads' . DIRECTORY_SEPARATOR);
define('PROCESSED_PATH', BASE_PATH . 'processed' . DIRECTORY_SEPARATOR);
define('TEMP_PATH', BASE_PATH . 'temp' . DIRECTORY_SEPARATOR);

// Upload-Einstellungen
define('MAX_FILES', 50);
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50 MB pro Datei
define('ALLOWED_TYPES', ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp']);

// Bildverarbeitungs-Einstellungen
define('DEFAULT_QUALITY', 85);
define('THUMBNAIL_SIZE', 300);
define('MAX_WIDTH', 4096);
define('MAX_HEIGHT', 4096);

// Session-Einstellungen
define('SESSION_LIFETIME', 3600); // 1 Stunde

// Ausgabeformate
define('OUTPUT_FORMATS', [
    'jpeg' => ['name' => 'JPEG', 'extension' => 'jpg', 'mime' => 'image/jpeg'],
    'png' => ['name' => 'PNG', 'extension' => 'png', 'mime' => 'image/png'],
    'webp' => ['name' => 'WebP', 'extension' => 'webp', 'mime' => 'image/webp']
]);

// Komprimierungseinstellungen nach Format
define('COMPRESSION_SETTINGS', [
    'jpeg' => [
        'quality_range' => [60, 100],
        'default_quality' => 85,
        'progressive' => true
    ],
    'png' => [
        'compression_level' => 9,
        'filters' => PNG_ALL_FILTERS
    ],
    'webp' => [
        'quality_range' => [60, 100],
        'default_quality' => 85,
        'method' => 6
    ]
]);

// Debug-Modus
define('DEBUG_MODE', false);

// Erstelle notwendige Verzeichnisse, falls nicht vorhanden
$directories = [UPLOAD_PATH, PROCESSED_PATH, TEMP_PATH];
foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        if (!mkdir($dir, 0755, true)) {
            error_log("Konnte Verzeichnis nicht erstellen: " . $dir);
        }
    }
}

// Session-Konfiguration
// Nur setzen, wenn noch keine Session aktiv ist
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
    session_set_cookie_params(SESSION_LIFETIME);
}

// Hilfsfunktionen
/**
 * Generiert einen eindeutigen Dateinamen
 */
function generateUniqueFilename($extension) {
    return uniqid('img_', true) . '.' . $extension;
}

/**
 * Bereinigt Dateinamen
 */
function sanitizeFilename($filename) {
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
    return substr($filename, 0, 255);
}

/**
 * Formatiert Dateigrößen
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

/**
 * Bereinigt alte temporäre Dateien
 */
function cleanupOldFiles() {
    $directories = [UPLOAD_PATH, PROCESSED_PATH, TEMP_PATH];
    $maxAge = 3600; // 1 Stunde
    
    foreach ($directories as $dir) {
        if (is_dir($dir)) {
            $files = glob($dir . '*');
            foreach ($files as $file) {
                if (is_file($file) && (time() - filemtime($file)) > $maxAge) {
                    @unlink($file);
                }
            }
        }
    }
}

/**
 * Überprüft PHP-Konfiguration und gibt Empfehlungen
 */
function checkPHPConfiguration() {
    $issues = [];
    
    // Prüfe max_execution_time
    $maxExecTime = ini_get('max_execution_time');
    if ($maxExecTime < 300 && $maxExecTime != 0) {
        $issues[] = "max_execution_time ist zu niedrig ($maxExecTime). Empfohlen: 300";
    }
    
    // Prüfe memory_limit
    $memoryLimit = ini_get('memory_limit');
    $memoryBytes = convertToBytes($memoryLimit);
    if ($memoryBytes < 256 * 1024 * 1024) {
        $issues[] = "memory_limit ist zu niedrig ($memoryLimit). Empfohlen: 256M";
    }
    
    // Prüfe post_max_size
    $postMaxSize = ini_get('post_max_size');
    $postMaxBytes = convertToBytes($postMaxSize);
    if ($postMaxBytes < 100 * 1024 * 1024) {
        $issues[] = "post_max_size ist zu niedrig ($postMaxSize). Empfohlen: 100M";
    }
    
    // Prüfe upload_max_filesize
    $uploadMaxSize = ini_get('upload_max_filesize');
    $uploadMaxBytes = convertToBytes($uploadMaxSize);
    if ($uploadMaxBytes < 50 * 1024 * 1024) {
        $issues[] = "upload_max_filesize ist zu niedrig ($uploadMaxSize). Empfohlen: 50M";
    }
    
    // Prüfe GD Library
    if (!extension_loaded('gd')) {
        $issues[] = "GD Library ist nicht installiert. Diese ist erforderlich für die Bildverarbeitung.";
    }
    
    return $issues;
}

/**
 * Konvertiert PHP-Größenangaben in Bytes
 */
function convertToBytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;
    switch($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    return $val;
}

// Prüfe Konfiguration bei Debug-Modus
if (DEBUG_MODE === true) {
    $configIssues = checkPHPConfiguration();
    if (!empty($configIssues)) {
        error_log("PHP-Konfigurationsprobleme gefunden:");
        foreach ($configIssues as $issue) {
            error_log("- " . $issue);
        }
    }
}