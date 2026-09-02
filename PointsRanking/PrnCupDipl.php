<?php
/**
 * PrnCupDipl.php - Diplomas for the whole Puchar Polski, reusing the Diplomas
 * module's renderer and the tournament's diploma configuration. Only the
 * competition name is cup-specific, so the diploma states the cup and not the
 * single round.
 *
 * GET parameters:
 *   Class - 'ind' (individual) or 'mix' (mixed)
 */
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
CheckTourSession(true);
require_once('Fun_PointsRanking.php');
require_once('PointsRankingCalc.php');
require_once('Presets.php');
require_once('Fun_Cup.php');
require_once('CupCalc.php');
require_once(dirname(__FILE__) . '/../Diplomas/DiplomaSetup.php');
require_once(dirname(__FILE__) . '/../Diplomas/PLDiplomaPdf.php');

pl_points_ensure_tables();

$tourId = $_SESSION['TourId'];
if (pl_points_get_tournament_preset($tourId) !== PL_CUP_PRESET_KEY) {
    die('Klasyfikacja generalna dotyczy wyłącznie Pucharu Polski.');
}

pl_cup_ensure_tables();
pl_diploma_ensure_tables();

$classKey = isset($_GET['Class']) && is_string($_GET['Class']) ? $_GET['Class'] : '';
if (!in_array($classKey, ['ind', 'mix'], true)) {
    die('Nieprawidłowa klasyfikacja.');
}

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
    in_array(PL_CUP_ROUNDS, $storedRounds, true),
    pl_cup_current_directory($tourId)
);

$diplomaConfig = pl_diploma_get_config($tourId);
// Everything but the competition name comes from the round's own diploma
// configuration - the cup diploma is handed out on the last day of the final
// round. The name itself is derived per category (see below).
$pdf = PLDiplomaPdf::createInstance('Dyplomy - Puchar Polski ' . intval($config['Edition']));
$printed = 0;

foreach ($classifications[$classKey]['sections'] as $section) {
    // Each category names its own cup: "w Pucharze Polski Juniorek Młodszych 2026".
    $competitionName = pl_cup_diploma_competition_name($classKey, $section['category'], $config['Edition']);

    foreach ($section['rows'] as $row) {
        if ($row['rank'] < $diplomaConfig['PlaceFrom'] || $row['rank'] > $diplomaConfig['PlaceTo']) {
            continue;
        }

        $pdf->printDiploma(
            $competitionName,
            $diplomaConfig['Dates'],
            $diplomaConfig['Location'],
            $section['label'],
            $row['rank'],
            $classKey === 'mix' ? $row['club_name'] : $row['name'],
            $classKey === 'mix' ? '' : $row['club_name'],
            [], // no member list: a mixed cup row belongs to the club, not a fixed pair
            $diplomaConfig['BodyText'],
            $diplomaConfig['HeadJudge'],
            $diplomaConfig['Organizer'],
            ''
        );
        $printed++;
    }
}

if ($printed === 0) {
    die('Brak wierszy klasyfikacji w skonfigurowanym zakresie miejsc.');
}

$pdf->Output($classKey === 'mix' ? 'dyplomy_pp_miksty.pdf' : 'dyplomy_pp_indywidualne.pdf', 'I');
