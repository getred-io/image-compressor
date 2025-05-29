<?php
/**
 * Download Manager
 * Dateipfad: /image-compressor/download_manager.php
 * 
 * Bietet eine Übersicht aller verarbeiteten Dateien mit Download-Optionen
 */

session_start();
require_once 'config/config.php';
require_once 'config/lang_config.php';

// Lade Übersetzungen
$lang = loadTranslations();

// Prüfe ob verarbeitete Dateien vorhanden sind
if (!isset($_SESSION['processed_files']) || empty($_SESSION['processed_files'])) {
    header('Location: index.php');
    exit;
}

$processedFiles = $_SESSION['processed_files'];
$fileCount = count($processedFiles);
?>
<!DOCTYPE html>
<html lang="<?php echo detectLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('downloadManager.title'); ?> - <?php echo __('app.title'); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .download-manager {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        
        .file-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .file-table th {
            background-color: #3498db;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: bold;
        }
        
        .file-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #ecf0f1;
        }
        
        .file-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .download-link {
            color: #3498db;
            text-decoration: none;
            font-weight: bold;
        }
        
        .download-link:hover {
            text-decoration: underline;
        }
        
        .checkbox-column {
            width: 40px;
            text-align: center;
        }
        
        .action-buttons {
            margin: 20px 0;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .statistics-box {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
    </style>
    <script src="assets/js/language.js"></script>
</head>
<body>
    <div class="container">
        <header>
            <h1><?php echo __('downloadManager.title'); ?></h1>
            <p><?php echo __('downloadManager.filesProcessed', ['count' => $fileCount]); ?></p>
        </header>

        <div class="download-manager">
            <div class="action-buttons">
                <button class="btn btn-success" onclick="downloadZip()">
                    <?php echo __('downloadManager.downloadAllZip'); ?>
                </button>
                <button class="btn btn-primary" onclick="downloadSelected()">
                    <?php echo __('downloadManager.downloadSelectedZip'); ?>
                </button>
                <button class="btn btn-primary" onclick="selectAll()">
                    <?php echo __('downloadManager.selectAll'); ?>
                </button>
                <button class="btn" onclick="selectNone()">
                    <?php echo __('downloadManager.deselectAll'); ?>
                </button>
            </div>

            <table class="file-table">
                <thead>
                    <tr>
                        <th class="checkbox-column">
                            <input type="checkbox" id="selectAllCheckbox" onchange="toggleAll()">
                        </th>
                        <th><?php echo __('downloadManager.filename'); ?></th>
                        <th><?php echo __('downloadManager.original'); ?></th>
                        <th><?php echo __('downloadManager.compressed'); ?></th>
                        <th><?php echo __('downloadManager.savings'); ?></th>
                        <th><?php echo __('downloadManager.actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($processedFiles as $fileId => $file): ?>
                    <tr>
                        <td class="checkbox-column">
                            <input type="checkbox" class="file-checkbox" value="<?php echo $fileId; ?>">
                        </td>
                        <td><?php echo htmlspecialchars($file['original_name']); ?></td>
                        <td><?php echo formatFileSize($file['original_size']); ?></td>
                        <td><?php echo formatFileSize($file['processed_size']); ?></td>
                        <td>
                            <?php 
                            $savings = $file['original_size'] - $file['processed_size'];
                            $savingsPercent = round(($savings / $file['original_size']) * 100, 1);
                            $savingsClass = $savingsPercent > 0 ? 'savings-positive' : 'savings-negative';
                            ?>
                            <span class="<?php echo $savingsClass; ?>">
                                <?php echo formatFileSize($savings); ?> (<?php echo $savingsPercent; ?>%)
                            </span>
                        </td>
                        <td>
                            <a href="download.php?action=single&id=<?php echo $fileId; ?>" 
                               class="download-link"><?php echo __('downloadManager.download'); ?></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php
            // Berechne Gesamtstatistiken
            $totalOriginal = 0;
            $totalProcessed = 0;
            foreach ($processedFiles as $file) {
                $totalOriginal += $file['original_size'];
                $totalProcessed += $file['processed_size'];
            }
            $totalSavings = $totalOriginal - $totalProcessed;
            $totalSavingsPercent = round(($totalSavings / $totalOriginal) * 100, 1);
            ?>

            <div class="statistics-box">
                <h3><?php echo __('downloadManager.totalStatistics'); ?></h3>
                <p>
                    <strong><?php echo __('downloadManager.original'); ?>:</strong> <?php echo formatFileSize($totalOriginal); ?> → 
                    <strong><?php echo __('downloadManager.compressed'); ?>:</strong> <?php echo formatFileSize($totalProcessed); ?>
                </p>
                <p>
                    <strong><?php echo __('downloadManager.totalSavings'); ?>:</strong> 
                    <span class="savings-positive">
                        <?php echo formatFileSize($totalSavings); ?> (<?php echo $totalSavingsPercent; ?>%)
                    </span>
                </p>
            </div>

            <div class="action-buttons">
                <a href="index.php?reset=1" class="btn btn-primary"><?php echo __('downloadManager.newSession'); ?></a>
                <a href="index.php" class="btn"><?php echo __('downloadManager.backToOverview'); ?></a>
            </div>
        </div>
    </div>

    <script>
    // Initialisiere Sprache
    lang.init().then(() => {
        const originalChangeLanguage = lang.changeLanguage.bind(lang);
        lang.changeLanguage = async function(newLang) {
            await originalChangeLanguage(newLang);
            // Seite mit neuer Sprache neu laden
            window.location.href = window.location.pathname + '?lang=' + newLang;
        };
    });

    function downloadZip() {
        window.location.href = 'download.php?action=zip';
    }

    function downloadSelected() {
        const checkboxes = document.querySelectorAll('.file-checkbox:checked');
        if (checkboxes.length === 0) {
            alert(lang.t('downloadManager.selectAtLeastOne'));
            return;
        }

        const fileIds = Array.from(checkboxes).map(cb => cb.value);
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'download.php?action=selected';
        
        fileIds.forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'files[]';
            input.value = id;
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
    }

    function selectAll() {
        document.querySelectorAll('.file-checkbox').forEach(cb => cb.checked = true);
        document.getElementById('selectAllCheckbox').checked = true;
    }

    function selectNone() {
        document.querySelectorAll('.file-checkbox').forEach(cb => cb.checked = false);
        document.getElementById('selectAllCheckbox').checked = false;
    }

    function toggleAll() {
        const selectAll = document.getElementById('selectAllCheckbox').checked;
        document.querySelectorAll('.file-checkbox').forEach(cb => cb.checked = selectAll);
    }
    </script>
</body>
</html>