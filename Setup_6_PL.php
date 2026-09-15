<?php
/*
 * PZŁucz — Setup: Hala 18 m / Indoor (Type 6)
 *
 * 2 sesje strzeleckie po 10 serii × 3 strzały (= 30 strzał / sesja, 300 pkt maks).
 * Faza eliminacyjna: top 32 (ind.), top 16 (zespoły).
 * U15, U12 i PU12 bez eliminacji.
 * U12 strzela na 15 m, PU12 (łuk popularny) na 10 m — pozostałe 18 m.
 *
 * Podrundy (sub-rules): SetAllClass (wszystko), SetSeniorClass (tylko M/W),
 * SetYouthClass (łuk klasyczny, U24+U21), Poland-RU18, Poland-RU15 —
 * patrz lib.php pl_preset_table().
 */

$TourType = 6;

$tourDetTypeName        = 'Type_Indoor 18';
$tourDetNumDist         = '2';
$tourDetNumEnds         = '10';
$tourDetMaxDistScore    = '300';
$tourDetMaxFinIndScore  = '150';
$tourDetMaxFinTeamScore = '240';
$tourDetCategory        = '2';   // 2 = Indoor
$tourDetElabTeam        = '0';
$tourDetElimination     = '0';
$tourDetGolds           = '10';
$tourDetXNine           = '9';
$tourDetGoldsChars      = 'L';
$tourDetXNineChars      = 'J';
$tourDetDouble          = '0';

require_once(dirname(__FILE__) . '/lib.php');
require_once(dirname(dirname(__FILE__)) . '/lib.php');

$preset = pl_resolve_preset($TourType, isset($subRuleName) ? $subRuleName : '');
pl_setup_indoor($TourId, $TourType, $PL_CLASS_NAMES, $PL_MIXED_CLASS_NAMES, $preset);

$tourDetails = array(
    'ToCollation'        => $tourCollation,
    'ToTypeName'         => $tourDetTypeName,
    'ToNumDist'          => $tourDetNumDist,
    'ToNumEnds'          => $tourDetNumEnds,
    'ToMaxDistScore'     => $tourDetMaxDistScore,
    'ToMaxFinIndScore'   => $tourDetMaxFinIndScore,
    'ToMaxFinTeamScore'  => $tourDetMaxFinTeamScore,
    'ToCategory'         => $tourDetCategory,
    'ToElabTeam'         => $tourDetElabTeam,
    'ToElimination'      => $tourDetElimination,
    'ToGolds'            => $tourDetGolds,
    'ToXNine'            => $tourDetXNine,
    'ToGoldsChars'       => $tourDetGoldsChars,
    'ToXNineChars'       => $tourDetXNineChars,
    'ToDouble'           => $tourDetDouble,
    'ToIocCode'          => $tourDetIocCode,
);
UpdateTourDetails($TourId, $tourDetails);
