<?php
/**
 * PrnCombinedRankingDipl.php — Generate diploma PDFs from the combined ranking.
 *
 * POST parameters:
 *   tour1  (int, required) — Tournament 1 ID
 *   tour2  (int, optional) — Tournament 2 ID; omit or 0 for single-tournament ranking
 *
 * All diploma content (competition name, dates, location, judge, organizer, place range)
 * is read from PLDiplomaConfig for the active session tournament.
 * Title lines are not printed on combined ranking diplomas.
 */

require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php';
CheckTourSession(true);
require_once dirname(__FILE__) . '/Fun_CombinedRanking.php';
require_once dirname(__FILE__) . '/../Diplomas/DiplomaSetup.php';
require_once dirname(__FILE__) . '/../Diplomas/PLDiplomaPdf.php';

// ── Input validation ──────────────────────────────────────────────────────────

$tour1 = isset($_POST['tour1']) && $_POST['tour1'] !== '' ? (int)$_POST['tour1'] : 0;
$tour2 = isset($_POST['tour2']) && $_POST['tour2'] !== '' ? (int)$_POST['tour2'] : 0;

if ($tour1 === 0) {
    die('Błąd: należy wybrać co najmniej jeden turniej (Dzień 1).');
}

// ── Diploma config ────────────────────────────────────────────────────────────

pl_diploma_ensure_tables();
$config     = pl_diploma_get_config((int)$_SESSION['TourId']);
$eventTexts = pl_diploma_get_event_texts((int)$_SESSION['TourId']);

// ── Compute combined ranking ──────────────────────────────────────────────────

$data1    = pl_combined_ranking_load($tour1);
$data2    = ($tour2 > 0) ? pl_combined_ranking_load($tour2) : [];
$merged   = pl_combined_ranking_merge($data1, $data2);
$labels   = pl_combined_ranking_get_div_labels($tour1);
$sections = pl_combined_ranking_compute($merged, $labels);

$placeFrom = (int)$config['PlaceFrom'];
$placeTo   = (int)$config['PlaceTo'];

// ── Check whether any qualifying rows exist ───────────────────────────────────

$hasRows = false;
foreach ($sections as $section) {
    foreach ($section['rows'] as $row) {
        if ($row['rank'] >= $placeFrom && $row['rank'] <= $placeTo) {
            $hasRows = true;
            break 2;
        }
    }
}

if (!$hasRows) {
    die('Brak wyników w podanym zakresie miejsc do wygenerowania dyplomów.');
}

// ── Generate PDF ──────────────────────────────────────────────────────────────

$pdf = PLDiplomaPdf::createInstance('Dyplomy rankingu łączonego');

foreach ($sections as $section) {
    foreach ($section['rows'] as $row) {
        if ($row['rank'] < $placeFrom || $row['rank'] > $placeTo) {
            continue;
        }

        $compositeKey = 'I:' . $section['divClass'];
        $classText = $section['label'];
        if (isset($eventTexts[$compositeKey]) && !empty($eventTexts[$compositeKey]['customText'])) {
            $classText = $eventTexts[$compositeKey]['customText'];
        }

        $pdf->printDiploma(
            $config['CompetitionName'],
            $config['Dates'],
            $config['Location'],
            $classText,
            $row['rank'],
            $row['name'],
            $row['club'],
            [],                   // no team members — always individual
            $config['BodyText'],
            $config['HeadJudge'],
            $config['Organizer'],
            ''                    // no title line for combined ranking diplomas
        );
    }
}

$pdf->Output('dyplomy_ranking_laczony.pdf', 'I');
