<?php

declare(strict_types=1);
/*
 * PZŁucz — Setup: Hala 18 m / Indoor (Type 6)
 *
 * 2 sesje strzeleckie po 10 serii × 3 strzały (= 30 strzał / sesja, 300 pkt maks).
 * Faza eliminacyjna: top 32 (ind.), top 16 (zespoły).
 * U15 i U12 bez eliminacji.
 * U12 strzela na 15 m (pozostałe 18 m).
 *
 * Tarcze eliminacyjne:
 *   R Senior/U24/U21/50+:  Triple 40 cm (TGT_IND_6_big10)
 *   R U18:                 Single 40 cm (TGT_IND_1_big10)
 *   R U15:                 Single 60 cm (TGT_IND_1_big10)
 *   R U12:                 Single 80 cm (TGT_IND_1_big10)
 *   C Senior/U21/50+:      Triple 40 cm z małą dziesiątką (TGT_IND_6_small10)
 *   C U18:                 Single 40 cm z małą dziesiątką (TGT_IND_1_small10)
 *   C U15:                 Single 60 cm z małą dziesiątką (TGT_IND_1_small10)
 *   B wszystkie:           Single 40 cm (TGT_IND_1_big10)
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
$DistanceInfoArray      = [[10, 3], [10, 3]];

require_once(__DIR__ . '/lib.php');
require_once(dirname(__DIR__) . '/lib.php');

// ---- Divisions & Classes ---------------------------------------------------
CreateStandardDivisions($TourId, $TourType);
CreateStandardClasses($TourId, $TourType);  // includes U15 + U12

// ---- Distances -------------------------------------------------------------

// Recurve — all except U12: 2 × 18 m
CreateDistanceNew($TourId, $TourType, 'RM', [['18m-1', 18], ['18m-2', 18]]);
CreateDistanceNew($TourId, $TourType, 'RW', [['18m-1', 18], ['18m-2', 18]]);
CreateDistanceNew($TourId, $TourType, 'RU24M', [['18m-1', 18], ['18m-2', 18]]);
CreateDistanceNew($TourId, $TourType, 'RU24W', [['18m-1', 18], ['18m-2', 18]]);
CreateDistanceNew($TourId, $TourType, 'RU21M', [['18m-1', 18], ['18m-2', 18]]);
CreateDistanceNew($TourId, $TourType, 'RU21W', [['18m-1', 18], ['18m-2', 18]]);
CreateDistanceNew($TourId, $TourType, 'RU18M', [['18m-1', 18], ['18m-2', 18]]);
CreateDistanceNew($TourId, $TourType, 'RU18W', [['18m-1', 18], ['18m-2', 18]]);
CreateDistanceNew($TourId, $TourType, 'R50M', [['18m-1', 18], ['18m-2', 18]]);
CreateDistanceNew($TourId, $TourType, 'R50W', [['18m-1', 18], ['18m-2', 18]]);
CreateDistanceNew($TourId, $TourType, 'RU15M', [['18m-1', 18], ['18m-2', 18]]);
CreateDistanceNew($TourId, $TourType, 'RU15W', [['18m-1', 18], ['18m-2', 18]]);

// Recurve — U12: 2 × 15 m
CreateDistanceNew($TourId, $TourType, 'RU12M', [['15m-1', 15], ['15m-2', 15]]);
CreateDistanceNew($TourId, $TourType, 'RU12W', [['15m-1', 15], ['15m-2', 15]]);

// Compound & Barebow — all: 2 × 18 m
CreateDistanceNew($TourId, $TourType, 'C%', [['18m-1', 18], ['18m-2', 18]]);
CreateDistanceNew($TourId, $TourType, 'B%', [['18m-1', 18], ['18m-2', 18]]);

// ---- Individual Events -----------------------------------------------------
$indFirstPhase  = 16;  // top 32
$teamFirstPhase = 8;   // top 16
$i = 1;

// --- Recurve individual (set system) ---

// Senior / U24 / U21 / Master: Triple 40 cm
$optR = [
    'EvFinalFirstPhase' => $indFirstPhase,
    'EvFinalTargetType' => TGT_IND_6_big10,
    'EvElimEnds'        => 5, 'EvElimArrows' => 3, 'EvElimSO' => 1,
    'EvFinEnds'         => 5, 'EvFinArrows'  => 3, 'EvFinSO'  => 1,
    'EvMatchMode'       => 1,
    'EvMatchArrowsNo'   => 240, 'EvFinalAthTarget' => 240,
    'EvTargetSize'      => 40, 'EvDistance' => 18,
    'EvGolds'           => $tourDetGolds,
    'EvXNine'           => $tourDetXNine,
    'EvGoldsChars'      => $tourDetGoldsChars,
    'EvXNineChars'      => $tourDetXNineChars,
];
foreach (['M', 'W', 'U24M', 'U24W', 'U21M', 'U21W', '50M', '50W'] as $cl) {
    CreateEventNew($TourId, "R{$cl}", "Łuk klasyczny - {$PL_CLASS_NAMES[$cl]}", $i++, $optR);
}

// U18 Recurve: Single 40 cm
$optRU18 = $optR;
$optRU18['EvFinalTargetType'] = TGT_IND_1_big10;
foreach (['U18M', 'U18W'] as $cl) {
    CreateEventNew($TourId, "R{$cl}", "Łuk klasyczny - {$PL_CLASS_NAMES[$cl]}", $i++, $optRU18);
}

// U15 Recurve: Single 60 cm, no elimination
$optRU15 = $optR;
$optRU15['EvFinalFirstPhase'] = 0;
$optRU15['EvFinalTargetType'] = TGT_IND_1_big10;
$optRU15['EvTargetSize']      = 60;
foreach (['U15M', 'U15W'] as $cl) {
    CreateEventNew($TourId, "R{$cl}", "Łuk klasyczny - {$PL_CLASS_NAMES[$cl]}", $i++, $optRU15);
}

// U12 Recurve: Single 80 cm at 15 m, no elimination
$optRU12 = $optR;
$optRU12['EvFinalFirstPhase'] = 0;
$optRU12['EvFinalTargetType'] = TGT_IND_1_big10;
$optRU12['EvTargetSize']      = 80;
$optRU12['EvDistance']        = 15;
foreach (['U12M', 'U12W'] as $cl) {
    CreateEventNew($TourId, "R{$cl}", "Łuk klasyczny - {$PL_CLASS_NAMES[$cl]}", $i++, $optRU12);
}

// --- Compound individual (cumulative) ---

// Senior / U21 / Master: Triple 40 cm (small 10)
$optC = [
    'EvFinalFirstPhase' => $indFirstPhase,
    'EvFinalTargetType' => TGT_IND_6_small10,
    'EvElimEnds'        => 5, 'EvElimArrows' => 3, 'EvElimSO' => 1,
    'EvFinEnds'         => 5, 'EvFinArrows'  => 3, 'EvFinSO'  => 1,
    'EvMatchMode'       => 0,
    'EvMatchArrowsNo'   => 240, 'EvFinalAthTarget' => 240,
    'EvTargetSize'      => 40, 'EvDistance' => 18,
    'EvGolds'           => $tourDetGolds,
    'EvXNine'           => $tourDetXNine,
    'EvGoldsChars'      => $tourDetGoldsChars,
    'EvXNineChars'      => $tourDetXNineChars,
];
foreach (['M', 'W', 'U21M', 'U21W', '50M', '50W'] as $cl) {
    CreateEventNew($TourId, "C{$cl}", "Łuk bloczkowy - {$PL_CLASS_NAMES[$cl]}", $i++, $optC);
}

// U18 Compound: Single 40 cm (small 10)
$optCU18 = $optC;
$optCU18['EvFinalTargetType'] = TGT_IND_1_small10;
foreach (['U18M', 'U18W'] as $cl) {
    CreateEventNew($TourId, "C{$cl}", "Łuk bloczkowy - {$PL_CLASS_NAMES[$cl]}", $i++, $optCU18);
}

// U15 Compound: Single 60 cm (small 10), no elimination
$optCU15 = $optC;
$optCU15['EvFinalFirstPhase'] = 0;
$optCU15['EvFinalTargetType'] = TGT_IND_1_small10;
$optCU15['EvTargetSize']      = 60;
foreach (['U15M', 'U15W'] as $cl) {
    CreateEventNew($TourId, "C{$cl}", "Łuk bloczkowy - {$PL_CLASS_NAMES[$cl]}", $i++, $optCU15);
}

// --- Barebow individual (set system) ---
$optB = [
    'EvFinalFirstPhase' => $indFirstPhase,
    'EvFinalTargetType' => TGT_IND_1_big10,
    'EvElimEnds'        => 5, 'EvElimArrows' => 3, 'EvElimSO' => 1,
    'EvFinEnds'         => 5, 'EvFinArrows'  => 3, 'EvFinSO'  => 1,
    'EvMatchMode'       => 1,
    'EvMatchArrowsNo'   => 240, 'EvFinalAthTarget' => 240,
    'EvTargetSize'      => 40, 'EvDistance' => 18,
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

// Recurve team (set system, Triple 40 cm)
$optRT = [
    'EvTeamEvent'       => 1, 'EvMaxTeamPerson' => 3,
    'EvFinalFirstPhase' => $teamFirstPhase,
    'EvFinalTargetType' => TGT_IND_6_big10,
    'EvElimEnds'        => 4, 'EvElimArrows' => 6, 'EvElimSO' => 3,
    'EvFinEnds'         => 4, 'EvFinArrows'  => 6, 'EvFinSO'  => 3,
    'EvMatchMode'       => 1,
    'EvTargetSize'      => 40, 'EvDistance' => 18,
    'EvGolds'           => $tourDetGolds,
    'EvXNine'           => $tourDetXNine,
    'EvGoldsChars'      => $tourDetGoldsChars,
    'EvXNineChars'      => $tourDetXNineChars,
];
foreach (['M', 'W', 'U24M', 'U24W', 'U21M', 'U21W', 'U18M', 'U18W', '50M', '50W'] as $cl) {
    CreateEventNew($TourId, "R{$cl}", "Łuk klasyczny - {$PL_CLASS_NAMES[$cl]} zespoły", $i++, $optRT);
}
// U15 + U12 Recurve team — no elimination
$optRTU15 = $optRT;
$optRTU15['EvFinalFirstPhase'] = 0;
foreach (['U15M', 'U15W', 'U12M', 'U12W'] as $cl) {
    CreateEventNew($TourId, "R{$cl}", "Łuk klasyczny - {$PL_CLASS_NAMES[$cl]} zespoły", $i++, $optRTU15);
}

// Compound team (cumulative, Triple 40 cm small 10)
$optCT = [
    'EvTeamEvent'       => 1, 'EvMaxTeamPerson' => 3,
    'EvFinalFirstPhase' => $teamFirstPhase,
    'EvFinalTargetType' => TGT_IND_6_small10,
    'EvElimEnds'        => 4, 'EvElimArrows' => 6, 'EvElimSO' => 3,
    'EvFinEnds'         => 4, 'EvFinArrows'  => 6, 'EvFinSO'  => 3,
    'EvMatchMode'       => 0,
    'EvTargetSize'      => 40, 'EvDistance' => 18,
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

// Barebow team (set system, Single 40 cm)
$optBT = [
    'EvTeamEvent'       => 1, 'EvMaxTeamPerson' => 3,
    'EvFinalFirstPhase' => $teamFirstPhase,
    'EvFinalTargetType' => TGT_IND_1_big10,
    'EvElimEnds'        => 4, 'EvElimArrows' => 6, 'EvElimSO' => 3,
    'EvFinEnds'         => 4, 'EvFinArrows'  => 6, 'EvFinSO'  => 3,
    'EvMatchMode'       => 1,
    'EvTargetSize'      => 40, 'EvDistance' => 18,
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
// Senior / U24 / U21 / 50+: Triple 40 cm (big 10)
$optRMX = [
    'EvTeamEvent'       => 1,
    'EvMixedTeam'       => 1,
    'EvMaxTeamPerson'   => 2,
    'EvFinalFirstPhase' => $mixFirstPhase,
    'EvFinalTargetType' => TGT_IND_6_big10,
    'EvMatchMode'       => 1,
    'EvElimEnds'        => 4, 'EvElimArrows' => 4, 'EvElimSO' => 2,
    'EvFinEnds'         => 4, 'EvFinArrows'  => 4, 'EvFinSO'  => 2,
    'EvTargetSize'      => 40, 'EvDistance' => 18,
    'EvGolds'           => $tourDetGolds,
    'EvXNine'           => $tourDetXNine,
    'EvGoldsChars'      => $tourDetGoldsChars,
    'EvXNineChars'      => $tourDetXNineChars,
];
foreach (['', 'U24', 'U21', '50'] as $age) {
    CreateEventNew(
        $TourId,
        "R{$age}X",
        "Łuk klasyczny - {$PL_MIXED_CLASS_NAMES[$age]} zespoły mieszane",
        $i++,
        $optRMX
    );
}
// U18 Recurve mixed: Single 40 cm
$optRMX['EvFinalTargetType'] = TGT_IND_1_big10;
CreateEventNew(
    $TourId,
    'RU18X',
    "Łuk klasyczny - {$PL_MIXED_CLASS_NAMES['U18']} zespoły mieszane",
    $i++,
    $optRMX
);
// U15 Recurve mixed: Single 60 cm, no elimination
$optRMXU15 = $optRMX;
$optRMXU15['EvFinalFirstPhase'] = 0;
$optRMXU15['EvTargetSize']      = 60;
CreateEventNew(
    $TourId,
    'RU15X',
    "Łuk klasyczny - {$PL_MIXED_CLASS_NAMES['U15']} zespoły mieszane",
    $i++,
    $optRMXU15
);

// Compound mixed teams (cumulative)
// Senior / U21 / 50+: Triple 40 cm (small 10)
$optCMX = [
    'EvTeamEvent'       => 1,
    'EvMixedTeam'       => 1,
    'EvMaxTeamPerson'   => 2,
    'EvFinalFirstPhase' => $mixFirstPhase,
    'EvFinalTargetType' => TGT_IND_6_small10,
    'EvMatchMode'       => 0,
    'EvElimEnds'        => 4, 'EvElimArrows' => 4, 'EvElimSO' => 2,
    'EvFinEnds'         => 4, 'EvFinArrows'  => 4, 'EvFinSO'  => 2,
    'EvTargetSize'      => 40, 'EvDistance' => 18,
    'EvGolds'           => $tourDetGolds,
    'EvXNine'           => $tourDetXNine,
    'EvGoldsChars'      => $tourDetGoldsChars,
    'EvXNineChars'      => $tourDetXNineChars,
];
foreach (['', 'U21', '50'] as $age) {
    CreateEventNew(
        $TourId,
        "C{$age}X",
        "Łuk bloczkowy - {$PL_MIXED_CLASS_NAMES[$age]} zespoły mieszane",
        $i++,
        $optCMX
    );
}
// U18 Compound mixed: Single 40 cm (small 10)
$optCMX['EvFinalTargetType'] = TGT_IND_1_small10;
CreateEventNew(
    $TourId,
    'CU18X',
    "Łuk bloczkowy - {$PL_MIXED_CLASS_NAMES['U18']} zespoły mieszane",
    $i++,
    $optCMX
);
// U15 Compound mixed: Single 60 cm (small 10), no elimination
$optCMXU15 = $optCMX;
$optCMXU15['EvFinalFirstPhase'] = 0;
$optCMXU15['EvTargetSize']      = 60;
CreateEventNew(
    $TourId,
    'CU15X',
    "Łuk bloczkowy - {$PL_MIXED_CLASS_NAMES['U15']} zespoły mieszane",
    $i++,
    $optCMXU15
);

// Barebow mixed teams (set system): Single 40 cm (big 10)
$optBMX = [
    'EvTeamEvent'       => 1,
    'EvMixedTeam'       => 1,
    'EvMaxTeamPerson'   => 2,
    'EvFinalFirstPhase' => $mixFirstPhase,
    'EvFinalTargetType' => TGT_IND_1_big10,
    'EvMatchMode'       => 1,
    'EvElimEnds'        => 4, 'EvElimArrows' => 4, 'EvElimSO' => 2,
    'EvFinEnds'         => 4, 'EvFinArrows'  => 4, 'EvFinSO'  => 2,
    'EvTargetSize'      => 40, 'EvDistance' => 18,
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
// R Senior / U24 / U21 / Master: Triple 40 cm (big 10)
CreateTargetFace(
    $TourId,
    $i++,
    'Triple 40 cm (R Senior/U24/U21/Master)',
    'REG-^R(M|W|U24|U21|50)',
    '1',
    TGT_IND_6_big10,
    40,
    TGT_IND_6_big10,
    40
);
// R U18: Single 40 cm
CreateTargetFace(
    $TourId,
    $i++,
    'Single 40 cm (R U18)',
    'REG-^RU18',
    '1',
    TGT_IND_1_big10,
    40,
    TGT_IND_1_big10,
    40
);
// R U15: Single 60 cm
CreateTargetFace(
    $TourId,
    $i++,
    '60 cm (R U15)',
    'RU15%',
    '1',
    TGT_IND_1_big10,
    60,
    TGT_IND_1_big10,
    60
);
// R U12: Single 80 cm
CreateTargetFace(
    $TourId,
    $i++,
    '80 cm (R U12)',
    'RU12%',
    '1',
    TGT_IND_1_big10,
    80,
    TGT_IND_1_big10,
    80
);
// C Senior / U21 / Master: Triple 40 cm (small 10)
CreateTargetFace(
    $TourId,
    $i++,
    'Triple 40 cm (C Senior/U21/Master)',
    'REG-^C(M|W|U21|50)',
    '1',
    TGT_IND_6_small10,
    40,
    TGT_IND_6_small10,
    40
);
// C U18: Single 40 cm (small 10)
CreateTargetFace(
    $TourId,
    $i++,
    'Single 40 cm (C U18)',
    'REG-^CU18',
    '1',
    TGT_IND_1_small10,
    40,
    TGT_IND_1_small10,
    40
);
// C U15: Single 60 cm (small 10)
CreateTargetFace(
    $TourId,
    $i++,
    '60 cm (C U15)',
    'CU15%',
    '1',
    TGT_IND_1_small10,
    60,
    TGT_IND_1_small10,
    60
);
// Barebow: Single 40 cm (big 10)
CreateTargetFace(
    $TourId,
    $i++,
    'Single 40 cm (Barebow)',
    'B%',
    '1',
    TGT_IND_1_big10,
    40,
    TGT_IND_1_big10,
    40
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
