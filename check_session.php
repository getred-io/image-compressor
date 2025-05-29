<?php
/**
 * Session-Status Überprüfung
 * Dateipfad: /image-compressor/check_session.php
 * 
 * Gibt den aktuellen Session-Status zurück
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

$response = [
    'session_id' => session_id(),
    'has_uploaded_files' => isset($_SESSION['uploaded_files']) && !empty($_SESSION['uploaded_files']),
    'uploaded_count' => isset($_SESSION['uploaded_files']) ? count($_SESSION['uploaded_files']) : 0,
    'has_processed_files' => isset($_SESSION['processed_files']) && !empty($_SESSION['processed_files']),
    'processed_count' => isset($_SESSION['processed_files']) ? count($_SESSION['processed_files']) : 0,
    'upload_session_id' => $_SESSION['upload_session_id'] ?? null
];

// Wenn angefordert, gebe auch die Datei-IDs zurück
if (isset($_GET['include_files']) && $_GET['include_files'] === '1') {
    $response['uploaded_files'] = [];
    if (isset($_SESSION['uploaded_files'])) {
        foreach ($_SESSION['uploaded_files'] as $id => $file) {
            $response['uploaded_files'][] = [
                'id' => $id,
                'name' => $file['original_name'],
                'exists' => file_exists($file['path'])
            ];
        }
    }
}

echo json_encode($response);