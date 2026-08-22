<?php
/**
 * PrnPointsRankingDipl.php - Club / voivodeship diplomas for the active
 * points-ranking preset, reusing the Diplomas module's renderer and
 * per-tournament configuration (design D7).
 *
 * GET parameters:
 *   Report - 'CLUB' or 'VOIVODESHIP'
 */
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
CheckTourSession(true);
require_once('Fun_PointsRanking.php');
require_once('PointsRankingCalc.php');
require_once('Presets.php');
require_once(dirname(__FILE__) . '/../Diplomas/DiplomaSetup.php');
require_once(dirname(__FILE__) . '/../Diplomas/PLDiplomaPdf.php');

pl_points_ensure_tables();
pl_diploma_ensure_tables();

$reportKind = isset($_GET['Report']) ? $_GET['Report'] : '';
if (!in_array($reportKind, ['CLUB', 'VOIVODESHIP'], true)) {
    die('Nieprawidłowy typ klasyfikacji.');
}

$activePresetKey = pl_points_get_tournament_preset($_SESSION['TourId']);
if (!$activePresetKey || !isset(PL_POINTS_PRESETS[$activePresetKey])) {
    die('Nie wybrano klasyfikacji punktowej.');
}
$activePreset = PL_POINTS_PRESETS[$activePresetKey];

$result = pl_points_calculate($_SESSION['TourId'], $activePreset);
$report = current(array_filter($result['reports'], fn ($r) => $r['kind'] === $reportKind));
if ($report === false) {
    die('Ta klasyfikacja nie jest częścią aktywnego presetu.');
}

$config = pl_diploma_get_config($_SESSION['TourId']);
$rows = array_values(array_filter(
    $report['rows'],
    fn ($row) => $row['rank'] >= $config['PlaceFrom'] && $row['rank'] <= $config['PlaceTo']
));

if (empty($rows)) {
    die('Brak wierszy klasyfikacji w skonfigurowanym zakresie miejsc.');
}

$classText = $reportKind === 'CLUB' ? 'Klasyfikacja klubowa' : 'Klasyfikacja województw';
$fileName = $reportKind === 'CLUB' ? 'dyplomy_klasyfikacja_klubowa.pdf' : 'dyplomy_klasyfikacja_wojewodztw.pdf';

$pdf = PLDiplomaPdf::createInstance('Dyplomy - ' . $classText);

foreach ($rows as $row) {
    $recipientName = $reportKind === 'CLUB' ? $row['club_name'] : $row['voivodeship'];

    $pdf->printDiploma(
        $config['CompetitionName'],
        $config['Dates'],
        $config['Location'],
        $classText,
        $row['rank'],
        $recipientName,
        '', // empty club line
        [], // no team member list
        $config['BodyText'],
        $config['HeadJudge'],
        $config['Organizer'],
        ''  // no title phrase for club/voivodeship diplomas
    );
}

$pdf->Output($fileName, 'I');
