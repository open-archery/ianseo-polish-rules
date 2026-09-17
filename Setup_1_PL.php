<?php
/*
 * PZŁucz — Setup: Runda 1440 / WA FITA (Type 1)
 *
 * 4 distances, kwalifikacje bez fazy eliminacji.
 * Klasy: Senior, U24 (tylko R), U21, U18. Brak U15, U12, U10 i Master.
 * Łuk barebow nie istnieje w tej rundzie.
 *
 * Odległości:
 *   R/C Seniorzy/U24/U21 M:  90–70–50–30 m
 *   R/C Senior K/U24W/U21W/U18M: 70–60–50–30 m
 *   R/C U18W:              60–50–40–30 m
 *
 * Podrundy (sub-rules): SetAllClass (wszystko), SetSeniorClass (tylko M/W),
 * Poland-RU24/RU21/RU18 (tylko łuk klasyczny, jedna kategoria) — patrz
 * lib.php pl_preset_table().
 */

$TourType = 1;

$tourDetTypeName        = 'Type_FITA';
$tourDetNumDist         = '4';
$tourDetNumEnds         = '12';
$tourDetMaxDistScore    = '360';
$tourDetMaxFinIndScore  = '150';
$tourDetMaxFinTeamScore = '240';
$tourDetCategory        = '1';   // 1 = Outdoor
$tourDetElabTeam        = '0';   // 0 = Standard
$tourDetElimination     = '0';
$tourDetGolds           = '10+X';
$tourDetXNine           = 'X';
$tourDetGoldsChars      = 'KL';
$tourDetXNineChars      = 'K';
$tourDetDouble          = '0';

require_once(dirname(__FILE__) . '/lib.php');
require_once(dirname(dirname(__FILE__)) . '/lib.php');

$preset = pl_resolve_preset($TourType, isset($subRuleName) ? $subRuleName : '');
pl_setup_1440($TourId, $TourType, $PL_CLASS_NAMES, $preset);

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
