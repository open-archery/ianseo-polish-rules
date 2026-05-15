<?php
/**
 * AjaxGetScores.php — Return Qualifications scores for a scorecard barcode.
 *
 * Parses the barcode text from the scorecard (format: {bib}-{div}-{class}-{session}),
 * looks up the archer in Entries, and returns QuD{N}Score/Gold/Xnine from Qualifications.
 *
 * GET/POST params:
 *   barcode_text  string  e.g. "5083-R-U21M-2"
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

// Parse barcode format: {bib}-{div}-{class}-{session}
// The session index is always the last segment after the final dash.
$parts   = explode('-', $barcodeText);
$session = (count($parts) >= 2) ? intval(array_pop($parts)) : 0;
$bib     = array_shift($parts); // first segment is the bib number

if ($bib === '' || $session < 1 || $session > 8) {
    echo json_encode(['found' => false, 'error' => 'Nieprawidłowy format kodu: ' . htmlspecialchars($barcodeText)]);
    exit;
}

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
