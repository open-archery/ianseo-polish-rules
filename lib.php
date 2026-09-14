<?php
/*
 * PZŁucz — shared helpers for PL tournament setup scripts.
 *
 * Included by each Setup_*_PL.php file.  Provides:
 *   CreateStandardDivisions($TourId, $TourType)
 *   CreateStandardClasses($TourId, $TourType, $KidsOnly = false)
 *   InsertStandardEvents($TourId, $TourType, $KidsOnly = false)
 *   pl_setup_70m_family($TourId, $TourType, $Multiplier, $PL_CLASS_NAMES, $PL_MIXED_CLASS_NAMES)
 *   pl_double_legs($legs, $isDouble)
 */

$tourCollation  = 'polish';
$tourDetIocCode = 'POL';
if (empty($SubRule)) $SubRule = '1';

// Human-readable Polish names for each age/gender class code.
$PL_CLASS_NAMES = array(
    'M'    => 'Seniorzy',
    'W'    => 'Seniorki',
    'U24M' => 'Młodzieżowiec',
    'U24W' => 'Młodzieżowniczka',
    'U21M' => 'Junior',
    'U21W' => 'Juniorka',
    'U18M' => 'Junior młodszy',
    'U18W' => 'Juniorka młodsza',
    '50M'  => 'Master mężczyźni',
    '50W'  => 'Master kobiety',
    'U15M' => 'Młodzik',
    'U15W' => 'Młodziczka',
    'U12M' => 'Dziecko chłopcy',
    'U12W' => 'Dziecko dziewczęta',
);

// Gender-neutral age labels used in mixed team event names.
$PL_MIXED_CLASS_NAMES = array(
    ''    => 'Seniorzy',
    'U24' => 'Młodzieżowcy',
    'U21' => 'Juniorzy',
    'U18' => 'Juniorzy młodsi',
    '50'  => 'Master',
    'U15' => 'Młodziki',
    'U12' => 'Dzieci',
);

// ---------------------------------------------------------------------------
// Divisions: R (Recurve), C (Compound), B (Barebow)
// ---------------------------------------------------------------------------
function CreateStandardDivisions($TourId, $TourType) {
    $i = 1;
    CreateDivision($TourId, $i++, 'R', 'Łuk klasyczny', 1, 'R', 'R');
    CreateDivision($TourId, $i++, 'C', 'Łuk bloczkowy',  1, 'C', 'C');
    CreateDivision($TourId, $i++, 'B', 'Łuk barebow',    1, 'B', 'B');
}

// ---------------------------------------------------------------------------
// Age classes.  ClDivisionsAllowed enforces bow-type restrictions per class:
//   U24  → Recurve only
//   50M/W → Recurve + Compound (no Barebow)
//   U15  → Recurve + Compound (no Barebow)
//   U12  → Recurve only
//   All others → all three divisions
//
// Type 1 (1440): Senior/U24/U21/U18/50 only
// Type 3 (70m):  adds U15
// Type 6 (18m):  adds U15 + U12
// Type 37 (double 70m): same as type 3 — adds U15
//
// $KidsOnly = true (TourType 16, children's round): skip every base class and
// U15, emit only U12M/U12W. See design.md, "$KidsOnly — concrete signature".
// ---------------------------------------------------------------------------
function CreateStandardClasses($TourId, $TourType, $KidsOnly = false) {
    $i = 1;

    if ($KidsOnly) {
        // Self-only ValidClass: the upward chain ('U12M,U15M,U18M,U21M,M')
        // used elsewhere in this function only makes sense when those older
        // classes actually exist in the same tournament (TourType 6). On a
        // U12-only TourType 16 tournament they don't, so the chain must not
        // reference them — same pattern as the base 'M'/'W' classes below,
        // which chain to themselves only.
        CreateClass($TourId, $i++, 9, 12, 0, 'U12M', 'U12M', 'Dziecko chłopcy',        1, 'R');
        CreateClass($TourId, $i++, 9, 12, 1, 'U12W', 'U12W', 'Dziecko dziewczęta',        1, 'R');
        return;
    }

    $hasU15 = in_array($TourType, array(3, 6, 37));
    $hasU12 = ($TourType == 6);

    CreateClass($TourId, $i++, 21, 49,  0, 'M',    'M',                        'Seniorzy',         1, 'R,C,B');
    CreateClass($TourId, $i++, 21, 49,  1, 'W',    'W',                        'Seniorki',         1, 'R,C,B');
    CreateClass($TourId, $i++, 21, 23,  0, 'U24M', 'U24M,M',                   'Młodzieżowiec',    1, 'R');
    CreateClass($TourId, $i++, 21, 23,  1, 'U24W', 'U24W,W',                   'Młodzieżowniczka', 1, 'R');
    CreateClass($TourId, $i++, 18, 20,  0, 'U21M', 'U21M,M',                   'Junior',           1, 'R,C,B');
    CreateClass($TourId, $i++, 18, 20,  1, 'U21W', 'U21W,W',                   'Juniorka',         1, 'R,C,B');
    CreateClass($TourId, $i++, 15, 17,  0, 'U18M', 'U18M,U21M,M',              'Junior młodszy',   1, 'R,C,B');
    CreateClass($TourId, $i++, 15, 17,  1, 'U18W', 'U18W,U21W,W',              'Juniorka młodsza', 1, 'R,C,B');
    CreateClass($TourId, $i++, 50, 100, 0, '50M',  '50M,M',                    'Master mężczyźni',         1, 'R,C');
    CreateClass($TourId, $i++, 50, 100, 1, '50W',  '50W,W',                    'Master kobiety',         1, 'R,C');

    if ($hasU15) {
        CreateClass($TourId, $i++, 13, 14, 0, 'U15M', 'U15M,U18M,U21M,M',     'Młodzik',          1, 'R,C');
        CreateClass($TourId, $i++, 13, 14, 1, 'U15W', 'U15W,U18W,U21W,W',     'Młodziczka',       1, 'R,C');
    }
    if ($hasU12) {
        CreateClass($TourId, $i++, 9, 12, 0, 'U12M', 'U12M,U15M,U18M,U21M,M', 'Dziecko chłopcy',        1, 'R');
        CreateClass($TourId, $i++, 9, 12, 1, 'U12W', 'U12W,U15W,U18W,U21W,W', 'Dziecko dziewczęta',        1, 'R');
    }
}

// ---------------------------------------------------------------------------
// Bind division+class pairs to their events.
// Individual: Team=0, Number=1
// Team:       Team=1, Number=3
// U12 only appears in type 6; U15 appears in types 3, 6 and 37.
//
// $KidsOnly = true (TourType 16): only U12M/U12W individual + team, Recurve
// only, no mixed team (U12 mixed teams don't exist anywhere in the module).
// ---------------------------------------------------------------------------
function InsertStandardEvents($TourId, $TourType, $KidsOnly = false) {
    if ($KidsOnly) {
        $rClasses    = array('U12M', 'U12W');
        $cClasses    = array();
        $bClasses    = array();
        $rMixedAges  = array();
        $cMixedAges  = array();
        $bMixedAges  = array();
    } else {
        $rClasses = array('M', 'W', 'U24M', 'U24W', 'U21M', 'U21W', 'U18M', 'U18W', '50M', '50W');
        $cClasses = array('M', 'W', 'U21M', 'U21W', 'U18M', 'U18W', '50M', '50W');
        $bClasses = array('M', 'W', 'U21M', 'U21W', 'U18M', 'U18W');

        if (in_array($TourType, array(3, 6, 37))) {
            $rClasses[] = 'U15M'; $rClasses[] = 'U15W';
            $cClasses[] = 'U15M'; $cClasses[] = 'U15W';
        }
        if ($TourType == 6) {
            $rClasses[] = 'U12M'; $rClasses[] = 'U12W';
        }

        $rMixedAges = array('', 'U24', 'U21', 'U18', '50');
        $cMixedAges = array('', 'U21', 'U18', '50');
        $bMixedAges = array('', 'U21', 'U18');
        if (in_array($TourType, array(3, 6, 37))) {
            $rMixedAges[] = 'U15';
            $cMixedAges[] = 'U15';
        }
    }

    // Individual
    foreach ($rClasses as $cl) { InsertClassEvent($TourId, 0, 1, "R{$cl}", 'R', $cl); }
    foreach ($cClasses as $cl) { InsertClassEvent($TourId, 0, 1, "C{$cl}", 'C', $cl); }
    foreach ($bClasses as $cl) { InsertClassEvent($TourId, 0, 1, "B{$cl}", 'B', $cl); }

    // Team
    foreach ($rClasses as $cl) { InsertClassEvent($TourId, 1, 3, "R{$cl}", 'R', $cl); }
    foreach ($cClasses as $cl) { InsertClassEvent($TourId, 1, 3, "C{$cl}", 'C', $cl); }
    foreach ($bClasses as $cl) { InsertClassEvent($TourId, 1, 3, "B{$cl}", 'B', $cl); }

    // Mixed Team (Team=1 binds W class; Team=2 binds M class; Number=1)
    // InsertClassEvent silently skips if the event was never created (e.g. type 1).
    foreach ($rMixedAges as $age) {
        InsertClassEvent($TourId, 1, 1, "R{$age}X", 'R', "{$age}W");
        InsertClassEvent($TourId, 2, 1, "R{$age}X", 'R', "{$age}M");
    }
    foreach ($cMixedAges as $age) {
        InsertClassEvent($TourId, 1, 1, "C{$age}X", 'C', "{$age}W");
        InsertClassEvent($TourId, 2, 1, "C{$age}X", 'C', "{$age}M");
    }
    foreach ($bMixedAges as $age) {
        InsertClassEvent($TourId, 1, 1, "B{$age}X", 'B', "{$age}W");
        InsertClassEvent($TourId, 2, 1, "B{$age}X", 'B', "{$age}M");
    }
}

// Groups legs by meters (in first-appearance order) and doubles each group's
// session count with sequential "-N" labels, so every class shoots its
// existing distance(s) twice as many sessions (Podwójna runda) without a
// special case for the asymmetric U15 [40m, 20m] split — a single 70m×2
// group becomes 70m-1..70m-4, while 40m×1 + 20m×1 becomes two groups:
// 40m-1, 40m-2, 20m-1, 20m-2 (not interleaved).
function pl_double_legs($legs, $isDouble) {
    if (!$isDouble) return $legs;

    $counts = array();
    $order  = array();
    foreach ($legs as $leg) {
        $meters = $leg[1];
        if (!isset($counts[$meters])) {
            $counts[$meters] = 0;
            $order[] = $meters;
        }
        $counts[$meters]++;
    }

    $out = array();
    foreach ($order as $meters) {
        for ($i = 1; $i <= $counts[$meters] * 2; $i++) {
            $out[] = array($meters . 'm-' . $i, $meters);
        }
    }
    return $out;
}

// ---------------------------------------------------------------------------
// Shared body for the 70m-Round family (Single-Distance Round / Double Round).
// $Multiplier: 1 = single (TourType 3), 2 = double (TourType 37).
//
// $TourId and the class-name lookup tables are explicit parameters, not
// `global` — GetSetupFile() (Common/Fun_ScriptsOnNewTour.inc.php) only
// promotes $tourDetGolds/$tourDetXNine/$tourDetGoldsChars/$tourDetXNineChars
// to true PHP globals with its own `global` statement before requiring the
// setup script; $TourId (a parameter, not global-declared there) and
// $PL_CLASS_NAMES/$PL_MIXED_CLASS_NAMES (assigned at this file's top level,
// also never global-declared) are not real globals and would not be visible
// to a function via `global`. See design.md, "Scope trap".
// ---------------------------------------------------------------------------
function pl_setup_70m_family($TourId, $TourType, $Multiplier, $PL_CLASS_NAMES, $PL_MIXED_CLASS_NAMES) {
    global $tourDetGolds, $tourDetXNine, $tourDetGoldsChars, $tourDetXNineChars;

    $isDouble = ($Multiplier > 1);

    $baseDistanceInfo  = array(array(6, 6), array(6, 6));
    $DistanceInfoArray = $baseDistanceInfo;
    for ($m = 1; $m < $Multiplier; $m++) {
        $DistanceInfoArray = array_merge($DistanceInfoArray, $baseDistanceInfo);
    }

    // ---- Divisions & Classes ------------------------------------------------
    CreateStandardDivisions($TourId, $TourType);
    CreateStandardClasses($TourId, $TourType);  // includes U15

    // ---- Distances ------------------------------------------------------------

    // Recurve — Senior / U24 / U21: 2 × 70 m (4 × 70 m when doubled)
    CreateDistanceNew($TourId, $TourType, 'RM',    pl_double_legs(array(array('70m-1', 70), array('70m-2', 70)), $isDouble));
    CreateDistanceNew($TourId, $TourType, 'RW',    pl_double_legs(array(array('70m-1', 70), array('70m-2', 70)), $isDouble));
    CreateDistanceNew($TourId, $TourType, 'RU24M', pl_double_legs(array(array('70m-1', 70), array('70m-2', 70)), $isDouble));
    CreateDistanceNew($TourId, $TourType, 'RU24W', pl_double_legs(array(array('70m-1', 70), array('70m-2', 70)), $isDouble));
    CreateDistanceNew($TourId, $TourType, 'RU21M', pl_double_legs(array(array('70m-1', 70), array('70m-2', 70)), $isDouble));
    CreateDistanceNew($TourId, $TourType, 'RU21W', pl_double_legs(array(array('70m-1', 70), array('70m-2', 70)), $isDouble));

    // Recurve — U18 / Master: 2 × 60 m (4 × 60 m when doubled)
    CreateDistanceNew($TourId, $TourType, 'RU18M', pl_double_legs(array(array('60m-1', 60), array('60m-2', 60)), $isDouble));
    CreateDistanceNew($TourId, $TourType, 'RU18W', pl_double_legs(array(array('60m-1', 60), array('60m-2', 60)), $isDouble));
    CreateDistanceNew($TourId, $TourType, 'R50M',  pl_double_legs(array(array('60m-1', 60), array('60m-2', 60)), $isDouble));
    CreateDistanceNew($TourId, $TourType, 'R50W',  pl_double_legs(array(array('60m-1', 60), array('60m-2', 60)), $isDouble));

    // Recurve — U15: 40 m + 20 m (40 m, 40 m, 20 m, 20 m when doubled)
    CreateDistanceNew($TourId, $TourType, 'RU15M', pl_double_legs(array(array('40m', 40), array('20m', 20)), $isDouble));
    CreateDistanceNew($TourId, $TourType, 'RU15W', pl_double_legs(array(array('40m', 40), array('20m', 20)), $isDouble));

    // Compound — all: 2 × 50 m (4 × 50 m when doubled)
    CreateDistanceNew($TourId, $TourType, 'C%', pl_double_legs(array(array('50m-1', 50), array('50m-2', 50)), $isDouble));

    // Barebow — all: 2 × 50 m (4 × 50 m when doubled)
    CreateDistanceNew($TourId, $TourType, 'B%', pl_double_legs(array(array('50m-1', 50), array('50m-2', 50)), $isDouble));

    // ---- Individual Events (with elimination, except U15) ----------------------
    $indFirstPhase  = 48;  // top 104
    $teamFirstPhase = 12;  // top 24
    $i = 1;

    // --- Recurve individual (set system) ---
    $optR = array(
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
    );
    foreach (array('M', 'W', 'U24M', 'U24W', 'U21M', 'U21W') as $cl) {
        CreateEventNew($TourId, "R{$cl}", "Łuk klasyczny - {$PL_CLASS_NAMES[$cl]}", $i++, $optR);
    }
    $optR['EvDistance'] = 60;
    foreach (array('U18M', 'U18W', '50M', '50W') as $cl) {
        CreateEventNew($TourId, "R{$cl}", "Łuk klasyczny - {$PL_CLASS_NAMES[$cl]}", $i++, $optR);
    }

    // U15 — no elimination
    $optRU15 = $optR;
    $optRU15['EvFinalFirstPhase'] = 0;
    $optRU15['EvDistance']        = 40;
    $optRU15['EvTargetSize']      = 122;
    foreach (array('U15M', 'U15W') as $cl) {
        CreateEventNew($TourId, "R{$cl}", "Łuk klasyczny - {$PL_CLASS_NAMES[$cl]}", $i++, $optRU15);
    }

    // --- Compound individual (cumulative) ---
    $optC = array(
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
    );
    foreach (array('M', 'W', 'U21M', 'U21W', 'U18M', 'U18W', '50M', '50W') as $cl) {
        CreateEventNew($TourId, "C{$cl}", "Łuk bloczkowy - {$PL_CLASS_NAMES[$cl]}", $i++, $optC);
    }

    // U15 Compound — no elimination
    $optCU15 = $optC;
    $optCU15['EvFinalFirstPhase'] = 0;
    foreach (array('U15M', 'U15W') as $cl) {
        CreateEventNew($TourId, "C{$cl}", "Łuk bloczkowy - {$PL_CLASS_NAMES[$cl]}", $i++, $optCU15);
    }

    // --- Barebow individual (set system) ---
    $optB = array(
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
    );
    foreach (array('M', 'W', 'U21M', 'U21W', 'U18M', 'U18W') as $cl) {
        CreateEventNew($TourId, "B{$cl}", "Łuk barebow - {$PL_CLASS_NAMES[$cl]}", $i++, $optB);
    }

    // ---- Team Events -----------------------------------------------------------
    $i = 1;

    // Recurve team (set system)
    $optRT = array(
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
    );
    foreach (array('M', 'W', 'U24M', 'U24W', 'U21M', 'U21W') as $cl) {
        CreateEventNew($TourId, "R{$cl}", "Łuk klasyczny - {$PL_CLASS_NAMES[$cl]} zespoły", $i++, $optRT);
    }
    $optRT['EvDistance'] = 60;
    foreach (array('U18M', 'U18W', '50M', '50W') as $cl) {
        CreateEventNew($TourId, "R{$cl}", "Łuk klasyczny - {$PL_CLASS_NAMES[$cl]} zespoły", $i++, $optRT);
    }
    // U15 Recurve team — no elimination
    $optRTU15 = $optRT;
    $optRTU15['EvFinalFirstPhase'] = 0;
    $optRTU15['EvDistance']        = 40;
    $optRTU15['EvTargetSize']      = 122;
    foreach (array('U15M', 'U15W') as $cl) {
        CreateEventNew($TourId, "R{$cl}", "Łuk klasyczny - {$PL_CLASS_NAMES[$cl]} zespoły", $i++, $optRTU15);
    }

    // Compound team (cumulative)
    $optCT = array(
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
    );
    foreach (array('M', 'W', 'U21M', 'U21W', 'U18M', 'U18W', '50M', '50W') as $cl) {
        CreateEventNew($TourId, "C{$cl}", "Łuk bloczkowy - {$PL_CLASS_NAMES[$cl]} zespoły", $i++, $optCT);
    }
    // U15 Compound team — no elimination
    $optCTU15 = $optCT;
    $optCTU15['EvFinalFirstPhase'] = 0;
    foreach (array('U15M', 'U15W') as $cl) {
        CreateEventNew($TourId, "C{$cl}", "Łuk bloczkowy - {$PL_CLASS_NAMES[$cl]} zespoły", $i++, $optCTU15);
    }

    // Barebow team (set system)
    $optBT = array(
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
    );
    foreach (array('M', 'W', 'U21M', 'U21W', 'U18M', 'U18W') as $cl) {
        CreateEventNew($TourId, "B{$cl}", "Łuk barebow - {$PL_CLASS_NAMES[$cl]} zespoły", $i++, $optBT);
    }

    // ---- Mixed Team Events -----------------------------------------------------
    $mixFirstPhase = 12;  // top 24 (1/12 finału)
    $i = 1;

    // Recurve mixed teams (set system)
    $optRMX = array(
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
    );
    // Senior / U24 / U21: 70 m
    foreach (array('', 'U24', 'U21') as $age) {
        CreateEventNew($TourId, "R{$age}X",
            "Łuk klasyczny - {$PL_MIXED_CLASS_NAMES[$age]} zespoły mieszane", $i++, $optRMX);
    }
    // U18 / 50+: 60 m
    $optRMX['EvDistance'] = 60;
    foreach (array('U18', '50') as $age) {
        CreateEventNew($TourId, "R{$age}X",
            "Łuk klasyczny - {$PL_MIXED_CLASS_NAMES[$age]} zespoły mieszane", $i++, $optRMX);
    }
    // U15: 40 m, no elimination
    $optRMXU15 = $optRMX;
    $optRMXU15['EvFinalFirstPhase'] = 0;
    $optRMXU15['EvDistance']        = 40;
    $optRMXU15['EvTargetSize']      = 122;
    CreateEventNew($TourId, 'RU15X',
        "Łuk klasyczny - {$PL_MIXED_CLASS_NAMES['U15']} zespoły mieszane", $i++, $optRMXU15);

    // Compound mixed teams (cumulative)
    $optCMX = array(
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
    );
    foreach (array('', 'U21', 'U18', '50') as $age) {
        CreateEventNew($TourId, "C{$age}X",
            "Łuk bloczkowy - {$PL_MIXED_CLASS_NAMES[$age]} zespoły mieszane", $i++, $optCMX);
    }
    // U15 Compound — no elimination
    $optCMXU15 = $optCMX;
    $optCMXU15['EvFinalFirstPhase'] = 0;
    CreateEventNew($TourId, 'CU15X',
        "Łuk bloczkowy - {$PL_MIXED_CLASS_NAMES['U15']} zespoły mieszane", $i++, $optCMXU15);

    // Barebow mixed teams (set system)
    $optBMX = array(
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
    );
    foreach (array('', 'U21', 'U18') as $age) {
        CreateEventNew($TourId, "B{$age}X",
            "Łuk barebow - {$PL_MIXED_CLASS_NAMES[$age]} zespoły mieszane", $i++, $optBMX);
    }

    // ---- Target Faces ----------------------------------------------------------
    $i = 1;
    // Recurve (incl. Barebow): 122 cm full face
    CreateTargetFace($TourId, $i++, 'Recurve/Barebow domyślna', 'REG-^[RB]', '1',
        TGT_OUT_FULL, 122, TGT_OUT_FULL, 122);
    // Compound: 80 cm 6-ring
    CreateTargetFace($TourId, $i++, 'Compound domyślna', 'C%', '1',
        TGT_OUT_5_big10, 80, TGT_OUT_5_big10, 80);
    // U15 Recurve: 122 cm for 40 m, 80 cm for 20 m
    CreateTargetFace($TourId, $i++, 'Recurve Młodzik (40 m / 20 m)', 'RU15%', '1',
        TGT_OUT_FULL, 122, TGT_OUT_FULL, 80);

    // ---- Event-class bindings, Finals, Distance Info ---------------------------
    InsertStandardEvents($TourId, $TourType);
    CreateFinals($TourId);
    CreateDistanceInformation($TourId, $DistanceInfoArray, 20, 4);
}

// ---------------------------------------------------------------------------
// Shared body for the Children's Round (TourType 16, U12-only). $TourId and
// $PL_CLASS_NAMES are explicit parameters for the same reason as
// pl_setup_70m_family() above — see its doc comment.
// ---------------------------------------------------------------------------
function pl_setup_kids_round($TourId, $TourType, $PL_CLASS_NAMES) {
    global $tourDetGolds, $tourDetXNine, $tourDetGoldsChars, $tourDetXNineChars;

    $DistanceInfoArray = array(array(6, 3), array(6, 3), array(6, 3), array(6, 3));

    // ---- Divisions & Classes ---------------------------------------------------
    CreateStandardDivisions($TourId, $TourType);
    CreateStandardClasses($TourId, $TourType, true);  // U12M/U12W only

    // ---- Distances --------------------------------------------------------------
    CreateDistanceNew($TourId, $TourType, 'RU12M', array(
        array('25m', 25), array('20m', 20), array('15m', 15), array('10m', 10),
    ));
    CreateDistanceNew($TourId, $TourType, 'RU12W', array(
        array('25m', 25), array('20m', 20), array('15m', 15), array('10m', 10),
    ));

    // ---- Individual Events (no elimination) ------------------------------------
    $i = 1;
    $optR = array(
        'EvFinalFirstPhase' => 0,
        'EvFinalTargetType' => TGT_OUT_FULL,
        'EvElimEnds'        => 5, 'EvElimArrows' => 3, 'EvElimSO' => 1,
        'EvFinEnds'         => 5, 'EvFinArrows'  => 3, 'EvFinSO'  => 1,
        'EvMatchMode'       => 1,
        'EvMatchArrowsNo'   => 240, 'EvFinalAthTarget' => 240,
        'EvTargetSize'      => 122, 'EvDistance' => 25,
        'EvGolds'           => $tourDetGolds,
        'EvXNine'           => $tourDetXNine,
        'EvGoldsChars'      => $tourDetGoldsChars,
        'EvXNineChars'      => $tourDetXNineChars,
    );
    foreach (array('U12M', 'U12W') as $cl) {
        CreateEventNew($TourId, "R{$cl}", "Łuk klasyczny - {$PL_CLASS_NAMES[$cl]}", $i++, $optR);
    }

    // ---- Team Events -------------------------------------------------------------
    $i = 1;
    $optRT = array(
        'EvTeamEvent'       => 1, 'EvMaxTeamPerson' => 3,
        'EvFinalFirstPhase' => 0,
        'EvFinalTargetType' => TGT_OUT_FULL,
        'EvElimEnds'        => 4, 'EvElimArrows' => 6, 'EvElimSO' => 3,
        'EvFinEnds'         => 4, 'EvFinArrows'  => 6, 'EvFinSO'  => 3,
        'EvMatchMode'       => 1,
        'EvTargetSize'      => 122, 'EvDistance' => 25,
        'EvGolds'           => $tourDetGolds,
        'EvXNine'           => $tourDetXNine,
        'EvGoldsChars'      => $tourDetGoldsChars,
        'EvXNineChars'      => $tourDetXNineChars,
    );
    foreach (array('U12M', 'U12W') as $cl) {
        CreateEventNew($TourId, "R{$cl}", "Łuk klasyczny - {$PL_CLASS_NAMES[$cl]} zespoły", $i++, $optRT);
    }

    // ---- Target Faces ------------------------------------------------------------
    $i = 1;
    // 25m/20m on 122cm, 15m/10m on 80cm (legs 1-4, in that order)
    CreateTargetFace($TourId, $i++, 'Dzieci 25m/20m/15m/10m', 'RU12%', '1',
        TGT_OUT_FULL, 122, TGT_OUT_FULL, 122, TGT_OUT_FULL, 80, TGT_OUT_FULL, 80);

    // ---- Event-class bindings, Finals, Distance Info ---------------------------
    InsertStandardEvents($TourId, $TourType, true);
    CreateFinals($TourId);
    CreateDistanceInformation($TourId, $DistanceInfoArray, 20, 4);
}
