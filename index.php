<?php
/**
 * Image Compressor - Hauptseite
 * Dateipfad: /image-compressor/index.php
 * 
 * Diese Datei stellt die Benutzeroberfläche für den Bild-Upload bereit
 */

require_once 'config/config.php';
require_once 'init_session.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-i18n="app.title">Bildkomprimierung</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1 data-i18n="app.title">Bildkomprimierung</h1>
            <p data-i18n="app.subtitle">Laden Sie bis zu 50 Bilder gleichzeitig hoch</p>
        </header>

        <main>
            <!-- Upload-Bereich -->
            <div class="upload-section">
                <form id="uploadForm" enctype="multipart/form-data">
                    <div class="upload-area" id="uploadArea" data-i18n-title="tooltips.uploadArea">
                        <div class="upload-icon" aria-hidden="true">📁</div>
                        <p data-i18n="upload.dragDrop">Dateien hier ablegen oder klicken zum Auswählen</p>
                        <p class="upload-info" data-i18n="upload.maxFiles">Maximal 50 Dateien gleichzeitig</p>
                    </div>
                    <input type="file" id="fileInput" name="files[]" multiple accept="image/*" hidden>
                </form>

                <!-- Fortschrittsanzeige -->
                <div class="progress-section" id="progressSection" style="display: none;">
                    <h3 data-i18n="upload.progressTitle">Upload-Fortschritt</h3>
                    <div class="progress-bar">
                        <div class="progress-fill" id="progressFill"></div>
                    </div>
                    <p class="progress-text" id="progressText">0 von 0 Dateien hochgeladen</p>
                </div>

                <!-- Dateiliste -->
                <div class="file-list" id="fileList" style="display: none;">
                    <h3 data-i18n="upload.selectedFiles">Ausgewählte Dateien</h3>
                    <ul id="fileListItems"></ul>
                </div>
            </div>

            <!-- Analyse-Ergebnisse -->
            <div class="analysis-section" id="analysisSection" style="display: none;">
                <h2 data-i18n="analysis.title">Bildanalyse abgeschlossen</h2>
                <div class="analysis-results" id="analysisResults"></div>
                
                <!-- Format-Auswahl -->
                <div class="format-selection">
                    <h3 data-i18n="analysis.selectFormat">Ausgabeformat wählen</h3>
                    <form id="processForm">
                        <div class="format-options" id="formatOptions"></div>
                        <div class="quality-settings">
                            <label for="quality" data-i18n="analysis.quality">Qualität</label> (1-100):
                            <input type="range" id="quality" name="quality" min="1" max="100" value="85" data-i18n-title="tooltips.qualitySlider">
                            <span id="qualityValue">85</span>
                        </div>
                        <button type="submit" class="btn btn-primary" data-i18n="analysis.processButton">Bilder verarbeiten</button>
                    </form>
                </div>
            </div>

            <!-- Ergebnis-Anzeige -->
            <div class="results-section" id="resultsSection" style="display: none;">
                <h2 data-i18n="results.title">Verarbeitete Bilder</h2>
                <div class="results-grid" id="resultsGrid"></div>
                <div class="download-options" id="downloadOptions"></div>
            </div>
        </main>

        <footer>
            <p data-i18n="footer.copyright">&copy; 2025 marcbackes.net - Image Compressor</p>
        </footer>
    </div>

    <script src="assets/js/language.js"></script>
    <script src="assets/js/app.js"></script>
    <script>
        // Initialisiere Sprache beim Laden
        document.addEventListener('DOMContentLoaded', async () => {
            await lang.init();
            // Erst danach die App initialisieren
            initializeApp();
        });
    </script>
</body>
</html>