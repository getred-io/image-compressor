<?php
/**
 * ImageProcessor Klasse
 * Dateipfad: /image-compressor/classes/ImageProcessor.php
 * 
 * Hauptklasse für die Bildverarbeitung und Komprimierung
 */

// Lade Sprachkonfiguration wenn noch nicht geladen
if (!function_exists('__')) {
    require_once dirname(__DIR__) . '/config/lang_config.php';
}

class ImageProcessor {
    private $sourceImage = null;
    private $imageInfo = null;
    private $quality = null;
    
    /**
     * Konstruktor
     */
    public function __construct() {
        $this->quality = DEFAULT_QUALITY;
    }
    
    /**
     * Lädt ein Bild
     */
    public function loadImage($imagePath) {
        if (!file_exists($imagePath)) {
            throw new Exception(__('errors.fileNotFound') . ': ' . $imagePath);
        }
        
        // Lösche vorheriges Bild falls vorhanden
        if ($this->sourceImage !== null && is_resource($this->sourceImage)) {
            imagedestroy($this->sourceImage);
            $this->sourceImage = null;
        }
        
        $this->imageInfo = getimagesize($imagePath);
        if ($this->imageInfo === false) {
            throw new Exception(__('errors.invalidImageFile'));
        }
        
        // Lade Bild basierend auf Typ
        switch ($this->imageInfo['mime']) {
            case 'image/jpeg':
            case 'image/jpg':
                $this->sourceImage = imagecreatefromjpeg($imagePath);
                break;
            case 'image/png':
                $this->sourceImage = imagecreatefrompng($imagePath);
                break;
            case 'image/gif':
                $this->sourceImage = imagecreatefromgif($imagePath);
                break;
            case 'image/webp':
                $this->sourceImage = imagecreatefromwebp($imagePath);
                break;
            default:
                throw new Exception(__('errors.unsupportedFileType', ['type' => $this->imageInfo['mime']]));
        }
        
        if ($this->sourceImage === false) {
            $this->sourceImage = null;
            throw new Exception(__('errors.imageLoadFailed'));
        }
        
        return $this;
    }
    
    /**
     * Setzt die Qualität
     */
    public function setQuality($quality) {
        $this->quality = max(1, min(100, $quality));
        return $this;
    }
    
    /**
     * Verarbeitet und speichert das Bild
     */
    public function process($outputPath, $format, $options = []) {
        if (!$this->sourceImage) {
            throw new Exception(__('errors.imageLoadFailed'));
        }
        
        // Erstelle Ausgabebild
        $width = imagesx($this->sourceImage);
        $height = imagesy($this->sourceImage);
        
        // Prüfe maximale Dimensionen
        if ($width > MAX_WIDTH || $height > MAX_HEIGHT) {
            list($width, $height) = $this->calculateNewDimensions($width, $height);
            $outputImage = $this->resize($width, $height);
        } else {
            $outputImage = $this->sourceImage;
        }
        
        // Speichere Bild im gewünschten Format
        $success = false;
        switch ($format) {
            case 'jpeg':
                $success = $this->saveAsJpeg($outputImage, $outputPath);
                break;
            case 'png':
                $success = $this->saveAsPng($outputImage, $outputPath);
                break;
            case 'webp':
                $success = $this->saveAsWebp($outputImage, $outputPath);
                break;
            default:
                throw new Exception(__('errors.unsupportedFormat') . ': ' . $format);
        }
        
        // Räume auf
        if ($outputImage !== $this->sourceImage && is_resource($outputImage)) {
            imagedestroy($outputImage);
        }
        
        return $success;
    }
    
    /**
     * Erstellt ein Thumbnail
     */
    public function createThumbnail($outputPath, $size = THUMBNAIL_SIZE) {
        if (!$this->sourceImage) {
            throw new Exception('Kein Bild geladen');
        }
        
        $width = imagesx($this->sourceImage);
        $height = imagesy($this->sourceImage);
        
        // Berechne Thumbnail-Dimensionen (quadratisch mit Crop)
        $sourceRatio = $width / $height;
        
        if ($sourceRatio > 1) {
            // Landscape
            $newWidth = $size;
            $newHeight = $size;
            $srcX = round(($width - $height) / 2);
            $srcY = 0;
            $srcWidth = $height;
            $srcHeight = $height;
        } else {
            // Portrait oder quadratisch
            $newWidth = $size;
            $newHeight = $size;
            $srcX = 0;
            $srcY = round(($height - $width) / 2);
            $srcWidth = $width;
            $srcHeight = $width;
        }
        
        // Erstelle Thumbnail
        $thumbnail = imagecreatetruecolor($newWidth, $newHeight);
        
        // Transparenz erhalten
        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
        
        // Kopiere und skaliere
        imagecopyresampled(
            $thumbnail, $this->sourceImage,
            0, 0, (int)$srcX, (int)$srcY,
            (int)$newWidth, (int)$newHeight,
            (int)$srcWidth, (int)$srcHeight
        );
        
        // Speichere als JPEG für Thumbnails
        $success = imagejpeg($thumbnail, $outputPath, 85);
        imagedestroy($thumbnail);
        
        return $success;
    }
    
    /**
     * Berechnet neue Dimensionen unter Beibehaltung des Seitenverhältnisses
     */
    private function calculateNewDimensions($width, $height) {
        $ratio = $width / $height;
        
        if ($width > MAX_WIDTH) {
            $width = MAX_WIDTH;
            $height = $width / $ratio;
        }
        
        if ($height > MAX_HEIGHT) {
            $height = MAX_HEIGHT;
            $width = $height * $ratio;
        }
        
        return [round($width), round($height)];
    }
    
    /**
     * Skaliert das Bild
     */
    private function resize($newWidth, $newHeight) {
        $width = imagesx($this->sourceImage);
        $height = imagesy($this->sourceImage);
        
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        
        // Für PNG/GIF: Transparenz behandeln
        if ($this->imageInfo['mime'] === 'image/png' || 
            $this->imageInfo['mime'] === 'image/gif' ||
            $this->imageInfo['mime'] === 'image/webp') {
            
            // Transparenz erhalten
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            
            // Transparenten Hintergrund setzen
            $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
            imagefill($resized, 0, 0, $transparent);
        } else {
            // Für JPEG: weißer Hintergrund
            $white = imagecolorallocate($resized, 255, 255, 255);
            imagefill($resized, 0, 0, $white);
        }
        
        // Skaliere Bild
        imagecopyresampled(
            $resized, $this->sourceImage,
            0, 0, 0, 0,
            (int)$newWidth, (int)$newHeight,
            $width, $height
        );
        
        return $resized;
    }
    
    /**
     * Speichert als JPEG
     */
    private function saveAsJpeg($image, $outputPath) {
        // Konvertiere zu True Color falls nötig
        if (!imageistruecolor($image)) {
            $truecolor = imagecreatetruecolor(imagesx($image), imagesy($image));
        
            // Weißer Hintergrund für JPEG
            $white = imagecolorallocate($truecolor, 255, 255, 255);
            imagefill($truecolor, 0, 0, $white);
        
            imagecopy($truecolor, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
            $image = $truecolor;
        }
    
        // NEU: Bei sehr hoher Qualität (>95) Original beibehalten wenn möglich
        $actualQuality = $this->quality;
        if ($actualQuality > 95) {
            // Begrenze auf 95 um Größenzunahme zu vermeiden
            $actualQuality = 95;
        }
    
        // Progressive JPEG
        imageinterlace($image, true);
    
        return imagejpeg($image, $outputPath, $actualQuality);
    }
    
    /**
     * Speichert als PNG
     */
    private function saveAsPng($image, $outputPath) {
        // PNG-Kompression (0-9, wobei 9 = maximale Kompression)
        $compression = COMPRESSION_SETTINGS['png']['compression_level'];
        
        // Transparenz aktivieren
        imagealphablending($image, false);
        imagesavealpha($image, true);
        
        return imagepng($image, $outputPath, $compression, COMPRESSION_SETTINGS['png']['filters']);
    }
    
    /**
     * Speichert als WebP
     */
    private function saveAsWebp($image, $outputPath) {
        if (!function_exists('imagewebp')) {
            throw new Exception(__('errors.webpNotSupported'));
        }
        
        // Transparenz aktivieren
        imagealphablending($image, false);
        imagesavealpha($image, true);
        
        return imagewebp($image, $outputPath, $this->quality);
    }
    
    /**
     * Gibt Bildinformationen zurück
     */
    public function getImageInfo() {
        if (!$this->sourceImage) {
            return null;
        }
        
        return [
            'width' => imagesx($this->sourceImage),
            'height' => imagesy($this->sourceImage),
            'mime' => $this->imageInfo['mime'],
            'bits' => $this->imageInfo['bits'] ?? null,
            'channels' => $this->imageInfo['channels'] ?? null
        ];
    }
    
    /**
     * Destruktor - Räumt Speicher auf
     */
    public function __destruct() {
        if ($this->sourceImage !== null && is_resource($this->sourceImage)) {
            imagedestroy($this->sourceImage);
            $this->sourceImage = null;
        }
    }
}