<?php
/**
 * PrnCupRanking.php - The Puchar Polski cup classification as a single
 * streamed A4 PDF, in the standard ianseo report look (IanseoPdf).
 */
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
CheckTourSession(true);
require_once('Fun_PointsRanking.php');
require_once('PointsRankingCalc.php');
require_once('Presets.php');
require_once('PointsRankingPdf.php');
require_once('Fun_Cup.php');
require_once('CupCalc.php');

pl_points_ensure_tables();

$tourId = $_SESSION['TourId'];
if (pl_points_get_tournament_preset($tourId) !== PL_CUP_PRESET_KEY) {
    die('Klasyfikacja generalna dotyczy wyłącznie Pucharu Polski.');
}

pl_cup_ensure_tables();

$config = pl_cup_get_config($tourId);
$roundRows = pl_cup_load_rounds($config['Edition']);
if (empty($roundRows)) {
    die('Brak zapisanych rund dla tej edycji.');
}

$storedRounds = pl_cup_stored_rounds($config['Edition']);
$classifications = pl_cup_build_classifications(
    $roundRows,
    pl_cup_load_barrages($config['Edition']),
    pl_cup_category_meta($tourId),
    in_array(PL_CUP_ROUNDS, $storedRounds, true)
);

const PL_CUP_PDF_WIDTH = 190; // A4 portrait, IanseoPdf's 10mm side margins

// The header must carry the edition even when the configured name omits it,
// so PDFs of two seasons cannot be confused.
$cupName = $config['DiplomaName'] !== '' ? $config['DiplomaName'] : 'Puchar Polski';
if (strpos($cupName, (string) $config['Edition']) === false) {
    $cupName .= ' ' . $config['Edition'];
}

/**
 * Short marks, kept inside the column's width at 8 pt: "baraż" alone would read
 * the same on a row a shoot-off decided and on one still waiting for it, and the
 * PDF is the official output, so it says which.
 */
const PL_CUP_PDF_MARKS = ['BARRAGE' => 'baraż', 'SHARED' => 'm. dzielone'];

function pl_cup_pdf_mark($row)
{
    if (!empty($row['barrage_resolved'])) {
        return 'po barażu';
    }
    return $row['tie_mark'] === '' ? '' : (PL_CUP_PDF_MARKS[$row['tie_mark']] ?? '');
}

function pl_cup_pdf_classification($pdf, array $classification, $isMixed, $cupName)
{
    $roundWidth = 9;
    $nameWidth = 44;
    $clubWidth = 36;
    $fixed = 14 + $nameWidth + $clubWidth + 20 + PL_CUP_ROUNDS * $roundWidth + 14;

    $columns = [
        ['label' => 'Miejsce', 'width' => 14, 'align' => 'C', 'bold' => true],
        ['label' => $isMixed ? 'Klub' : 'Zawodnik', 'width' => $nameWidth, 'align' => 'L', 'bold' => !$isMixed],
        ['label' => $isMixed ? 'Skład' : 'Klub', 'width' => $clubWidth, 'align' => 'L'],
        ['label' => $isMixed ? 'Kod klubu' : 'Nr licencji', 'width' => 20, 'align' => 'C'],
    ];
    for ($round = 1; $round <= PL_CUP_ROUNDS; $round++) {
        $columns[] = ['label' => 'R' . $round, 'width' => $roundWidth, 'align' => 'C'];
    }
    $columns[] = ['label' => 'Suma', 'width' => 14, 'align' => 'C', 'bold' => true];
    $columns[] = ['label' => 'Uwagi', 'width' => PL_CUP_PDF_WIDTH - $fixed, 'align' => 'C'];

    foreach ($classification['sections'] as $section) {
        $rows = array_map(function ($row) use ($isMixed) {
            $cells = [
                $row['rank'],
                $isMixed ? $row['club_name'] : $row['name'],
                $isMixed ? $row['name'] : $row['club_name'],
                $row['identity'],
            ];
            for ($round = 1; $round <= PL_CUP_ROUNDS; $round++) {
                $cells[] = isset($row['rounds'][$round]) ? intval($row['rounds'][$round]) : '';
            }
            $cells[] = intval($row['total']);
            $cells[] = pl_cup_pdf_mark($row);
            return $cells;
        }, $section['rows']);

        $pdf->AddPage();
        $pdf->renderTable($cupName . ' - ' . $classification['label'] . ' - ' . $section['label'], $columns, $rows);
    }
}

$pdf = new PointsRankingPdf($cupName . ' - klasyfikacja generalna', true /* portrait */);
$pdf->SetAutoPageBreak(true, 15);
$pdf->startPageGroup();

pl_cup_pdf_classification($pdf, $classifications['ind'], false, $cupName);
pl_cup_pdf_classification($pdf, $classifications['mix'], true, $cupName);

$pdf->Output('puchar_polski_klasyfikacja.pdf', 'I');
