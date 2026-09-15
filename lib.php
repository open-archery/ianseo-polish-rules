<?php
/*
 * PZŁucz — shared helpers for PL tournament setup scripts.
 *
 * Included by each Setup_*_PL.php file.  Provides:
 *   CreateStandardDivisions($TourId, $TourType, $preset = array())
 *   CreateStandardClasses($TourId, $TourType, $preset = array())
 *   InsertStandardEvents($TourId, $TourType, $preset = array())
 *   pl_resolve_preset($TourType, $subRuleName)
 *   pl_class_in_preset($class, $division, $preset)
 *   pl_setup_70m_family($TourId, $TourType, $Multiplier, $PL_CLASS_NAMES, $PL_MIXED_CLASS_NAMES, $preset)
 *   pl_setup_1440($TourId, $TourType, $PL_CLASS_NAMES, $preset)
 *   pl_setup_indoor($TourId, $TourType, $PL_CLASS_NAMES, $PL_MIXED_CLASS_NAMES, $preset)
 *   pl_double_legs($legs, $isDouble)
 *
 * Preset mechanism: a preset is array('divisions' => [...], 'classes' => [...]),
 * either key optional/omitted meaning "no restriction on that axis". Every
 * builder above filters what it creates by the preset; pl_class_in_preset()
 * is the single check reused everywhere a class/division pair is considered,
 * including each Setup_*_PL.php's own per-class CreateEventNew() loops — the
 * builders here only stop CreateClass()/InsertClassEvent() calls, but
 * CreateEventNew() (core, Modules/Sets/lib.php) unconditionally inserts an
 * Events row regardless of whether a matching class exists, so every
 * per-class CreateEventNew() loop needs its own preset guard too, or a
 * filtered-out class still leaves a bindable-to-nothing orphan Event behind.
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
    'U15M' => 'Młodzik',
    'U15W' => 'Młodziczka',
    'U12M' => 'Dziecko chłopcy',
    'U12W' => 'Dziecko dziewczęta',
    'PU12M' => 'Dziecko chłopcy - łuk popularny',
    'PU12W' => 'Dziecko dziewczęta - łuk popularny',
    '40M'  => 'Master 40-49 mężczyźni',
    '40W'  => 'Master 40-49 kobiety',
    '50M'  => 'Master 50-59 mężczyźni',
    '50W'  => 'Master 50-59 kobiety',
    '60M'  => 'Master 60-69 mężczyźni',
    '60W'  => 'Master 60-69 kobiety',
    '70M'  => 'Master 70+ mężczyźni',
    '70W'  => 'Master 70+ kobiety',
    '80M'  => 'Master 80+ mężczyźni',
    '80W'  => 'Master 80+ kobiety',
);

// Gender-neutral age labels used in mixed team event names. No Masters entry:
// Masters has no mixed-team competition defined (see design.md).
$PL_MIXED_CLASS_NAMES = array(
    ''    => 'Seniorzy',
    'U24' => 'Młodzieżowcy',
    'U21' => 'Juniorzy',
    'U18' => 'Juniorzy młodsi',
    'U15' => 'Młodziki',
    'U12' => 'Dzieci',
);

// ---------------------------------------------------------------------------
// Preset resolution: sub-rule value -> {divisions?, classes?} filter, per
// TourType. Abstract naming: reuses ianseo's own translated Install.php
// sub-rule vocabulary where its meaning fits (SetAllClass/SetSeniorClass/
// SetYouthClass/SetMasterClass); module-specific keys elsewhere, which
// ianseo renders without a translated label (see design.md).
// ---------------------------------------------------------------------------
function pl_preset_table() {
    $senior = array('classes' => array('M', 'W'));

    return array(
        1 => array(
            'SetAllClass'    => array(),
            'SetSeniorClass' => $senior,
            'Poland-RU24'    => array('divisions' => array('R'), 'classes' => array('U24M', 'U24W')),
            'Poland-RU21'    => array('divisions' => array('R'), 'classes' => array('U21M', 'U21W')),
            'Poland-RU18'    => array('divisions' => array('R'), 'classes' => array('U18M', 'U18W')),
        ),
        3 => array(
            'SetAllClass'        => array(),
            'SetSeniorClass'     => $senior,
            'Poland-RU24U21U18'  => array('divisions' => array('R'), 'classes' => array('U24M', 'U24W', 'U21M', 'U21W', 'U18M', 'U18W')),
            'SetYouthClass'      => array('divisions' => array('R'), 'classes' => array('U24M', 'U24W', 'U21M', 'U21W')),
            'Poland-RU18'    => array('divisions' => array('R'), 'classes' => array('U18M', 'U18W')),
            'Poland-RU15'        => array('divisions' => array('R'), 'classes' => array('U15M', 'U15W')),
            'SetMasterClass'     => array('classes' => array(
                '40M', '40W', '50M', '50W', '60M', '60W', '70M', '70W', '80M', '80W',
            )),
        ),
        37 => array(
            'SetAllClass'        => array(),
            'SetSeniorClass'     => $senior,
            'Poland-RU24U21U18'  => array('divisions' => array('R'), 'classes' => array('U24M', 'U24W', 'U21M', 'U21W', 'U18M', 'U18W')),
            'SetYouthClass'      => array('divisions' => array('R'), 'classes' => array('U24M', 'U24W', 'U21M', 'U21W')),
            'Poland-RU18'    => array('divisions' => array('R'), 'classes' => array('U18M', 'U18W')),
            'Poland-RU15'        => array('divisions' => array('R'), 'classes' => array('U15M', 'U15W')),
        ),
        6 => array(
            'SetAllClass'     => array(),
            'SetSeniorClass'  => $senior,
            'SetYouthClass'   => array('divisions' => array('R'), 'classes' => array('U24M', 'U24W', 'U21M', 'U21W')),
            'Poland-RU18' => array('divisions' => array('R'), 'classes' => array('U18M', 'U18W')),
            'Poland-RU15'     => array('divisions' => array('R'), 'classes' => array('U15M', 'U15W')),
        ),
        // TourType 16 has one fixed, non-selectable configuration — no entries
        // needed here; pl_standard_class_candidates() is U12-only for it
        // regardless of preset (see CreateStandardClasses()).
    );
}

// Maps a selected sub-rule value to its preset. Empty/unrecognised input
// (including TourType 16, which has no rules table entry) resolves to the
// unfiltered preset — the same behaviour as explicitly selecting SetAllClass.
function pl_resolve_preset($TourType, $subRuleName) {
    if (empty($subRuleName)) return array();
    $table = pl_preset_table();
    return $table[$TourType][$subRuleName] ?? array();
}

// True if $class in $division survives $preset. An omitted or empty key on
// either axis means "no restriction on that axis" — matches how an absent
// 'divisions'/'classes' key is documented in design.md.
function pl_class_in_preset($class, $division, $preset) {
    if (!empty($preset['divisions']) && !in_array($division, $preset['divisions'], true)) return false;
    if (!empty($preset['classes']) && !in_array($class, $preset['classes'], true)) return false;
    return true;
}

// ---------------------------------------------------------------------------
// Divisions: R (Recurve), C (Compound), B (Barebow). TourType 1 (1440 Round)
// never has a Barebow division — PZŁucz's 1440 Round does not offer it.
// ---------------------------------------------------------------------------
function CreateStandardDivisions($TourId, $TourType, $preset = array()) {
    $i = 1;
    $all = array(
        array('R', 'Łuk klasyczny', 'R', 'R'),
        array('C', 'Łuk bloczkowy', 'C', 'C'),
        array('B', 'Łuk barebow',   'B', 'B'),
    );
    foreach ($all as $d) {
        if ($TourType == 1 && $d[0] === 'B') continue;
        if (!empty($preset['divisions']) && !in_array($d[0], $preset['divisions'], true)) continue;
        CreateDivision($TourId, $i++, $d[0], $d[1], 1, $d[2], $d[3]);
    }
}

// ---------------------------------------------------------------------------
// Full (unfiltered) class candidate list for a TourType — the single source
// of truth CreateStandardClasses()/InsertStandardEvents() and every
// Setup_*_PL.php's own per-class CreateEventNew() loop filter down from via
// pl_class_in_preset(), so a preset can never leave an Event with no class
// to bind to.
//
// ClDivisionsAllowed enforces bow-type restrictions per class:
//   U24, U12, PU12 -> Recurve only
//   U15            -> Recurve + Compound (no Barebow)
//   Masters        -> Recurve + Compound + Barebow (all five bands)
//   All others     -> all divisions the TourType itself has (R,C,B; R,C on
//                     TourType 1, which has no Barebow — see
//                     CreateStandardDivisions())
//
// Type 1 (1440): Senior/U24/U21/U18 only. No U15, U12, PU12 or Masters.
// Type 3 (70m):  adds U15, U12, PU12; Masters (5 bands x R/C/B) exclusive.
// Type 6 (18m):  adds U15, U12, PU12 (no Masters).
// Type 37 (double 70m): same as type 3 minus U12/PU12/Masters.
// Type 16 (children's round): U12 only, self-only ClValidClass — no other
//   class exists in this tournament to chain upward into (see gotchas.md,
//   "ClValidClass ... must only name classes that actually exist in that
//   tournament").
// ---------------------------------------------------------------------------
function pl_standard_class_candidates($TourType) {
    if ($TourType == 16) {
        return array(
            array('code' => 'U12M', 'ageFrom' => 9, 'ageTo' => 12, 'sex' => 0, 'valid' => 'U12M', 'name' => 'Dziecko chłopcy',        'div' => array('R')),
            array('code' => 'U12W', 'ageFrom' => 9, 'ageTo' => 12, 'sex' => 1, 'valid' => 'U12W', 'name' => 'Dziecko dziewczęta',        'div' => array('R')),
        );
    }

    $hasU15      = in_array($TourType, array(3, 6, 37));
    $hasU12      = in_array($TourType, array(3, 6));
    $hasPU12     = in_array($TourType, array(3, 6));
    $hasMasters  = ($TourType == 3);

    $c = array();
    $c[] = array('code' => 'M',    'ageFrom' => 21, 'ageTo' => 49,  'sex' => 0, 'valid' => 'M',                        'name' => 'Seniorzy',              'div' => array('R', 'C', 'B'));
    $c[] = array('code' => 'W',    'ageFrom' => 21, 'ageTo' => 49,  'sex' => 1, 'valid' => 'W',                        'name' => 'Seniorki',              'div' => array('R', 'C', 'B'));
    $c[] = array('code' => 'U24M', 'ageFrom' => 21, 'ageTo' => 23,  'sex' => 0, 'valid' => 'U24M,M',                   'name' => 'Młodzieżowiec',         'div' => array('R'));
    $c[] = array('code' => 'U24W', 'ageFrom' => 21, 'ageTo' => 23,  'sex' => 1, 'valid' => 'U24W,W',                   'name' => 'Młodzieżowniczka',      'div' => array('R'));
    $c[] = array('code' => 'U21M', 'ageFrom' => 18, 'ageTo' => 20,  'sex' => 0, 'valid' => 'U21M,M',                   'name' => 'Junior',                'div' => array('R', 'C', 'B'));
    $c[] = array('code' => 'U21W', 'ageFrom' => 18, 'ageTo' => 20,  'sex' => 1, 'valid' => 'U21W,W',                   'name' => 'Juniorka',              'div' => array('R', 'C', 'B'));
    $c[] = array('code' => 'U18M', 'ageFrom' => 15, 'ageTo' => 17,  'sex' => 0, 'valid' => 'U18M,U21M,M',              'name' => 'Junior młodszy',        'div' => array('R', 'C', 'B'));
    $c[] = array('code' => 'U18W', 'ageFrom' => 15, 'ageTo' => 17,  'sex' => 1, 'valid' => 'U18W,U21W,W',              'name' => 'Juniorka młodsza',      'div' => array('R', 'C', 'B'));

    if ($hasU15) {
        $c[] = array('code' => 'U15M', 'ageFrom' => 13, 'ageTo' => 14, 'sex' => 0, 'valid' => 'U15M,U18M,U21M,M', 'name' => 'Młodzik',    'div' => array('R', 'C'));
        $c[] = array('code' => 'U15W', 'ageFrom' => 13, 'ageTo' => 14, 'sex' => 1, 'valid' => 'U15W,U18W,U21W,W', 'name' => 'Młodziczka', 'div' => array('R', 'C'));
    }
    if ($hasU12) {
        $c[] = array('code' => 'U12M', 'ageFrom' => 9, 'ageTo' => 12, 'sex' => 0, 'valid' => 'U12M,U15M,U18M,U21M,M', 'name' => 'Dziecko chłopcy',        'div' => array('R'));
        $c[] = array('code' => 'U12W', 'ageFrom' => 9, 'ageTo' => 12, 'sex' => 1, 'valid' => 'U12W,U15W,U18W,U21W,W', 'name' => 'Dziecko dziewczęta',        'div' => array('R'));
    }
    if ($hasPU12) {
        $c[] = array('code' => 'PU12M', 'ageFrom' => 9, 'ageTo' => 12, 'sex' => 0, 'valid' => 'PU12M', 'name' => 'Dziecko chłopcy - łuk popularny',        'div' => array('R'));
        $c[] = array('code' => 'PU12W', 'ageFrom' => 9, 'ageTo' => 12, 'sex' => 1, 'valid' => 'PU12W', 'name' => 'Dziecko dziewczęta - łuk popularny',        'div' => array('R'));
    }
    if ($hasMasters) {
        $bands = array('40' => array(40, 49), '50' => array(50, 59), '60' => array(60, 69), '70' => array(70, 79), '80' => array(80, 100));
        foreach ($bands as $band => $range) {
            $c[] = array('code' => "{$band}M", 'ageFrom' => $range[0], 'ageTo' => $range[1], 'sex' => 0, 'valid' => "{$band}M", 'name' => "Master {$band}",  'div' => array('R', 'C', 'B'));
            $c[] = array('code' => "{$band}W", 'ageFrom' => $range[0], 'ageTo' => $range[1], 'sex' => 1, 'valid' => "{$band}W", 'name' => "Master {$band}",  'div' => array('R', 'C', 'B'));
        }
    }

    if ($TourType == 1) {
        foreach ($c as &$row) {
            $row['div'] = array_values(array_diff($row['div'], array('B')));
        }
        unset($row);
    }

    return $c;
}

function CreateStandardClasses($TourId, $TourType, $preset = array()) {
    $i = 1;
    foreach (pl_standard_class_candidates($TourType) as $cand) {
        $divisions = array();
        foreach ($cand['div'] as $div) {
            if (pl_class_in_preset($cand['code'], $div, $preset)) $divisions[] = $div;
        }
        if (empty($divisions)) continue;

        // $PL_CLASS_NAMES carries the age-band label ("Master 40") for Masters
        // codes; use the real Polish gendered name here instead ("Master
        // 40-49 mężczyźni"/"kobiety") via $cand['name'] for those, and every
        // candidate's own name otherwise — $cand['name'] is already correct
        // for every class, so just use it directly.
        CreateClass($TourId, $i++, $cand['ageFrom'], $cand['ageTo'], $cand['sex'], $cand['code'], $cand['valid'], $cand['name'], 1, implode(',', $divisions));
    }
}

// ---------------------------------------------------------------------------
// Bind division+class pairs to their events. U15/U12/PU12/Masters follow the
// same TourType/preset rules as CreateStandardClasses() (single source of
// truth: pl_standard_class_candidates()). No mixed-team events exist for
// U12, PU12 or Masters (see design.md).
// ---------------------------------------------------------------------------
function InsertStandardEvents($TourId, $TourType, $preset = array()) {
    $candidates = pl_standard_class_candidates($TourType);

    $byDivision = array('R' => array(), 'C' => array(), 'B' => array());
    foreach ($candidates as $cand) {
        foreach ($cand['div'] as $div) {
            if (pl_class_in_preset($cand['code'], $div, $preset)) {
                $byDivision[$div][] = $cand['code'];
            }
        }
    }
    $rClasses = $byDivision['R'];
    $cClasses = $byDivision['C'];
    $bClasses = $byDivision['B'];

    // Individual
    foreach ($rClasses as $cl) { InsertClassEvent($TourId, 0, 1, "R{$cl}", 'R', $cl); }
    foreach ($cClasses as $cl) { InsertClassEvent($TourId, 0, 1, "C{$cl}", 'C', $cl); }
    foreach ($bClasses as $cl) { InsertClassEvent($TourId, 0, 1, "B{$cl}", 'B', $cl); }

    // Team
    foreach ($rClasses as $cl) { InsertClassEvent($TourId, 1, 3, "R{$cl}", 'R', $cl); }
    foreach ($cClasses as $cl) { InsertClassEvent($TourId, 1, 3, "C{$cl}", 'C', $cl); }
    foreach ($bClasses as $cl) { InsertClassEvent($TourId, 1, 3, "B{$cl}", 'B', $cl); }

    if ($TourType == 16) return; // no mixed team events for the Children's Round

    // Mixed Team (Team=1 binds W class; Team=2 binds M class; Number=1).
    // InsertClassEvent silently skips if the event was never created (e.g.
    // type 1, which has no mixed team events at all). No U12/PU12/Masters
    // mixed ages exist — deliberately absent from these lists.
    $rMixedAges = array('', 'U24', 'U21', 'U18');
    $cMixedAges = array('', 'U21', 'U18');
    $bMixedAges = array('', 'U21', 'U18');
    if ($TourType != 1) {
        $rMixedAges[] = 'U15';
        $cMixedAges[] = 'U15';
    }

    foreach (array('R' => $rMixedAges, 'C' => $cMixedAges, 'B' => $bMixedAges) as $div => $ages) {
        foreach ($ages as $age) {
            if (!pl_class_in_preset("{$age}M", $div, $preset) || !pl_class_in_preset("{$age}W", $div, $preset)) continue;
            InsertClassEvent($TourId, 1, 1, "{$div}{$age}X", $div, "{$age}W");
            InsertClassEvent($TourId, 2, 1, "{$div}{$age}X", $div, "{$age}M");
        }
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
// setup script; $TourId (a parameter of `GetSetupFile()`, never `global`-declared)
// and $PL_CLASS_NAMES/$PL_MIXED_CLASS_NAMES (assigned at this file's top level,
// also never global-declared) are not real globals and would not be visible
// to a function via `global`. See design.md, "Scope trap".
//
// U12/PU12/Masters only apply when $TourType == 3 (Masters) or 3/6 (U12,
// PU12) — $TourType == 37 (double round) explicitly excludes all three, so
// pl_standard_class_candidates() already omits them for 37 and no extra
// guard is needed here beyond the preset filter every per-class loop applies.
// ---------------------------------------------------------------------------
function pl_setup_70m_family($TourId, $TourType, $Multiplier, $PL_CLASS_NAMES, $PL_MIXED_CLASS_NAMES, $preset = array()) {
    global $tourDetGolds, $tourDetXNine, $tourDetGoldsChars, $tourDetXNineChars;

    $isDouble = ($Multiplier > 1);

    $baseDistanceInfo  = array(array(6, 6), array(6, 6));
    $DistanceInfoArray = $baseDistanceInfo;
    for ($m = 1; $m < $Multiplier; $m++) {
        $DistanceInfoArray = array_merge($DistanceInfoArray, $baseDistanceInfo);
    }

    // ---- Divisions & Classes ------------------------------------------------
    CreateStandardDivisions($TourId, $TourType, $preset);
    CreateStandardClasses($TourId, $TourType, $preset);

    // ---- Distances ------------------------------------------------------------

    // Recurve — Senior / U24 / U21: 2 × 70 m (4 × 70 m when doubled)
    CreateDistanceNew($TourId, $TourType, 'RM',    pl_double_legs(array(array('70m-1', 70), array('70m-2', 70)), $isDouble));
    CreateDistanceNew($TourId, $TourType, 'RW',    pl_double_legs(array(array('70m-1', 70), array('70m-2', 70)), $isDouble));
    CreateDistanceNew($TourId, $TourType, 'RU24M', pl_double_legs(array(array('70m-1', 70), array('70m-2', 70)), $isDouble));
    CreateDistanceNew($TourId, $TourType, 'RU24W', pl_double_legs(array(array('70m-1', 70), array('70m-2', 70)), $isDouble));
    CreateDistanceNew($TourId, $TourType, 'RU21M', pl_double_legs(array(array('70m-1', 70), array('70m-2', 70)), $isDouble));
    CreateDistanceNew($TourId, $TourType, 'RU21W', pl_double_legs(array(array('70m-1', 70), array('70m-2', 70)), $isDouble));

    // Recurve — U18: 2 × 60 m (4 × 60 m when doubled)
    CreateDistanceNew($TourId, $TourType, 'RU18M', pl_double_legs(array(array('60m-1', 60), array('60m-2', 60)), $isDouble));
    CreateDistanceNew($TourId, $TourType, 'RU18W', pl_double_legs(array(array('60m-1', 60), array('60m-2', 60)), $isDouble));

    // Recurve — U15: 40 m + 20 m (40 m, 40 m, 20 m, 20 m when doubled)
    CreateDistanceNew($TourId, $TourType, 'RU15M', pl_double_legs(array(array('40m', 40), array('20m', 20)), $isDouble));
    CreateDistanceNew($TourId, $TourType, 'RU15W', pl_double_legs(array(array('40m', 40), array('20m', 20)), $isDouble));

    // Recurve — U12: 2 × 15 m (4 × 15 m when doubled); PU12: 2 × 10 m —
    // TourType 3 only, never 37 (pl_class_in_preset() only checks the
    // preset axis, not TourType eligibility — this guard is load-bearing,
    // not redundant with pl_standard_class_candidates() excluding these
    // classes from TourType 37's roster).
    if ($TourType == 3) {
        CreateDistanceNew($TourId, $TourType, 'RU12M', pl_double_legs(array(array('15m-1', 15), array('15m-2', 15)), $isDouble));
        CreateDistanceNew($TourId, $TourType, 'RU12W', pl_double_legs(array(array('15m-1', 15), array('15m-2', 15)), $isDouble));
        CreateDistanceNew($TourId, $TourType, 'RPU12M', pl_double_legs(array(array('10m-1', 10), array('10m-2', 10)), $isDouble));
        CreateDistanceNew($TourId, $TourType, 'RPU12W', pl_double_legs(array(array('10m-1', 10), array('10m-2', 10)), $isDouble));
    }

    // Compound — all: 2 × 50 m (4 × 50 m when doubled)
    CreateDistanceNew($TourId, $TourType, 'C%', pl_double_legs(array(array('50m-1', 50), array('50m-2', 50)), $isDouble));

    // Barebow — all: 2 × 50 m (4 × 50 m when doubled)
    CreateDistanceNew($TourId, $TourType, 'B%', pl_double_legs(array(array('50m-1', 50), array('50m-2', 50)), $isDouble));

    // Masters — R shortens by band; C/B constant 50m — TourType 3 only
    if ($TourType == 3) {
        CreateDistanceNew($TourId, $TourType, 'R40M', array(array('70m-1', 70), array('70m-2', 70)));
        CreateDistanceNew($TourId, $TourType, 'R40W', array(array('70m-1', 70), array('70m-2', 70)));
        CreateDistanceNew($TourId, $TourType, 'R50M', array(array('70m-1', 70), array('70m-2', 70)));
        CreateDistanceNew($TourId, $TourType, 'R50W', array(array('70m-1', 70), array('70m-2', 70)));
        CreateDistanceNew($TourId, $TourType, 'R60M', array(array('60m-1', 60), array('60m-2', 60)));
        CreateDistanceNew($TourId, $TourType, 'R60W', array(array('60m-1', 60), array('60m-2', 60)));
        CreateDistanceNew($TourId, $TourType, 'R70M', array(array('50m-1', 50), array('50m-2', 50)));
        CreateDistanceNew($TourId, $TourType, 'R70W', array(array('50m-1', 50), array('50m-2', 50)));
        CreateDistanceNew($TourId, $TourType, 'R80M', array(array('50m-1', 50), array('50m-2', 50)));
        CreateDistanceNew($TourId, $TourType, 'R80W', array(array('50m-1', 50), array('50m-2', 50)));
    }

    // ---- Individual Events (with elimination, except U15/U12/PU12) ----------------
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
        if (!pl_class_in_preset($cl, 'R', $preset)) continue;
        CreateEventNew($TourId, "R{$cl}", "Łuk klasyczny - {$PL_CLASS_NAMES[$cl]}", $i++, $optR);
    }
    $optR['EvDistance'] = 60;
    foreach (array('U18M', 'U18W') as $cl) {
        if (!pl_class_in_preset($cl, 'R', $preset)) continue;
        CreateEventNew($TourId, "R{$cl}", "Łuk klasyczny - {$PL_CLASS_NAMES[$cl]}", $i++, $optR);
    }

    // U15/U12/PU12 — no elimination
    $optRU15 = $optR;
    $optRU15['EvFinalFirstPhase'] = 0;
    $optRU15['EvDistance']        = 40;
    $optRU15['EvTargetSize']      = 122;
    foreach (array('U15M', 'U15W') as $cl) {
        if (!pl_class_in_preset($cl, 'R', $preset)) continue;
        CreateEventNew($TourId, "R{$cl}", "Łuk klasyczny - {$PL_CLASS_NAMES[$cl]}", $i++, $optRU15);
    }
    // TourType 3 only, never 37 — same reason as the distances above.
    if ($TourType == 3) {
        $optRU12 = $optR;
        $optRU12['EvFinalFirstPhase'] = 0;
        $optRU12['EvDistance']        = 15;
        $optRU12['EvTargetSize']      = 122;
        foreach (array('U12M', 'U12W') as $cl) {
            if (!pl_class_in_preset($cl, 'R', $preset)) continue;
            CreateEventNew($TourId, "R{$cl}", "Łuk klasyczny - {$PL_CLASS_NAMES[$cl]}", $i++, $optRU12);
        }
        $optRPU12 = $optR;
        $optRPU12['EvFinalFirstPhase'] = 0;
        $optRPU12['EvDistance']        = 10;
        $optRPU12['EvTargetSize']      = 122;
        foreach (array('PU12M', 'PU12W') as $cl) {
            if (!pl_class_in_preset($cl, 'R', $preset)) continue;
            CreateEventNew($TourId, "R{$cl}", "Łuk klasyczny - {$PL_CLASS_NAMES[$cl]}", $i++, $optRPU12);
        }
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
    foreach (array('M', 'W', 'U21M', 'U21W', 'U18M', 'U18W') as $cl) {
        if (!pl_class_in_preset($cl, 'C', $preset)) continue;
        CreateEventNew($TourId, "C{$cl}", "Łuk bloczkowy - {$PL_CLASS_NAMES[$cl]}", $i++, $optC);
    }

    // U15 Compound — no elimination
    $optCU15 = $optC;
    $optCU15['EvFinalFirstPhase'] = 0;
    foreach (array('U15M', 'U15W') as $cl) {
        if (!pl_class_in_preset($cl, 'C', $preset)) continue;
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
        if (!pl_class_in_preset($cl, 'B', $preset)) continue;
        CreateEventNew($TourId, "B{$cl}", "Łuk barebow - {$PL_CLASS_NAMES[$cl]}", $i++, $optB);
    }

    // --- Masters individual (elimination for every band, every division) ---
    if ($TourType == 3) {
        $mastersR = $optR;
        $mastersR['EvFinalFirstPhase'] = $indFirstPhase;
        foreach (array('40M' => 70, '40W' => 70, '50M' => 70, '50W' => 70, '60M' => 60, '60W' => 60, '70M' => 50, '70W' => 50, '80M' => 50, '80W' => 50) as $cl => $dist) {
            if (!pl_class_in_preset($cl, 'R', $preset)) continue;
            $mastersR['EvDistance'] = $dist;
            CreateEventNew($TourId, "R{$cl}", "Łuk klasyczny - {$PL_CLASS_NAMES[$cl]}", $i++, $mastersR);
        }

        $mastersC = $optC;
        $mastersC['EvFinalFirstPhase'] = $indFirstPhase;
        foreach (array('40M', '40W', '50M', '50W', '60M', '60W') as $cl) {
            if (!pl_class_in_preset($cl, 'C', $preset)) continue;
            $mastersC['EvFinalTargetType'] = TGT_OUT_5_big10;
            CreateEventNew($TourId, "C{$cl}", "Łuk bloczkowy - {$PL_CLASS_NAMES[$cl]}", $i++, $mastersC);
        }
        foreach (array('70M', '70W', '80M', '80W') as $cl) {
            if (!pl_class_in_preset($cl, 'C', $preset)) continue;
            $mastersC['EvFinalTargetType'] = TGT_OUT_FULL;
            CreateEventNew($TourId, "C{$cl}", "Łuk bloczkowy - {$PL_CLASS_NAMES[$cl]}", $i++, $mastersC);
        }

        $mastersB = $optB;
        $mastersB['EvFinalFirstPhase'] = $indFirstPhase;
        foreach (array('40M', '40W', '50M', '50W', '60M', '60W', '70M', '70W', '80M', '80W') as $cl) {
            if (!pl_class_in_preset($cl, 'B', $preset)) continue;
            CreateEventNew($TourId, "B{$cl}", "Łuk barebow - {$PL_CLASS_NAMES[$cl]}", $i++, $mastersB);
        }
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
        if (!pl_class_in_preset($cl, 'R', $preset)) continue;
        CreateEventNew($TourId, "R{$cl}", "Łuk klasyczny - {$PL_CLASS_NAMES[$cl]} zespoły", $i++, $optRT);
    }
    $optRT['EvDistance'] = 60;
    foreach (array('U18M', 'U18W') as $cl) {
        if (!pl_class_in_preset($cl, 'R', $preset)) continue;
        CreateEventNew($TourId, "R{$cl}", "Łuk klasyczny - {$PL_CLASS_NAMES[$cl]} zespoły", $i++, $optRT);
    }
    // U15/U12/PU12 Recurve team — no elimination
    $optRTU15 = $optRT;
    $optRTU15['EvFinalFirstPhase'] = 0;
    $optRTU15['EvDistance']        = 40;
    $optRTU15['EvTargetSize']      = 122;
    foreach (array('U15M', 'U15W') as $cl) {
        if (!pl_class_in_preset($cl, 'R', $preset)) continue;
        CreateEventNew($TourId, "R{$cl}", "Łuk klasyczny - {$PL_CLASS_NAMES[$cl]} zespoły", $i++, $optRTU15);
    }
    // TourType 3 only, never 37 — same reason as the individual events above.
    if ($TourType == 3) {
        $optRTU12 = $optRTU15;
        $optRTU12['EvDistance'] = 15;
        foreach (array('U12M', 'U12W') as $cl) {
            if (!pl_class_in_preset($cl, 'R', $preset)) continue;
            CreateEventNew($TourId, "R{$cl}", "Łuk klasyczny - {$PL_CLASS_NAMES[$cl]} zespoły", $i++, $optRTU12);
        }
        $optRTPU12 = $optRTU15;
        $optRTPU12['EvDistance'] = 10;
        foreach (array('PU12M', 'PU12W') as $cl) {
            if (!pl_class_in_preset($cl, 'R', $preset)) continue;
            CreateEventNew($TourId, "R{$cl}", "Łuk klasyczny - {$PL_CLASS_NAMES[$cl]} zespoły", $i++, $optRTPU12);
        }
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
    foreach (array('M', 'W', 'U21M', 'U21W', 'U18M', 'U18W') as $cl) {
        if (!pl_class_in_preset($cl, 'C', $preset)) continue;
        CreateEventNew($TourId, "C{$cl}", "Łuk bloczkowy - {$PL_CLASS_NAMES[$cl]} zespoły", $i++, $optCT);
    }
    // U15 Compound team — no elimination
    $optCTU15 = $optCT;
    $optCTU15['EvFinalFirstPhase'] = 0;
    foreach (array('U15M', 'U15W') as $cl) {
        if (!pl_class_in_preset($cl, 'C', $preset)) continue;
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
        if (!pl_class_in_preset($cl, 'B', $preset)) continue;
        CreateEventNew($TourId, "B{$cl}", "Łuk barebow - {$PL_CLASS_NAMES[$cl]} zespoły", $i++, $optBT);
    }

    // --- Masters team (elimination for every band, every division) ---
    if ($TourType == 3) {
        $mastersRT = $optRT;
        $mastersRT['EvFinalFirstPhase'] = $teamFirstPhase;
        foreach (array('40M' => 70, '40W' => 70, '50M' => 70, '50W' => 70, '60M' => 60, '60W' => 60, '70M' => 50, '70W' => 50, '80M' => 50, '80W' => 50) as $cl => $dist) {
            if (!pl_class_in_preset($cl, 'R', $preset)) continue;
            $mastersRT['EvDistance'] = $dist;
            CreateEventNew($TourId, "R{$cl}", "Łuk klasyczny - {$PL_CLASS_NAMES[$cl]} zespoły", $i++, $mastersRT);
        }

        $mastersCT = $optCT;
        $mastersCT['EvFinalFirstPhase'] = $teamFirstPhase;
        foreach (array('40M', '40W', '50M', '50W', '60M', '60W') as $cl) {
            if (!pl_class_in_preset($cl, 'C', $preset)) continue;
            $mastersCT['EvFinalTargetType'] = TGT_OUT_5_big10;
            CreateEventNew($TourId, "C{$cl}", "Łuk bloczkowy - {$PL_CLASS_NAMES[$cl]} zespoły", $i++, $mastersCT);
        }
        foreach (array('70M', '70W', '80M', '80W') as $cl) {
            if (!pl_class_in_preset($cl, 'C', $preset)) continue;
            $mastersCT['EvFinalTargetType'] = TGT_OUT_FULL;
            CreateEventNew($TourId, "C{$cl}", "Łuk bloczkowy - {$PL_CLASS_NAMES[$cl]} zespoły", $i++, $mastersCT);
        }

        $mastersBT = $optBT;
        $mastersBT['EvFinalFirstPhase'] = $teamFirstPhase;
        foreach (array('40M', '40W', '50M', '50W', '60M', '60W', '70M', '70W', '80M', '80W') as $cl) {
            if (!pl_class_in_preset($cl, 'B', $preset)) continue;
            CreateEventNew($TourId, "B{$cl}", "Łuk barebow - {$PL_CLASS_NAMES[$cl]} zespoły", $i++, $mastersBT);
        }
    }

    // ---- Mixed Team Events -----------------------------------------------------
    // No U12/PU12/Masters mixed-team events (see design.md).
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
        if (!pl_class_in_preset("{$age}M", 'R', $preset) || !pl_class_in_preset("{$age}W", 'R', $preset)) continue;
        CreateEventNew($TourId, "R{$age}X",
            "Łuk klasyczny - {$PL_MIXED_CLASS_NAMES[$age]} zespoły mieszane", $i++, $optRMX);
    }
    // U18: 60 m
    $optRMX['EvDistance'] = 60;
    if (pl_class_in_preset('U18M', 'R', $preset) && pl_class_in_preset('U18W', 'R', $preset)) {
        CreateEventNew($TourId, 'RU18X',
            "Łuk klasyczny - {$PL_MIXED_CLASS_NAMES['U18']} zespoły mieszane", $i++, $optRMX);
    }
    // U15: 40 m, no elimination
    $optRMXU15 = $optRMX;
    $optRMXU15['EvFinalFirstPhase'] = 0;
    $optRMXU15['EvDistance']        = 40;
    $optRMXU15['EvTargetSize']      = 122;
    if (pl_class_in_preset('U15M', 'R', $preset) && pl_class_in_preset('U15W', 'R', $preset)) {
        CreateEventNew($TourId, 'RU15X',
            "Łuk klasyczny - {$PL_MIXED_CLASS_NAMES['U15']} zespoły mieszane", $i++, $optRMXU15);
    }

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
    foreach (array('', 'U21') as $age) {
        if (!pl_class_in_preset("{$age}M", 'C', $preset) || !pl_class_in_preset("{$age}W", 'C', $preset)) continue;
        CreateEventNew($TourId, "C{$age}X",
            "Łuk bloczkowy - {$PL_MIXED_CLASS_NAMES[$age]} zespoły mieszane", $i++, $optCMX);
    }
    if (pl_class_in_preset('U18M', 'C', $preset) && pl_class_in_preset('U18W', 'C', $preset)) {
        CreateEventNew($TourId, 'CU18X',
            "Łuk bloczkowy - {$PL_MIXED_CLASS_NAMES['U18']} zespoły mieszane", $i++, $optCMX);
    }
    // U15 Compound — no elimination
    $optCMXU15 = $optCMX;
    $optCMXU15['EvFinalFirstPhase'] = 0;
    if (pl_class_in_preset('U15M', 'C', $preset) && pl_class_in_preset('U15W', 'C', $preset)) {
        CreateEventNew($TourId, 'CU15X',
            "Łuk bloczkowy - {$PL_MIXED_CLASS_NAMES['U15']} zespoły mieszane", $i++, $optCMXU15);
    }

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
        if (!pl_class_in_preset("{$age}M", 'B', $preset) || !pl_class_in_preset("{$age}W", 'B', $preset)) continue;
        CreateEventNew($TourId, "B{$age}X",
            "Łuk barebow - {$PL_MIXED_CLASS_NAMES[$age]} zespoły mieszane", $i++, $optBMX);
    }

    // ---- Target Faces ----------------------------------------------------------
    $i = 1;
    // Recurve (incl. Barebow): 122 cm full face
    CreateTargetFace($TourId, $i++, 'Łuk klasyczny/barebow domyślna', 'REG-^[RB]', '1',
        TGT_OUT_FULL, 122, TGT_OUT_FULL, 122);
    // Compound: 80 cm 6-ring face (broad default; Masters 70+/80+ narrows
    // this below with their own full-face definition, same "narrower face
    // inserted later overrides the broad one" pattern U15's face already
    // relies on above — no lookahead/lookaround, only basic alternation, to
    // stay compatible with whatever engine evaluates TfRegExp).
    CreateTargetFace($TourId, $i++, 'Łuk bloczkowy domyślna', 'C%', '1',
        TGT_OUT_5_big10, 80, TGT_OUT_5_big10, 80);
    // Recurve U15: 122 cm for 40 m, 80 cm for 20 m
    CreateTargetFace($TourId, $i++, 'Łuk klasyczny Młodzik (40 m / 20 m)', 'RU15%', '1',
        TGT_OUT_FULL, 122, TGT_OUT_FULL, 80);
    // Recurve U12 / PU12: 122 cm full face
    CreateTargetFace($TourId, $i++, 'Łuk klasyczny Dziecko (U12/łuk popularny)', 'REG-^R(P?U12)', '1',
        TGT_OUT_FULL, 122, TGT_OUT_FULL, 122);
    // Compound Masters 70+/80+: 80 cm full face (not the 6-ring face) —
    // narrower than 'C%' above, so it overrides it for these four classes.
    CreateTargetFace($TourId, $i++, 'Łuk bloczkowy Master 70+/80+', 'REG-^C(70|80)', '1',
        TGT_OUT_FULL, 80, TGT_OUT_FULL, 80);

    // ---- Event-class bindings, Finals, Distance Info ---------------------------
    InsertStandardEvents($TourId, $TourType, $preset);
    CreateFinals($TourId);
    CreateDistanceInformation($TourId, $DistanceInfoArray, 20, 4);
}

// ---------------------------------------------------------------------------
// Shared body for the Children's Round (TourType 16, U12-only). $TourId and
// $PL_CLASS_NAMES are explicit parameters for the same reason as
// pl_setup_70m_family() above — see its doc comment. No preset: this
// TourType has one fixed, non-selectable configuration (U12-only, enforced
// structurally by pl_standard_class_candidates(), not by a passed-in filter).
// ---------------------------------------------------------------------------
function pl_setup_kids_round($TourId, $TourType, $PL_CLASS_NAMES) {
    global $tourDetGolds, $tourDetXNine, $tourDetGoldsChars, $tourDetXNineChars;

    $DistanceInfoArray = array(array(6, 3), array(6, 3), array(6, 3), array(6, 3));

    // ---- Divisions & Classes ---------------------------------------------------
    CreateStandardDivisions($TourId, $TourType);
    CreateStandardClasses($TourId, $TourType);  // U12M/U12W only, see pl_standard_class_candidates()

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
    CreateTargetFace($TourId, $i++, 'Łuk klasyczny Dziecko 25m/20m/15m/10m', 'RU12%', '1',
        TGT_OUT_FULL, 122, TGT_OUT_FULL, 122, TGT_OUT_FULL, 80, TGT_OUT_FULL, 80);

    // ---- Event-class bindings, Finals, Distance Info ---------------------------
    InsertStandardEvents($TourId, $TourType);
    CreateFinals($TourId);
    CreateDistanceInformation($TourId, $DistanceInfoArray, 20, 4);
}

// ---------------------------------------------------------------------------
// Shared body for the 1440 Round (TourType 1). No U15/U12/PU12/Masters, no
// Barebow division (see pl_standard_class_candidates()/CreateStandardDivisions()
// — both already exclude B for TourType 1) and no mixed team events (this
// round never had any). Compound's qualification distances mirror Recurve's
// per-class table exactly; its target face stays the 80cm 6-ring face
// unchanged — only the *distances* mirror Recurve, not the scoring face.
// $TourId/$PL_CLASS_NAMES are explicit parameters for the same scope reason
// as pl_setup_70m_family() above.
// ---------------------------------------------------------------------------
function pl_setup_1440($TourId, $TourType, $PL_CLASS_NAMES, $preset = array()) {
    global $tourDetGolds, $tourDetXNine, $tourDetGoldsChars, $tourDetXNineChars;

    $DistanceInfoArray = array(array(6, 6), array(6, 6), array(6, 6), array(6, 6));

    // ---- Divisions & Classes ----------------------------------------------------
    CreateStandardDivisions($TourId, $TourType, $preset);
    CreateStandardClasses($TourId, $TourType, $preset);

    // ---- Distances ---------------------------------------------------------------
    // Shared by Recurve and Compound — Compound mirrors Recurve's per-class
    // distances exactly on this TourType.
    $groupA = array(array('90m', 90), array('70m', 70), array('50m', 50), array('30m', 30)); // M, U24M, U21M
    $groupB = array(array('70m', 70), array('60m', 60), array('50m', 50), array('30m', 30)); // W, U24W, U21W, U18M
    $groupC = array(array('60m', 60), array('50m', 50), array('40m', 40), array('30m', 30)); // U18W

    foreach (array('R', 'C') as $div) {
        foreach (array('M', 'U24M', 'U21M') as $cl) {
            if (pl_class_in_preset($cl, $div, $preset)) CreateDistanceNew($TourId, $TourType, "{$div}{$cl}", $groupA);
        }
        foreach (array('W', 'U24W', 'U21W', 'U18M') as $cl) {
            if (pl_class_in_preset($cl, $div, $preset)) CreateDistanceNew($TourId, $TourType, "{$div}{$cl}", $groupB);
        }
        if (pl_class_in_preset('U18W', $div, $preset)) CreateDistanceNew($TourId, $TourType, "{$div}U18W", $groupC);
    }

    // ---- Individual Events ---------------------------------------------------
    $indFirstPhase  = 48;  // top 104
    $teamFirstPhase = 12;  // top 24
    $i = 1;

    // Recurve individual
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
    foreach (array('M', 'W', 'U24M', 'U24W', 'U21M', 'U21W', 'U18M', 'U18W') as $cl) {
        if (!pl_class_in_preset($cl, 'R', $preset)) continue;
        CreateEventNew($TourId, "R{$cl}", "Łuk klasyczny - {$PL_CLASS_NAMES[$cl]}", $i++, $optR);
    }

    // Compound individual — same distances as Recurve, own scoring face
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
    foreach (array('M', 'W', 'U21M', 'U21W', 'U18M', 'U18W') as $cl) {
        if (!pl_class_in_preset($cl, 'C', $preset)) continue;
        CreateEventNew($TourId, "C{$cl}", "Łuk bloczkowy - {$PL_CLASS_NAMES[$cl]}", $i++, $optC);
    }

    // ---- Team Events ----------------------------------------------------------
    $i = 1;

    $optRT = array(
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
    );
    foreach (array('M', 'W', 'U24M', 'U24W', 'U21M', 'U21W', 'U18M', 'U18W') as $cl) {
        if (!pl_class_in_preset($cl, 'R', $preset)) continue;
        CreateEventNew($TourId, "R{$cl}", "Łuk klasyczny - {$PL_CLASS_NAMES[$cl]} zespoły", $i++, $optRT);
    }

    $optCT = $optRT;
    $optCT['EvFinalTargetType'] = TGT_OUT_5_big10;
    $optCT['EvTargetSize']      = 80;
    $optCT['EvMatchMode']       = 0;
    foreach (array('M', 'W', 'U21M', 'U21W', 'U18M', 'U18W') as $cl) {
        if (!pl_class_in_preset($cl, 'C', $preset)) continue;
        CreateEventNew($TourId, "C{$cl}", "Łuk bloczkowy - {$PL_CLASS_NAMES[$cl]} zespoły", $i++, $optCT);
    }

    // ---- Target Faces ----------------------------------------------------------
    $i = 1;
    // Recurve: 122 cm for distances 1-2, 80 cm for distances 3-4
    CreateTargetFace($TourId, $i++, 'Łuk klasyczny domyślna', 'R%', '1',
        TGT_OUT_FULL, 122, TGT_OUT_FULL, 122, TGT_OUT_FULL, 80, TGT_OUT_FULL, 80);
    // Compound: 80 cm 6-ring (TGT_OUT_5_big10) for all 4 distances — unchanged
    // face, only the qualification distances now mirror Recurve's.
    CreateTargetFace($TourId, $i++, 'Łuk bloczkowy domyślna', 'C%', '1',
        TGT_OUT_5_big10, 80, TGT_OUT_5_big10, 80, TGT_OUT_5_big10, 80, TGT_OUT_5_big10, 80);

    // ---- Event-class bindings, Finals, Distance Info, Tour Update --------------
    InsertStandardEvents($TourId, $TourType, $preset);
    CreateFinals($TourId);
    CreateDistanceInformation($TourId, $DistanceInfoArray, 20, 4);
}

// ---------------------------------------------------------------------------
// Shared body for the Indoor Round (TourType 6). $TourId/$PL_CLASS_NAMES/
// $PL_MIXED_CLASS_NAMES are explicit parameters for the same scope reason as
// pl_setup_70m_family() above. U12 shoots at 15 m (80 cm face); PU12 shoots
// at 10 m (122 cm face) — both Recurve only, no elimination, no mixed team.
// ---------------------------------------------------------------------------
function pl_setup_indoor($TourId, $TourType, $PL_CLASS_NAMES, $PL_MIXED_CLASS_NAMES, $preset = array()) {
    global $tourDetGolds, $tourDetXNine, $tourDetGoldsChars, $tourDetXNineChars;

    $DistanceInfoArray = array(array(10, 3), array(10, 3));

    // ---- Divisions & Classes ----------------------------------------------------
    CreateStandardDivisions($TourId, $TourType, $preset);
    CreateStandardClasses($TourId, $TourType, $preset);

    // ---- Distances ---------------------------------------------------------------
    foreach (array('RM', 'RW', 'RU24M', 'RU24W', 'RU21M', 'RU21W', 'RU18M', 'RU18W', 'RU15M', 'RU15W') as $code) {
        $cl = substr($code, 1);
        if (!pl_class_in_preset($cl, 'R', $preset)) continue;
        CreateDistanceNew($TourId, $TourType, $code, array(array('18m-1', 18), array('18m-2', 18)));
    }
    foreach (array('U12M', 'U12W') as $cl) {
        if (!pl_class_in_preset($cl, 'R', $preset)) continue;
        CreateDistanceNew($TourId, $TourType, "R{$cl}", array(array('15m-1', 15), array('15m-2', 15)));
    }
    foreach (array('PU12M', 'PU12W') as $cl) {
        if (!pl_class_in_preset($cl, 'R', $preset)) continue;
        CreateDistanceNew($TourId, $TourType, "R{$cl}", array(array('10m-1', 10), array('10m-2', 10)));
    }
    CreateDistanceNew($TourId, $TourType, 'C%', array(array('18m-1', 18), array('18m-2', 18)));
    CreateDistanceNew($TourId, $TourType, 'B%', array(array('18m-1', 18), array('18m-2', 18)));

    // ---- Individual Events -----------------------------------------------------
    $indFirstPhase  = 16;  // top 32
    $teamFirstPhase = 8;   // top 16
    $i = 1;

    // --- Recurve individual (set system) ---
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
    foreach (array('M', 'W', 'U24M', 'U24W', 'U21M', 'U21W') as $cl) {
        if (!pl_class_in_preset($cl, 'R', $preset)) continue;
        CreateEventNew($TourId, "R{$cl}", "Łuk klasyczny - {$PL_CLASS_NAMES[$cl]}", $i++, $optR);
    }

    $optRU18 = $optR;
    $optRU18['EvFinalTargetType'] = TGT_IND_1_big10;
    foreach (array('U18M', 'U18W') as $cl) {
        if (!pl_class_in_preset($cl, 'R', $preset)) continue;
        CreateEventNew($TourId, "R{$cl}", "Łuk klasyczny - {$PL_CLASS_NAMES[$cl]}", $i++, $optRU18);
    }

    $optRU15 = $optR;
    $optRU15['EvFinalFirstPhase'] = 0;
    $optRU15['EvFinalTargetType'] = TGT_IND_1_big10;
    $optRU15['EvTargetSize']      = 60;
    foreach (array('U15M', 'U15W') as $cl) {
        if (!pl_class_in_preset($cl, 'R', $preset)) continue;
        CreateEventNew($TourId, "R{$cl}", "Łuk klasyczny - {$PL_CLASS_NAMES[$cl]}", $i++, $optRU15);
    }

    $optRU12 = $optR;
    $optRU12['EvFinalFirstPhase'] = 0;
    $optRU12['EvFinalTargetType'] = TGT_IND_1_big10;
    $optRU12['EvTargetSize']      = 80;
    $optRU12['EvDistance']        = 15;
    foreach (array('U12M', 'U12W') as $cl) {
        if (!pl_class_in_preset($cl, 'R', $preset)) continue;
        CreateEventNew($TourId, "R{$cl}", "Łuk klasyczny - {$PL_CLASS_NAMES[$cl]}", $i++, $optRU12);
    }

    // PU12 — Recurve only, 10 m, 122 cm full face, no elimination
    $optRPU12 = $optR;
    $optRPU12['EvFinalFirstPhase'] = 0;
    $optRPU12['EvFinalTargetType'] = TGT_OUT_FULL;
    $optRPU12['EvTargetSize']      = 122;
    $optRPU12['EvDistance']        = 10;
    foreach (array('PU12M', 'PU12W') as $cl) {
        if (!pl_class_in_preset($cl, 'R', $preset)) continue;
        CreateEventNew($TourId, "R{$cl}", "Łuk klasyczny - {$PL_CLASS_NAMES[$cl]}", $i++, $optRPU12);
    }

    // --- Compound individual (cumulative) ---
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
    foreach (array('M', 'W', 'U21M', 'U21W') as $cl) {
        if (!pl_class_in_preset($cl, 'C', $preset)) continue;
        CreateEventNew($TourId, "C{$cl}", "Łuk bloczkowy - {$PL_CLASS_NAMES[$cl]}", $i++, $optC);
    }

    $optCU18 = $optC;
    $optCU18['EvFinalTargetType'] = TGT_IND_1_small10;
    foreach (array('U18M', 'U18W') as $cl) {
        if (!pl_class_in_preset($cl, 'C', $preset)) continue;
        CreateEventNew($TourId, "C{$cl}", "Łuk bloczkowy - {$PL_CLASS_NAMES[$cl]}", $i++, $optCU18);
    }

    $optCU15 = $optC;
    $optCU15['EvFinalFirstPhase'] = 0;
    $optCU15['EvFinalTargetType'] = TGT_IND_1_small10;
    $optCU15['EvTargetSize']      = 60;
    foreach (array('U15M', 'U15W') as $cl) {
        if (!pl_class_in_preset($cl, 'C', $preset)) continue;
        CreateEventNew($TourId, "C{$cl}", "Łuk bloczkowy - {$PL_CLASS_NAMES[$cl]}", $i++, $optCU15);
    }

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
        if (!pl_class_in_preset($cl, 'B', $preset)) continue;
        CreateEventNew($TourId, "B{$cl}", "Łuk barebow - {$PL_CLASS_NAMES[$cl]}", $i++, $optB);
    }

    // ---- Team Events -----------------------------------------------------------
    $i = 1;

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
    foreach (array('M', 'W', 'U24M', 'U24W', 'U21M', 'U21W', 'U18M', 'U18W') as $cl) {
        if (!pl_class_in_preset($cl, 'R', $preset)) continue;
        CreateEventNew($TourId, "R{$cl}", "Łuk klasyczny - {$PL_CLASS_NAMES[$cl]} zespoły", $i++, $optRT);
    }
    $optRTU15 = $optRT;
    $optRTU15['EvFinalFirstPhase'] = 0;
    foreach (array('U15M', 'U15W', 'U12M', 'U12W') as $cl) {
        if (!pl_class_in_preset($cl, 'R', $preset)) continue;
        CreateEventNew($TourId, "R{$cl}", "Łuk klasyczny - {$PL_CLASS_NAMES[$cl]} zespoły", $i++, $optRTU15);
    }
    $optRTPU12 = $optRTU15;
    $optRTPU12['EvFinalTargetType'] = TGT_OUT_FULL;
    $optRTPU12['EvTargetSize']      = 122;
    $optRTPU12['EvDistance']        = 10;
    foreach (array('PU12M', 'PU12W') as $cl) {
        if (!pl_class_in_preset($cl, 'R', $preset)) continue;
        CreateEventNew($TourId, "R{$cl}", "Łuk klasyczny - {$PL_CLASS_NAMES[$cl]} zespoły", $i++, $optRTPU12);
    }

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
    foreach (array('M', 'W', 'U21M', 'U21W', 'U18M', 'U18W') as $cl) {
        if (!pl_class_in_preset($cl, 'C', $preset)) continue;
        CreateEventNew($TourId, "C{$cl}", "Łuk bloczkowy - {$PL_CLASS_NAMES[$cl]} zespoły", $i++, $optCT);
    }
    $optCTU15 = $optCT;
    $optCTU15['EvFinalFirstPhase'] = 0;
    foreach (array('U15M', 'U15W') as $cl) {
        if (!pl_class_in_preset($cl, 'C', $preset)) continue;
        CreateEventNew($TourId, "C{$cl}", "Łuk bloczkowy - {$PL_CLASS_NAMES[$cl]} zespoły", $i++, $optCTU15);
    }

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
        if (!pl_class_in_preset($cl, 'B', $preset)) continue;
        CreateEventNew($TourId, "B{$cl}", "Łuk barebow - {$PL_CLASS_NAMES[$cl]} zespoły", $i++, $optBT);
    }

    // ---- Mixed Team Events -----------------------------------------------------
    // No U12/PU12 mixed team events.
    $mixFirstPhase = 12;  // top 24 (1/12 finału)
    $i = 1;

    $optRMX = array(
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
    );
    foreach (array('', 'U24', 'U21') as $age) {
        if (!pl_class_in_preset("{$age}M", 'R', $preset) || !pl_class_in_preset("{$age}W", 'R', $preset)) continue;
        CreateEventNew($TourId, "R{$age}X",
            "Łuk klasyczny - {$PL_MIXED_CLASS_NAMES[$age]} zespoły mieszane", $i++, $optRMX);
    }
    $optRMX['EvFinalTargetType'] = TGT_IND_1_big10;
    if (pl_class_in_preset('U18M', 'R', $preset) && pl_class_in_preset('U18W', 'R', $preset)) {
        CreateEventNew($TourId, 'RU18X',
            "Łuk klasyczny - {$PL_MIXED_CLASS_NAMES['U18']} zespoły mieszane", $i++, $optRMX);
    }
    $optRMXU15 = $optRMX;
    $optRMXU15['EvFinalFirstPhase'] = 0;
    $optRMXU15['EvTargetSize']      = 60;
    if (pl_class_in_preset('U15M', 'R', $preset) && pl_class_in_preset('U15W', 'R', $preset)) {
        CreateEventNew($TourId, 'RU15X',
            "Łuk klasyczny - {$PL_MIXED_CLASS_NAMES['U15']} zespoły mieszane", $i++, $optRMXU15);
    }

    $optCMX = array(
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
    );
    foreach (array('', 'U21') as $age) {
        if (!pl_class_in_preset("{$age}M", 'C', $preset) || !pl_class_in_preset("{$age}W", 'C', $preset)) continue;
        CreateEventNew($TourId, "C{$age}X",
            "Łuk bloczkowy - {$PL_MIXED_CLASS_NAMES[$age]} zespoły mieszane", $i++, $optCMX);
    }
    $optCMX['EvFinalTargetType'] = TGT_IND_1_small10;
    if (pl_class_in_preset('U18M', 'C', $preset) && pl_class_in_preset('U18W', 'C', $preset)) {
        CreateEventNew($TourId, 'CU18X',
            "Łuk bloczkowy - {$PL_MIXED_CLASS_NAMES['U18']} zespoły mieszane", $i++, $optCMX);
    }
    $optCMXU15 = $optCMX;
    $optCMXU15['EvFinalFirstPhase'] = 0;
    $optCMXU15['EvTargetSize']      = 60;
    if (pl_class_in_preset('U15M', 'C', $preset) && pl_class_in_preset('U15W', 'C', $preset)) {
        CreateEventNew($TourId, 'CU15X',
            "Łuk bloczkowy - {$PL_MIXED_CLASS_NAMES['U15']} zespoły mieszane", $i++, $optCMXU15);
    }

    $optBMX = array(
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
    );
    foreach (array('', 'U21', 'U18') as $age) {
        if (!pl_class_in_preset("{$age}M", 'B', $preset) || !pl_class_in_preset("{$age}W", 'B', $preset)) continue;
        CreateEventNew($TourId, "B{$age}X",
            "Łuk barebow - {$PL_MIXED_CLASS_NAMES[$age]} zespoły mieszane", $i++, $optBMX);
    }

    // ---- Target Faces ----------------------------------------------------------
    $i = 1;
    CreateTargetFace($TourId, $i++, 'Łuk klasyczny Triple 40 cm (Senior/U24/U21)',
        'REG-^R(M|W|U24|U21)', '1',
        TGT_IND_6_big10, 40, TGT_IND_6_big10, 40);
    CreateTargetFace($TourId, $i++, 'Łuk klasyczny Single 40 cm (U18)',
        'REG-^RU18', '1',
        TGT_IND_1_big10, 40, TGT_IND_1_big10, 40);
    CreateTargetFace($TourId, $i++, 'Łuk klasyczny 60 cm (U15)',
        'RU15%', '1',
        TGT_IND_1_big10, 60, TGT_IND_1_big10, 60);
    CreateTargetFace($TourId, $i++, 'Łuk klasyczny 80 cm (U12)',
        'RU12%', '1',
        TGT_IND_1_big10, 80, TGT_IND_1_big10, 80);
    CreateTargetFace($TourId, $i++, 'Łuk klasyczny 122 cm (łuk popularny)',
        'RPU12%', '1',
        TGT_OUT_FULL, 122, TGT_OUT_FULL, 122);
    CreateTargetFace($TourId, $i++, 'Łuk bloczkowy Triple 40 cm (Senior/U21)',
        'REG-^C(M|W|U21)', '1',
        TGT_IND_6_small10, 40, TGT_IND_6_small10, 40);
    CreateTargetFace($TourId, $i++, 'Łuk bloczkowy Single 40 cm (U18)',
        'REG-^CU18', '1',
        TGT_IND_1_small10, 40, TGT_IND_1_small10, 40);
    CreateTargetFace($TourId, $i++, 'Łuk bloczkowy 60 cm (U15)',
        'CU15%', '1',
        TGT_IND_1_small10, 60, TGT_IND_1_small10, 60);
    CreateTargetFace($TourId, $i++, 'Łuk barebow Single 40 cm',
        'B%', '1',
        TGT_IND_1_big10, 40, TGT_IND_1_big10, 40);

    // ---- Event-class bindings, Finals, Distance Info, Tour Update --------------
    InsertStandardEvents($TourId, $TourType, $preset);
    CreateFinals($TourId);
    CreateDistanceInformation($TourId, $DistanceInfoArray, 20, 4);
}
