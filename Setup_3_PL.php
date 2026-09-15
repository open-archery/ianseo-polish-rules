<?php
/*
 * PZŁucz — Setup: Runda 70m / Single-Distance Round (Type 3)
 *
 * 2 sesje strzeleckie, faza eliminacyjna dla Senior/U24/U21/U18/Master.
 * U15 bez eliminacji (za młodzi zgodnie z przepisami PZŁucz).
 *
 * Odległości (dystanse): indywidualnie na kategorię
 *   R Senior / U24 / U21:  2 × 70 m
 *   R U18 / 50+:           2 × 60 m
 *   R U15:                 40 m + 20 m
 *   C wszystkie:           2 × 50 m
 *   B wszystkie:           2 × 50 m
 *
 * Faza eliminacji (outdoor):
 *   Ind:  EvFinalFirstPhase=48 → top 104 kwalifikowanych
 *   Team: EvFinalFirstPhase=12 → top 24 kwalifikowanych
 *   U15:  EvFinalFirstPhase=0  → brak eliminacji
 */

$TourType  = 3;

$tourDetTypeName        = 'Type_70m Round';
$tourDetNumDist         = '2';
$tourDetNumEnds         = '12';
$tourDetMaxDistScore    = '360';
$tourDetMaxFinIndScore  = '150';
$tourDetMaxFinTeamScore = '240';
$tourDetCategory        = '1';   // 1 = Outdoor
$tourDetElabTeam        = '0';
$tourDetElimination     = '0';
$tourDetGolds           = '10+X';
$tourDetXNine           = 'X';
$tourDetGoldsChars      = 'KL';
$tourDetXNineChars      = 'K';
$tourDetDouble          = '0';

require_once(dirname(__FILE__) . '/lib.php');
require_once(dirname(dirname(__FILE__)) . '/lib.php');

$preset = pl_resolve_preset($TourType, isset($subRuleName) ? $subRuleName : '');
pl_setup_70m_family($TourId, $TourType, 1, $PL_CLASS_NAMES, $PL_MIXED_CLASS_NAMES, $preset);

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
