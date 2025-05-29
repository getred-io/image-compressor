<?php
/**
 * AJAX Batch-Verarbeitung
 * Dateipfad: /image-compressor/process_ajax.php
 * 
 * Verarbeitet Bilder einzeln über AJAX um Timeouts zu vermeiden
 */

// Fehlerbehandlung
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Erhöhe Limits für einzelne Bildverarbeitung
@set_time_limit(60); // 1 Minute pro Bild
@ini_set('memory_limit', '128M');

session_start();
require_once 'config/config.php';
require_once 'classes/ImageProcessor.php';
require_once 'classes/FileManager.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Prüfe Request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Methode nicht erlaubt', 405);
    }

    // Hole Parameter
    $fileId = $_POST['file_id'] ?? null;
    $format = $_POST['format'] ?? 'jpeg';
    $quality = intval($_POST['quality'] ?? DEFAULT_QUALITY);

    // NEU: Warnung bei sehr hoher Qualität
    if ($quality > 95 && ($format === 'jpeg' || $format === 'webp')) {
        error_log("Warnung: Sehr hohe Qualität ($quality) kann zu größeren Dateien führen");
    }

    if (!$fileId) {
        throw new Exception('Keine Datei-ID angegeben', 400);
    }
    
    if (!$fileId) {
        throw new Exception('Keine Datei-ID angegeben', 400);
    }
    
    // Prüfe ob Datei in Session existiert
    if (!isset($_SESSION['uploaded_files'][$fileId])) {
        throw new Exception('Datei nicht gefunden', 404);
    }
    
    $fileData = $_SESSION['uploaded_files'][$fileId];
    
    // Prüfe ob Datei existiert
    if (!file_exists($fileData['path'])) {
        throw new Exception('Originaldatei nicht gefunden', 404);
    }
    
    // Initialisiere Klassen
    $processor = new ImageProcessor();
    $fileManager = new FileManager();
    
    // Lade und verarbeite Bild
    $processor->loadImage($fileData['path']);
    $processor->setQuality($quality);
    
    // Generiere Ausgabedateiname
    $outputExtension = OUTPUT_FORMATS[$format]['extension'];
    $outputFilename = pathinfo($fileData['original_name'], PATHINFO_FILENAME) . '.' . $outputExtension;
    $outputFilename = $fileManager->createSafeFilename($outputFilename, $outputExtension);
    $outputPath = PROCESSED_PATH . $outputFilename;
    
    // Verarbeite Bild
    if (!$processor->process($outputPath, $format)) {
        throw new Exception('Fehler bei der Bildverarbeitung');
    }
    
    // Erstelle Thumbnail
    $thumbnailFilename = 'thumb_' . $outputFilename;
    $thumbnailPath = PROCESSED_PATH . $thumbnailFilename;
    $processor->createThumbnail($thumbnailPath);
    
    // Berechne Statistiken
    $originalSize = filesize($fileData['path']);
    $processedSize = filesize($outputPath);
    $savings = $originalSize - $processedSize;
    $savingsPercent = ($originalSize > 0) ? ($savings / $originalSize) * 100 : 0;
    
    // Speichere in Session
    if (!isset($_SESSION['processed_files'])) {
        $_SESSION['processed_files'] = [];
    }
    
    $_SESSION['processed_files'][$fileId] = [
        'id' => $fileId,
        'original_name' => $fileData['original_name'],
        'processed_name' => $outputFilename,
        'processed_path' => $outputPath,
        'thumbnail_path' => $thumbnailPath,
        'format' => $format,
        'quality' => $quality,
        'original_size' => $originalSize,
        'processed_size' => $processedSize,
        'savings' => $savings,
        'savings_percent' => $savingsPercent
    ];
    
    // Lösche Original nach erfolgreicher Verarbeitung
    @unlink($fileData['path']);
    unset($_SESSION['uploaded_files'][$fileId]);
    
    // Sende Erfolgsantwort
    echo json_encode([
        'success' => true,
        'file' => [
            'id' => $fileId,
            'original_name' => $fileData['original_name'],
            'processed_name' => $outputFilename,
            'thumbnail_url' => 'processed/' . $thumbnailFilename,
            'original_size' => formatFileSize($originalSize),
            'processed_size' => formatFileSize($processedSize),
            'savings' => formatFileSize($savings),
            'savings_percent' => round($savingsPercent, 1)
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}