# Image Compressor - Dokumentation

## Inhaltsverzeichnis
1. [Übersicht](#übersicht)
2. [Funktionsweise](#funktionsweise)
3. [Neue Sprachen hinzufügen](#neue-sprachen-hinzufügen)
4. [Debug-Modus aktivieren](#debug-modus-aktivieren)
5. [Technische Details](#technische-details)

## Übersicht

Der Image Compressor ist eine webbasierte Anwendung zur Batch-Komprimierung von Bildern. Die Anwendung unterstützt:
- Upload von bis zu 50 Bildern gleichzeitig
- Drag & Drop Funktionalität
- Ausgabeformate: JPEG, PNG, WebP
- Qualitätseinstellungen (1-100%)
- Mehrsprachigkeit (DE/EN)
- ZIP-Download für mehrere Dateien

## Funktionsweise

### 1. Upload-Phase
- Benutzer wählt Bilder über Datei-Dialog oder Drag & Drop
- Dateien werden asynchron via AJAX hochgeladen
- Fortschrittsanzeige zeigt Upload-Status
- Dateien werden temporär in `/uploads/` gespeichert

### 2. Analyse-Phase
- Hochgeladene Bilder werden analysiert
- Prüfung auf Transparenz, Farbtiefe, Dimensionen
- Formatempfehlung basierend auf Bildeigenschaften
- Schätzung der möglichen Dateigröße-Einsparung

### 3. Verarbeitungs-Phase
- Benutzer wählt Ausgabeformat und Qualität
- Bilder werden einzeln über AJAX verarbeitet (vermeidet Timeouts)
- GD Library wird für Bildmanipulation verwendet
- Thumbnails werden automatisch erstellt

### 4. Download-Phase
- Verarbeitete Bilder können einzeln heruntergeladen werden
- ZIP-Download für alle oder ausgewählte Dateien
- Download Manager für Übersicht aller Dateien

## Neue Sprachen hinzufügen

### Schritt 1: Sprachdatei erstellen

Erstellen Sie eine neue JSON-Datei im Verzeichnis `/assets/lang/`:

```bash
/assets/lang/es.json  # Beispiel für Spanisch
```

### Schritt 2: Übersetzungen hinzufügen

Kopieren Sie die Struktur aus `de.json` oder `en.json` und übersetzen Sie alle Texte:

```json
{
  "app": {
    "title": "Compresión de Imágenes",
    "subtitle": "Sube hasta 50 imágenes a la vez"
  },
  "upload": {
    "dragDrop": "Arrastra archivos aquí o haz clic para seleccionar",
    "maxFiles": "Máximo 50 archivos a la vez",
    // ... weitere Übersetzungen
  }
}
```

### Schritt 3: PHP-Konfiguration anpassen

In `/config/lang_config.php` die neue Sprache hinzufügen:

```php
// Zeile 11: Verfügbare Sprachen erweitern
define('AVAILABLE_LANGUAGES', ['de', 'en', 'es']);
```

### Schritt 4: JavaScript-Konfiguration anpassen

In `/assets/js/language.js` die neue Sprache hinzufügen:

```javascript
// Zeile 10: supportedLanguages erweitern
this.supportedLanguages = ['de', 'en', 'es'];
```

### Schritt 5: Sprachauswahl erweitern

In `/assets/js/language.js` in der `createLanguageSelector()` Funktion:

```javascript
selector.innerHTML = `
    <select id="languageSelect" class="language-dropdown">
        <option value="de" ${this.currentLang === 'de' ? 'selected' : ''}>🇩🇪 Deutsch</option>
        <option value="en" ${this.currentLang === 'en' ? 'selected' : ''}>🇬🇧 English</option>
        <option value="es" ${this.currentLang === 'es' ? 'selected' : ''}>🇪🇸 Español</option>
    </select>
`;
```

## Debug-Modus aktivieren

### Methode 1: Global aktivieren

In `/config/config.php` ändern:

```php
// Zeile 50: DEBUG_MODE auf true setzen
define('DEBUG_MODE', true);

// Zeile 8-10: Fehlerausgabe aktivieren (NUR für Entwicklung!)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
```

### Methode 2: Debug-Seite verwenden

Öffnen Sie im Browser:
```
https://ihre-domain.de/debug.php
```

Diese Seite zeigt:
- PHP-Konfiguration
- GD Library Status
- Verzeichnis-Berechtigungen
- Session-Daten
- Error-Log Einträge

**⚠️ WICHTIG**: `debug.php` nach der Fehlersuche löschen!

### Debug-Ausgaben im Code

Für temporäres Debugging:

```php
// PHP
error_log('Debug: Variable = ' . print_r($variable, true));

// JavaScript
console.log('Debug:', variable);
window.debugAnalysisData(); // Zeigt Analyse-Daten
```

## Technische Details

### Verzeichnisstruktur
```
/image-compressor/
├── assets/
│   ├── css/style.css
│   ├── js/
│   │   ├── app.js         # Haupt-JavaScript
│   │   └── language.js    # Sprachverwaltung
│   └── lang/
│       ├── de.json        # Deutsche Übersetzungen
│       └── en.json        # Englische Übersetzungen
├── classes/
│   ├── FileManager.php    # Dateiverwaltung
│   └── ImageProcessor.php # Bildverarbeitung
├── config/
│   ├── config.php         # Hauptkonfiguration
│   └── lang_config.php    # Sprachkonfiguration
├── processed/             # Verarbeitete Bilder
├── temp/                  # Temporäre Dateien
├── uploads/               # Upload-Verzeichnis
└── *.php                  # PHP-Seiten
```

### Session-Verwaltung

Sessions werden automatisch nach 1 Stunde bereinigt. Manuelle Bereinigung:
- URL-Parameter `?reset=1` löscht aktuelle Session
- Alte Dateien werden automatisch nach 1 Stunde gelöscht

### Bildverarbeitung

Die GD Library wird für alle Bildoperationen verwendet:
- **Größenanpassung**: Bilder über 4096x4096px werden skaliert
- **Transparenz**: Wird bei PNG→JPEG mit weißem Hintergrund gefüllt
- **Progressive JPEGs**: Automatisch für bessere Web-Performance
- **Qualität**: 
  - JPEG/WebP: 1-100 (Standard: 85)
  - PNG: Maximale Kompression (Level 9)

### Performance-Optimierungen

1. **Batch-Processing**: Einzelne AJAX-Requests vermeiden Timeouts
2. **Speicher-Management**: Bilder werden nach Verarbeitung aus dem Speicher entfernt
3. **Thumbnail-Generierung**: 300x300px für schnelle Vorschau
4. **Stichproben-Analyse**: Transparenz-Check prüft nur Teile des Bildes

### Fehlerbehebung

**Problem: Timeout bei großen Bildern**
- Lösung: `max_execution_time` in PHP erhöhen
- Alternative: Weniger Bilder gleichzeitig verarbeiten

**Problem: Memory Limit Fehler**
- Lösung: `memory_limit` in PHP auf 256M erhöhen
- Check mit `debug.php`

**Problem: Upload schlägt fehl**
- Prüfen: `upload_max_filesize` und `post_max_size` in PHP
- Verzeichnisrechte prüfen (755 für Ordner)

**Problem: WebP nicht verfügbar**
- GD Library muss mit WebP-Support kompiliert sein
- Check mit `debug.php` → GD Info



![Image-Compression](https://github.com/user-attachments/assets/5761854d-035b-4b28-a363-b4ff4a0c0fa2)

