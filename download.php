<?php
/**
 * Download-Handler
 * Dateipfad: /image-compressor/download.php
 * 
 * Verwaltet Downloads von einzelnen Dateien und ZIP-Archiven
 */

session_start();
require_once 'config/config.php';
require_once 'classes/FileManager.php';

// Prüfe ob verarbeitete Dateien vorhanden sind
if (!isset($_SESSION['processed_files']) || empty($_SESSION['processed_files'])) {
    http_response_code(404);
    die('Keine verarbeiteten Dateien gefunden');
}

$fileManager = new FileManager();

// Bestimme Download-Typ
$action = $_GET['action'] ?? 'zip';
$fileId = $_GET['id'] ?? null;

try {
    switch ($action) {
        case 'single':
            // Download einzelne Datei
            if (!$fileId || !isset($_SESSION['processed_files'][$fileId])) {
                throw new Exception('Datei nicht gefunden');
            }
            
            $file = $_SESSION['processed_files'][$fileId];
            if (!file_exists($file['processed_path'])) {
                throw new Exception('Verarbeitete Datei nicht gefunden');
            }
            
            // Sende Datei
            $fileManager->sendFileDownload(
                $file['processed_path'], 
                $file['processed_name']
            );
            break;
            
        case 'selected':
            // Download ausgewählte Dateien als ZIP
            if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['files'])) {
                throw new Exception('Keine Dateien ausgewählt');
            }
            
            $selectedIds = $_POST['files'];
            $files = [];
            
            foreach ($selectedIds as $id) {
                if (isset($_SESSION['processed_files'][$id])) {
                    $fileData = $_SESSION['processed_files'][$id];
                    if (file_exists($fileData['processed_path'])) {
                        $files[] = [
                            'path' => $fileData['processed_path'],
                            'archive_name' => $fileData['processed_name']
                        ];
                    }
                }
            }
            
            if (empty($files)) {
                throw new Exception('Keine gültigen Dateien zum Archivieren gefunden');
            }
            
            // Erstelle ZIP mit ausgewählten Dateien
            $zipInfo = $fileManager->createZipArchive($files, 'selected_images_' . date('Y-m-d_H-i-s') . '.zip');
            
            // Sende ZIP
            $fileManager->sendFileDownload($zipInfo['path'], $zipInfo['name']);
            break;
            
        case 'zip':
            // Erstelle ZIP-Archiv mit allen Dateien
            $files = [];
            foreach ($_SESSION['processed_files'] as $fileData) {
                if (file_exists($fileData['processed_path'])) {
                    $files[] = [
                        'path' => $fileData['processed_path'],
                        'archive_name' => $fileData['processed_name']
                    ];
                }
            }
            
            if (empty($files)) {
                throw new Exception('Keine Dateien zum Archivieren gefunden');
            }
            
            // Erstelle ZIP
            $zipInfo = $fileManager->createZipArchive($files);
            
            // Sende ZIP
            $fileManager->sendFileDownload($zipInfo['path'], $zipInfo['name']);
            break;
            
        case 'all':
            // Erstelle JSON mit Download-Links für Ajax-Anfragen
            header('Content-Type: application/json');
            
            $downloadLinks = [];
            foreach ($_SESSION['processed_files'] as $id => $file) {
                $downloadLinks[] = [
                    'id' => $id,
                    'name' => $file['processed_name'],
                    'url' => 'download.php?action=single&id=' . $id,
                    'size' => formatFileSize($file['processed_size'])
                ];
            }
            
            echo json_encode([
                'success' => true,
                'files' => $downloadLinks,
                'zip_url' => 'download.php?action=zip'
            ]);
            exit;
            break;
            
        default:
            throw new Exception('Ungültige Aktion');
    }
    
} catch (Exception $e) {
    // Bei Fehlern
    if ($action === 'all') {
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
    } else {
        http_response_code(500);
        die('Fehler: ' . $e->getMessage());
    }
}