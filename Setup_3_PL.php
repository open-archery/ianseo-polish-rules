<?php

declare(strict_types=1);
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

$TourType = 3;

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
$DistanceInfoArray      = [[6, 6], [6, 6]];

require_once(__DIR__ . '/lib.php');
require_once(dirname(__DIR__) . '/lib.php');

// ---- Divisions & Classes ---------------------------------------------------
CreateStandardDivisions($TourId, $TourType);
CreateStandardClasses($TourId, $TourType);  // includes U15

// ---- Distances -------------------------------------------------------------

// Recurve — Senior / U24 / U21: 2 × 70 m
CreateDistanceNew($TourId, $TourType, 'RM', [['70m-1', 70], ['70m-2', 70]]);
CreateDistanceNew($TourId, $TourType, 'RW', [['70m-1', 70], ['70m-2', 70]]);
CreateDistanceNew($TourId, $TourType, 'RU24M', [['70m-1', 70], ['70m-2', 70]]);
CreateDistanceNew($TourId, $TourType, 'RU24W', [['70m-1', 70], ['70m-2', 70]]);
CreateDistanceNew($TourId, $TourType, 'RU21M', [['70m-1', 70], ['70m-2', 70]]);
CreateDistanceNew($TourId, $TourType, 'RU21W', [['70m-1', 70], ['70m-2', 70]]);

// Recurve — U18 / Master: 2 × 60 m
CreateDistanceNew($TourId, $TourType, 'RU18M', [['60m-1', 60], ['60m-2', 60]]);
CreateDistanceNew($TourId, $TourType, 'RU18W', [['60m-1', 60], ['60m-2', 60]]);
CreateDistanceNew($TourId, $TourType, 'R50M', [['60m-1', 60], ['60m-2', 60]]);
CreateDistanceNew($TourId, $TourType, 'R50W', [['60m-1', 60], ['60m-2', 60]]);

// Recurve — U15: 40 m + 20 m
CreateDistanceNew($TourId, $TourType, 'RU15M', [['40m', 40], ['20m', 20]]);
CreateDistanceNew($TourId, $TourType, 'RU15W', [['40m', 40], ['20m', 20]]);

// Compound — all: 2 × 50 m
CreateDistanceNew($TourId, $TourType, 'C%', [['50m-1', 50], ['50m-2', 50]]);

// Barebow — all: 2 × 50 m
CreateDistanceNew($TourId, $TourType, 'B%', [['50m-1', 50], ['50m-2', 50]]);

// ---- Individual Events (with elimination, except U15) ----------------------
$indFirstPhase  = 48;  // top 104
$teamFirstPhase = 12;  // top 24
$i = 1;

// --- Recurve individual (set system) ---
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
foreach (['M', 'W', 'U24M', 'U24W', 'U21M', 'U21W'] as $cl) {
    CreateEventNew($TourId, "R{$cl}", "Łuk klasyczny - {$PL_CLASS_NAMES[$cl]}", $i++, $optR);
}
$optR['EvDistance'] = 60;
foreach (['U18M', 'U18W', '50M', '50W'] as $cl) {
    CreateEventNew($TourId, "R{$cl}", "Łuk klasyczny - {$PL_CLASS_NAMES[$cl]}", $i++, $optR);
}

// U15 — no elimination
$optRU15 = $optR;
$optRU15['EvFinalFirstPhase'] = 0;
$optRU15['EvDistance']        = 40;
$optRU15['EvTargetSize']      = 122;
foreach (['U15M', 'U15W'] as $cl) {
    CreateEventNew($TourId, "R{$cl}", "Łuk klasyczny - {$PL_CLASS_NAMES[$cl]}", $i++, $optRU15);
}

// --- Compound individual (cumulative) ---
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

// U15 Compound — no elimination
$optCU15 = $optC;
$optCU15['EvFinalFirstPhase'] = 0;
foreach (['U15M', 'U15W'] as $cl) {
    CreateEventNew($TourId, "C{$cl}", "Łuk bloczkowy - {$PL_CLASS_NAMES[$cl]}", $i++, $optCU15);
}

// --- Barebow individual (set system) ---
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

// ---- Team Events -----------------------------------------------------------
$i = 1;

// Recurve team (set system)
$optRT = [
    'EvTeamEvent'       => 1, 'EvMaxTeamPerson' => 3,
    'EvFinalFirstPhase' => $teamFirstPhase,
    'EvFinalTargetType' => TGT_OUT_FULL,
    'EvElimEnds'        => 4, 'EvElimArrows' => 6, 'EvElimSO' => 3,
    'EvFinEnds'         => 4, 'EvFinArrows'  => 6, 'EvFinSO'  => 3,
    'EvMatchMode'       => 1,
    'EvTargetSize'      => 122, 'EvDistance' => 70,
    'EvGolds'           => $tourDetGolds,
    'EvXNine'           => $tourDetXNine,
    'EvGoldsChars'      => $tourDetGoldsChars,
    'EvXNineChars'      => $tourDetXNineChars,
];
foreach (['M', 'W', 'U24M', 'U24W', 'U21M', 'U21W'] as $cl) {
    CreateEventNew($TourId, "R{$cl}", "Łuk klasyczny - {$PL_CLASS_NAMES[$cl]} zespoły", $i++, $optRT);
}
$optRT['EvDistance'] = 60;
foreach (['U18M', 'U18W', '50M', '50W'] as $cl) {
    CreateEventNew($TourId, "R{$cl}", "Łuk klasyczny - {$PL_CLASS_NAMES[$cl]} zespoły", $i++, $optRT);
}
// U15 Recurve team — no elimination
$optRTU15 = $optRT;
$optRTU15['EvFinalFirstPhase'] = 0;
$optRTU15['EvDistance']        = 40;
$optRTU15['EvTargetSize']      = 122;
foreach (['U15M', 'U15W'] as $cl) {
    CreateEventNew($TourId, "R{$cl}", "Łuk klasyczny - {$PL_CLASS_NAMES[$cl]} zespoły", $i++, $optRTU15);
}

// Compound team (cumulative)
$optCT = [
    'EvTeamEvent'       => 1, 'EvMaxTeamPerson' => 3,
    'EvFinalFirstPhase' => $teamFirstPhase,
    'EvFinalTargetType' => TGT_OUT_5_big10,
    'EvElimEnds'        => 4, 'EvElimArrows' => 6, 'EvElimSO' => 3,
    'EvFinEnds'         => 4, 'EvFinArrows'  => 6, 'EvFinSO'  => 3,
    'EvMatchMode'       => 0,
    'EvTargetSize'      => 80, 'EvDistance' => 50,
    'EvGolds'           => $tourDetGolds,
    'EvXNine'           => $tourDetXNine,
    'EvGoldsChars'      => $tourDetGoldsChars,
    'EvXNineChars'      => $tourDetXNineChars,
];
foreach (['M', 'W', 'U21M', 'U21W', 'U18M', 'U18W', '50M', '50W'] as $cl) {
    CreateEventNew($TourId, "C{$cl}", "Łuk bloczkowy - {$PL_CLASS_NAMES[$cl]} zespoły", $i++, $optCT);
}
// U15 Compound team — no elimination
$optCTU15 = $optCT;
$optCTU15['EvFinalFirstPhase'] = 0;
foreach (['U15M', 'U15W'] as $cl) {
    CreateEventNew($TourId, "C{$cl}", "Łuk bloczkowy - {$PL_CLASS_NAMES[$cl]} zespoły", $i++, $optCTU15);
}

// Barebow team (set system)
$optBT = [
    'EvTeamEvent'       => 1, 'EvMaxTeamPerson' => 3,
    'EvFinalFirstPhase' => $teamFirstPhase,
    'EvFinalTargetType' => TGT_OUT_FULL,
    'EvElimEnds'        => 4, 'EvElimArrows' => 6, 'EvElimSO' => 3,
    'EvFinEnds'         => 4, 'EvFinArrows'  => 6, 'EvFinSO'  => 3,
    'EvMatchMode'       => 1,
    'EvTargetSize'      => 122, 'EvDistance' => 50,
    'EvGolds'           => $tourDetGolds,
    'EvXNine'           => $tourDetXNine,
    'EvGoldsChars'      => $tourDetGoldsChars,
    'EvXNineChars'      => $tourDetXNineChars,
];
foreach (['M', 'W', 'U21M', 'U21W', 'U18M', 'U18W'] as $cl) {
    CreateEventNew($TourId, "B{$cl}", "Łuk barebow - {$PL_CLASS_NAMES[$cl]} zespoły", $i++, $optBT);
}

// ---- Mixed Team Events -----------------------------------------------------
$mixFirstPhase = 12;  // top 24 (1/12 finału)
$i = 1;

// Recurve mixed teams (set system)
$optRMX = [
    'EvTeamEvent'       => 1,
    'EvMixedTeam'       => 1,
    'EvMaxTeamPerson'   => 2,
    'EvFinalFirstPhase' => $mixFirstPhase,
    'EvFinalTargetType' => TGT_OUT_FULL,
    'EvMatchMode'       => 1,
    'EvElimEnds'        => 4, 'EvElimArrows' => 4, 'EvElimSO' => 2,
    'EvFinEnds'         => 4, 'EvFinArrows'  => 4, 'EvFinSO'  => 2,
    'EvTargetSize'      => 122, 'EvDistance' => 70,
    'EvGolds'           => $tourDetGolds,
    'EvXNine'           => $tourDetXNine,
    'EvGoldsChars'      => $tourDetGoldsChars,
    'EvXNineChars'      => $tourDetXNineChars,
];
// Senior / U24 / U21: 70 m
foreach (['', 'U24', 'U21'] as $age) {
    CreateEventNew(
        $TourId,
        "R{$age}X",
        "Łuk klasyczny - {$PL_MIXED_CLASS_NAMES[$age]} zespoły mieszane",
        $i++,
        $optRMX
    );
}
// U18 / 50+: 60 m
$optRMX['EvDistance'] = 60;
foreach (['U18', '50'] as $age) {
    CreateEventNew(
        $TourId,
        "R{$age}X",
        "Łuk klasyczny - {$PL_MIXED_CLASS_NAMES[$age]} zespoły mieszane",
        $i++,
        $optRMX
    );
}
// U15: 40 m, no elimination
$optRMXU15 = $optRMX;
$optRMXU15['EvFinalFirstPhase'] = 0;
$optRMXU15['EvDistance']        = 40;
$optRMXU15['EvTargetSize']      = 122;
CreateEventNew(
    $TourId,
    'RU15X',
    "Łuk klasyczny - {$PL_MIXED_CLASS_NAMES['U15']} zespoły mieszane",
    $i++,
    $optRMXU15
);

// Compound mixed teams (cumulative)
$optCMX = [
    'EvTeamEvent'       => 1,
    'EvMixedTeam'       => 1,
    'EvMaxTeamPerson'   => 2,
    'EvFinalFirstPhase' => $mixFirstPhase,
    'EvFinalTargetType' => TGT_OUT_5_big10,
    'EvMatchMode'       => 0,
    'EvElimEnds'        => 4, 'EvElimArrows' => 4, 'EvElimSO' => 2,
    'EvFinEnds'         => 4, 'EvFinArrows'  => 4, 'EvFinSO'  => 2,
    'EvTargetSize'      => 80, 'EvDistance' => 50,
    'EvGolds'           => $tourDetGolds,
    'EvXNine'           => $tourDetXNine,
    'EvGoldsChars'      => $tourDetGoldsChars,
    'EvXNineChars'      => $tourDetXNineChars,
];
foreach (['', 'U21', 'U18', '50'] as $age) {
    CreateEventNew(
        $TourId,
        "C{$age}X",
        "Łuk bloczkowy - {$PL_MIXED_CLASS_NAMES[$age]} zespoły mieszane",
        $i++,
        $optCMX
    );
}
// U15 Compound — no elimination
$optCMXU15 = $optCMX;
$optCMXU15['EvFinalFirstPhase'] = 0;
CreateEventNew(
    $TourId,
    'CU15X',
    "Łuk bloczkowy - {$PL_MIXED_CLASS_NAMES['U15']} zespoły mieszane",
    $i++,
    $optCMXU15
);

// Barebow mixed teams (set system)
$optBMX = [
    'EvTeamEvent'       => 1,
    'EvMixedTeam'       => 1,
    'EvMaxTeamPerson'   => 2,
    'EvFinalFirstPhase' => $mixFirstPhase,
    'EvFinalTargetType' => TGT_OUT_FULL,
    'EvMatchMode'       => 1,
    'EvElimEnds'        => 4, 'EvElimArrows' => 4, 'EvElimSO' => 2,
    'EvFinEnds'         => 4, 'EvFinArrows'  => 4, 'EvFinSO'  => 2,
    'EvTargetSize'      => 122, 'EvDistance' => 50,
    'EvGolds'           => $tourDetGolds,
    'EvXNine'           => $tourDetXNine,
    'EvGoldsChars'      => $tourDetGoldsChars,
    'EvXNineChars'      => $tourDetXNineChars,
];
foreach (['', 'U21', 'U18'] as $age) {
    CreateEventNew(
        $TourId,
        "B{$age}X",
        "Łuk barebow - {$PL_MIXED_CLASS_NAMES[$age]} zespoły mieszane",
        $i++,
        $optBMX
    );
}

// ---- Target Faces ----------------------------------------------------------
$i = 1;
// Recurve (incl. Barebow): 122 cm full face
CreateTargetFace(
    $TourId,
    $i++,
    'Recurve/Barebow domyślna',
    'REG-^[RB]',
    '1',
    TGT_OUT_FULL,
    122,
    TGT_OUT_FULL,
    122
);
// Compound: 80 cm 6-ring
CreateTargetFace(
    $TourId,
    $i++,
    'Compound domyślna',
    'C%',
    '1',
    TGT_OUT_5_big10,
    80,
    TGT_OUT_5_big10,
    80
);
// U15 Recurve: 122 cm for 40 m, 80 cm for 20 m
CreateTargetFace(
    $TourId,
    $i++,
    'Recurve Młodzik (40 m / 20 m)',
    'RU15%',
    '1',
    TGT_OUT_FULL,
    122,
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
