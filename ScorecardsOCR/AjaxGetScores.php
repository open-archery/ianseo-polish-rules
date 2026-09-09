<?php
/**
 * AjaxGetScores.php — Return Qualifications scores for a scorecard barcode.
 *
 * Parses the barcode text (format: {EnCode}-{Div}-{Cls} or {EnCode}-{Div}-{Cls}-{Distance})
 * and returns QuD{N}Score/Gold/Xnine (per-distance) or QuScore/QuGold/QuXnine (grand total).
 *
 * POST params:
 *   barcode_text  string   e.g. "5083-R-U21M" or "5083-R-U21M-2"
 *   session       int|null Distance index (1–8) derived from OCR session_label; used when
 *                          the barcode itself carries no Distance suffix.
 */

require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php';
CheckTourSession(false);
require_once __DIR__ . '/Fun_ScorecardsOcr.php';

header('Content-Type: application/json; charset=utf-8');

$barcodeText = trim($_REQUEST['barcode_text'] ?? '');

if ($barcodeText === '') {
    echo json_encode(['found' => false, 'error' => 'Brak kodu kreskowego.']);
    exit;
}

// Barcode format: {EnCode}-{Div}-{Cls} or {EnCode}-{Div}-{Cls}-{Distance}
// The first dash-separated segment is the entry code (= bib).
// The last segment is the Distance index (1–8) when present; otherwise it is
// the class code (non-numeric or out of range) and should be ignored.
$parts = explode('-', $barcodeText);
$bib   = $parts[0];

if ($bib === '') {
    echo json_encode(['found' => false, 'error' => 'Nieprawidłowy format kodu: ' . htmlspecialchars($barcodeText)]);
    exit;
}

// Try to read the Distance from the barcode itself (most authoritative).
$lastSegment    = count($parts) >= 2 ? intval(end($parts)) : 0;
$barcodeSession = ($lastSegment >= 1 && $lastSegment <= 8) ? $lastSegment : 0;

// Fall back to the session derived from OCR session_label, sent as POST param.
$postSession = isset($_REQUEST['session']) ? intval($_REQUEST['session']) : 0;
$session     = ($barcodeSession >= 1) ? $barcodeSession
             : (($postSession >= 1 && $postSession <= 8) ? $postSession : 0);

pl_ocr_install();

$tourId = intval($_SESSION['TourId'] ?? 0);
if ($tourId <= 0) {
    echo json_encode(['found' => false, 'error' => 'Brak aktywnych zawodów.']);
    exit;
}

$result          = pl_ocr_lookup_scores($bib, $session, $tourId);
$result['bib']     = $bib;
$result['session'] = $session;

echo json_encode($result);
