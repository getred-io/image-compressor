<?php
/**
 * Upload-Handler
 * Dateipfad: /image-compressor/upload.php
 * 
 * Verarbeitet den asynchronen Upload von Bilddateien
 */

session_start();
require_once 'config/config.php';
require_once 'config/lang_config.php';

// Setze JSON-Header
header('Content-Type: application/json');

// Prüfe Request-Methode
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => __('errors.methodNotAllowed')]);
    exit;
}

// Initialisiere Session-Arrays falls nicht vorhanden
if (!isset($_SESSION['uploaded_files'])) {
    $_SESSION['uploaded_files'] = [];
}

// Neue Session-ID für diese Upload-Gruppe
if (!isset($_SESSION['upload_session_id'])) {
    $_SESSION['upload_session_id'] = uniqid('session_', true);
    // Bereinige alte Dateien aus vorherigen Sessions
    cleanupOldFiles();
}

// Prüfe ob Dateien hochgeladen wurden
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => __('errors.noFileUploaded')]);
    exit;
}

// Prüfe Anzahl der bereits hochgeladenen Dateien
if (count($_SESSION['uploaded_files']) >= MAX_FILES) {
    http_response_code(400);
    echo json_encode(['error' => __('errors.maxFilesReached', ['max' => MAX_FILES])]);
    exit;
}

$uploadedFile = $_FILES['file'];

// Validiere Dateityp
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $uploadedFile['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, ALLOWED_TYPES)) {
    http_response_code(400);
    echo json_encode(['error' => __('errors.unsupportedFileType', ['type' => $mimeType])]);
    exit;
}

// Validiere Dateigröße
if ($uploadedFile['size'] > MAX_FILE_SIZE) {
    http_response_code(400);
    echo json_encode(['error' => __('errors.fileTooLarge', ['max' => formatFileSize(MAX_FILE_SIZE)])]);
    exit;
}

// Generiere eindeutigen Dateinamen
$fileExtension = pathinfo($uploadedFile['name'], PATHINFO_EXTENSION);
$uniqueFilename = generateUniqueFilename($fileExtension);
$uploadPath = UPLOAD_PATH . $uniqueFilename;

// Verschiebe Datei in Upload-Verzeichnis
if (!move_uploaded_file($uploadedFile['tmp_name'], $uploadPath)) {
    http_response_code(500);
    echo json_encode(['error' => __('errors.saveFileFailed')]);
    exit;
}

// Hole Bildinfos
$imageInfo = getimagesize($uploadPath);
if ($imageInfo === false) {
    unlink($uploadPath);
    http_response_code(400);
    echo json_encode(['error' => __('errors.invalidImageFile')]);
    exit;
}

// Speichere Dateiinformationen in Session
$fileData = [
    'id' => $uniqueFilename,
    'original_name' => sanitizeFilename($uploadedFile['name']),
    'filename' => $uniqueFilename,
    'size' => $uploadedFile['size'],
    'mime_type' => $mimeType,
    'width' => $imageInfo[0],
    'height' => $imageInfo[1],
    'upload_time' => time(),
    'path' => $uploadPath
];

$_SESSION['uploaded_files'][$uniqueFilename] = $fileData;

// Bereinige alte Dateien
cleanupOldFiles();

// Sende Erfolgsantwort
echo json_encode([
    'success' => true,
    'file' => [
        'id' => $fileData['id'],
        'name' => $fileData['original_name'],
        'size' => formatFileSize($fileData['size']),
        'dimensions' => $fileData['width'] . ' x ' . $fileData['height'] . ' px',
        'type' => $fileData['mime_type']
    ],
    'total_files' => count($_SESSION['uploaded_files'])
]);