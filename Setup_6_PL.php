<?php
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
$DistanceInfoArray      = array(array(10, 3), array(10, 3));

require_once(dirname(__FILE__) . '/lib.php');
require_once(dirname(dirname(__FILE__)) . '/lib.php');

// ---- Divisions & Classes ---------------------------------------------------
CreateStandardDivisions($TourId, $TourType);
CreateStandardClasses($TourId, $TourType);  // includes U15 + U12

// ---- Distances -------------------------------------------------------------

// Recurve — all except U12: 2 × 18 m
CreateDistanceNew($TourId, $TourType, 'RM',    array(array('18m-1', 18), array('18m-2', 18)));
CreateDistanceNew($TourId, $TourType, 'RW',    array(array('18m-1', 18), array('18m-2', 18)));
CreateDistanceNew($TourId, $TourType, 'RU24M', array(array('18m-1', 18), array('18m-2', 18)));
CreateDistanceNew($TourId, $TourType, 'RU24W', array(array('18m-1', 18), array('18m-2', 18)));
CreateDistanceNew($TourId, $TourType, 'RU21M', array(array('18m-1', 18), array('18m-2', 18)));
CreateDistanceNew($TourId, $TourType, 'RU21W', array(array('18m-1', 18), array('18m-2', 18)));
CreateDistanceNew($TourId, $TourType, 'RU18M', array(array('18m-1', 18), array('18m-2', 18)));
CreateDistanceNew($TourId, $TourType, 'RU18W', array(array('18m-1', 18), array('18m-2', 18)));
CreateDistanceNew($TourId, $TourType, 'R50M',  array(array('18m-1', 18), array('18m-2', 18)));
CreateDistanceNew($TourId, $TourType, 'R50W',  array(array('18m-1', 18), array('18m-2', 18)));
CreateDistanceNew($TourId, $TourType, 'RU15M', array(array('18m-1', 18), array('18m-2', 18)));
CreateDistanceNew($TourId, $TourType, 'RU15W', array(array('18m-1', 18), array('18m-2', 18)));

// Recurve — U12: 2 × 15 m
CreateDistanceNew($TourId, $TourType, 'RU12M', array(array('15m-1', 15), array('15m-2', 15)));
CreateDistanceNew($TourId, $TourType, 'RU12W', array(array('15m-1', 15), array('15m-2', 15)));

// Compound & Barebow — all: 2 × 18 m
CreateDistanceNew($TourId, $TourType, 'C%', array(array('18m-1', 18), array('18m-2', 18)));
CreateDistanceNew($TourId, $TourType, 'B%', array(array('18m-1', 18), array('18m-2', 18)));

// ---- Individual Events -----------------------------------------------------
$indFirstPhase  = 16;  // top 32
$teamFirstPhase = 8;   // top 16
$i = 1;

// --- Recurve individual (set system) ---

// Senior / U24 / U21 / Master: Triple 40 cm
$optR = array(
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
);
foreach (array('M', 'W', 'U24M', 'U24W', 'U21M', 'U21W', '50M', '50W') as $cl) {
    CreateEventNew($TourId, "R{$cl}", "Łuk klasyczny - {$cl}", $i++, $optR);
}

// U18 Recurve: Single 40 cm
$optRU18 = $optR;
$optRU18['EvFinalTargetType'] = TGT_IND_1_big10;
CreateEventNew($TourId, 'RU18M', 'Łuk klasyczny - Junior młodszy',   $i++, $optRU18);
CreateEventNew($TourId, 'RU18W', 'Łuk klasyczny - Juniorka młodsza', $i++, $optRU18);

// U15 Recurve: Single 60 cm, no elimination
$optRU15 = $optR;
$optRU15['EvFinalFirstPhase'] = 0;
$optRU15['EvFinalTargetType'] = TGT_IND_1_big10;
$optRU15['EvTargetSize']      = 60;
CreateEventNew($TourId, 'RU15M', 'Łuk klasyczny - Młodzik',    $i++, $optRU15);
CreateEventNew($TourId, 'RU15W', 'Łuk klasyczny - Młodziczka', $i++, $optRU15);

// U12 Recurve: Single 80 cm at 15 m, no elimination
$optRU12 = $optR;
$optRU12['EvFinalFirstPhase'] = 0;
$optRU12['EvFinalTargetType'] = TGT_IND_1_big10;
$optRU12['EvTargetSize']      = 80;
$optRU12['EvDistance']        = 15;
CreateEventNew($TourId, 'RU12M', 'Łuk klasyczny - Dziecko chłopcy', $i++, $optRU12);
CreateEventNew($TourId, 'RU12W', 'Łuk klasyczny - Dziecko dziewczęta', $i++, $optRU12);

// --- Compound individual (cumulative) ---

// Senior / U21 / Master: Triple 40 cm (small 10)
$optC = array(
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
);
foreach (array('M', 'W', 'U21M', 'U21W', '50M', '50W') as $cl) {
    CreateEventNew($TourId, "C{$cl}", "Łuk bloczkowy - {$cl}", $i++, $optC);
}

// U18 Compound: Single 40 cm (small 10)
$optCU18 = $optC;
$optCU18['EvFinalTargetType'] = TGT_IND_1_small10;
CreateEventNew($TourId, 'CU18M', 'Łuk bloczkowy - Junior młodszy',   $i++, $optCU18);
CreateEventNew($TourId, 'CU18W', 'Łuk bloczkowy - Juniorka młodsza', $i++, $optCU18);

// U15 Compound: Single 60 cm (small 10), no elimination
$optCU15 = $optC;
$optCU15['EvFinalFirstPhase'] = 0;
$optCU15['EvFinalTargetType'] = TGT_IND_1_small10;
$optCU15['EvTargetSize']      = 60;
CreateEventNew($TourId, 'CU15M', 'Łuk bloczkowy - Młodzik',    $i++, $optCU15);
CreateEventNew($TourId, 'CU15W', 'Łuk bloczkowy - Młodziczka', $i++, $optCU15);

// --- Barebow individual (set system) ---
$optB = array(
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
);
foreach (array('M', 'W', 'U21M', 'U21W', 'U18M', 'U18W') as $cl) {
    CreateEventNew($TourId, "B{$cl}", "Łuk barebow - {$cl}", $i++, $optB);
}

// ---- Team Events -----------------------------------------------------------
$i = 1;

// Recurve team (set system, Triple 40 cm)
$optRT = array(
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
);
foreach (array('M', 'W', 'U24M', 'U24W', 'U21M', 'U21W', 'U18M', 'U18W', '50M', '50W') as $cl) {
    CreateEventNew($TourId, "R{$cl}", "Łuk klasyczny - {$cl} zespoły", $i++, $optRT);
}
// U15 + U12 Recurve team — no elimination
$optRTU15 = $optRT;
$optRTU15['EvFinalFirstPhase'] = 0;
foreach (array('U15M', 'U15W', 'U12M', 'U12W') as $cl) {
    CreateEventNew($TourId, "R{$cl}", "Łuk klasyczny - {$cl} zespoły", $i++, $optRTU15);
}

// Compound team (cumulative, Triple 40 cm small 10)
$optCT = array(
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
);
foreach (array('M', 'W', 'U21M', 'U21W', 'U18M', 'U18W', '50M', '50W') as $cl) {
    CreateEventNew($TourId, "C{$cl}", "Łuk bloczkowy - {$cl} zespoły", $i++, $optCT);
}
// U15 Compound team — no elimination
$optCTU15 = $optCT;
$optCTU15['EvFinalFirstPhase'] = 0;
foreach (array('U15M', 'U15W') as $cl) {
    CreateEventNew($TourId, "C{$cl}", "Łuk bloczkowy - {$cl} zespoły", $i++, $optCTU15);
}

// Barebow team (set system, Single 40 cm)
$optBT = array(
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
);
foreach (array('M', 'W', 'U21M', 'U21W', 'U18M', 'U18W') as $cl) {
    CreateEventNew($TourId, "B{$cl}", "Łuk barebow - {$cl} zespoły", $i++, $optBT);
}

// ---- Target Faces ----------------------------------------------------------
$i = 1;
// R Senior / U24 / U21 / Master: Triple 40 cm (big 10)
CreateTargetFace($TourId, $i++, 'Triple 40 cm (R Senior/U24/U21/Master)',
    'REG-^R(M|W|U24|U21|50)', '1',
    TGT_IND_6_big10, 40, TGT_IND_6_big10, 40);
// R U18: Single 40 cm
CreateTargetFace($TourId, $i++, 'Single 40 cm (R U18)',
    'REG-^RU18', '1',
    TGT_IND_1_big10, 40, TGT_IND_1_big10, 40);
// R U15: Single 60 cm
CreateTargetFace($TourId, $i++, '60 cm (R U15)',
    'RU15%', '1',
    TGT_IND_1_big10, 60, TGT_IND_1_big10, 60);
// R U12: Single 80 cm
CreateTargetFace($TourId, $i++, '80 cm (R U12)',
    'RU12%', '1',
    TGT_IND_1_big10, 80, TGT_IND_1_big10, 80);
// C Senior / U21 / Master: Triple 40 cm (small 10)
CreateTargetFace($TourId, $i++, 'Triple 40 cm (C Senior/U21/Master)',
    'REG-^C(M|W|U21|50)', '1',
    TGT_IND_6_small10, 40, TGT_IND_6_small10, 40);
// C U18: Single 40 cm (small 10)
CreateTargetFace($TourId, $i++, 'Single 40 cm (C U18)',
    'REG-^CU18', '1',
    TGT_IND_1_small10, 40, TGT_IND_1_small10, 40);
// C U15: Single 60 cm (small 10)
CreateTargetFace($TourId, $i++, '60 cm (C U15)',
    'CU15%', '1',
    TGT_IND_1_small10, 60, TGT_IND_1_small10, 60);
// Barebow: Single 40 cm (big 10)
CreateTargetFace($TourId, $i++, 'Single 40 cm (Barebow)',
    'B%', '1',
    TGT_IND_1_big10, 40, TGT_IND_1_big10, 40);

// ---- Event-class bindings, Finals, Distance Info, Tour Update --------------
InsertStandardEvents($TourId, $TourType);
CreateFinals($TourId);
CreateDistanceInformation($TourId, $DistanceInfoArray, 20, 4);

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
