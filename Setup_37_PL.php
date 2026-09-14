<?php
/*
 * PZŁucz — Setup: Podwójna runda 70m / Double Round (Type 37)
 *
 * Jak Setup_3_PL.php, ale każda sesja podwojona: 4 sesje strzeleckie zamiast 2,
 * 144 strzały zamiast 72. Faza eliminacyjna identyczna jak w rundzie pojedynczej.
 *
 * Odległości (dystanse): jak Setup_3_PL.php, każda sesja podwojona
 *   R Senior / U24 / U21:  4 × 70 m
 *   R U18 / 50+:           4 × 60 m
 *   R U15:                 40 m, 40 m, 20 m, 20 m
 *   C wszystkie:           4 × 50 m
 *   B wszystkie:           4 × 50 m
 */

$TourType  = 37;

$tourDetTypeName        = 'Type_2x70mRound';
$tourDetNumDist         = '4';
$tourDetNumEnds         = '24';
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
$tourDetDouble          = '1';

require_once(dirname(__FILE__) . '/lib.php');
require_once(dirname(dirname(__FILE__)) . '/lib.php');

pl_setup_70m_family($TourId, $TourType, 2, $PL_CLASS_NAMES, $PL_MIXED_CLASS_NAMES);

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
