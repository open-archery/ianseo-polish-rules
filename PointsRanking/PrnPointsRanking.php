<?php
/**
 * PrnPointsRanking.php - Render the active preset's declared reports as a
 * single streamed A4 PDF, using the standard ianseo report look (IanseoPdf).
 */
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
CheckTourSession(true);
require_once('Fun_PointsRanking.php');
require_once('PointsRankingCalc.php');
require_once('Presets.php');
require_once('PointsRankingPdf.php');

pl_points_ensure_tables();

$activePresetKey = pl_points_get_tournament_preset($_SESSION['TourId']);
if (!$activePresetKey || !isset(PL_POINTS_PRESETS[$activePresetKey])) {
    die('Nie wybrano klasyfikacji punktowej.');
}
$activePreset = PL_POINTS_PRESETS[$activePresetKey];

$result = pl_points_calculate($_SESSION['TourId'], $activePreset);
if (empty($result['reports'])) {
    die('Brak wyników do wydrukowania.');
}

const PL_POINTS_PDF_WIDTH = 190; // A4 portrait, IanseoPdf's 10mm side margins

function pl_points_pdf_row_label(array $row)
{
    return $row['name'] ?? $row['club_name'];
}

function pl_points_pdf_separate_report($pdf, array $report)
{
    $subject = $report['subject'] ?? 'IND';
    $isMixed = $subject === 'MIXED';
    $isTeam = $subject !== 'IND';

    foreach ($report['sections'] as $section) {
        if ($isMixed) {
            // No license number (a pair has two, not one); club identifies the
            // entry, "Skład" (roster) names the pair.
            $columns = [
                ['label' => 'Miejsce', 'width' => 14, 'align' => 'C', 'bold' => true],
                ['label' => 'Klub', 'width' => 57, 'align' => 'L'],
                ['label' => 'Skład', 'width' => 62, 'align' => 'L'],
                ['label' => 'Miejsce w zawodach', 'width' => 20, 'align' => 'C'],
                ['label' => 'Punkty', 'width' => PL_POINTS_PDF_WIDTH - 14 - 57 - 62 - 20, 'align' => 'C', 'bold' => true],
            ];
            $rows = array_map(fn ($row) => [
                $row['rank'],
                $row['club_name'],
                pl_points_pdf_row_label($row),
                $row['place'],
                pl_points_format_number($row['points']),
            ], $section['rows']);
        } else {
            $columns = [
                ['label' => 'Miejsce', 'width' => 14, 'align' => 'C', 'bold' => true],
                // Bold only when this column actually holds an athlete's name (not a team/club name).
                ['label' => $isTeam ? 'Zespół' : 'Zawodnik', 'width' => 62, 'align' => 'L', 'bold' => !$isTeam],
                ['label' => 'Klub', 'width' => 57, 'align' => 'L'],
                ['label' => 'Nr licencji', 'width' => 22, 'align' => 'C'],
                ['label' => 'Miejsce w zawodach', 'width' => 20, 'align' => 'C'],
                ['label' => 'Punkty', 'width' => PL_POINTS_PDF_WIDTH - 14 - 62 - 57 - 22 - 20, 'align' => 'C', 'bold' => true],
            ];
            $rows = array_map(fn ($row) => [
                $row['rank'],
                pl_points_pdf_row_label($row),
                $row['club_name'],
                $isTeam ? '' : $row['code'],
                $row['place'],
                pl_points_format_number($row['points']),
            ], $section['rows']);
        }

        $pdf->AddPage();
        $pdf->renderTable($report['label'] . ' - ' . $section['label'], $columns, $rows);
    }
}

function pl_points_pdf_combined_report($pdf, array $report)
{
    // No overall "Miejsce" column — see pl_points_render_combined_report()'s
    // HTML counterpart for why.
    $classKeys = $report['classifications'];
    $fixed = 46 + 40 + 20 + 15;
    $pairW = count($classKeys) > 0 ? floor((PL_POINTS_PDF_WIDTH - $fixed) / count($classKeys)) : 0;
    $placeW = max(9, round($pairW * 0.35));
    $pointsW = $pairW - $placeW;

    $columns = [
        ['label' => 'Zawodnik', 'width' => 46, 'align' => 'L', 'bold' => true],
        ['label' => 'Klub', 'width' => 40, 'align' => 'L'],
        ['label' => 'Nr licencji', 'width' => 20, 'align' => 'C'],
    ];
    foreach ($classKeys as $ck) {
        $short = pl_points_classification_short_label($ck);
        $columns[] = ['label' => 'M. ' . $short, 'width' => $placeW, 'align' => 'C'];
        $columns[] = ['label' => 'Pkt. ' . $short, 'width' => $pointsW, 'align' => 'C'];
    }
    $columns[] = ['label' => 'Suma', 'width' => 15, 'align' => 'C', 'bold' => true];

    foreach ($report['sections'] as $section) {
        $rows = array_map(function ($row) use ($classKeys) {
            $cells = [$row['name'], $row['club_name'], $row['code']];
            foreach ($classKeys as $ck) {
                $col = $row['columns'][$ck] ?? ['value' => 0, 'dropped' => false, 'place' => null];
                $cells[] = $col['place'] !== null ? intval($col['place']) : '';
                if ($col['value'] <= 0) {
                    $cells[] = '';
                } elseif ($col['dropped']) {
                    $cells[] = ['dropped' => pl_points_format_number($col['value'])];
                } else {
                    $cells[] = pl_points_format_number($col['value']);
                }
            }
            $cells[] = pl_points_format_number($row['points']);
            return $cells;
        }, $section['rows']);

        $pdf->AddPage();
        $pdf->renderTable($report['label'] . ' - ' . $section['label'], $columns, $rows);
    }
}

function pl_points_pdf_club_report($pdf, array $report)
{
    $showVoiv = !empty($report['show_voivodeship']);
    $columns = $showVoiv
        ? [
            ['label' => 'Miejsce', 'width' => 15, 'align' => 'C', 'bold' => true],
            ['label' => 'Klub', 'width' => 93, 'align' => 'L'],
            ['label' => 'Województwo', 'width' => 52, 'align' => 'L'],
            ['label' => 'Suma', 'width' => PL_POINTS_PDF_WIDTH - 15 - 93 - 52, 'align' => 'C', 'bold' => true],
        ]
        : [
            ['label' => 'Miejsce', 'width' => 15, 'align' => 'C', 'bold' => true],
            ['label' => 'Klub', 'width' => 145, 'align' => 'L'],
            ['label' => 'Suma', 'width' => PL_POINTS_PDF_WIDTH - 15 - 145, 'align' => 'C', 'bold' => true],
        ];

    $rows = array_map(function ($row) use ($showVoiv) {
        $cells = [$row['rank'], $row['club_name']];
        if ($showVoiv) {
            $cells[] = $row['voivodeship'];
        }
        $cells[] = pl_points_format_number($row['points']);
        return $cells;
    }, $report['rows']);

    $pdf->AddPage();
    $pdf->renderTable($report['label'], $columns, $rows);
}

function pl_points_pdf_voivodeship_report($pdf, array $report)
{
    $columns = [
        ['label' => 'Miejsce', 'width' => 20, 'align' => 'C', 'bold' => true],
        ['label' => 'Województwo', 'width' => 135, 'align' => 'L'],
        ['label' => 'Suma', 'width' => PL_POINTS_PDF_WIDTH - 20 - 135, 'align' => 'C', 'bold' => true],
    ];

    $rows = array_map(fn ($row) => [$row['rank'], $row['voivodeship'], pl_points_format_number($row['points'])], $report['rows']);

    $pdf->AddPage();
    $pdf->renderTable($report['label'], $columns, $rows);
}

// Preset name goes into the document metadata title only (not printed on any
// page) — the visible header stays the standard ianseo tournament header.
$pdf = new PointsRankingPdf('Klasyfikacja punktowa - ' . $activePreset['name'], true /* portrait */);
$pdf->SetAutoPageBreak(true, 15);
$pdf->startPageGroup();

foreach ($result['reports'] as $report) {
    switch ($report['kind']) {
        case 'SEPARATE':
            pl_points_pdf_separate_report($pdf, $report);
            break;
        case 'COMBINED':
            pl_points_pdf_combined_report($pdf, $report);
            break;
        case 'CLUB':
            pl_points_pdf_club_report($pdf, $report);
            break;
        case 'VOIVODESHIP':
            pl_points_pdf_voivodeship_report($pdf, $report);
            break;
    }
}

$pdf->Output('klasyfikacja_punktowa.pdf', 'I');
