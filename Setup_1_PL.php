<?php

declare(strict_types=1);

/*
 * PZŁucz — Setup: Runda 1440 / WA FITA (Type 1)
 *
 * 4 distances, kwalifikacje bez fazy eliminacji.
 * Klasy: Senior, U24 (tylko R), U21, U18, Master 50+
 * Brak U15 i U12 w tej rundzie.
 *
 * Odległości:
 *   R Seniorzy/U24/U21 M:  90–70–50–30 m
 *   R Senior K/U24W/U21W/U18M/50M: 70–60–50–30 m
 *   R U18W/50W:            60–50–40–30 m
 *   C wszystkie:           4 × 50 m
 *   B wszystkie:           4 × 50 m
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
$DistanceInfoArray      = [[6, 6], [6, 6], [6, 6], [6, 6]];

require_once(__DIR__ . '/lib.php');
require_once(dirname(__DIR__) . '/lib.php');

// ---- Divisions & Classes ---------------------------------------------------
CreateStandardDivisions($TourId, $TourType);
CreateStandardClasses($TourId, $TourType);

// ---- Distances -------------------------------------------------------------

// Recurve — Men Senior / U24M / U21M: 90-70-50-30 m
CreateDistanceNew($TourId, $TourType, 'RM', [['90m', 90], ['70m', 70], ['50m', 50], ['30m', 30]]);
CreateDistanceNew($TourId, $TourType, 'RU24M', [['90m', 90], ['70m', 70], ['50m', 50], ['30m', 30]]);
CreateDistanceNew($TourId, $TourType, 'RU21M', [['90m', 90], ['70m', 70], ['50m', 50], ['30m', 30]]);

// Recurve — Women Senior / U24W / U21W / U18M / 50M: 70-60-50-30m
CreateDistanceNew($TourId, $TourType, 'RW', [['70m', 70], ['60m', 60], ['50m', 50], ['30m', 30]]);
CreateDistanceNew($TourId, $TourType, 'RU24W', [['70m', 70], ['60m', 60], ['50m', 50], ['30m', 30]]);
CreateDistanceNew($TourId, $TourType, 'RU21W', [['70m', 70], ['60m', 60], ['50m', 50], ['30m', 30]]);
CreateDistanceNew($TourId, $TourType, 'RU18M', [['70m', 70], ['60m', 60], ['50m', 50], ['30m', 30]]);
CreateDistanceNew($TourId, $TourType, 'R50M', [['70m', 70], ['60m', 60], ['50m', 50], ['30m', 30]]);

// Recurve — U18W / 50W: 60-50-40-30m
CreateDistanceNew($TourId, $TourType, 'RU18W', [['60m', 60], ['50m', 50], ['40m', 40], ['30m', 30]]);
CreateDistanceNew($TourId, $TourType, 'R50W', [['60m', 60], ['50m', 50], ['40m', 40], ['30m', 30]]);

// Compound — all categories: 4 × 50m
CreateDistanceNew($TourId, $TourType, 'C%', [['50m-1', 50], ['50m-2', 50], ['50m-3', 50], ['50m-4', 50]]);

// Barebow — all categories: 4 × 50m
CreateDistanceNew($TourId, $TourType, 'B%', [['50m-1', 50], ['50m-2', 50], ['50m-3', 50], ['50m-4', 50]]);

// ---- Individual Events ---------------------------------------------------
$indFirstPhase  = 48;  // top 104
$teamFirstPhase = 12;  // top 24
$i = 1;

// Recurve individual
$optR = [
    'EvFinalFirstPhase' => $indFirstPhase,
    'EvFinalTargetType' => TGT_OUT_FULL,
    'EvElimEnds'        => 5, 'EvElimArrows' => 3, 'EvElimSO' => 1,
    'EvFinEnds'         => 5, 'EvFinArrows'  => 3, 'EvFinSO'  => 1,
    'EvMatchMode'       => 1,
    'EvMatchArrowsNo'   => 240, 'EvFinalAthTarget' => 240,
    'EvTargetSize'      => 122, 'EvDistance' => 70,
    'EvGolds'           => $tourDetGolds,
    'EvXNine'           => $tourDetXNine,
    'EvGoldsChars'      => $tourDetGoldsChars,
    'EvXNineChars'      => $tourDetXNineChars,
];
foreach (['M', 'W', 'U24M', 'U24W', 'U21M', 'U21W', 'U18M', 'U18W', '50M', '50W'] as $cl) {
    CreateEventNew($TourId, "R{$cl}", "Łuk klasyczny - {$PL_CLASS_NAMES[$cl]}", $i++, $optR);
}

// Compound individual
$optC = [
    'EvFinalFirstPhase' => $indFirstPhase,
    'EvFinalTargetType' => TGT_OUT_5_big10,
    'EvElimEnds'        => 5, 'EvElimArrows' => 3, 'EvElimSO' => 1,
    'EvFinEnds'         => 5, 'EvFinArrows'  => 3, 'EvFinSO'  => 1,
    'EvMatchMode'       => 0,
    'EvMatchArrowsNo'   => 240, 'EvFinalAthTarget' => 240,
    'EvTargetSize'      => 80, 'EvDistance' => 50,
    'EvGolds'           => $tourDetGolds,
    'EvXNine'           => $tourDetXNine,
    'EvGoldsChars'      => $tourDetGoldsChars,
    'EvXNineChars'      => $tourDetXNineChars,
];
foreach (['M', 'W', 'U21M', 'U21W', 'U18M', 'U18W', '50M', '50W'] as $cl) {
    CreateEventNew($TourId, "C{$cl}", "Łuk bloczkowy - {$PL_CLASS_NAMES[$cl]}", $i++, $optC);
}

// Barebow individual
$optB = [
    'EvFinalFirstPhase' => $indFirstPhase,
    'EvFinalTargetType' => TGT_OUT_FULL,
    'EvElimEnds'        => 5, 'EvElimArrows' => 3, 'EvElimSO' => 1,
    'EvFinEnds'         => 5, 'EvFinArrows'  => 3, 'EvFinSO'  => 1,
    'EvMatchMode'       => 1,
    'EvMatchArrowsNo'   => 240, 'EvFinalAthTarget' => 240,
    'EvTargetSize'      => 122, 'EvDistance' => 50,
    'EvGolds'           => $tourDetGolds,
    'EvXNine'           => $tourDetXNine,
    'EvGoldsChars'      => $tourDetGoldsChars,
    'EvXNineChars'      => $tourDetXNineChars,
];
foreach (['M', 'W', 'U21M', 'U21W', 'U18M', 'U18W'] as $cl) {
    CreateEventNew($TourId, "B{$cl}", "Łuk barebow - {$PL_CLASS_NAMES[$cl]}", $i++, $optB);
}

// ---- Team Events ----------------------------------------------------------
$i = 1;

// Recurve team
$optRT = [
    'EvTeamEvent'       => 1,
    'EvMaxTeamPerson'   => 3,
    'EvFinalFirstPhase' => $teamFirstPhase,
    'EvFinalTargetType' => TGT_OUT_FULL,
    'EvTargetSize'      => 122, 'EvDistance' => 70,
    'EvMatchMode'       => 1,
    'EvElimEnds'        => 4, 'EvElimArrows' => 6, 'EvElimSO' => 3,
    'EvFinEnds'         => 4, 'EvFinArrows'  => 6, 'EvFinSO'  => 3,
    'EvGolds'           => $tourDetGolds,
    'EvXNine'           => $tourDetXNine,
    'EvGoldsChars'      => $tourDetGoldsChars,
    'EvXNineChars'      => $tourDetXNineChars,
];
foreach (['M', 'W', 'U24M', 'U24W', 'U21M', 'U21W', 'U18M', 'U18W', '50M', '50W'] as $cl) {
    CreateEventNew($TourId, "R{$cl}", "Łuk klasyczny - {$PL_CLASS_NAMES[$cl]} zespoły", $i++, $optRT);
}

// Compound team
$optCT = $optRT;
$optCT['EvFinalTargetType'] = TGT_OUT_5_big10;
$optCT['EvTargetSize']      = 80;
$optCT['EvMatchMode']       = 0;
foreach (['M', 'W', 'U21M', 'U21W', 'U18M', 'U18W', '50M', '50W'] as $cl) {
    CreateEventNew($TourId, "C{$cl}", "Łuk bloczkowy - {$PL_CLASS_NAMES[$cl]} zespoły", $i++, $optCT);
}

// Barebow team
$optBT = $optRT;
foreach (['M', 'W', 'U21M', 'U21W', 'U18M', 'U18W'] as $cl) {
    CreateEventNew($TourId, "B{$cl}", "Łuk barebow - {$PL_CLASS_NAMES[$cl]} zespoły", $i++, $optBT);
}

// ---- Target Faces ----------------------------------------------------------
$i = 1;
// Recurve: 122 cm for distances 1-2, 80 cm for distances 3-4
CreateTargetFace(
    $TourId,
    $i++,
    'Recurve domyślna',
    'R%',
    '1',
    TGT_OUT_FULL,
    122,
    TGT_OUT_FULL,
    122,
    TGT_OUT_FULL,
    80,
    TGT_OUT_FULL,
    80
);
// Compound: 80 cm 6-ring (TGT_OUT_5_big10) for all 4 distances
CreateTargetFace(
    $TourId,
    $i++,
    'Compound domyślna',
    'C%',
    '1',
    TGT_OUT_5_big10,
    80,
    TGT_OUT_5_big10,
    80,
    TGT_OUT_5_big10,
    80,
    TGT_OUT_5_big10,
    80
);
// Barebow: 80 cm full face for all 4 distances
CreateTargetFace(
    $TourId,
    $i++,
    'Barebow domyślna',
    'B%',
    '1',
    TGT_OUT_FULL,
    80,
    TGT_OUT_FULL,
    80,
    TGT_OUT_FULL,
    80,
    TGT_OUT_FULL,
    80
);

// ---- Event-class bindings, Finals, Distance Info, Tour Update --------------
InsertStandardEvents($TourId, $TourType);
CreateFinals($TourId);
CreateDistanceInformation($TourId, $DistanceInfoArray, 20, 4);

$tourDetails = [
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
];
UpdateTourDetails($TourId, $tourDetails);
