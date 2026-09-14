<?php

namespace PL\Tests;

if (PHP_SAPI !== 'cli') {
    exit;
}

require_once __DIR__ . '/lib.php';

final class LibTest extends \PlTestCase
{
    // --- CreateStandardDivisions ---------------------------------------------

    public function testCreateStandardDivisionsCreatesRecurveCompoundBarebow(): void
    {
        \CreateStandardDivisions(7, 1);

        $calls = \CallLog::calls('CreateDivision');
        $this->assertCount(3, $calls);
        $this->assertSame([7, 1, 'R', 'Łuk klasyczny', 1, 'R', 'R'], $calls[0]);
        $this->assertSame([7, 2, 'C', 'Łuk bloczkowy', 1, 'C', 'C'], $calls[1]);
        $this->assertSame([7, 3, 'B', 'Łuk barebow', 1, 'B', 'B'], $calls[2]);
    }

    // --- CreateStandardClasses ------------------------------------------------

    public function testCreateStandardClassesType1HasNoU15OrU12(): void
    {
        \CreateStandardClasses(7, 1);

        $this->assertCount(10, \CallLog::calls('CreateClass'));
        $this->assertCount(0, \CallLog::callsMatching('CreateClass', fn ($a) => str_starts_with($a[5], 'U15')));
        $this->assertCount(0, \CallLog::callsMatching('CreateClass', fn ($a) => str_starts_with($a[5], 'U12')));
    }

    public function testCreateStandardClassesType3AddsU15Only(): void
    {
        \CreateStandardClasses(7, 3);

        $this->assertCount(12, \CallLog::calls('CreateClass'));
        $this->assertCount(2, \CallLog::callsMatching('CreateClass', fn ($a) => str_starts_with($a[5], 'U15')));
        $this->assertCount(0, \CallLog::callsMatching('CreateClass', fn ($a) => str_starts_with($a[5], 'U12')));
    }

    public function testCreateStandardClassesType6AddsU15AndU12(): void
    {
        \CreateStandardClasses(7, 6);

        $this->assertCount(14, \CallLog::calls('CreateClass'));
        $this->assertCount(2, \CallLog::callsMatching('CreateClass', fn ($a) => str_starts_with($a[5], 'U15')));
        $this->assertCount(2, \CallLog::callsMatching('CreateClass', fn ($a) => str_starts_with($a[5], 'U12')));
    }

    public function testCreateStandardClassesU24IsRecurveOnly(): void
    {
        \CreateStandardClasses(7, 1);

        $u24 = \CallLog::callsMatching('CreateClass', fn ($a) => $a[5] === 'U24M');
        $this->assertCount(1, $u24);
        $this->assertSame('R', $u24[0][9]);
    }

    public function testCreateStandardClassesIndicesAreSequential(): void
    {
        \CreateStandardClasses(7, 1);

        $indices = array_column(\CallLog::calls('CreateClass'), 1);
        $this->assertSame(range(1, 10), $indices);
    }

    // --- InsertStandardEvents -------------------------------------------------

    public function testInsertStandardEventsType1HasNoU15OrU12(): void
    {
        \InsertStandardEvents(7, 1);

        $this->assertCount(0, \CallLog::callsMatching('InsertClassEvent', fn ($a) => str_contains($a[3], 'U15')));
        $this->assertCount(0, \CallLog::callsMatching('InsertClassEvent', fn ($a) => str_contains($a[3], 'U12')));
    }

    public function testInsertStandardEventsType3AddsU15ForRecurveAndCompoundOnly(): void
    {
        \InsertStandardEvents(7, 3);

        $this->assertGreaterThan(0, count(\CallLog::callsMatching('InsertClassEvent', fn ($a) => $a[3] === 'RU15M')));
        $this->assertGreaterThan(0, count(\CallLog::callsMatching('InsertClassEvent', fn ($a) => $a[3] === 'CU15M')));
        $this->assertCount(0, \CallLog::callsMatching('InsertClassEvent', fn ($a) => $a[3] === 'BU15M'));
        $this->assertCount(0, \CallLog::callsMatching('InsertClassEvent', fn ($a) => str_contains($a[3], 'U12')));
    }

    public function testInsertStandardEventsType6AddsU12ForRecurveOnly(): void
    {
        \InsertStandardEvents(7, 6);

        $this->assertGreaterThan(0, count(\CallLog::callsMatching('InsertClassEvent', fn ($a) => $a[3] === 'RU12M')));
        $this->assertCount(0, \CallLog::callsMatching('InsertClassEvent', fn ($a) => $a[3] === 'CU12M'));
        $this->assertCount(0, \CallLog::callsMatching('InsertClassEvent', fn ($a) => $a[3] === 'BU12M'));
    }

    public function testCreateStandardClassesType37AddsU15Only(): void
    {
        \CreateStandardClasses(7, 37);

        $this->assertCount(12, \CallLog::calls('CreateClass'));
        $this->assertCount(2, \CallLog::callsMatching('CreateClass', fn ($a) => str_starts_with($a[5], 'U15')));
        $this->assertCount(0, \CallLog::callsMatching('CreateClass', fn ($a) => str_starts_with($a[5], 'U12')));
    }

    public function testInsertStandardEventsType37AddsU15ForRecurveAndCompoundOnly(): void
    {
        \InsertStandardEvents(7, 37);

        $this->assertGreaterThan(0, count(\CallLog::callsMatching('InsertClassEvent', fn ($a) => $a[3] === 'RU15M')));
        $this->assertGreaterThan(0, count(\CallLog::callsMatching('InsertClassEvent', fn ($a) => $a[3] === 'CU15M')));
        $this->assertCount(0, \CallLog::callsMatching('InsertClassEvent', fn ($a) => $a[3] === 'BU15M'));
        $this->assertCount(0, \CallLog::callsMatching('InsertClassEvent', fn ($a) => str_contains($a[3], 'U12')));
    }

    // --- $KidsOnly (TourType 16) -------------------------------------------

    public function testCreateStandardClassesKidsOnlyCreatesU12OnlyRecurve(): void
    {
        \CreateStandardClasses(7, 16, true);

        $calls = \CallLog::calls('CreateClass');
        $this->assertCount(2, $calls);
        $this->assertSame('U12M', $calls[0][5]);
        $this->assertSame('R', $calls[0][9]);
        $this->assertSame('U12W', $calls[1][5]);
        $this->assertSame('R', $calls[1][9]);

        // Self-only ValidClass — no upward chain to U15/U18/U21/M, which
        // don't exist as classes in a $KidsOnly (TourType 16) tournament.
        $this->assertSame('U12M', $calls[0][6]);
        $this->assertSame('U12W', $calls[1][6]);
    }

    public function testCreateStandardClassesKidsOnlyDefaultFalseUnchanged(): void
    {
        \CreateStandardClasses(7, 1, false);
        $this->assertCount(10, \CallLog::calls('CreateClass'));
    }

    public function testInsertStandardEventsKidsOnlyBindsU12RecurveOnlyNoMixedTeam(): void
    {
        \InsertStandardEvents(7, 16, true);

        $calls = \CallLog::calls('InsertClassEvent');
        $codes = array_unique(array_column($calls, 3));
        sort($codes);

        // Individual (Team=0) + Team (Team=1): 2 classes x 2 = 4 bindings, no mixed team.
        $this->assertCount(4, $calls);
        $this->assertSame(['RU12M', 'RU12W'], $codes);
    }

    public function testInsertStandardEventsIndividualUsesTeamZeroNumberOne(): void
    {
        \InsertStandardEvents(7, 1);

        $rm = \CallLog::callsMatching('InsertClassEvent', fn ($a) => $a[3] === 'RM');
        $this->assertCount(2, $rm); // one individual, one team row both coded "RM"
        // Individual: Team=0, Number=1 (args: TourId, Team, Number, EvCode, Division, Class)
        $this->assertSame([7, 0, 1, 'RM', 'R', 'M'], $rm[0]);
        // Team: Team=1, Number=3
        $this->assertSame([7, 1, 3, 'RM', 'R', 'M'], $rm[1]);
    }

    public function testInsertStandardEventsMixedTeamBindsBothGendersToSameEventCode(): void
    {
        \InsertStandardEvents(7, 1);

        $mixed = \CallLog::callsMatching('InsertClassEvent', fn ($a) => $a[3] === 'RX');
        $this->assertCount(2, $mixed);
        $this->assertSame([7, 1, 1, 'RX', 'R', 'W'], $mixed[0]);
        $this->assertSame([7, 2, 1, 'RX', 'R', 'M'], $mixed[1]);
    }

    // --- pl_setup_70m_family (Setup_3_PL.php / Setup_37_PL.php shared body) ---
    //
    // Local fixtures, not the real $PL_CLASS_NAMES/$PL_MIXED_CLASS_NAMES
    // globals: lib.php's require happens at this test *file's* top level, but
    // PHPUnit's own file-discovery loader may itself require this file from
    // inside one of its own methods — which would make lib.php's top-level
    // assignments local to that loader's frame, not true PHP globals, the
    // same trap documented in design.md for GetSetupFile(). A local fixture
    // sidesteps the question entirely instead of relying on an unverified
    // assumption about PHPUnit's internals.

    private function plClassNames(): array
    {
        return [
            'M' => 'Test-M', 'W' => 'Test-W',
            'U24M' => 'Test-U24M', 'U24W' => 'Test-U24W',
            'U21M' => 'Test-U21M', 'U21W' => 'Test-U21W',
            'U18M' => 'Test-U18M', 'U18W' => 'Test-U18W',
            '50M' => 'Test-50M', '50W' => 'Test-50W',
            'U15M' => 'Test-U15M', 'U15W' => 'Test-U15W',
        ];
    }

    private function plMixedClassNames(): array
    {
        return [
            '' => 'Test-Senior', 'U24' => 'Test-U24', 'U21' => 'Test-U21',
            'U18' => 'Test-U18', '50' => 'Test-50', 'U15' => 'Test-U15',
        ];
    }

    public function testPlSetup70mFamilyMultiplierOneMatchesSingleDistanceRound(): void
    {
        \pl_setup_70m_family(7, 3, 1, $this->plClassNames(), $this->plMixedClassNames());

        $rm = \CallLog::callsMatching('CreateDistanceNew', fn ($a) => $a[2] === 'RM');
        $this->assertCount(1, $rm);
        $this->assertSame([['70m-1', 70], ['70m-2', 70]], $rm[0][3]);

        $ru15m = \CallLog::callsMatching('CreateDistanceNew', fn ($a) => $a[2] === 'RU15M');
        $this->assertCount(1, $ru15m);
        $this->assertSame([['40m', 40], ['20m', 20]], $ru15m[0][3]);

        $di = \CallLog::calls('CreateDistanceInformation');
        $this->assertCount(1, $di);
        $this->assertSame([[6, 6], [6, 6]], $di[0][1]);
    }

    public function testPlSetup70mFamilyMultiplierTwoDoublesEverySession(): void
    {
        \pl_setup_70m_family(7, 37, 2, $this->plClassNames(), $this->plMixedClassNames());

        $rm = \CallLog::callsMatching('CreateDistanceNew', fn ($a) => $a[2] === 'RM');
        $this->assertCount(1, $rm);
        $this->assertSame([['70m-1', 70], ['70m-2', 70], ['70m-3', 70], ['70m-4', 70]], $rm[0][3]);

        // U15's asymmetric [40m, 20m] split doubles per-distance, not interleaved.
        $ru15m = \CallLog::callsMatching('CreateDistanceNew', fn ($a) => $a[2] === 'RU15M');
        $this->assertCount(1, $ru15m);
        $this->assertSame(
            [['40m-1', 40], ['40m-2', 40], ['20m-1', 20], ['20m-2', 20]],
            $ru15m[0][3]
        );

        $di = \CallLog::calls('CreateDistanceInformation');
        $this->assertCount(1, $di);
        $this->assertSame([[6, 6], [6, 6], [6, 6], [6, 6]], $di[0][1]);
    }

    public function testPlSetup70mFamilyType37MatchesType3ExceptDistances(): void
    {
        \pl_setup_70m_family(7, 3, 1, $this->plClassNames(), $this->plMixedClassNames());
        $singleDivisions = \CallLog::calls('CreateDivision');
        $singleClasses   = \CallLog::calls('CreateClass');
        $singleEvents    = \CallLog::calls('CreateEventNew');
        $singleFaces     = \CallLog::calls('CreateTargetFace');
        $singleBindings  = \CallLog::calls('InsertClassEvent');

        \CallLog::reset();

        \pl_setup_70m_family(7, 37, 2, $this->plClassNames(), $this->plMixedClassNames());
        $doubleDivisions = \CallLog::calls('CreateDivision');
        $doubleClasses   = \CallLog::calls('CreateClass');
        $doubleEvents    = \CallLog::calls('CreateEventNew');
        $doubleFaces     = \CallLog::calls('CreateTargetFace');
        $doubleBindings  = \CallLog::calls('InsertClassEvent');

        // None of these five builders take $TourType as an argument, so a
        // parity run produces byte-identical calls regardless of whether it
        // was TourType 3/multiplier 1 or TourType 37/multiplier 2 — only
        // CreateDistanceNew/CreateDistanceInformation (checked above) differ.
        $this->assertSame($singleDivisions, $doubleDivisions);
        $this->assertSame($singleClasses, $doubleClasses);
        $this->assertSame($singleEvents, $doubleEvents);
        $this->assertSame($singleFaces, $doubleFaces);
        $this->assertSame($singleBindings, $doubleBindings);
    }

    // --- pl_setup_kids_round (Setup_16_PL.php) ---------------------------------

    private function plKidsClassNames(): array
    {
        return ['U12M' => 'Test-U12M', 'U12W' => 'Test-U12W'];
    }

    public function testPlSetupKidsRoundDistancesAndFaces(): void
    {
        \pl_setup_kids_round(7, 16, $this->plKidsClassNames());

        foreach (['RU12M', 'RU12W'] as $code) {
            $calls = \CallLog::callsMatching('CreateDistanceNew', fn ($a) => $a[2] === $code);
            $this->assertCount(1, $calls, "expected one CreateDistanceNew call for $code");
            $this->assertSame([['25m', 25], ['20m', 20], ['15m', 15], ['10m', 10]], $calls[0][3]);
        }

        $faces = \CallLog::calls('CreateTargetFace');
        $this->assertCount(1, $faces);
        // CreateTargetFace($TourId, $Id, $Name, $Classes, $Default, $T1, $W1, $T2, $W2, $T3, $W3, $T4, $W4)
        $this->assertSame(
            [7, 1, 'Dzieci 25m/20m/15m/10m', 'RU12%', '1', TGT_OUT_FULL, 122, TGT_OUT_FULL, 122, TGT_OUT_FULL, 80, TGT_OUT_FULL, 80],
            $faces[0]
        );
    }

    public function testPlSetupKidsRoundThreeArrowEndsSeventyTwoArrowsTotal(): void
    {
        \pl_setup_kids_round(7, 16, $this->plKidsClassNames());

        $di = \CallLog::calls('CreateDistanceInformation');
        $this->assertCount(1, $di);
        $legs = $di[0][1];
        $this->assertCount(4, $legs);
        foreach ($legs as $leg) {
            $this->assertSame([6, 3], $leg); // 6 ends x 3 arrows = 18 arrows/leg
        }
        $totalArrows = array_sum(array_map(fn ($leg) => $leg[0] * $leg[1], $legs));
        $this->assertSame(72, $totalArrows);
    }

    public function testPlSetupKidsRoundNoElimination(): void
    {
        \pl_setup_kids_round(7, 16, $this->plKidsClassNames());

        $events = \CallLog::calls('CreateEventNew');
        $this->assertCount(4, $events); // U12M/U12W x (individual + team)
        foreach ($events as $ev) {
            $this->assertSame(0, $ev[4]['EvFinalFirstPhase']);
        }
    }

    public function testPlSetupKidsRoundEveryEventHasBindingNoOrphans(): void
    {
        \pl_setup_kids_round(7, 16, $this->plKidsClassNames());
        \InsertStandardEvents(7, 16, true);

        $eventCodes   = array_unique(array_map(fn ($a) => $a[1], \CallLog::calls('CreateEventNew')));
        $bindingCodes = array_unique(array_map(fn ($a) => $a[3], \CallLog::calls('InsertClassEvent')));
        sort($eventCodes);
        sort($bindingCodes);

        $this->assertSame(['RU12M', 'RU12W'], $eventCodes);
        $this->assertSame($eventCodes, $bindingCodes);
    }
}
