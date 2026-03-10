<?php
/*
 * PZŁucz — shared helpers for PL tournament setup scripts.
 *
 * Included by each Setup_*_PL.php file.  Provides:
 *   CreateStandardDivisions($TourId, $TourType)
 *   CreateStandardClasses($TourId, $TourType)
 *   InsertStandardEvents($TourId, $TourType)
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
// ---------------------------------------------------------------------------
function CreateStandardClasses($TourId, $TourType) {
    $i      = 1;
    $hasU15 = in_array($TourType, array(3, 6));
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
// U12 only appears in type 6; U15 appears in types 3 and 6.
// ---------------------------------------------------------------------------
function InsertStandardEvents($TourId, $TourType) {
    $rClasses = array('M', 'W', 'U24M', 'U24W', 'U21M', 'U21W', 'U18M', 'U18W', '50M', '50W');
    $cClasses = array('M', 'W', 'U21M', 'U21W', 'U18M', 'U18W', '50M', '50W');
    $bClasses = array('M', 'W', 'U21M', 'U21W', 'U18M', 'U18W');

    if (in_array($TourType, array(3, 6))) {
        $rClasses[] = 'U15M'; $rClasses[] = 'U15W';
        $cClasses[] = 'U15M'; $cClasses[] = 'U15W';
    }
    if ($TourType == 6) {
        $rClasses[] = 'U12M'; $rClasses[] = 'U12W';
    }

    // Individual
    foreach ($rClasses as $cl) { InsertClassEvent($TourId, 0, 1, "R{$cl}", 'R', $cl); }
    foreach ($cClasses as $cl) { InsertClassEvent($TourId, 0, 1, "C{$cl}", 'C', $cl); }
    foreach ($bClasses as $cl) { InsertClassEvent($TourId, 0, 1, "B{$cl}", 'B', $cl); }

    // Team
    foreach ($rClasses as $cl) { InsertClassEvent($TourId, 1, 3, "R{$cl}", 'R', $cl); }
    foreach ($cClasses as $cl) { InsertClassEvent($TourId, 1, 3, "C{$cl}", 'C', $cl); }
    foreach ($bClasses as $cl) { InsertClassEvent($TourId, 1, 3, "B{$cl}", 'B', $cl); }
}
