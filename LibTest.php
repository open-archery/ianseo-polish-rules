<?php

namespace PL\Tests;

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
}
