<?php
/**
 * Holt die aktuelle Sprache aus der Session
 * Dateipfad: /image-compressor/get_language.php
 */

session_start();
require_once 'config/config.php';
require_once 'config/lang_config.php';

header('Content-Type: application/json; charset=utf-8');

// Hole aktuelle Sprache
$currentLang = detectLanguage();

echo json_encode([
    'language' => $currentLang,
    'available' => AVAILABLE_LANGUAGES
]);