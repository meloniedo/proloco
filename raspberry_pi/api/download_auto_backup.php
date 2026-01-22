<?php
// ========================================
// DOWNLOAD BACKUP AUTOMATICO SALVATO
// ========================================
require_once '../includes/config.php';

$filename = $_GET['file'] ?? null;

if (!$filename) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Nome file mancante']);
    exit;
}

// Sicurezza: rimuovi path traversal
$filename = basename($filename);
$backupDir = dirname(__DIR__) . '/backups';
$filepath = $backupDir . '/' . $filename;

if (!file_exists($filepath)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'File non trovato']);
    exit;
}

// Determina content type
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
if ($ext === 'xlsx') {
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
} else {
    header('Content-Type: application/vnd.ms-excel');
}

header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filepath));
readfile($filepath);
