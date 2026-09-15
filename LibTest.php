<?php

namespace PL\Tests;

if (PHP_SAPI !== 'cli') {
    exit;
}

require_once __DIR__ . '/lib.php';

final class LibTest extends \PlTestCase
{
    // --- pl_class_in_preset / pl_resolve_preset --------------------------------

    public function testClassInPresetUnfilteredAllowsEverything(): void
    {
        $this->assertTrue(\pl_class_in_preset('U21M', 'R', array()));
        $this->assertTrue(\pl_class_in_preset('U21M', 'C', array()));
    }

    public function testClassInPresetDivisionFilter(): void
    {
        $preset = array('divisions' => array('R'));
        $this->assertTrue(\pl_class_in_preset('U21M', 'R', $preset));
        $this->assertFalse(\pl_class_in_preset('U21M', 'C', $preset));
    }

    public function testClassInPresetClassFilter(): void
    {
        $preset = array('classes' => array('M', 'W'));
        $this->assertTrue(\pl_class_in_preset('M', 'R', $preset));
        $this->assertFalse(\pl_class_in_preset('U21M', 'R', $preset));
    }

    public function testResolvePresetEmptySubRuleIsUnfiltered(): void
    {
        $this->assertSame(array(), \pl_resolve_preset(3, ''));
    }

    public function testResolvePresetUnknownSubRuleIsUnfiltered(): void
    {
        $this->assertSame(array(), \pl_resolve_preset(3, 'NotARegisteredSubRule'));
    }

    public function testResolvePresetSetAllClassIsUnfiltered(): void
    {
        $this->assertSame(array(), \pl_resolve_preset(3, 'SetAllClass'));
    }

    public function testResolvePresetSetSeniorClass(): void
    {
        $this->assertSame(array('classes' => array('M', 'W')), \pl_resolve_preset(3, 'SetSeniorClass'));
    }

    public function testResolvePresetRecurveOnlyYouthCut(): void
    {
        $preset = \pl_resolve_preset(3, 'Poland-RU21');
        // Poland-RU21 only exists on TourType 1 — TourType 3 has no such key.
        $this->assertSame(array(), $preset);

        $preset1 = \pl_resolve_preset(1, 'Poland-RU21');
        $this->assertSame(array('R'), $preset1['divisions']);
        $this->assertSame(array('U21M', 'U21W'), $preset1['classes']);
    }

    public function testResolvePresetMasterClassOnlyOnTourType3(): void
    {
        $this->assertArrayHasKey('classes', \pl_resolve_preset(3, 'SetMasterClass'));
        $this->assertSame(array(), \pl_resolve_preset(37, 'SetMasterClass'));
        $this->assertSame(array(), \pl_resolve_preset(1, 'SetMasterClass'));
        $this->assertSame(array(), \pl_resolve_preset(6, 'SetMasterClass'));
    }

    // --- CreateStandardDivisions ---------------------------------------------

    public function testCreateStandardDivisionsCreatesRecurveCompoundBarebowOnType3(): void
    {
        \CreateStandardDivisions(7, 3);

        $calls = \CallLog::calls('CreateDivision');
        $this->assertCount(3, $calls);
        $this->assertSame([7, 1, 'R', 'Łuk klasyczny', 1, 'R', 'R'], $calls[0]);
        $this->assertSame([7, 2, 'C', 'Łuk bloczkowy', 1, 'C', 'C'], $calls[1]);
        $this->assertSame([7, 3, 'B', 'Łuk barebow', 1, 'B', 'B'], $calls[2]);
    }

    public function testCreateStandardDivisionsType1HasNoBarebow(): void
    {
        \CreateStandardDivisions(7, 1);

        $calls = \CallLog::calls('CreateDivision');
        $this->assertCount(2, $calls);
        $codes = array_column($calls, 2);
        $this->assertSame(['R', 'C'], $codes);
    }

    public function testCreateStandardDivisionsDivisionPreset(): void
    {
        \CreateStandardDivisions(7, 3, array('divisions' => array('C', 'B')));

        $codes = array_column(\CallLog::calls('CreateDivision'), 2);
        $this->assertSame(['C', 'B'], $codes);
    }

    // --- CreateStandardClasses ------------------------------------------------

    public function testCreateStandardClassesType1HasNoU15U12MasterOrBarebow(): void
    {
        \CreateStandardClasses(7, 1);

        $calls = \CallLog::calls('CreateClass');
        $this->assertCount(8, $calls);
        $this->assertCount(0, \CallLog::callsMatching('CreateClass', fn ($a) => str_starts_with($a[5], 'U15')));
        $this->assertCount(0, \CallLog::callsMatching('CreateClass', fn ($a) => str_starts_with($a[5], 'U12')));
        foreach ($calls as $call) {
            $this->assertStringNotContainsString('B', $call[9], "class {$call[5]} must not be eligible in Barebow on TourType 1");
        }
    }

    public function testCreateStandardClassesType3HasU15U12PU12AndMasters(): void
    {
        \CreateStandardClasses(7, 3);

        // 8 base + U15(2) + U12(2) + PU12(2) + Masters(10) = 24
        $this->assertCount(24, \CallLog::calls('CreateClass'));
        $this->assertCount(2, \CallLog::callsMatching('CreateClass', fn ($a) => str_starts_with($a[5], 'U15')));
        $this->assertCount(2, \CallLog::callsMatching('CreateClass', fn ($a) => $a[5] === 'U12M' || $a[5] === 'U12W'));
        $this->assertCount(2, \CallLog::callsMatching('CreateClass', fn ($a) => str_starts_with($a[5], 'PU12')));
        $this->assertCount(10, \CallLog::callsMatching('CreateClass', fn ($a) => preg_match('/^(40|50|60|70|80)[MW]$/', $a[5])));
    }

    public function testCreateStandardClassesType37HasU15OnlyNoU12PU12OrMasters(): void
    {
        \CreateStandardClasses(7, 37);

        // 8 base + U15(2) = 10
        $this->assertCount(10, \CallLog::calls('CreateClass'));
        $this->assertCount(2, \CallLog::callsMatching('CreateClass', fn ($a) => str_starts_with($a[5], 'U15')));
        $this->assertCount(0, \CallLog::callsMatching('CreateClass', fn ($a) => str_contains($a[5], 'U12')));
        $this->assertCount(0, \CallLog::callsMatching('CreateClass', fn ($a) => preg_match('/^(40|50|60|70|80)[MW]$/', $a[5])));
    }

    public function testCreateStandardClassesType6HasU15U12AndPU12NoMasters(): void
    {
        \CreateStandardClasses(7, 6);

        // 8 base + U15(2) + U12(2) + PU12(2) = 14
        $this->assertCount(14, \CallLog::calls('CreateClass'));
        $this->assertCount(2, \CallLog::callsMatching('CreateClass', fn ($a) => str_starts_with($a[5], 'U15')));
        $this->assertCount(2, \CallLog::callsMatching('CreateClass', fn ($a) => $a[5] === 'U12M' || $a[5] === 'U12W'));
        $this->assertCount(2, \CallLog::callsMatching('CreateClass', fn ($a) => str_starts_with($a[5], 'PU12')));
        $this->assertCount(0, \CallLog::callsMatching('CreateClass', fn ($a) => preg_match('/^(40|50|60|70|80)[MW]$/', $a[5])));
    }

    public function testCreateStandardClassesType16IsU12OnlyRegardlessOfPreset(): void
    {
        \CreateStandardClasses(7, 16);

        $calls = \CallLog::calls('CreateClass');
        $this->assertCount(2, $calls);
        $this->assertSame('U12M', $calls[0][5]);
        $this->assertSame('R', $calls[0][9]);
        $this->assertSame('U12W', $calls[1][5]);
        $this->assertSame('R', $calls[1][9]);

        // Self-only ValidClass — no upward chain to U15/U18/U21/M, which
        // don't exist as classes in a TourType 16 tournament.
        $this->assertSame('U12M', $calls[0][6]);
        $this->assertSame('U12W', $calls[1][6]);
    }

    public function testCreateStandardClassesType16StructuralRestrictionComposesWithPreset(): void
    {
        // TourType 16's candidate list is structurally U12-only regardless of
        // preset (no M/W/U21/etc candidates exist to filter in the first
        // place) — but a preset naming only one of the two U12 classes still
        // applies normally on top of that.
        \CreateStandardClasses(7, 16, array('classes' => array('U12M')));

        $codes = array_column(\CallLog::calls('CreateClass'), 5);
        $this->assertSame(['U12M'], $codes);
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
        $this->assertSame(range(1, 8), $indices);
    }

    public function testCreateStandardClassesRecurveOnlyPresetKeepsOnlyThatDivision(): void
    {
        \CreateStandardClasses(7, 3, array('divisions' => array('R'), 'classes' => array('U21M', 'U21W')));

        $calls = \CallLog::calls('CreateClass');
        $this->assertCount(2, $calls);
        $this->assertSame('U21M', $calls[0][5]);
        $this->assertSame('R', $calls[0][9]);
        $this->assertSame('U21W', $calls[1][5]);
        $this->assertSame('R', $calls[1][9]);
    }

    public function testCreateStandardClassesImpossibleCombinationCreatesNothing(): void
    {
        // U24 is never eligible in C — a C-only preset naming U24 survives
        // neither axis's intersection, so nothing is created for it.
        \CreateStandardClasses(7, 1, array('divisions' => array('C'), 'classes' => array('U24M', 'U24W')));

        $this->assertCount(0, \CallLog::calls('CreateClass'));
    }

    public function testCreateStandardClassesMastersBandsAreSelfOnlyValidClass(): void
    {
        \CreateStandardClasses(7, 3);

        foreach (\CallLog::callsMatching('CreateClass', fn ($a) => preg_match('/^(40|50|60|70|80)[MW]$/', $a[5])) as $call) {
            $this->assertSame($call[5], $call[6], "{$call[5]} must chain to itself only");
            $this->assertSame('R,C,B', $call[9]);
        }
    }

    public function testCreateStandardClassesPU12IsSelfOnlyValidClassRecurveOnly(): void
    {
        \CreateStandardClasses(7, 3);

        $pu12 = \CallLog::callsMatching('CreateClass', fn ($a) => str_starts_with($a[5], 'PU12'));
        foreach ($pu12 as $call) {
            $this->assertSame($call[5], $call[6]);
            $this->assertSame('R', $call[9]);
        }
    }

    // --- InsertStandardEvents -------------------------------------------------

    public function testInsertStandardEventsType1HasNoU15OrU12(): void
    {
        \InsertStandardEvents(7, 1);

        $this->assertCount(0, \CallLog::callsMatching('InsertClassEvent', fn ($a) => str_contains($a[3], 'U15')));
        $this->assertCount(0, \CallLog::callsMatching('InsertClassEvent', fn ($a) => str_contains($a[3], 'U12')));
        // Individual/team B bindings are empty (B isn't in any TT1 candidate's
        // division list) — the mixed-team loop still *attempts* a few B
        // bindings (BX/BU21X/BU18X), same as it always has for TT1's R/C
        // mixed events with no matching CreateEventNew call: InsertClassEvent
        // no-ops in the real DB layer when the Event row doesn't exist
        // (pl_setup_1440 never creates one, TT1 has no mixed team events at
        // all), so this is inert rather than orphaning anything.
        $this->assertCount(0, \CallLog::callsMatching('InsertClassEvent', fn ($a) => $a[4] === 'B' && (str_contains($a[3], 'U15') || str_contains($a[3], 'U12'))));
    }

    public function testInsertStandardEventsType3AddsU15ForRecurveAndCompoundOnly(): void
    {
        \InsertStandardEvents(7, 3);

        $this->assertGreaterThan(0, count(\CallLog::callsMatching('InsertClassEvent', fn ($a) => $a[3] === 'RU15M')));
        $this->assertGreaterThan(0, count(\CallLog::callsMatching('InsertClassEvent', fn ($a) => $a[3] === 'CU15M')));
        $this->assertCount(0, \CallLog::callsMatching('InsertClassEvent', fn ($a) => $a[3] === 'BU15M'));
    }

    public function testInsertStandardEventsType3BindsU12AndPU12RecurveOnly(): void
    {
        \InsertStandardEvents(7, 3);

        $u12 = \CallLog::callsMatching('InsertClassEvent', fn ($a) => $a[3] === 'RU12M' || $a[3] === 'RU12W');
        $this->assertCount(4, $u12); // individual + team, M + W

        $pu12 = \CallLog::callsMatching('InsertClassEvent', fn ($a) => $a[3] === 'RPU12M' || $a[3] === 'RPU12W');
        $this->assertCount(4, $pu12);

        $this->assertCount(0, \CallLog::callsMatching('InsertClassEvent', fn ($a) => $a[4] !== 'R' && str_contains($a[3], 'U12')));
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

        $this->assertCount(10, \CallLog::calls('CreateClass'));
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

    public function testInsertStandardEventsType16BindsU12OnlyNoMixedTeam(): void
    {
        \InsertStandardEvents(7, 16);

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

    public function testInsertStandardEventsMixedTeamRespectsPreset(): void
    {
        // Senior-only preset — no U21/U18/etc mixed team bindings should occur.
        \InsertStandardEvents(7, 3, array('classes' => array('M', 'W')));

        $this->assertCount(2, \CallLog::callsMatching('InsertClassEvent', fn ($a) => $a[3] === 'RX'));
        $this->assertCount(0, \CallLog::callsMatching('InsertClassEvent', fn ($a) => $a[3] === 'RU21X'));
        $this->assertCount(0, \CallLog::callsMatching('InsertClassEvent', fn ($a) => $a[3] === 'RU18X'));
    }

    public function testInsertStandardEventsNoMastersMixedTeam(): void
    {
        \InsertStandardEvents(7, 3);

        $this->assertCount(0, \CallLog::callsMatching('InsertClassEvent', fn ($a) => preg_match('/^R(40|50|60|70|80)X$/', $a[3])));
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
            'U15M' => 'Test-U15M', 'U15W' => 'Test-U15W',
            'U12M' => 'Test-U12M', 'U12W' => 'Test-U12W',
            'PU12M' => 'Test-PU12M', 'PU12W' => 'Test-PU12W',
            '40M' => 'Test-40M', '40W' => 'Test-40W',
            '50M' => 'Test-50M', '50W' => 'Test-50W',
            '60M' => 'Test-60M', '60W' => 'Test-60W',
            '70M' => 'Test-70M', '70W' => 'Test-70W',
            '80M' => 'Test-80M', '80W' => 'Test-80W',
        ];
    }

    private function plMixedClassNames(): array
    {
        return [
            '' => 'Test-Senior', 'U24' => 'Test-U24', 'U21' => 'Test-U21',
            'U18' => 'Test-U18', 'U15' => 'Test-U15',
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

    public function testPlSetup70mFamilyType37ExcludesU12PU12AndMasters(): void
    {
        \pl_setup_70m_family(7, 3, 1, $this->plClassNames(), $this->plMixedClassNames());
        $type3Classes = \CallLog::calls('CreateClass');

        \CallLog::reset();

        \pl_setup_70m_family(7, 37, 2, $this->plClassNames(), $this->plMixedClassNames());
        $type37Classes = \CallLog::calls('CreateClass');

        $this->assertCount(24, $type3Classes);
        $this->assertCount(10, $type37Classes);

        $type37Codes = array_column($type37Classes, 5);
        $this->assertNotContains('U12M', $type37Codes);
        $this->assertNotContains('PU12M', $type37Codes);
        $this->assertCount(0, array_filter($type37Codes, fn ($c) => preg_match('/^(40|50|60|70|80)[MW]$/', $c)));
    }

    public function testPlSetup70mFamilyType37CreatesNoU12PU12OrMastersEventsOrDistances(): void
    {
        // Regression: pl_class_in_preset() only checks the preset axis, not
        // TourType eligibility — a per-class CreateEventNew()/CreateDistanceNew()
        // loop that forgets an explicit TourType guard still fires on 37 even
        // though CreateStandardClasses() correctly excludes these classes
        // there, leaving orphaned Events/TournamentDistances rows with no
        // class to bind to.
        \pl_setup_70m_family(7, 37, 2, $this->plClassNames(), $this->plMixedClassNames());

        $eventCodes = array_column(\CallLog::calls('CreateEventNew'), 1);
        $this->assertCount(0, array_filter($eventCodes, fn ($c) => str_contains($c, 'U12') || preg_match('/^R(40|50|60|70|80)[MW]$/', $c)),
            'no U12/PU12/Masters event should be created on TourType 37');

        $distanceClasses = array_column(\CallLog::calls('CreateDistanceNew'), 2);
        $this->assertCount(0, array_filter($distanceClasses, fn ($c) => str_contains($c, 'U12') || preg_match('/^R(40|50|60|70|80)[MW]$/', $c)),
            'no U12/PU12/Masters distance should be created on TourType 37');
    }

    public function testPlSetup70mFamilyMastersOnlyOnType3(): void
    {
        \pl_setup_70m_family(7, 3, 1, $this->plClassNames(), $this->plMixedClassNames());

        $masters = \CallLog::callsMatching('CreateEventNew', fn ($a) => preg_match('/^[RCB](40|50|60|70|80)[MW]$/', $a[1]));
        // 5 bands x 2 genders x 3 divisions = 30 individual + 30 team = 60
        $this->assertCount(60, $masters);
        foreach ($masters as $ev) {
            $this->assertContains($ev[4]['EvFinalFirstPhase'], [48, 12],
                "Masters event {$ev[1]} must have an elimination phase configured (48=ind, 12=team)");
        }
    }

    public function testPlSetup70mFamilyMasters7080CompoundUsesFullFace(): void
    {
        \pl_setup_70m_family(7, 3, 1, $this->plClassNames(), $this->plMixedClassNames());

        $c70 = \CallLog::callsMatching('CreateEventNew', fn ($a) => $a[1] === 'C70M');
        $this->assertCount(2, $c70); // individual + team
        foreach ($c70 as $ev) {
            $this->assertSame(\TGT_OUT_FULL, $ev[4]['EvFinalTargetType']);
        }

        $c50 = \CallLog::callsMatching('CreateEventNew', fn ($a) => $a[1] === 'C50M');
        foreach ($c50 as $ev) {
            $this->assertSame(\TGT_OUT_5_big10, $ev[4]['EvFinalTargetType']);
        }
    }

    public function testPlSetup70mFamilyRespectsRecurveOnlyPreset(): void
    {
        \pl_setup_70m_family(7, 3, 1, $this->plClassNames(), $this->plMixedClassNames(),
            array('divisions' => array('R'), 'classes' => array('U21M', 'U21W')));

        $classes = array_column(\CallLog::calls('CreateClass'), 5);
        $this->assertSame(['U21M', 'U21W'], $classes);

        $events = array_column(\CallLog::calls('CreateEventNew'), 1);
        // RU21M/RU21W (individual + team) plus their mixed-team event, since
        // both genders survive the preset — no CU21M/BU21M.
        sort($events);
        $this->assertSame(['RU21M', 'RU21M', 'RU21W', 'RU21W', 'RU21X'], $events);
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
            [7, 1, 'Łuk klasyczny Dziecko 25m/20m/15m/10m', 'RU12%', '1', TGT_OUT_FULL, 122, TGT_OUT_FULL, 122, TGT_OUT_FULL, 80, TGT_OUT_FULL, 80],
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
        \InsertStandardEvents(7, 16);

        $eventCodes   = array_unique(array_map(fn ($a) => $a[1], \CallLog::calls('CreateEventNew')));
        $bindingCodes = array_unique(array_map(fn ($a) => $a[3], \CallLog::calls('InsertClassEvent')));
        sort($eventCodes);
        sort($bindingCodes);

        $this->assertSame(['RU12M', 'RU12W'], $eventCodes);
        $this->assertSame($eventCodes, $bindingCodes);
    }

    public function testPlSetupKidsRoundPU12NeverCreated(): void
    {
        \pl_setup_kids_round(7, 16, $this->plKidsClassNames());

        $this->assertCount(0, \CallLog::callsMatching('CreateEventNew', fn ($a) => str_contains($a[1], 'PU12')));
    }

    // --- pl_setup_1440 (Setup_1_PL.php) -----------------------------------------

    public function testPlSetup1440NoBarebow(): void
    {
        \pl_setup_1440(7, 1, $this->plClassNames());

        $this->assertCount(0, \CallLog::callsMatching('CreateEventNew', fn ($a) => str_starts_with($a[1], 'B')));
        $divisions = array_column(\CallLog::calls('CreateDivision'), 2);
        $this->assertSame(['R', 'C'], $divisions);
    }

    public function testPlSetup1440CompoundDistancesMirrorRecurve(): void
    {
        \pl_setup_1440(7, 1, $this->plClassNames());

        foreach (['M', 'W', 'U24M', 'U24W', 'U21M', 'U21W', 'U18M', 'U18W'] as $cl) {
            $r = \CallLog::callsMatching('CreateDistanceNew', fn ($a) => $a[2] === "R{$cl}");
            $c = \CallLog::callsMatching('CreateDistanceNew', fn ($a) => $a[2] === "C{$cl}");
            $this->assertCount(1, $r, "missing R{$cl} distance");
            $this->assertCount(1, $c, "missing C{$cl} distance");
            $this->assertSame($r[0][3], $c[0][3], "C{$cl} distances must equal R{$cl}'s");
        }
    }

    public function testPlSetup1440CompoundFaceStaysSixRing(): void
    {
        \pl_setup_1440(7, 1, $this->plClassNames());

        $faces = \CallLog::callsMatching('CreateTargetFace', fn ($a) => $a[3] === 'C%');
        $this->assertCount(1, $faces);
        $this->assertSame(TGT_OUT_5_big10, $faces[0][5]);
        $this->assertSame(80, $faces[0][6]);
    }

    public function testPlSetup1440RespectsRecurveOnlyPreset(): void
    {
        \pl_setup_1440(7, 1, $this->plClassNames(), array('divisions' => array('R'), 'classes' => array('U21M', 'U21W')));

        $classes = array_column(\CallLog::calls('CreateClass'), 5);
        $this->assertSame(['U21M', 'U21W'], $classes);

        $events = array_column(\CallLog::calls('CreateEventNew'), 1);
        sort($events);
        $this->assertSame(['RU21M', 'RU21M', 'RU21W', 'RU21W'], $events);
    }

    // --- pl_setup_indoor (Setup_6_PL.php) ---------------------------------------

    public function testPlSetupIndoorU12AndPU12DistinctDistancesNoElimination(): void
    {
        \pl_setup_indoor(7, 6, $this->plClassNames(), $this->plMixedClassNames());

        $u12 = \CallLog::callsMatching('CreateDistanceNew', fn ($a) => $a[2] === 'RU12M');
        $this->assertCount(1, $u12);
        $this->assertSame([['15m-1', 15], ['15m-2', 15]], $u12[0][3]);

        $pu12 = \CallLog::callsMatching('CreateDistanceNew', fn ($a) => $a[2] === 'RPU12M');
        $this->assertCount(1, $pu12);
        $this->assertSame([['10m-1', 10], ['10m-2', 10]], $pu12[0][3]);

        foreach (\CallLog::callsMatching('CreateEventNew', fn ($a) => str_contains($a[1], 'U12')) as $ev) {
            $this->assertSame(0, $ev[4]['EvFinalFirstPhase']);
        }
    }

    public function testPlSetupIndoorPU12RecurveOnly(): void
    {
        \pl_setup_indoor(7, 6, $this->plClassNames(), $this->plMixedClassNames());

        $this->assertCount(0, \CallLog::callsMatching('CreateEventNew', fn ($a) => str_starts_with($a[1], 'C') && str_contains($a[1], 'PU12')));
        $this->assertCount(0, \CallLog::callsMatching('CreateEventNew', fn ($a) => str_starts_with($a[1], 'B') && str_contains($a[1], 'PU12')));
    }

    public function testPlSetupIndoorNoU12OrPU12MixedTeam(): void
    {
        \pl_setup_indoor(7, 6, $this->plClassNames(), $this->plMixedClassNames());

        $this->assertCount(0, \CallLog::callsMatching('CreateEventNew', fn ($a) => str_contains($a[1], 'U12') && ($a[4]['EvMixedTeam'] ?? 0) === 1));
    }
}
