<?php
/**
 * API zum Setzen der Sprache
 * Dateipfad: /image-compressor/set_language.php
 */

session_start();
require_once 'config/config.php';
require_once 'config/lang_config.php';

header('Content-Type: application/json; charset=utf-8');

// Prüfe Request-Methode
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Hole Sprache aus Request
$lang = $_POST['lang'] ?? null;

if (!$lang) {
    http_response_code(400);
    echo json_encode(['error' => 'No language specified']);
    exit;
}

// Setze Sprache
if (setLanguage($lang)) {
    echo json_encode([
        'success' => true,
        'language' => $lang,
        'message' => __('app.title') // Test-Übersetzung
    ]);
} else {
    http_response_code(400);
    echo json_encode([
        'error' => 'Invalid language',
        'available' => AVAILABLE_LANGUAGES
    ]);
}