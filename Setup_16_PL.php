<?php
/*
 * PZŁucz — Setup: Runda dziecięca / Children's Round (Type 16)
 *
 * Konkurencja wyłącznie dla kategorii Dzieci (U12), łuk klasyczny — żadna inna
 * klasa ani konkurencja nie jest tworzona (§2.3.1.10.11).
 *
 * 4 dystanse po 18 strzał (72 strzały łącznie), serie 3-strzałowe (§2.4.1.1).
 * Bez fazy eliminacyjnej — regulamin nie definiuje eliminacji dla U12.
 *
 * Odległości (§2.1.2.3.1-2): 25 m, 20 m, 15 m, 10 m
 *   25 m, 20 m → tarcza 122 cm
 *   15 m, 10 m → tarcza 80 cm
 */

$TourType = 16;

$tourDetTypeName        = 'Type_GiochiGioventuW';
$tourDetNumDist         = '4';
$tourDetNumEnds         = '24';
$tourDetMaxDistScore    = '180';
$tourDetMaxFinIndScore  = '0';
$tourDetMaxFinTeamScore = '0';
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

pl_setup_kids_round($TourId, $TourType, $PL_CLASS_NAMES);

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
