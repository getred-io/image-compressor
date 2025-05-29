<?php
/**
 * FileManager Klasse
 * Dateipfad: /image-compressor/classes/FileManager.php
 * 
 * Verwaltung von Dateien und ZIP-Archiven
 */

// Lade Sprachkonfiguration wenn noch nicht geladen
if (!function_exists('__')) {
    require_once dirname(__DIR__) . '/config/lang_config.php';
}

class FileManager {
    private $tempDir;
    private $processedDir;
    
    /**
     * Konstruktor
     */
    public function __construct() {
        $this->tempDir = TEMP_PATH;
        $this->processedDir = PROCESSED_PATH;
        
        // Stelle sicher, dass Verzeichnisse existieren
        $this->ensureDirectoryExists($this->tempDir);
        $this->ensureDirectoryExists($this->processedDir);
    }
    
    /**
     * Stellt sicher, dass ein Verzeichnis existiert
     */
    private function ensureDirectoryExists($directory) {
        if (!file_exists($directory)) {
            if (!mkdir($directory, 0755, true)) {
                throw new Exception(__('errors.directoryCreationFailed', ['directory' => $directory]));
            }
        }
    }
    
    /**
     * Erstellt ein ZIP-Archiv aus mehreren Dateien
     */
    public function createZipArchive($files, $outputName = null) {
        if (empty($files)) {
            throw new Exception(__('errors.noFilesForZip'));
        }
        
        // Generiere Ausgabename falls nicht angegeben
        if ($outputName === null) {
            $outputName = 'images_' . date('Y-m-d_H-i-s') . '.zip';
        }
        
        $zipPath = $this->tempDir . $outputName;
        
        // Erstelle ZIP-Archiv
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception(__('errors.zipCreationFailed'));
        }
        
        // Füge Dateien hinzu
        foreach ($files as $file) {
            if (file_exists($file['path'])) {
                // Verwende originalen Namen oder generierten Namen
                $entryName = isset($file['archive_name']) ? $file['archive_name'] : basename($file['path']);
                $zip->addFile($file['path'], $entryName);
            }
        }
        
        // Schließe ZIP
        $zip->close();
        
        if (!file_exists($zipPath)) {
            throw new Exception(__('errors.zipCreationFailed'));
        }
        
        return [
            'path' => $zipPath,
            'name' => $outputName,
            'size' => filesize($zipPath),
            'file_count' => count($files)
        ];
    }
    
    /**
     * Sendet eine Datei zum Download
     */
    public function sendFileDownload($filePath, $downloadName = null) {
        if (!file_exists($filePath)) {
            throw new Exception(__('errors.fileNotFound'));
        }
        
        // Bestimme Download-Namen
        if ($downloadName === null) {
            $downloadName = basename($filePath);
        }
        
        // Bestimme MIME-Type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);
        
        // Setze Header
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Sende Datei
        readfile($filePath);
        
        // Optional: Lösche temporäre Datei
        if (strpos($filePath, $this->tempDir) === 0) {
            unlink($filePath);
        }
        
        exit;
    }
    
    /**
     * Löscht alte temporäre Dateien
     */
    public function cleanupOldFiles($maxAge = 3600) {
        $directories = [$this->tempDir, $this->processedDir, UPLOAD_PATH];
        $deletedCount = 0;
        
        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            
            $files = scandir($dir);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                
                $filePath = $dir . $file;
                if (is_file($filePath) && (time() - filemtime($filePath)) > $maxAge) {
                    if (unlink($filePath)) {
                        $deletedCount++;
                    }
                }
            }
        }
        
        return $deletedCount;
    }
    
    /**
     * Kopiert eine Datei mit neuem Namen
     */
    public function copyFile($sourcePath, $destinationDir, $newName = null) {
        if (!file_exists($sourcePath)) {
            throw new Exception(__('errors.fileNotFound'));
        }
        
        $this->ensureDirectoryExists($destinationDir);
        
        if ($newName === null) {
            $newName = basename($sourcePath);
        }
        
        $destinationPath = $destinationDir . $newName;
        
        if (!copy($sourcePath, $destinationPath)) {
            throw new Exception(__('errors.copyFileFailed'));
        }
        
        return $destinationPath;
    }
    
    /**
     * Verschiebt eine Datei
     */
    public function moveFile($sourcePath, $destinationDir, $newName = null) {
        $destinationPath = $this->copyFile($sourcePath, $destinationDir, $newName);
        
        if (!unlink($sourcePath)) {
            throw new Exception(__('errors.deleteOriginalFailed'));
        }
        
        return $destinationPath;
    }
    
    /**
     * Gibt verfügbaren Speicherplatz zurück
     */
    public function getAvailableSpace() {
        $freeSpace = disk_free_space($this->tempDir);
        return [
            'bytes' => $freeSpace,
            'formatted' => formatFileSize($freeSpace)
        ];
    }
    
    /**
     * Erstellt einen sicheren Dateinamen
     */
    public function createSafeFilename($originalName, $extension = null) {
        // Entferne Dateierweiterung
        $name = pathinfo($originalName, PATHINFO_FILENAME);
        
        // Ersetze unsichere Zeichen
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
        
        // Begrenze Länge
        $name = substr($name, 0, 100);
        
        // Füge Zeitstempel hinzu für Eindeutigkeit
        $name .= '_' . time();
        
        // Füge Erweiterung hinzu
        if ($extension === null) {
            $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        }
        
        return $name . '.' . $extension;
    }
    
    /**
     * Prüft ob genug Speicherplatz vorhanden ist
     */
    public function hasEnoughSpace($requiredBytes) {
        $available = disk_free_space($this->tempDir);
        return $available > $requiredBytes;
    }
}