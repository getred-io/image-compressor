<?php
/**
 * Lade verarbeitete Dateien aus der Session
 * Dateipfad: /image-compressor/get_processed_files.php
 * 
 * Gibt alle bereits verarbeiteten Dateien zurück
 */

session_start();
require_once 'config/config.php';

header('Content-Type: application/json; charset=utf-8');

$response = [
    'success' => true,
    'files' => []
];

// Prüfe ob verarbeitete Dateien in der Session sind
if (isset($_SESSION['processed_files']) && !empty($_SESSION['processed_files'])) {
    foreach ($_SESSION['processed_files'] as $fileId => $fileData) {
        // Prüfe ob Dateien noch existieren
        $fileExists = file_exists($fileData['processed_path']);
        $thumbnailExists = file_exists($fileData['thumbnail_path']);
        
        if ($fileExists) {
            // Formatiere Daten für JavaScript
            $response['files'][] = [
                'id' => $fileId,
                'original_name' => $fileData['original_name'],
                'processed_name' => $fileData['processed_name'],
                'thumbnail_url' => $thumbnailExists ? 'processed/' . basename($fileData['thumbnail_path']) : '',
                'original_size' => formatFileSize($fileData['original_size']),
                'processed_size' => formatFileSize($fileData['processed_size']),
                'savings' => formatFileSize($fileData['savings']),
                'savings_percent' => round($fileData['savings_percent'], 1),
                'format' => $fileData['format'],
                'quality' => $fileData['quality']
            ];
        }
    }
}

echo json_encode($response);