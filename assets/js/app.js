/**
 * JavaScript für Bildkomprimierung
 * Dateipfad: /image-compressor/assets/js/app.js
 */

// Globale Variablen
let uploadedFiles = [];
let processedFiles = [];
let currentUploadCount = 0;
let totalUploadCount = 0;
let analysisData = null;
let newUploadedFiles = [];
let shownHints = new Set(); 

// Konfiguration
const USE_BATCH_PROCESSING = true; // true = process_ajax.php (einzeln), false = process.php (alle auf einmal)

// DOM-Elemente
const elements = {
    uploadArea: document.getElementById('uploadArea'),
    fileInput: document.getElementById('fileInput'),
    progressSection: document.getElementById('progressSection'),
    progressFill: document.getElementById('progressFill'),
    progressText: document.getElementById('progressText'),
    fileList: document.getElementById('fileList'),
    fileListItems: document.getElementById('fileListItems'),
    analysisSection: document.getElementById('analysisSection'),
    analysisResults: document.getElementById('analysisResults'),
    formatOptions: document.getElementById('formatOptions'),
    processForm: document.getElementById('processForm'),
    resultsSection: document.getElementById('resultsSection'),
    resultsGrid: document.getElementById('resultsGrid'),
    downloadOptions: document.getElementById('downloadOptions'),
    qualitySlider: document.getElementById('quality'),
    qualityValue: document.getElementById('qualityValue')
};

// Event Listeners
document.addEventListener('DOMContentLoaded', async () => {
    // WICHTIG: Erst Sprache laden, dann App initialisieren
    await lang.init();
    initializeApp();
});

/**
 * Initialisiert die Anwendung
 */
function initializeApp() {
    // Reset der Variablen bei Seitenladung (nur wenn reset=1)
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('reset') === '1') {
        resetApplicationState();
    } else {
        // Lade bereits verarbeitete Dateien aus der Session
        loadProcessedFilesFromSession();
    }

    if (window.appInitialized) {
    return;
    }
    window.appInitialized = true;
    // Upload-Area Events
    elements.uploadArea.addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();
    elements.fileInput.click();
    });
    elements.fileInput.addEventListener('change', handleFileSelect);
    
    // Drag & Drop Events
    elements.uploadArea.addEventListener('dragover', handleDragOver);
    elements.uploadArea.addEventListener('drop', handleDrop);
    elements.uploadArea.addEventListener('dragleave', handleDragLeave);
    
    // Qualitäts-Slider
    elements.qualitySlider.addEventListener('input', updateQualityValue);
    // Einsparungen bei Qualitätsänderung aktualisieren
    elements.qualitySlider.addEventListener('input', () => {
        const selectedFormat = document.querySelector('input[name="format"]:checked');
        if (selectedFormat && analysisData) {
            updateEstimatedSavings(selectedFormat.value);
        }
    });
    
    // Verarbeitungs-Formular
    elements.processForm.addEventListener('submit', handleProcessSubmit);
    
    // Verhindere Standard-Drag-Verhalten
    document.addEventListener('dragover', (e) => e.preventDefault());
    document.addEventListener('drop', (e) => e.preventDefault());
}

/**
 * Setzt den Anwendungsstatus zurück
 */
function resetApplicationState() {
    uploadedFiles = [];
    processedFiles = [];
    newUploadedFiles = [];
    currentUploadCount = 0;
    totalUploadCount = 0;
    analysisData = null;
    shownHints.clear();
    
    // Verstecke Bereiche die noch von vorheriger Session sichtbar sein könnten
    if (elements.analysisSection) elements.analysisSection.style.display = 'none';
    if (elements.resultsSection) elements.resultsSection.style.display = 'none';
    if (elements.progressSection) elements.progressSection.style.display = 'none';
    if (elements.fileList) elements.fileList.style.display = 'none';
}

/**
 * Lädt bereits verarbeitete Dateien aus der Session
 */
async function loadProcessedFilesFromSession() {
    try {
        const response = await fetch('get_processed_files.php');
        const data = await response.json();
        
        if (data.success && data.files && data.files.length > 0) {
            processedFiles = data.files;
            
            // Zeige Ergebnisbereich wenn Dateien vorhanden
            elements.resultsSection.style.display = 'block';
            displayPreviouslyProcessedFiles();
            
            // Zeige Download-Optionen
            displayProcessResults({
                success: true,
                processed_count: 0, // Keine neuen verarbeitet
                error_count: 0,
                results: [],
                errors: [],
                statistics: calculateTotalStatistics(processedFiles),
                total_processed: processedFiles.length
            });
        }
    } catch (error) {
        console.error('Fehler beim Laden verarbeiteter Dateien:', error);
    }
}

/**
 * Behandelt Dateiauswahl
 */
function handleFileSelect(e) {
    const files = Array.from(e.target.files);
    validateAndUploadFiles(files);
}

/**
 * Behandelt Drag Over
 */
function handleDragOver(e) {
    e.preventDefault();
    elements.uploadArea.classList.add('drag-over');
}

/**
 * Behandelt Drag Leave
 */
function handleDragLeave(e) {
    e.preventDefault();
    elements.uploadArea.classList.remove('drag-over');
}

/**
 * Behandelt Drop
 */
function handleDrop(e) {
    e.preventDefault();
    elements.uploadArea.classList.remove('drag-over');
    
    const files = Array.from(e.dataTransfer.files);
    validateAndUploadFiles(files);
}

/**
 * Validiert und lädt Dateien hoch
 */
function validateAndUploadFiles(files) {
    // Filtere nur Bilddateien
    const imageFiles = files.filter(file => file.type.startsWith('image/'));
    
    if (imageFiles.length === 0) {
        showMessage(lang.t('upload.selectImages'), 'error');
        return;
    }
    
    // Prüfe maximale Anzahl
    const remainingSlots = 50 - uploadedFiles.length;
    if (imageFiles.length > remainingSlots) {
        showMessage(lang.t('upload.remainingSlots', { count: remainingSlots }), 'error');
        return;
    }
    
    // Starte Upload
    uploadFiles(imageFiles);
}

/**
 * Lädt Dateien asynchron hoch
 */
async function uploadFiles(files) {
    // Reset nur newUploadedFiles für diese Upload-Session
    newUploadedFiles = [];
    
    totalUploadCount = files.length;
    currentUploadCount = 0;
    
    // Zeige Fortschritt
    elements.progressSection.style.display = 'block';
    elements.fileList.style.display = 'block';
    
    // Leere vorherige Dateiliste (nur visuell)
    elements.fileListItems.innerHTML = '';
    
    updateProgress();
    
    // Upload jede Datei
    for (const file of files) {
        try {
            await uploadSingleFile(file);
            currentUploadCount++;
            updateProgress();
        } catch (error) {
            console.error('Upload-Fehler:', error);
            addFileToList(file.name, 'error', error.message);
        }
    }
    
    // Nach Upload: Analyse nur für neue Dateien starten
    if (newUploadedFiles.length > 0) {
        // Füge neue Dateien zu uploadedFiles hinzu
        uploadedFiles = uploadedFiles.concat(newUploadedFiles);
        analyzeImages();
    }
}

/**
 * Lädt eine einzelne Datei hoch
 */
async function uploadSingleFile(file) {
    const formData = new FormData();
    formData.append('file', file);
    
    const response = await fetch('upload.php', {
        method: 'POST',
        body: formData
    });
    
    if (!response.ok) {
        const error = await response.json();
        throw new Error(error.error || 'Upload fehlgeschlagen');
    }
    
    const result = await response.json();
    
    if (result.success) {
        // Füge zu newUploadedFiles hinzu (nur für aktuelle Session)
        newUploadedFiles.push(result.file);
        addFileToList(result.file.name, 'success', `${result.file.size} • ${result.file.dimensions}`);
    } else {
        throw new Error(result.error || 'Unbekannter Fehler');
    }
}

/**
 * Fügt Datei zur Liste hinzu
 */
function addFileToList(filename, status, info) {
    const li = document.createElement('li');
    li.className = status === 'error' ? 'error' : '';
    li.innerHTML = `
        <span>${filename}</span>
        <span>${info}</span>
    `;
    elements.fileListItems.appendChild(li);
}

/**
 * Aktualisiert Fortschrittsanzeige
 */
function updateProgress() {
    const percentage = totalUploadCount > 0 ? (currentUploadCount / totalUploadCount) * 100 : 0;
    elements.progressFill.style.width = percentage + '%';
    
// NEU: Aktualisiere auch den Titel
    const progressTitle = document.getElementById('progressTitle');
    if (progressTitle) {
        progressTitle.textContent = lang.t('upload.uploadProgress', { 
            current: currentUploadCount, 
            total: totalUploadCount 
        });
    }

    // WICHTIG: Direkte Textaktualisierung mit Fallback
    if (window.lang && window.lang.translations && Object.keys(window.lang.translations).length > 0) {
        // Verwende Übersetzung wenn verfügbar
        elements.progressText.textContent = lang.t('upload.uploadProgress', { 
            current: currentUploadCount, 
            total: totalUploadCount 
        });
    } else {
        // Fallback: Zeige Zahlen direkt
        elements.progressText.textContent = `${currentUploadCount} von ${totalUploadCount} Dateien hochgeladen`;
    }
}

/**
 * Analysiert hochgeladene Bilder
 */
async function analyzeImages() {
    try {
        const response = await fetch('analyze.php');
        const data = await response.json();
        
        if (data.success && data.analysis) {
            // Debug: Zeige Struktur der Analyse-Daten
            console.log('Analyse erfolgreich:', data.analysis);
            
            displayAnalysisResults(data.analysis);
            elements.analysisSection.style.display = 'block';
            elements.analysisSection.classList.add('fade-in');
        } else {
            showMessage('Fehler bei der Bildanalyse', 'error');
        }
    } catch (error) {
        console.error('Analyse-Fehler:', error);
        showMessage('Fehler bei der Bildanalyse', 'error');
    }
}

/**
 * Zeigt Analyseergebnisse an
 */
function displayAnalysisResults(analysis) {
    // Speichere Analyse-Daten für spätere Verwendung
    analysisData = { analysis: analysis };
    
    // Debug: Prüfe Datenstruktur
    console.log('Analysis data:', analysis);
    
    // Erstelle Zusammenfassung
    const summary = `
        <div class="analysis-summary">
            <h3>${lang.t('analysis.summary')}</h3>
            <p><strong>${lang.t('analysis.totalFiles', { count: analysis.total_files })}</strong> ${lang.t('analysis.totalSize')} <strong>${analysis.total_size}</strong></p>
            <p>${lang.t('analysis.recommendedFormat')}: <strong>${analysis.recommended_format.toUpperCase()}</strong></p>
            <div id="estimatedSavings">
                <p>${lang.t('analysis.estimatedSavings')}: <strong>${analysis.estimated_savings.formatted}</strong> (${analysis.estimated_savings.percentage}%)</p>
            </div>
        </div>
    `;
    
    elements.analysisResults.innerHTML = summary;
    
    // Erstelle Format-Optionen
    const formats = ['jpeg', 'png', 'webp'];
    let optionsHtml = '';
    
    formats.forEach(format => {
        const checked = format === analysis.recommended_format ? 'checked' : '';
        const tooltip = lang.t('tooltips.format' + format.charAt(0).toUpperCase() + format.slice(1));
        optionsHtml += `
            <div class="format-option">
                <input type="radio" id="format-${format}" name="format" value="${format}" ${checked} onchange="updateEstimatedSavings('${format}', true)">
                <label for="format-${format}" title="${tooltip}">${lang.t('formats.' + format)}</label>
            </div>
        `;
    });
    
    elements.formatOptions.innerHTML = optionsHtml;
}

/**
 * Aktualisiert Qualitätswert-Anzeige
 */
function updateQualityValue() {
    elements.qualityValue.textContent = elements.qualitySlider.value;
}

/**
 * Behandelt Formular-Submit für Verarbeitung
 */
async function handleProcessSubmit(e) {
    e.preventDefault();
    
    // Entscheide basierend auf Konfiguration
    if (USE_BATCH_PROCESSING) {
        await handleProcessSubmitBatch(e);
    } else {
        await handleProcessSubmitNormal(e);
    }
}

/**
 * Batch-Version mit process_ajax.php (Dateien einzeln verarbeiten)
 */
async function handleProcessSubmitBatch(e) {
    e.preventDefault();
    
    // Deaktiviere Button
    const submitButton = e.target.querySelector('button[type="submit"]');
    submitButton.disabled = true;
    
    const format = e.target.format.value;
    const quality = e.target.quality.value;
    
    // Prüfe ob neue Dateien zum Verarbeiten vorhanden sind
    if (newUploadedFiles.length === 0) {
        showMessage(lang.t('upload.noNewFilesToProcess'), 'error');
        submitButton.disabled = false;
        submitButton.innerHTML = lang.t('analysis.processButton');
        return;
    }
    
    // Initialisiere Ergebnis-Arrays
    const results = [];
    const errors = [];
    
    // Verarbeite nur neue Dateien
    const fileIds = newUploadedFiles.map(f => f.id);
    let processed = 0;
    
    console.log('Verarbeite neue Dateien:', fileIds);
    
    submitButton.innerHTML = `<span class="spinner"></span> ${lang.t('analysis.processing', { current: 0, total: fileIds.length })}`;
    
    // Zeige Ergebnisbereich schon mal an
    elements.resultsSection.style.display = 'block';
    elements.resultsSection.classList.add('fade-in');
    
    // Wenn schon Dateien verarbeitet wurden, zeige diese weiterhin an
    if (processedFiles.length > 0) {
        displayPreviouslyProcessedFiles();
    } else {
        elements.resultsGrid.innerHTML = `<p>${lang.t('results.processingRunning')}</p>`;
    }
    
    for (const fileId of fileIds) {
        try {
            const formData = new FormData();
            formData.append('file_id', fileId);
            formData.append('format', format);
            formData.append('quality', quality);
            
            console.log('Verarbeite Datei:', fileId);
            
            const response = await fetch('process_ajax.php', {
                method: 'POST',
                body: formData
            });
            
            const responseText = await response.text();
            let result;
            
            try {
                result = JSON.parse(responseText);
            } catch (e) {
                console.error('JSON Parse Error für Datei:', fileId);
                console.error('Response:', responseText);
                throw new Error('Ungültige Server-Antwort');
            }
            
            if (!response.ok) {
                throw new Error(result.error || `HTTP error! status: ${response.status}`);
            }
            
            if (result.success) {
                results.push(result.file);
                processedFiles.push(result.file); // Füge zu allen verarbeiteten Dateien hinzu
                processed++;
                submitButton.innerHTML = `<span class="spinner"></span> ${lang.t('analysis.processing', { current: processed, total: fileIds.length })}`;
                
                // Aktualisiere Ergebnisanzeige live
                displayProcessedFile(result.file);
            } else {
                errors.push({
                    id: fileId,
                    name: result.file_id || fileId,
                    error: result.error || 'Unbekannter Fehler'
                });
            }
        } catch (error) {
            console.error('Fehler bei Datei:', fileId, error);
            errors.push({
                id: fileId,
                name: fileId,
                error: error.message
            });
        }
    }
    
    // Berechne Gesamtstatistiken (für alle verarbeiteten Dateien)
    const statistics = calculateTotalStatistics(processedFiles);
    
    // Zeige finale Ergebnisse
    displayProcessResults({
        success: results.length > 0,
        processed_count: results.length,
        error_count: errors.length,
        results: results,
        errors: errors,
        statistics: statistics,
        total_processed: processedFiles.length
    });
    
    // Aktiviere Button wieder
    submitButton.disabled = false;
    submitButton.innerHTML = lang.t('analysis.processButton');
    
    // Zeige detaillierte Fehler falls vorhanden
    if (errors.length > 0) {
        let errorMessage = lang.t('errors.filesFailedProcessing', { count: errors.length }) + '\n\n';
        errors.forEach(err => {
            errorMessage += `• ${err.name}: ${err.error}\n`;
        });
        showMessage(errorMessage, 'error');
    }
    
    // Leere nur newUploadedFiles nach erfolgreicher Verarbeitung
    newUploadedFiles = [];
}

/**
 * Normale Version mit process.php (alle Dateien auf einmal)
 */
async function handleProcessSubmitNormal(e) {
    e.preventDefault();
    
    // Deaktiviere Button
    const submitButton = e.target.querySelector('button[type="submit"]');
    submitButton.disabled = true;
    submitButton.innerHTML = '<span class="spinner"></span> Verarbeite Bilder...';
    
    // Sammle Formulardaten
    const formData = new FormData(e.target);
    
    try {
        const response = await fetch('process.php', {
            method: 'POST',
            body: formData,
            signal: AbortSignal.timeout(300000) // 5 Minuten Timeout
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (result.success) {
            // Füge verarbeitete Dateien zu processedFiles hinzu
            if (result.results) {
                result.results.forEach(file => {
                    processedFiles.push(file);
                });
            }
            
            displayProcessResults(result);
            
            // Zeige Fehler falls vorhanden
            if (result.errors && result.errors.length > 0) {
                let errorMessage = `${result.errors.length} Datei(en) konnten nicht verarbeitet werden:\n`;
                result.errors.forEach(err => {
                    errorMessage += `- ${err.name}: ${err.error}\n`;
                });
                showMessage(errorMessage, 'error');
            }
        } else {
            showMessage('Fehler bei der Bildverarbeitung. Bitte versuchen Sie es erneut.', 'error');
        }
    } catch (error) {
        console.error('Verarbeitungsfehler:', error);
        
        let errorMessage = 'Fehler bei der Bildverarbeitung: ';
        if (error.name === 'AbortError') {
            errorMessage += 'Zeitüberschreitung. Bitte versuchen Sie es mit weniger Dateien.';
        } else if (error.message) {
            errorMessage += error.message;
        } else {
            errorMessage += 'Unbekannter Fehler. Bitte prüfen Sie die Konsole.';
        }
        
        showMessage(errorMessage, 'error');
    } finally {
        submitButton.disabled = false;
        submitButton.innerHTML = 'Bilder verarbeiten';
        
        // Leere newUploadedFiles nach Verarbeitung
        newUploadedFiles = [];
    }
}

/**
 * Zeigt einzelne verarbeitete Datei an (Live-Update)
 */
function displayProcessedFile(file) {
    // Beim ersten Mal leeren, wenn es der Platzhalter ist
    if (elements.resultsGrid.innerHTML === '<p>' + lang.t('results.processingRunning') + '</p>') {
        elements.resultsGrid.innerHTML = '';
    }
    
    const savingsClass = file.savings_percent > 0 ? 'savings-positive' : 'savings-negative';
    
    const fileHtml = `
        <div class="result-item fade-in" data-file-id="${file.id}">
            <img src="${file.thumbnail_url}" 
                 alt="${lang.t('altTexts.thumbnail', { filename: file.original_name })}" 
                 title="${lang.t('tooltips.thumbnail')}"
                 class="result-thumbnail" 
                 onclick="downloadFile('${file.id}')">
            <div class="result-info">
                <h4 title="${file.original_name}">${file.original_name}</h4>
                <div class="result-stats">
                    <p>${lang.t('results.original')}: ${file.original_size}</p>
                    <p>${lang.t('results.compressed')}: ${file.processed_size}</p>
                    <p class="${savingsClass}">${lang.t('results.savings')}: ${file.savings} (${file.savings_percent}%)</p>
                </div>
            </div>
        </div>
    `;
    
    elements.resultsGrid.insertAdjacentHTML('beforeend', fileHtml);
}

/**
 * Zeigt bereits verarbeitete Dateien an
 */
function displayPreviouslyProcessedFiles() {
    elements.resultsGrid.innerHTML = '';
    
    // Zeige alle bereits verarbeiteten Dateien
    processedFiles.forEach(file => {
        displayProcessedFile(file);
    });
}

/**
 * Berechnet Gesamtstatistiken
 */
function calculateTotalStatistics(results) {
    let totalOriginal = 0;
    let totalProcessed = 0;
    
    results.forEach(file => {
        totalOriginal += parseFileSize(file.original_size);
        totalProcessed += parseFileSize(file.processed_size);
    });
    
    const totalSavings = totalOriginal - totalProcessed;
    const savingsPercent = totalOriginal > 0 ? (totalSavings / totalOriginal) * 100 : 0;
    
    return {
        total_original_size: formatBytes(totalOriginal),
        total_processed_size: formatBytes(totalProcessed),
        total_savings: formatBytes(totalSavings),
        total_savings_percent: savingsPercent.toFixed(1)
    };
}

/**
 * Zeigt Verarbeitungsergebnisse an
 */
function displayProcessResults(result) {
    // Grid wurde bereits während der Verarbeitung gefüllt
    
    // Zeige Download-Optionen
    const totalFiles = result.total_processed || result.processed_count;
    const newFiles = result.processed_count;
    
    let statusText = '';
    if (newFiles > 0) {
        statusText = `<p class="success-message">${lang.t('results.newFilesProcessed', { count: newFiles })}</p>`;
    }
    if (totalFiles > newFiles) {
        statusText += `<p>${lang.t('results.totalFilesAvailable', { count: totalFiles })}</p>`;
    }
    
    const downloadHtml = `
        <h3>${lang.t('results.downloadOptions')}</h3>
        ${statusText}
        <div class="download-buttons">
            ${totalFiles > 1 ? 
                `<button class="btn btn-success" onclick="downloadZip()">${lang.t('results.downloadZip')}</button>` : 
                ''
            }
            ${totalFiles <= 5 ? 
                `<button class="btn btn-primary" onclick="showIndividualDownloads()">${lang.t('results.downloadSingle')}</button>` :
                ''
            }
            <button class="btn btn-primary" onclick="startNewSession()">${lang.t('results.newSession')}</button>
            ${totalFiles > 5 ? 
                `<a href="download_manager.php" class="btn btn-primary">${lang.t('results.downloadManager')}</a>` :
                ''
            }
        </div>
        <div id="individualDownloads" style="display: none;" class="mt-20">
            <p>${lang.t('results.clickThumbnails')}</p>
        </div>
        <div class="mt-20">
            <p><strong>${lang.t('results.statistics')}:</strong></p>
            <p>${lang.t('results.original')}: ${result.statistics.total_original_size} → 
               ${lang.t('results.compressed')}: ${result.statistics.total_processed_size} 
               (${result.statistics.total_savings_percent}% ${lang.t('results.saved')})</p>
        </div>
    `;
    
    elements.downloadOptions.innerHTML = downloadHtml;
}

/**
 * Lädt einzelne Datei herunter
 */
function downloadFile(fileId) {
    window.location.href = `download.php?action=single&id=${fileId}`;
}

/**
 * Lädt ZIP-Archiv herunter
 */
function downloadZip() {
    window.location.href = 'download.php?action=zip';
}

/**
 * Startet eine neue Session
 */
function startNewSession() {
    if (confirm(lang.t('confirmations.newSession'))) {
        // Reset JavaScript-Variablen
        resetApplicationState();
        
        // Lade Seite mit Reset-Parameter neu
        window.location.href = 'index.php?reset=1';
    }
}

/**
 * Zeigt Hinweis für Einzeldownloads
 */
function showIndividualDownloads() {
    const elem = document.getElementById('individualDownloads');
    elem.style.display = elem.style.display === 'none' ? 'block' : 'none';
}

/**
 * Prüft Session-Status vor Verarbeitung
 */
async function checkSessionStatus() {
    try {
        const response = await fetch('check_session.php');
        const data = await response.json();
        
        if (!data.has_uploaded_files) {
            uploadedFiles = [];
            return false;
        }
        
        return true;
    } catch (error) {
        console.error('Session-Check fehlgeschlagen:', error);
        return false;
    }
}

/**
 * Zeigt Nachrichten an
 */
function showMessage(message, type = 'info') {
    const messageDiv = document.createElement('div');
    messageDiv.className = `${type}-message fade-in`;
    messageDiv.textContent = message;
    
    // Füge am Anfang des Containers ein
    elements.uploadArea.parentElement.insertBefore(messageDiv, elements.uploadArea);
    
    // Entferne nach 5 Sekunden
    setTimeout(() => {
        messageDiv.remove();
    }, 5000);
}

/**
 * Aktualisiert die geschätzte Einsparung basierend auf dem gewählten Format
 */
function updateEstimatedSavings(selectedFormat, isFormatChange = false) {
    try {
        if (!analysisData || !analysisData.analysis) {
            console.error('Keine Analyse-Daten verfügbar');
            return;
        }
        
        // Hole aktuelle Qualität
        const quality = parseInt(elements.qualitySlider.value);
        
        // DEBUG: Zeige die Struktur der Daten
        console.log('analysisData structure:', analysisData);
        console.log('analysisData.analysis:', analysisData.analysis);
        console.log('analysisData.analysis.files:', analysisData.analysis.files);
        
        // Prüfe ob files existiert
        let files = analysisData.analysis.files;
        
        // Wenn files kein Array ist, versuche es zu konvertieren
        if (!Array.isArray(files)) {
            console.warn('files ist kein Array, versuche Konvertierung...');
            
            // Wenn es ein Objekt ist, konvertiere zu Array
            if (typeof files === 'object' && files !== null) {
                files = Object.values(files);
                console.log('Konvertiert zu Array:', files);
            } else {
                console.error('Kann files nicht zu Array konvertieren');
                return;
            }
        }
        
        const totalOriginalSize = analysisData.analysis.total_size_bytes || 0;
        let estimatedNewSize = 0;
        
        // Berechne geschätzte Größe für jede Datei
        files.forEach(file => {
            const originalBytes = file.size_bytes || 0;
            
            // Basis-Kompressionsfaktoren
            let compressionFactor = 1.0;

        // NEU: Qualitäts-Anpassung für JPEG und WebP
        let qualityFactor = 1.0;
        if (selectedFormat === 'jpeg' || selectedFormat === 'webp') {
            // Qualität 100 führt oft zu größeren Dateien
            if (quality > 95) {
                qualityFactor = 1.3; // Dateien können 30% größer werden
            } else if (quality >= 90) {
                qualityFactor = 1.1;
            } else if (quality >= 80) {
                qualityFactor = quality / 85;
            } else {
                qualityFactor = quality / 100;
            }
        }
            
            // Wenn das Format sich vom aktuellen unterscheidet
            if (file.current_format !== selectedFormat) {
            // Konvertierung von anderen Formaten zu JPEG
            if (selectedFormat === 'jpeg') {
                if (file.current_format === 'png') {
                    compressionFactor = file.has_transparency ? 0.25 : 0.2;
                } else if (file.current_format === 'webp') {
                    compressionFactor = 0.8;
                } else {
                    compressionFactor = 0.3; // Andere zu JPEG
                }
            }
            // Konvertierung zu WebP
            else if (selectedFormat === 'webp') {
                compressionFactor = 0.25; // WebP ist sehr effizient
            }
            // Konvertierung zu PNG
            else if (selectedFormat === 'png') {
                compressionFactor = 1.2; // PNG ist meist größer
            }
        } else {
            // Gleiches Format = Neukompression
            if (selectedFormat === 'jpeg') {
                compressionFactor = 0.3; // JPEG zu JPEG bei Q85
            } else if (selectedFormat === 'webp') {
                compressionFactor = 0.7;
            } else {
                compressionFactor = 0.85;
            }
        }
            
            // Kombiniere Format- und Qualitätsfaktor
            estimatedNewSize += originalBytes * compressionFactor * qualityFactor;
        });
        
        // Berechne Einsparungen
        const savings = totalOriginalSize - estimatedNewSize;
        const percentage = totalOriginalSize > 0 ? (savings / totalOriginalSize) * 100 : 0;
        
        // Aktualisiere Anzeige mit Animation
        const savingsDiv = document.getElementById('estimatedSavings');
        if (savingsDiv) {
            savingsDiv.style.opacity = '0.5';
            
setTimeout(() => {
    let savingsClass = percentage > 0 ? 'savings-positive' : 'savings-negative';
    let savingsText;
    
    if (percentage > 0) {
        savingsText = lang.t('analysis.estimatedSavings') + ' ' + 
                     lang.t('analysis.withFormat', {format: selectedFormat.toUpperCase()}) + ': ' +
                     `<strong class="${savingsClass}">${formatBytes(savings)}</strong> (${percentage.toFixed(1)}%)`;
    } else {
        savingsText = lang.t('analysis.sizeIncrease', {
            format: selectedFormat.toUpperCase(),
            size: formatBytes(Math.abs(savings)),
            percentage: Math.abs(percentage).toFixed(1)
        });
    }
    
    savingsDiv.innerHTML = `<p>${savingsText}</p>`;
    savingsDiv.style.opacity = '1';
    }, 200);
        }
        
        // Zeige Hinweis bei Format-Besonderheiten
        if (isFormatChange) {
            showFormatHints(selectedFormat, files);
        }
        
    } catch (error) {
        console.error('Fehler in updateEstimatedSavings:', error);
        console.error('Stack:', error.stack);
    }
}

/**
 * Parst Dateigröße von String zu Bytes
 */
function parseFileSize(sizeStr) {
    const match = sizeStr.match(/(\d+\.?\d*)\s*(\w+)/);
    if (!match) return 0;
    
    const value = parseFloat(match[1]);
    const unit = match[2].toUpperCase();
    
    const units = {
        'B': 1,
        'KB': 1024,
        'MB': 1024 * 1024,
        'GB': 1024 * 1024 * 1024
    };
    
    return value * (units[unit] || 1);
}

/**
 * Formatiert Bytes zu lesbarer Größe
 */
function formatBytes(bytes) {
    const units = ['B', 'KB', 'MB', 'GB'];
    let i = 0;
    while (bytes >= 1024 && i < units.length - 1) {
        bytes /= 1024;
        i++;
    }
    return bytes.toFixed(2) + ' ' + units[i];
}

/**
 * Zeigt Format-spezifische Hinweise
 */
function showFormatHints(format, files) {
    // Prüfe ob files ein Array ist
    if (!files || !Array.isArray(files)) {
        console.warn('Keine Datei-Array für Format-Hinweise');
        return;
    }

    // Erstelle eindeutigen Hinweis-Key
    const hintKey = `format_hint_${format}`;
    
    // Prüfe ob Hinweis bereits angezeigt wurde
    if (shownHints.has(hintKey)) {
        return;
    }
    
    // Prüfe ob Transparenz vorhanden ist
    const hasTransparentImages = files.some(f => f.has_transparency);
    
    if (format === 'jpeg' && hasTransparentImages) {
        setTimeout(() => {
            showMessage(lang.t('formats.hints.jpegTransparency'), 'info');
        }, 300);
    } else if (format === 'png' && files.length > 5) {
        setTimeout(() => {
            showMessage(lang.t('formats.hints.pngEfficiency'), 'info');
        }, 300);
    } else if (format === 'webp') {
        setTimeout(() => {
            showMessage(lang.t('formats.hints.webpCompatibility'), 'info');
        }, 300);
    }
}

// Globale Funktionen für onclick-Handler
window.downloadFile = downloadFile;
window.downloadZip = downloadZip;
window.startNewSession = startNewSession;
window.showIndividualDownloads = showIndividualDownloads;
window.updateEstimatedSavings = updateEstimatedSavings;

// Globale Funktion für Debugging
window.debugAnalysisData = function() {
    console.log('Current analysisData:', analysisData);
    if (analysisData && analysisData.analysis) {
        console.log('analysis.files:', analysisData.analysis.files);
        console.log('Is array?', Array.isArray(analysisData.analysis.files));
    }
    console.log('Current language:', lang.currentLang);
    console.log('Translations loaded:', lang.translations);
    console.log('Test upload progress:', lang.t('upload.uploadProgress', {current: 5, total: 10}));
}