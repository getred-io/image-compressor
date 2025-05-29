/**
 * Language Manager für Mehrsprachigkeit
 * Dateipfad: /image-compressor/assets/js/language.js
 */

class LanguageManager {
    constructor() {
        this.currentLang = 'de'; // Standard-Sprache
        this.translations = {};
        this.supportedLanguages = ['de', 'en'];
    }

    /**
     * Initialisiert den Language Manager
     */
    async init() {
        // Hole gespeicherte Sprache oder Browser-Sprache
        this.currentLang = await this.getSessionLanguage() || 
                          this.getSavedLanguage() || 
                          this.detectBrowserLanguage();
        
        // Lade Übersetzungen
        await this.loadTranslations(this.currentLang);
        
        // Aktualisiere UI
        this.updateUI();
        
        // Erstelle Sprachauswahl
        this.createLanguageSelector();
    }

    /**
     * Holt die Sprache aus der PHP-Session
     */
    async getSessionLanguage() {
        try {
            const response = await fetch('get_language.php');
            if (response.ok) {
                const data = await response.json();
                return data.language;
            }
        } catch (error) {
            console.error('Could not get session language:', error);
        }
        return null;
    }

    /**
     * Erkennt Browser-Sprache
     */
    detectBrowserLanguage() {
        const browserLang = navigator.language || navigator.userLanguage;
        const shortLang = browserLang.split('-')[0];
        
        return this.supportedLanguages.includes(shortLang) ? shortLang : 'de';
    }

    /**
     * Holt gespeicherte Sprache aus localStorage
     */
    getSavedLanguage() {
        return localStorage.getItem('selectedLanguage');
    }

    /**
     * Speichert ausgewählte Sprache
     */
    saveLanguage(lang) {
        localStorage.setItem('selectedLanguage', lang);
        
        // Synchronisiere mit PHP-Session
        this.syncLanguageWithPHP(lang);
    }

    /**
     * Synchronisiert Sprache mit PHP-Session
     */
    async syncLanguageWithPHP(lang) {
        try {
            const formData = new FormData();
            formData.append('lang', lang);
            
            const response = await fetch('set_language.php', {
                method: 'POST',
                body: formData
            });
            
            if (!response.ok) {
                console.error('Failed to sync language with server');
            }
        } catch (error) {
            console.error('Error syncing language:', error);
        }
    }

    /**
     * Lädt Übersetzungsdatei
     */
    async loadTranslations(lang) {
        try {
            const response = await fetch(`assets/lang/${lang}.json`);
            if (!response.ok) {
                throw new Error(`Could not load language file: ${lang}.json`);
            }
            this.translations = await response.json();
            this.currentLang = lang;
        } catch (error) {
            console.error('Error loading translations:', error);
            // Fallback zu Deutsch
            if (lang !== 'de') {
                await this.loadTranslations('de');
            }
        }
    }

    /**
     * Holt Übersetzung für einen Schlüssel
     */
    t(key, replacements = {}) {
        // Prüfe ob Übersetzungen geladen sind
        if (!this.translations || Object.keys(this.translations).length === 0) {
            console.warn('Translations not loaded yet');
            return this.formatFallback(key, replacements);
        }
        
        // Navigiere durch verschachtelte Objekte
        const keys = key.split('.');
        let translation = this.translations;
        
        for (const k of keys) {
            if (translation && translation[k]) {
                translation = translation[k];
            } else {
                console.warn(`Translation missing for key: ${key}`);
                return this.formatFallback(key, replacements);
            }
        }
        
        // Ersetze Platzhalter
        if (typeof translation === 'string') {
            let result = translation;
            Object.keys(replacements).forEach(placeholder => {
                const regex = new RegExp(`\\{${placeholder}\\}`, 'g');
                result = result.replace(regex, replacements[placeholder]);
            });
            return result;
        }
        
        return translation;
    }
    
    /**
     * Formatiert Fallback-Text
     */
    formatFallback(key, replacements = {}) {
        // Nimm letzten Teil des Keys und mache ihn lesbar
        const parts = key.split('.');
        let fallback = parts[parts.length - 1];
        
        // CamelCase zu Leerzeichen
        fallback = fallback.replace(/([A-Z])/g, ' $1').trim();
        fallback = fallback.charAt(0).toUpperCase() + fallback.slice(1);
        
        // Ersetze Platzhalter
        Object.keys(replacements).forEach(placeholder => {
            const regex = new RegExp(`\\{${placeholder}\\}`, 'g');
            fallback = fallback.replace(regex, replacements[placeholder]);
        });
        
        return fallback;
    }

    /**
     * Wechselt die Sprache
     */
    async changeLanguage(lang) {
        if (!this.supportedLanguages.includes(lang)) {
            console.error(`Language not supported: ${lang}`);
            return;
        }
        
        await this.loadTranslations(lang);
        this.saveLanguage(lang);
        this.updateUI();
        
        // Optional: Seite neu laden für vollständige Synchronisation
        // window.location.reload();
    }

    /**
     * Aktualisiert alle UI-Elemente mit Übersetzungen
     */
    updateUI() {
        // Aktualisiere alle Elemente mit data-i18n Attribut
        document.querySelectorAll('[data-i18n]').forEach(element => {
            const key = element.getAttribute('data-i18n');
            element.textContent = this.t(key);
        });

        // Aktualisiere Platzhalter
        document.querySelectorAll('[data-i18n-placeholder]').forEach(element => {
            const key = element.getAttribute('data-i18n-placeholder');
            element.placeholder = this.t(key);
        });

        // Aktualisiere Title-Attribute
        document.querySelectorAll('[data-i18n-title]').forEach(element => {
            const key = element.getAttribute('data-i18n-title');
            element.title = this.t(key);
        });

        // Aktualisiere Seitentitel
        document.title = this.t('app.title');
        
        // Aktualisiere HTML lang Attribut
        document.documentElement.lang = this.currentLang;
    }

    /**
     * Erstellt Sprachauswahl-Dropdown
     */
    createLanguageSelector() {
        // Prüfe ob Selector bereits existiert
        if (document.querySelector('.language-selector')) {
            return;
        }
        
        const selector = document.createElement('div');
        selector.className = 'language-selector';
        
        // Verwende Fallback wenn Übersetzung fehlt
        const ariaLabel = this.hasTranslation('accessibility.languageSelector') 
            ? this.t('accessibility.languageSelector') 
            : 'Language selection';
        
        selector.innerHTML = `
            <select id="languageSelect" class="language-dropdown" aria-label="${ariaLabel}">
                <option value="de" ${this.currentLang === 'de' ? 'selected' : ''}>🇩🇪 Deutsch</option>
                <option value="en" ${this.currentLang === 'en' ? 'selected' : ''}>🇬🇧 English</option>
            </select>
        `;
        
        // Füge Selector zum Header hinzu
        const header = document.querySelector('header');
        if (header) {
            header.appendChild(selector);
        }
        
        // Event Listener
        document.getElementById('languageSelect').addEventListener('change', (e) => {
            this.changeLanguage(e.target.value);
        });
    }

    /**
     * Formatiert Dateigröße mit lokalisierten Einheiten
     */
    formatFileSize(bytes) {
        const units = [
            this.t('fileSize.bytes'),
            this.t('fileSize.kilobytes'),
            this.t('fileSize.megabytes'),
            this.t('fileSize.gigabytes')
        ];
        
        let i = 0;
        let size = bytes;
        while (size >= 1024 && i < units.length - 1) {
            size /= 1024;
            i++;
        }
        
        return size.toFixed(2) + ' ' + units[i];
    }

    /**
     * Holt die aktuelle Sprache
     */
    getCurrentLanguage() {
        return this.currentLang;
    }

    /**
     * Prüft ob eine Übersetzung existiert
     */
    hasTranslation(key) {
        const keys = key.split('.');
        let translation = this.translations;
        
        for (const k of keys) {
            if (translation && translation[k]) {
                translation = translation[k];
            } else {
                return false;
            }
        }
        
        return true;
    }
}

// Erstelle globale Instanz
const lang = new LanguageManager();

// Exportiere für Verwendung in anderen Dateien
window.lang = lang;