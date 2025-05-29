<?php
/**
 * Debug-Datei für Bildkomprimierung
 * Dateipfad: /image-compressor/debug.php
 * 
 * Diese Datei hilft bei der Diagnose von Problemen
 * WICHTIG: Nach der Fehlersuche wieder löschen!
 */

session_start();
require_once 'config/config.php';

// Setze HTML-Header für bessere Lesbarkeit
header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html><head><title>Debug Info</title></head><body>";
echo "<h1>PHP Konfiguration Debug</h1>";

// PHP Version
echo "<h2>PHP Version</h2>";
echo "<p>" . phpversion() . "</p>";

// Wichtige PHP Einstellungen
echo "<h2>PHP Einstellungen</h2>";
echo "<ul>";
echo "<li>max_execution_time: " . ini_get('max_execution_time') . " Sekunden</li>";
echo "<li>memory_limit: " . ini_get('memory_limit') . "</li>";
echo "<li>post_max_size: " . ini_get('post_max_size') . "</li>";
echo "<li>upload_max_filesize: " . ini_get('upload_max_filesize') . "</li>";
echo "<li>max_file_uploads: " . ini_get('max_file_uploads') . "</li>";
echo "</ul>";

// GD Library
echo "<h2>GD Library</h2>";
if (extension_loaded('gd')) {
    $gd_info = gd_info();
    echo "<p>GD ist installiert!</p>";
    echo "<pre>" . print_r($gd_info, true) . "</pre>";
} else {
    echo "<p style='color:red'>GD Library ist NICHT installiert!</p>";
}

// Verzeichnisse
echo "<h2>Verzeichnisse</h2>";
$dirs = [
    'Upload' => UPLOAD_PATH,
    'Processed' => PROCESSED_PATH,
    'Temp' => TEMP_PATH
];

foreach ($dirs as $name => $path) {
    echo "<h3>$name: $path</h3>";
    if (file_exists($path)) {
        echo "<p style='color:green'>✓ Existiert</p>";
        echo "<p>Schreibbar: " . (is_writable($path) ? "<span style='color:green'>✓ Ja</span>" : "<span style='color:red'>✗ Nein</span>") . "</p>";
        
        // Zeige Dateien im Verzeichnis
        $files = scandir($path);
        $files = array_diff($files, ['.', '..']);
        echo "<p>Dateien: " . count($files) . "</p>";
        if (count($files) > 0) {
            echo "<ul>";
            foreach ($files as $file) {
                $fullPath = $path . $file;
                echo "<li>$file (" . formatFileSize(filesize($fullPath)) . ")</li>";
            }
            echo "</ul>";
        }
    } else {
        echo "<p style='color:red'>✗ Existiert nicht</p>";
    }
}

// Session Daten
echo "<h2>Session Daten</h2>";
echo "<h3>Session ID: " . session_id() . "</h3>";

if (isset($_SESSION['uploaded_files'])) {
    echo "<h4>Hochgeladene Dateien:</h4>";
    echo "<pre>" . print_r($_SESSION['uploaded_files'], true) . "</pre>";
} else {
    echo "<p>Keine hochgeladenen Dateien in der Session</p>";
}

if (isset($_SESSION['processed_files'])) {
    echo "<h4>Verarbeitete Dateien:</h4>";
    echo "<pre>" . print_r($_SESSION['processed_files'], true) . "</pre>";
} else {
    echo "<p>Keine verarbeiteten Dateien in der Session</p>";
}

// Error Log (letzte 20 Zeilen)
echo "<h2>Letzte Fehler aus error_log</h2>";
$errorLog = ini_get('error_log');
if ($errorLog && file_exists($errorLog)) {
    $lines = file($errorLog);
    $lastLines = array_slice($lines, -20);
    echo "<pre>";
    foreach ($lastLines as $line) {
        echo htmlspecialchars($line);
    }
    echo "</pre>";
} else {
    echo "<p>Kein Zugriff auf error_log</p>";
}

// Test-Upload Formular
echo "<h2>Test-Upload</h2>";
echo '<form action="upload.php" method="post" enctype="multipart/form-data">';
echo '<input type="file" name="file" accept="image/*">';
echo '<input type="submit" value="Test Upload">';
echo '</form>';

// Test-Verarbeitung
if (isset($_SESSION['uploaded_files']) && count($_SESSION['uploaded_files']) > 0) {
    echo "<h2>Test-Verarbeitung</h2>";
    echo '<form action="process.php" method="post">';
    echo '<input type="hidden" name="format" value="jpeg">';
    echo '<input type="hidden" name="quality" value="85">';
    echo '<input type="submit" value="Test Verarbeitung">';
    echo '</form>';
}

echo "</body></html>";