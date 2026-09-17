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

    public function testCreateStandardClassesType3HasU15U12U10AndMasters(): void
    {
        \CreateStandardClasses(7, 3);

        // 8 base + U15(2) + U12(2) + U10(2) + Masters(10) = 24
        $this->assertCount(24, \CallLog::calls('CreateClass'));
        $this->assertCount(2, \CallLog::callsMatching('CreateClass', fn ($a) => str_starts_with($a[5], 'U15')));
        $this->assertCount(2, \CallLog::callsMatching('CreateClass', fn ($a) => $a[5] === 'U12M' || $a[5] === 'U12W'));
        $this->assertCount(2, \CallLog::callsMatching('CreateClass', fn ($a) => str_starts_with($a[5], 'U10')));
        $this->assertCount(10, \CallLog::callsMatching('CreateClass', fn ($a) => preg_match('/^(40|50|60|70|80)[MW]$/', $a[5])));
    }

    public function testCreateStandardClassesType37HasU15OnlyNoU12U10OrMasters(): void
    {
        \CreateStandardClasses(7, 37);

        // 8 base + U15(2) = 10
        $this->assertCount(10, \CallLog::calls('CreateClass'));
        $this->assertCount(2, \CallLog::callsMatching('CreateClass', fn ($a) => str_starts_with($a[5], 'U15')));
        $this->assertCount(0, \CallLog::callsMatching('CreateClass', fn ($a) => str_contains($a[5], 'U12')));
        $this->assertCount(0, \CallLog::callsMatching('CreateClass', fn ($a) => preg_match('/^(40|50|60|70|80)[MW]$/', $a[5])));
    }

    public function testCreateStandardClassesType6HasU15U12AndU10NoMasters(): void
    {
        \CreateStandardClasses(7, 6);

        // 8 base + U15(2) + U12(2) + U10(2) = 14
        $this->assertCount(14, \CallLog::calls('CreateClass'));
        $this->assertCount(2, \CallLog::callsMatching('CreateClass', fn ($a) => str_starts_with($a[5], 'U15')));
        $this->assertCount(2, \CallLog::callsMatching('CreateClass', fn ($a) => $a[5] === 'U12M' || $a[5] === 'U12W'));
        $this->assertCount(2, \CallLog::callsMatching('CreateClass', fn ($a) => str_starts_with($a[5], 'U10')));
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

    public function testCreateStandardClassesU12AndU10AgeRangesDoNotOverlap(): void
    {
        // Regression: U12 and U10 used to share the exact same ageFrom/ageTo
        // (9-12) on both TourType 3 and 6 — a copy-paste bug that let a live
        // tournament's Classes rows contradict PZŁucz's actual split (U12
        // 11-12, U10 5-10, confirmed by the domain owner).
        foreach ([3, 6] as $tourType) {
            \CallLog::reset();
            \CreateStandardClasses(7, $tourType);

            foreach (['U12M', 'U12W'] as $code) {
                $calls = \CallLog::callsMatching('CreateClass', fn ($a) => $a[5] === $code);
                $this->assertCount(1, $calls, "expected exactly one CreateClass call for {$code} on TourType {$tourType}");
                $this->assertSame(11, $calls[0][2], "{$code} on TourType {$tourType} must have ageFrom=11");
                $this->assertSame(12, $calls[0][3], "{$code} on TourType {$tourType} must have ageTo=12");
            }
            foreach (['U10M', 'U10W'] as $code) {
                $calls = \CallLog::callsMatching('CreateClass', fn ($a) => $a[5] === $code);
                $this->assertCount(1, $calls, "expected exactly one CreateClass call for {$code} on TourType {$tourType}");
                $this->assertSame(5, $calls[0][2], "{$code} on TourType {$tourType} must have ageFrom=5");
                $this->assertSame(10, $calls[0][3], "{$code} on TourType {$tourType} must have ageTo=10");
            }
        }
    }

    public function testCreateStandardClassesType16U12AgeRangeMatchesGeneralU12(): void
    {
        \CreateStandardClasses(7, 16);

        foreach (\CallLog::calls('CreateClass') as $call) {
            $this->assertSame(11, $call[2], "{$call[5]} on TourType 16 must have ageFrom=11");
            $this->assertSame(12, $call[3], "{$call[5]} on TourType 16 must have ageTo=12");
        }
    }

    public function testCreateStandardClassesSeniorAgeFromExcludesU24(): void
    {
        // Regression: Senior ageFrom used to be 21, overlapping U24's 21-23
        // range — an archer aged 21-23 could ambiguously resolve to either
        // class. Senior now starts at 24, right after U24 ends.
        foreach ([1, 3, 6, 37] as $tourType) {
            \CallLog::reset();
            \CreateStandardClasses(7, $tourType);

            $senior = \CallLog::callsMatching('CreateClass', fn ($a) => $a[5] === 'M' || $a[5] === 'W');
            foreach ($senior as $call) {
                $this->assertSame(24, $call[2], "{$call[5]} on TourType {$tourType} must have ageFrom=24");
            }
        }
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

    public function testCreateStandardClassesSeniorAgeToIsOpenEnded(): void
    {
        // Senior M/W must resolve age-class for any adult age even in a
        // tournament with no Masters classes (Masters is opt-in, TourType 3
        // only) — see Import/Fun_BibImport.php's pl_bibimport_resolve_class().
        foreach ([1, 3, 6, 37] as $tourType) {
            \CallLog::reset();
            \CreateStandardClasses(7, $tourType);

            $senior = \CallLog::callsMatching('CreateClass', fn ($a) => $a[5] === 'M' || $a[5] === 'W');
            $this->assertCount(2, $senior, "TourType {$tourType} must create M and W");
            foreach ($senior as $call) {
                $this->assertSame(127, $call[3], "{$call[5]} on TourType {$tourType} must have ageTo=127 (max signed tinyint)");
            }
        }
    }

    public function testCreateStandardClassesUpwardEligibilityRestrictedBelowU21(): void
    {
        \CreateStandardClasses(7, 3);

        $validClassOf = function (string $id) {
            $calls = \CallLog::callsMatching('CreateClass', fn ($a) => $a[5] === $id);
            $this->assertCount(1, $calls, "expected exactly one CreateClass call for {$id}");
            return $calls[0][6];
        };

        // U12/U15 are a parallel track like Masters/U10 — no upward chain at all.
        $this->assertSame('U12M', $validClassOf('U12M'));
        $this->assertSame('U12W', $validClassOf('U12W'));
        $this->assertSame('U15M', $validClassOf('U15M'));
        $this->assertSame('U15W', $validClassOf('U15W'));

        // U18 may opt up to U21 only, never straight to Senior.
        $this->assertSame('U18M,U21M', $validClassOf('U18M'));
        $this->assertSame('U18W,U21W', $validClassOf('U18W'));

        // U21/U24 remain the only classes that reach Senior — unchanged.
        $this->assertSame('U21M,M', $validClassOf('U21M'));
        $this->assertSame('U21W,W', $validClassOf('U21W'));
        $this->assertSame('U24M,M', $validClassOf('U24M'));
        $this->assertSame('U24W,W', $validClassOf('U24W'));
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

    public function testCreateStandardClassesU10IsSelfOnlyValidClassRecurveOnly(): void
    {
        \CreateStandardClasses(7, 3);

        $u10 = \CallLog::callsMatching('CreateClass', fn ($a) => str_starts_with($a[5], 'U10'));
        foreach ($u10 as $call) {
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

    public function testInsertStandardEventsType3BindsU12AndU10RecurveOnly(): void
    {
        \InsertStandardEvents(7, 3);

        $u12 = \CallLog::callsMatching('InsertClassEvent', fn ($a) => $a[3] === 'RU12M' || $a[3] === 'RU12W');
        $this->assertCount(4, $u12); // individual + team, M + W

        $u10 = \CallLog::callsMatching('InsertClassEvent', fn ($a) => $a[3] === 'RU10M' || $a[3] === 'RU10W');
        $this->assertCount(4, $u10);

        $this->assertCount(0, \CallLog::callsMatching('InsertClassEvent', fn ($a) => $a[4] !== 'R' && (str_contains($a[3], 'U12') || str_contains($a[3], 'U10'))));
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
            'U10M' => 'Test-U10M', 'U10W' => 'Test-U10W',
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

    public function testPlSetup70mFamilyType37ExcludesU12U10AndMasters(): void
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
        $this->assertNotContains('U10M', $type37Codes);
        $this->assertCount(0, array_filter($type37Codes, fn ($c) => preg_match('/^(40|50|60|70|80)[MW]$/', $c)));
    }

    public function testPlSetup70mFamilyType37CreatesNoU12U10OrMastersEventsOrDistances(): void
    {
        // Regression: pl_class_in_preset() only checks the preset axis, not
        // TourType eligibility — a per-class CreateEventNew()/CreateDistanceNew()
        // loop that forgets an explicit TourType guard still fires on 37 even
        // though CreateStandardClasses() correctly excludes these classes
        // there, leaving orphaned Events/TournamentDistances rows with no
        // class to bind to.
        \pl_setup_70m_family(7, 37, 2, $this->plClassNames(), $this->plMixedClassNames());

        $eventCodes = array_column(\CallLog::calls('CreateEventNew'), 1);
        $this->assertCount(0, array_filter($eventCodes, fn ($c) => str_contains($c, 'U12') || str_contains($c, 'U10') || preg_match('/^R(40|50|60|70|80)[MW]$/', $c)),
            'no U12/U10/Masters event should be created on TourType 37');

        $distanceClasses = array_column(\CallLog::calls('CreateDistanceNew'), 2);
        $this->assertCount(0, array_filter($distanceClasses, fn ($c) => str_contains($c, 'U12') || str_contains($c, 'U10') || preg_match('/^R(40|50|60|70|80)[MW]$/', $c)),
            'no U12/U10/Masters distance should be created on TourType 37');
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

        // Regression: distances and target faces used to be created
        // unconditionally regardless of preset — a Recurve-only, U21-only
        // preset must not leave RM/RU24M/RU18M/RU15M/RU12M/RU10M/R40M-R80M
        // distances, C%/B% wildcard distances, or any Compound/Barebow
        // target face lying around unused.
        $distanceClasses = array_column(\CallLog::calls('CreateDistanceNew'), 2);
        $this->assertSame(['RU21M', 'RU21W'], $distanceClasses);

        $faceNames = array_column(\CallLog::calls('CreateTargetFace'), 2);
        $this->assertNotContains('Łuk bloczkowy domyślna', $faceNames);
        $this->assertNotContains('Łuk bloczkowy Master 70+/80+', $faceNames);
    }

    public function testPlSetup70mFamilySeniorOnlyPresetLeavesNoOrphanedDistancesOrFaces(): void
    {
        // Regression: SetSeniorClass (no division restriction, classes M/W
        // only) must not leave U12/U10/Masters/U15/U18/U21/U24 distances or
        // faces behind, and must still create the shared C%/B% distances
        // (M/W exist in every division).
        \pl_setup_70m_family(7, 3, 1, $this->plClassNames(), $this->plMixedClassNames(),
            \pl_resolve_preset(3, 'SetSeniorClass'));

        $distanceClasses = array_column(\CallLog::calls('CreateDistanceNew'), 2);
        sort($distanceClasses);
        // M/W are eligible in every division, so C% and B% both survive too.
        $this->assertSame(['B%', 'C%', 'RM', 'RW'], array_values(array_unique($distanceClasses)));
        $this->assertCount(0, array_filter($distanceClasses, fn ($c) => str_contains($c, 'U12') || preg_match('/^R(40|50|60|70|80)[MW]$/', $c)));
    }

    public function testPlSetup70mFamilyMasterPresetLeavesNoU12OrU10Distances(): void
    {
        // Regression: SetMasterClass (classes = the 10 band codes, no
        // division restriction) must not leave RU12M/RU12W/RU10M/RU10W
        // distances behind — CreateStandardClasses() never creates those
        // classes under this preset.
        \pl_setup_70m_family(7, 3, 1, $this->plClassNames(), $this->plMixedClassNames(),
            \pl_resolve_preset(3, 'SetMasterClass'));

        $distanceClasses = array_column(\CallLog::calls('CreateDistanceNew'), 2);
        $this->assertCount(0, array_filter($distanceClasses, fn ($c) => str_contains($c, 'U12') || str_contains($c, 'U10')));
    }

    public function testPlSetup70mFamilyGenericFaceDoesNotOverlapU12U10Face(): void
    {
        // Regression: the generic Recurve/Barebow default face ('^[RB]') used
        // to also match RU12M/RU12W/RU10M/RU10W with the exact same 122/122
        // values as the dedicated U12/U10 face below — two '1'-default,
        // regex-matched TargetFaces rows tied on every ORDER BY key core's
        // Fun_Targets.php query uses, so which row a real Entries.EnTargetFace
        // resolved to was undefined (confirmed live: M and W entries in the
        // same class split across the two rows). The generic face's regex
        // must no longer match U12/U10 codes, while still matching every
        // other Recurve/Barebow class and the dedicated U12/U10 face must
        // still match them.
        \pl_setup_70m_family(7, 3, 1, $this->plClassNames(), $this->plMixedClassNames());

        $faces = \CallLog::calls('CreateTargetFace');
        $generic = array_values(array_filter($faces, fn ($f) => $f[2] === 'Łuk klasyczny/barebow domyślna'));
        $this->assertCount(1, $generic);
        $genericRegex = '/' . str_replace('REG-', '', $generic[0][3]) . '/';

        foreach (['RM', 'RW', 'RU24M', 'RU21M', 'RU18M', 'RU15M', 'R40M', 'R80W', 'BM', 'BU18W'] as $code) {
            $this->assertSame(1, preg_match($genericRegex, $code), "generic default must still match {$code}");
        }
        foreach (['RU12M', 'RU12W', 'RU10M', 'RU10W'] as $code) {
            $this->assertSame(0, preg_match($genericRegex, $code), "generic default must no longer match {$code}");
        }

        $u12u10 = array_values(array_filter($faces, fn ($f) => $f[2] === 'Łuk klasyczny Dziecko (U12/łuk popularny)'));
        $this->assertCount(1, $u12u10);
        $specificRegex = '/' . str_replace('REG-', '', $u12u10[0][3]) . '/';
        foreach (['RU12M', 'RU12W', 'RU10M', 'RU10W'] as $code) {
            $this->assertSame(1, preg_match($specificRegex, $code), "U12/U10 face must still match {$code}");
        }
    }

    public function testPlSetup1440MastersClassNamesAreGenderedAndDistinct(): void
    {
        \CreateStandardClasses(7, 3);

        $named = [];
        foreach (\CallLog::calls('CreateClass') as $call) {
            if (preg_match('/^(40|50|60|70|80)[MW]$/', $call[5])) $named[$call[5]] = $call[7];
        }
        $this->assertNotSame($named['40M'], $named['40W'], '40M and 40W must not share a display name');
        $this->assertStringContainsString('mężczyźni', $named['40M']);
        $this->assertStringContainsString('kobiety', $named['40W']);
        // 70+ and 80+ are both open-ended and deliberately distinct labels
        // even though the age ranges overlap by design.
        $this->assertStringContainsString('70+', $named['70M']);
        $this->assertStringContainsString('80+', $named['80M']);
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

    public function testPlSetupKidsRoundU10NeverCreated(): void
    {
        \pl_setup_kids_round(7, 16, $this->plKidsClassNames());

        $this->assertCount(0, \CallLog::callsMatching('CreateEventNew', fn ($a) => str_contains($a[1], 'U10')));
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

        // U24 is Recurve-only (never eligible in Compound) — excluded here,
        // covered separately below.
        foreach (['M', 'W', 'U21M', 'U21W', 'U18M', 'U18W'] as $cl) {
            $r = \CallLog::callsMatching('CreateDistanceNew', fn ($a) => $a[2] === "R{$cl}");
            $c = \CallLog::callsMatching('CreateDistanceNew', fn ($a) => $a[2] === "C{$cl}");
            $this->assertCount(1, $r, "missing R{$cl} distance");
            $this->assertCount(1, $c, "missing C{$cl} distance");
            $this->assertSame($r[0][3], $c[0][3], "C{$cl} distances must equal R{$cl}'s");
        }
    }

    public function testPlSetup1440NoCompoundU24Distance(): void
    {
        // Regression: a shared R+C distance loop that iterates the same
        // class array for both divisions previously created CU24M/CU24W
        // distances even though U24 is Recurve-only and CreateStandardClasses()
        // never creates a Compound U24 class to use them.
        \pl_setup_1440(7, 1, $this->plClassNames());

        $this->assertCount(1, \CallLog::callsMatching('CreateDistanceNew', fn ($a) => $a[2] === 'RU24M'));
        $this->assertCount(0, \CallLog::callsMatching('CreateDistanceNew', fn ($a) => $a[2] === 'CU24M'));
        $this->assertCount(0, \CallLog::callsMatching('CreateDistanceNew', fn ($a) => $a[2] === 'CU24W'));
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

    public function testPlSetupIndoorU12AndU10DistinctDistancesNoElimination(): void
    {
        \pl_setup_indoor(7, 6, $this->plClassNames(), $this->plMixedClassNames());

        $u12 = \CallLog::callsMatching('CreateDistanceNew', fn ($a) => $a[2] === 'RU12M');
        $this->assertCount(1, $u12);
        $this->assertSame([['15m-1', 15], ['15m-2', 15]], $u12[0][3]);

        $u10 = \CallLog::callsMatching('CreateDistanceNew', fn ($a) => $a[2] === 'RU10M');
        $this->assertCount(1, $u10);
        $this->assertSame([['10m-1', 10], ['10m-2', 10]], $u10[0][3]);

        foreach (\CallLog::callsMatching('CreateEventNew', fn ($a) => str_contains($a[1], 'U12') || str_contains($a[1], 'U10')) as $ev) {
            $this->assertSame(0, $ev[4]['EvFinalFirstPhase']);
        }
    }

    public function testPlSetupIndoorU12TeamMatchesU12IndividualNotU15(): void
    {
        // Regression: U12 team events used to inherit U15's team config
        // unchanged (18 m, 40 cm, triple face) because both classes shared
        // one foreach loop and one options array. Team must match
        // individual U12's own 15 m / 80 cm / single face, and must differ
        // from U15's 18 m / 40 cm.
        \pl_setup_indoor(7, 6, $this->plClassNames(), $this->plMixedClassNames());

        $indiv = \CallLog::callsMatching('CreateEventNew', fn ($a) => $a[1] === 'RU12M' && ($a[4]['EvTeamEvent'] ?? 0) === 0)[0][4];
        $team  = \CallLog::callsMatching('CreateEventNew', fn ($a) => $a[1] === 'RU12M' && ($a[4]['EvTeamEvent'] ?? 0) === 1)[0][4];

        $this->assertSame($indiv['EvDistance'], $team['EvDistance']);
        $this->assertSame($indiv['EvTargetSize'], $team['EvTargetSize']);
        $this->assertSame($indiv['EvFinalTargetType'], $team['EvFinalTargetType']);
        $this->assertSame(15, $team['EvDistance']);
        $this->assertSame(80, $team['EvTargetSize']);
    }

    public function testPlSetupIndoorU10RecurveOnly(): void
    {
        \pl_setup_indoor(7, 6, $this->plClassNames(), $this->plMixedClassNames());

        $this->assertCount(0, \CallLog::callsMatching('CreateEventNew', fn ($a) => str_starts_with($a[1], 'C') && str_contains($a[1], 'U10')));
        $this->assertCount(0, \CallLog::callsMatching('CreateEventNew', fn ($a) => str_starts_with($a[1], 'B') && str_contains($a[1], 'U10')));
    }

    public function testPlSetupIndoorNoU12OrU10MixedTeam(): void
    {
        \pl_setup_indoor(7, 6, $this->plClassNames(), $this->plMixedClassNames());

        $this->assertCount(0, \CallLog::callsMatching('CreateEventNew', fn ($a) => (str_contains($a[1], 'U12') || str_contains($a[1], 'U10')) && ($a[4]['EvMixedTeam'] ?? 0) === 1));
    }
}
