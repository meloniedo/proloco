<?php
// ========================================
// API EXPORT EXCEL
// ========================================
require_once '../includes/config.php';

// Require PhpSpreadsheet (installare via composer)
// composer require phpoffice/phpspreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Per ora usiamo CSV come fallback (funziona senza librerie esterne)

$pdo = getDB();

// Ottieni vendite
$vendite = $pdo->query("SELECT * FROM vendite ORDER BY timestamp DESC")->fetchAll();
$spese = $pdo->query("SELECT * FROM spese ORDER BY timestamp DESC")->fetchAll();

// Genera CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="backup_proloco_' . date('Y-m-d_H-i') . '.csv"');

$output = fopen('php://output', 'w');

// BOM per Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Header vendite
fputcsv($output, ['=== VENDITE ==='], ';');
fputcsv($output, ['ID', 'Data', 'Ora', 'Prodotto', 'Categoria', 'Importo'], ';');

foreach ($vendite as $v) {
    $dt = new DateTime($v['timestamp']);
    fputcsv($output, [
        $v['id'],
        $dt->format('d/m/Y'),
        $dt->format('H:i:s'),
        $v['nome_prodotto'],
        $v['categoria'],
        number_format($v['prezzo'], 2, ',', '')
    ], ';');
}

// Totale vendite
$totaleVendite = array_reduce($vendite, function($sum, $v) { return $sum + $v['prezzo']; }, 0);
fputcsv($output, ['', '', '', '', 'TOTALE VENDITE:', number_format($totaleVendite, 2, ',', '')], ';');

fputcsv($output, [''], ';');

// Header spese
fputcsv($output, ['=== SPESE ==='], ';');
fputcsv($output, ['ID', 'Data', 'Ora', 'Categoria', 'Note', 'Importo'], ';');

foreach ($spese as $s) {
    $dt = new DateTime($s['timestamp']);
    fputcsv($output, [
        $s['id'],
        $dt->format('d/m/Y'),
        $dt->format('H:i:s'),
        $s['categoria_spesa'],
        $s['note'],
        number_format($s['importo'], 2, ',', '')
    ], ';');
}

// Totale spese
$totaleSpese = array_reduce($spese, function($sum, $s) { return $sum + $s['importo']; }, 0);
fputcsv($output, ['', '', '', '', 'TOTALE SPESE:', number_format($totaleSpese, 2, ',', '')], ';');

fputcsv($output, [''], ';');

// Riepilogo
fputcsv($output, ['=== RIEPILOGO ==='], ';');
fputcsv($output, ['Incassi Totali', number_format($totaleVendite, 2, ',', '')], ';');
fputcsv($output, ['Spese Totali', number_format($totaleSpese, 2, ',', '')], ';');
fputcsv($output, ['PROFITTO NETTO', number_format($totaleVendite - $totaleSpese, 2, ',', '')], ';');

fclose($output);
