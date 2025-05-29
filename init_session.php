<?php
/**
 * Session-Initialisierung
 * Dateipfad: /image-compressor/init_session.php
 * 
 * Diese Datei wird am Anfang jeder Seite eingebunden um sicherzustellen,
 * dass alte Sessions bereinigt werden
 */

// Starte Session falls noch nicht aktiv
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Prüft ob eine neue Session gestartet werden soll
 */
function checkAndResetSession() {
    // Prüfe ob Reset angefordert wurde
    if (isset($_GET['reset']) && $_GET['reset'] === '1') {
        resetImageSession();
        header('Location: index.php');
        exit;
    }
    
    // Prüfe ob Session zu alt ist (älter als 1 Stunde)
    if (isset($_SESSION['session_start_time'])) {
        $sessionAge = time() - $_SESSION['session_start_time'];
        if ($sessionAge > 3600) { // 1 Stunde
            resetImageSession();
        }
    } else {
        $_SESSION['session_start_time'] = time();
    }
}

/**
 * Setzt die Bild-Session zurück
 */
function resetImageSession() {
    // Lösche alle hochgeladenen Dateien
    if (isset($_SESSION['uploaded_files'])) {
        foreach ($_SESSION['uploaded_files'] as $file) {
            if (file_exists($file['path'])) {
                unlink($file['path']);
            }
        }
    }
    
    // Lösche alle verarbeiteten Dateien
    if (isset($_SESSION['processed_files'])) {
        foreach ($_SESSION['processed_files'] as $file) {
            if (file_exists($file['processed_path'])) {
                unlink($file['processed_path']);
            }
            if (file_exists($file['thumbnail_path'])) {
                unlink($file['thumbnail_path']);
            }
        }
    }
    
    // Setze Session-Variablen zurück
    unset($_SESSION['uploaded_files']);
    unset($_SESSION['processed_files']);
    unset($_SESSION['upload_session_id']);
    $_SESSION['session_start_time'] = time();
}

// Führe Session-Check aus
checkAndResetSession();