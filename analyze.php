<?php
/**
 * Bildanalyse
 * Dateipfad: /image-compressor/analyze.php
 * 
 * Analysiert hochgeladene Bilder und gibt Formatempfehlungen
 */

session_start();
require_once 'config/config.php';
require_once 'config/lang_config.php';

// Setze JSON-Header
header('Content-Type: application/json');

// Prüfe ob Dateien hochgeladen wurden
if (!isset($_SESSION['uploaded_files']) || empty($_SESSION['uploaded_files'])) {
    http_response_code(400);
    echo json_encode(['error' => __('errors.noFilesToAnalyze')]);
    exit;
}

$analysisResults = [];
$totalOriginalSize = 0;
$recommendations = [];

// Analysiere jede hochgeladene Datei
foreach ($_SESSION['uploaded_files'] as $fileId => $fileData) {
    if (!file_exists($fileData['path'])) {
        continue;
    }
    
    // Erweiterte Bildanalyse
    $analysis = analyzeImage($fileData['path'], $fileData);
    
    // Füge zur Gesamtgröße hinzu
    $totalOriginalSize += $fileData['size'];
    
    // Speichere Analyse
    $analysisResults[$fileId] = $analysis;
    
    // Sammle Formatempfehlungen
    if (!isset($recommendations[$analysis['recommended_format']])) {
        $recommendations[$analysis['recommended_format']] = 0;
    }
    $recommendations[$analysis['recommended_format']]++;
}

// Bestimme das am häufigsten empfohlene Format
arsort($recommendations);
$recommendedFormat = key($recommendations);

// Berechne geschätzte Einsparungen
$estimatedSavings = calculateEstimatedSavings($analysisResults, $recommendedFormat);

// Sende Analyseergebnisse
$response = [
    'success' => true,
    'analysis' => [
        'total_files' => count($analysisResults),
        'total_size' => formatFileSize($totalOriginalSize),
        'total_size_bytes' => $totalOriginalSize,
        'recommended_format' => $recommendedFormat,
        'estimated_savings' => $estimatedSavings,
        'format_distribution' => $recommendations,
        'files' => array_values(array_map(function($analysis) {
            return [
                'id' => $analysis['id'],
                'name' => $analysis['name'],
                'current_format' => $analysis['current_format'],
                'has_transparency' => $analysis['has_transparency'],
                'color_depth' => $analysis['color_depth'],
                'recommended_format' => $analysis['recommended_format'],
                'size' => formatFileSize($analysis['size']),
                'size_bytes' => $analysis['size'],
                'width' => $analysis['width'],
                'height' => $analysis['height']
            ];
        }, $analysisResults))
    ]
];

// Debug-Ausgabe nur wenn DEBUG_MODE aktiv
if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
    error_log('Analyze.php response: ' . json_encode($response));
}

echo json_encode($response);

/**
 * Analysiert ein einzelnes Bild
 */
function analyzeImage($imagePath, $fileData) {
    $imageInfo = getimagesize($imagePath);
    $format = explode('/', $imageInfo['mime'])[1];
    
    // Prüfe auf Transparenz
    $hasTransparency = false;
    $colorDepth = 24; // Standard für JPEG
    
    switch ($imageInfo['mime']) {
        case 'image/png':
            $hasTransparency = checkPngTransparency($imagePath);
            $colorDepth = 32; // PNG kann 32-bit sein
            break;
        case 'image/gif':
            $hasTransparency = checkGifTransparency($imagePath);
            $colorDepth = 8; // GIF ist 8-bit
            break;
        case 'image/webp':
            // WebP kann Transparenz haben
            $hasTransparency = checkWebpTransparency($imagePath);
            $colorDepth = 32;
            break;
    }
    
    // Bestimme empfohlenes Format
    $recommendedFormat = determineRecommendedFormat($fileData, $hasTransparency, $colorDepth);
    
    return [
        'id' => $fileData['id'],
        'name' => $fileData['original_name'],
        'size' => $fileData['size'],
        'width' => $fileData['width'],
        'height' => $fileData['height'],
        'current_format' => $format,
        'has_transparency' => $hasTransparency,
        'color_depth' => $colorDepth,
        'recommended_format' => $recommendedFormat,
        'mime_type' => $fileData['mime_type']
    ];
}

/**
 * Prüft PNG auf Transparenz (optimiert)
 */
function checkPngTransparency($filename) {
    $img = @imagecreatefrompng($filename);
    if (!$img) {
        return false;
    }
    
    $width = imagesx($img);
    $height = imagesy($img);
    
    // Prüfe nur Stichproben statt alle Pixel
    $sampleSize = min(100, $width * $height);
    $stepX = max(1, floor($width / sqrt($sampleSize)));
    $stepY = max(1, floor($height / sqrt($sampleSize)));
    
    for ($x = 0; $x < $width; $x += $stepX) {
        for ($y = 0; $y < $height; $y += $stepY) {
            $rgba = imagecolorat($img, $x, $y);
            $alpha = ($rgba & 0x7F000000) >> 24;
            if ($alpha > 0) {
                imagedestroy($img);
                return true;
            }
        }
    }
    
    imagedestroy($img);
    return false;
}

/**
 * Prüft GIF auf Transparenz
 */
function checkGifTransparency($filename) {
    $img = @imagecreatefromgif($filename);
    if (!$img) {
        return false;
    }
    $transparent = imagecolortransparent($img);
    imagedestroy($img);
    return $transparent >= 0;
}

/**
 * Prüft WebP auf Transparenz
 */
function checkWebpTransparency($filename) {
    if (!function_exists('imagecreatefromwebp')) {
        return false;
    }
    
    $img = @imagecreatefromwebp($filename);
    if (!$img) {
        return false;
    }
    
    // Prüfe ob Alphablending aktiviert ist
    imagesavealpha($img, true);
    
    $width = imagesx($img);
    $height = imagesy($img);
    
    // Prüfe Stichproben auf Alpha-Kanal
    $sampleSize = min(100, $width * $height);
    $stepX = max(1, floor($width / sqrt($sampleSize)));
    $stepY = max(1, floor($height / sqrt($sampleSize)));
    
    $hasTransparency = false;
    for ($x = 0; $x < $width && !$hasTransparency; $x += $stepX) {
        for ($y = 0; $y < $height && !$hasTransparency; $y += $stepY) {
            $rgba = imagecolorat($img, $x, $y);
            $alpha = ($rgba & 0x7F000000) >> 24;
            if ($alpha > 0) {
                $hasTransparency = true;
                break;
            }
        }
    }
    
    imagedestroy($img);
    return $hasTransparency;
}

/**
 * Bestimmt das empfohlene Format basierend auf Bildeigenschaften
 */
function determineRecommendedFormat($fileData, $hasTransparency, $colorDepth) {
    // Wenn Transparenz benötigt wird
    if ($hasTransparency) {
        // WebP unterstützt Transparenz und hat bessere Kompression
        if (function_exists('imagewebp')) {
            return 'webp';
        }
        return 'png';
    }
    
    // Für Fotos und Bilder ohne Transparenz
    $pixelCount = $fileData['width'] * $fileData['height'];
    
    // Bei sehr großen Bildern ist WebP effizienter
    if ($pixelCount > 2000000 && function_exists('imagewebp')) { // > 2 Megapixel
        return 'webp';
    }
    
    // Für normale Fotos ist JPEG meist ausreichend
    return 'jpeg';
}

/**
 * Berechnet geschätzte Einsparungen
 */
function calculateEstimatedSavings($analysisResults, $format) {
    $totalOriginal = 0;
    $estimatedNew = 0;
    
    foreach ($analysisResults as $analysis) {
        $totalOriginal += $analysis['size'];
        
        // Schätze neue Größe basierend auf Format
        $factor = 1.0;
        
        // NEU: Realistischere Faktoren basierend auf Erfahrungswerten
        if ($analysis['current_format'] === $format) {
            // Gleiches Format = Optimierung/Neukompression
            switch ($format) {
                case 'jpeg':
                    $factor = 0.3; // JPEG zu JPEG kann 70% sparen bei hoher Ausgangsqualität
                    break;
                case 'webp':
                    $factor = 0.7; // WebP zu WebP weniger Potenzial
                    break;
                case 'png':
                    $factor = 0.85; // PNG zu PNG meist nur kleine Optimierung
                    break;
            }
        } else {
            // Format-Konvertierung
            switch ($format) {
                case 'jpeg':
                    if ($analysis['current_format'] === 'png') {
                        $factor = 0.2; // PNG zu JPEG kann 80% sparen
                    } else if ($analysis['current_format'] === 'webp') {
                        $factor = 0.8; // WebP zu JPEG meist größer
                    }
                    break;
                case 'webp':
                    $factor = 0.25; // WebP kann ~75% sparen
                    break;
                case 'png':
                    $factor = 1.2; // PNG ist meist größer
                    break;
            }
        }
        
        $estimatedNew += $analysis['size'] * $factor;
    }
    
    $savings = $totalOriginal - $estimatedNew;
    $percentage = ($totalOriginal > 0) ? ($savings / $totalOriginal) * 100 : 0;
    
    return [
        'bytes' => $savings,
        'formatted' => formatFileSize($savings),
        'percentage' => round($percentage, 1)
    ];
}
