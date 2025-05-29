<?php
/**
 * Bildverarbeitung
 * Dateipfad: /image-compressor/process.php
 * 
 * Verarbeitet Bilder mit ausgewählten Einstellungen
 */

// Fehlerbehandlung - keine HTML-Ausgabe
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Output Buffering starten um unerwartete Ausgaben zu verhindern
ob_start();

// Erhöhe Limits für Bildverarbeitung BEVOR Session startet
@set_time_limit(300); // 5 Minuten
@ini_set('memory_limit', '256M');
@ini_set('max_execution_time', '300');

// Ignoriere Benutzerabbruch
ignore_user_abort(true);

session_start();
require_once 'config/config.php';
require_once 'classes/ImageProcessor.php';
require_once 'classes/FileManager.php';

// Setze JSON-Header
header('Content-Type: application/json; charset=utf-8');

try {
    // Prüfe Request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Methode nicht erlaubt', 405);
    }

    // Hole Parameter
    $format = $_POST['format'] ?? 'jpeg';
    $quality = intval($_POST['quality'] ?? DEFAULT_QUALITY);

    // Warnung bei sehr hoher Qualität
    if ($quality > 95 && ($format === 'jpeg' || $format === 'webp')) {
        error_log("Warnung: Sehr hohe Qualität ($quality) kann zu größeren Dateien führen");
    }

    // Validiere Format
    if (!isset(OUTPUT_FORMATS[$format])) {
        throw new Exception('Ungültiges Ausgabeformat', 400);
    }

    // Prüfe Session-Dateien
    if (!isset($_SESSION['uploaded_files']) || empty($_SESSION['uploaded_files'])) {
        throw new Exception('Keine Dateien zum Verarbeiten', 400);
    }

    // Initialisiere Klassen
    $fileManager = new FileManager();

    // Speichere verarbeitete Dateien
    $_SESSION['processed_files'] = [];
    $results = [];
    $errors = [];

    // Verarbeite jede Datei
    foreach ($_SESSION['uploaded_files'] as $fileId => $fileData) {
        try {
            // Prüfe ob Datei existiert
            if (!file_exists($fileData['path'])) {
                throw new Exception('Originaldatei nicht gefunden');
            }
            
            // Erstelle neuen Processor für jede Datei (um Speicher freizugeben)
            $processor = new ImageProcessor();
            
            // Lade Bild
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
            
            // Zerstöre Processor explizit um Speicher freizugeben
            $processor->__destruct();
            unset($processor);
            
            // Sammle Ergebnisse
            $originalSize = filesize($fileData['path']);
            $processedSize = filesize($outputPath);
            $savings = $originalSize - $processedSize;
            $savingsPercent = ($originalSize > 0) ? ($savings / $originalSize) * 100 : 0;
            
            $processedData = [
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
            
            $_SESSION['processed_files'][$fileId] = $processedData;
            
            $results[] = [
                'id' => $fileId,
                'success' => true,
                'original_name' => $fileData['original_name'],
                'processed_name' => $outputFilename,
                'thumbnail_url' => 'processed/' . $thumbnailFilename,
                'original_size' => formatFileSize($originalSize),
                'processed_size' => formatFileSize($processedSize),
                'savings' => formatFileSize($savings),
                'savings_percent' => round($savingsPercent, 1)
            ];
            
            // Explizite Garbage Collection nach jeder Datei
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
            
        } catch (Exception $e) {
            $errors[] = [
                'id' => $fileId,
                'name' => $fileData['original_name'],
                'error' => $e->getMessage()
            ];
            
            // Log detaillierte Fehler
            error_log('Bildverarbeitung Fehler: ' . $e->getMessage() . ' für Datei: ' . $fileData['original_name']);
        }
    }

    // Berechne Gesamtstatistiken
    $totalOriginalSize = 0;
    $totalProcessedSize = 0;
    foreach ($_SESSION['processed_files'] as $file) {
        $totalOriginalSize += $file['original_size'];
        $totalProcessedSize += $file['processed_size'];
    }

    $totalSavings = $totalOriginalSize - $totalProcessedSize;
    $totalSavingsPercent = $totalOriginalSize > 0 ? ($totalSavings / $totalOriginalSize) * 100 : 0;

    // Bereinige alte Dateien
    $fileManager->cleanupOldFiles();

    // Lösche Original-Uploads nach erfolgreicher Verarbeitung
    foreach ($_SESSION['uploaded_files'] as $fileId => $fileData) {
        if (isset($_SESSION['processed_files'][$fileId]) && file_exists($fileData['path'])) {
            @unlink($fileData['path']);
        }
    }

    // Leere Upload-Session für nächsten Durchgang
    unset($_SESSION['uploaded_files']);
    unset($_SESSION['upload_session_id']);

    // Sende erfolgreiche Antwort
    $response = [
        'success' => count($results) > 0,
        'processed_count' => count($results),
        'error_count' => count($errors),
        'results' => $results,
        'errors' => $errors,
        'statistics' => [
            'total_original_size' => formatFileSize($totalOriginalSize),
            'total_processed_size' => formatFileSize($totalProcessedSize),
            'total_savings' => formatFileSize($totalSavings),
            'total_savings_percent' => round($totalSavingsPercent, 1)
        ]
    ];
    
    // Lösche Output Buffer und sende Response
    ob_end_clean();
    echo json_encode($response);

} catch (Exception $e) {
    // Bei Fehlern: Lösche Output Buffer
    ob_end_clean();
    
    // Setze korrekten HTTP-Status
    $statusCode = $e->getCode() ?: 500;
    http_response_code($statusCode);
    
    // Sende JSON-Fehlerantwort
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'processed_count' => 0,
        'error_count' => 1,
        'results' => [],
        'errors' => []
    ]);
}