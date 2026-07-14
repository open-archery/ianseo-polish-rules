<?php

namespace PL\Tests\Import;

require_once __DIR__ . '/Fun_BibImport.php';

final class BibImportTest extends \PlTestCase
{
    private function sampleLue(array $overrides = []): object
    {
        return (object) array_merge([
            'LueCode'       => '5083',
            'LueIocCode'    => 'POL',
            'LueFamilyName' => 'Kowalski',
            'LueName'       => 'Jan',
            'LueSex'        => 0,
            'LueCtrlCode'   => '2003XXXX',
            'LueStatus'     => 1,
            'LueCountry'    => 'CSB',
            'LueCoDescr'    => 'Klub Sportowy Orzeł (Warszawa)',
        ], $overrides);
    }

    // --- pl_bibimport_lookup ---------------------------------------------

    public function testLookupFindsExistingLicence(): void
    {
        \FakeDb::on('/FROM LookUpEntries/', [(array) $this->sampleLue()]);

        $lue = \pl_bibimport_lookup('5083');

        $this->assertNotNull($lue);
        $this->assertSame('Kowalski', $lue->LueFamilyName);
        $this->assertCount(1, \FakeDb::executed("/LueCode = '5083'/"));
        $this->assertCount(1, \FakeDb::executed("/LueIocCode = 'POL'/"));
    }

    public function testLookupReturnsNullWhenNotFound(): void
    {
        $this->assertNull(\pl_bibimport_lookup('9999'));
    }

    // --- pl_bibimport_is_duplicate -----------------------------------------

    public function testIsDuplicateTrueWhenEntryExists(): void
    {
        \FakeDb::on('/FROM Entries/', [['EnId' => 1]]);

        $this->assertTrue(\pl_bibimport_is_duplicate('5083', 7));
        $this->assertCount(1, \FakeDb::executed("/EnCode\\s+= '5083'/"));
    }

    public function testIsDuplicateFalseWhenNoEntry(): void
    {
        $this->assertFalse(\pl_bibimport_is_duplicate('5083', 7));
    }

    // --- pl_bibimport_resolve_class -----------------------------------------

    public function testResolveClassReturnsMatchingRow(): void
    {
        $_SESSION['TourRealWhenTo'] = '2024-07-14';
        \FakeDb::on('/FROM Classes/', [['ClId' => 5]]);

        $row = \pl_bibimport_resolve_class(7, 0, '2003', 'R');

        $this->assertNotNull($row);
        $this->assertSame(5, $row->ClId);
        $this->assertCount(1, \FakeDb::executed('/ClAgeFrom <= 21\s+AND ClAgeTo\s+>= 21/'));
        $this->assertCount(1, \FakeDb::executed('/ClSex IN \(-1, 0\)/'));
    }

    public function testResolveClassReturnsNullWhenNoMatch(): void
    {
        $_SESSION['TourRealWhenTo'] = '2024-07-14';

        $this->assertNull(\pl_bibimport_resolve_class(7, 0, '2003', 'R'));
    }

    public function testResolveClassShortCircuitsOnInvalidBirthYear(): void
    {
        $_SESSION['TourRealWhenTo'] = '2024-07-14';

        $this->assertNull(\pl_bibimport_resolve_class(7, 0, '0', 'R'));
        $this->assertSame([], \FakeDb::$queries);
    }

    public function testResolveClassShortCircuitsOnInvalidTourYear(): void
    {
        $_SESSION['TourRealWhenTo'] = '';

        $this->assertNull(\pl_bibimport_resolve_class(7, 0, '2003', 'R'));
        $this->assertSame([], \FakeDb::$queries);
    }

    // --- pl_bibimport_upsert_country ---------------------------------------

    public function testUpsertCountryReturnsCachedIdWithoutQuerying(): void
    {
        $cache = ['CSB' => 42];

        $coId = \pl_bibimport_upsert_country(7, 'CSB', 'Czarna Strzała Bytom', $cache);

        $this->assertSame(42, $coId);
        $this->assertSame([], \FakeDb::$queries);
    }

    public function testUpsertCountryReturnsExistingIdFromDb(): void
    {
        \FakeDb::on('/FROM Countries/', [['CoId' => 42]]);
        $cache = [];

        $coId = \pl_bibimport_upsert_country(7, 'CSB', 'Czarna Strzała Bytom', $cache);

        $this->assertSame(42, $coId);
        $this->assertSame(42, $cache['CSB']);
        $this->assertCount(0, \FakeDb::executed('/INSERT INTO Countries/'));
    }

    public function testUpsertCountryInsertsWhenMissingAndCachesResult(): void
    {
        \FakeDb::willInsertId(99);
        $cache = [];

        $coId = \pl_bibimport_upsert_country(7, 'CSB', 'Czarna Strzała Bytom', $cache);

        $this->assertSame(99, $coId);
        $this->assertSame(99, $cache['CSB']);
        $this->assertCount(1, \FakeDb::executed('/INSERT INTO Countries/'));
    }

    public function testUpsertCountryTruncatesCodeToSixChars(): void
    {
        \FakeDb::willInsertId(1);
        $cache = [];

        \pl_bibimport_upsert_country(7, 'TOOLONGCODE', 'Some Club', $cache);

        $this->assertArrayHasKey('TOOLON', $cache);
        $this->assertCount(1, \FakeDb::executed("/INSERT INTO Countries.*CoCode\\s+= 'TOOLON'/s"));
    }

    // --- pl_bibimport_create_entry / create_qualification -------------------

    public function testCreateEntryMapsFieldsAndReturnsNewId(): void
    {
        \FakeDb::willInsertId(123);
        $lue = $this->sampleLue();

        $enId = \pl_bibimport_create_entry(7, $lue, 'R', '5', 42);

        $this->assertSame(123, $enId);
        $writes = \FakeDb::executed('/INSERT INTO Entries/');
        $this->assertCount(1, $writes);
        $this->assertStringContainsString("EnFirstName  = 'Kowalski'", $writes[0]);
        $this->assertStringContainsString("EnName       = 'Jan'", $writes[0]);
        $this->assertStringContainsString('EnCountry    = 42', $writes[0]);
    }

    public function testCreateQualificationInsertsSessionLink(): void
    {
        \pl_bibimport_create_qualification(123, 2);

        $writes = \FakeDb::executed('/INSERT INTO Qualifications/');
        $this->assertCount(1, $writes);
        $this->assertStringContainsString('VALUES (123, 2)', $writes[0]);
    }

    // --- pl_bibimport_run (orchestration) -----------------------------------

    public function testRunReturnsZeroedResultForEmptyInput(): void
    {
        $result = \pl_bibimport_run(7, "\n  \n", 'R', 1);

        $this->assertSame(0, $result['imported']);
        $this->assertSame([], \FakeDb::$queries);
    }

    public function testRunCollectsUnmatchedCodesWithoutOpeningTransaction(): void
    {
        $result = \pl_bibimport_run(7, '9999', 'R', 1);

        $this->assertSame(['9999'], $result['unmatched']);
        $this->assertSame(0, $result['imported']);
        $this->assertSame([], \FakeDb::$tx);
    }

    public function testRunCollectsDuplicates(): void
    {
        \FakeDb::on('/FROM LookUpEntries/', [(array) $this->sampleLue()]);
        \FakeDb::on('/FROM Entries/', [['EnId' => 1]]);

        $result = \pl_bibimport_run(7, '5083', 'R', 1);

        $this->assertCount(1, $result['duplicates']);
        $this->assertSame('5083', $result['duplicates'][0]['code']);
        $this->assertSame('Kowalski Jan', $result['duplicates'][0]['name']);
        $this->assertSame(0, $result['imported']);
    }

    public function testRunImportsSuccessfullyAndCommits(): void
    {
        $_SESSION['TourRealWhenTo'] = '2024-07-14';
        \FakeDb::on('/FROM LookUpEntries/', [(array) $this->sampleLue()]);
        \FakeDb::on('/FROM Classes/', [['ClId' => 5]]);
        \FakeDb::on('/FROM Countries/', [['CoId' => 42]]);
        \FakeDb::willInsertId(123);

        $result = \pl_bibimport_run(7, '5083', 'R', 1);

        $this->assertSame(1, $result['imported']);
        $this->assertSame([], $result['classUnresolved']);
        $this->assertNull($result['error']);
        $this->assertSame(['begin', 'commit'], \FakeDb::$tx);
        $this->assertCount(1, \FakeDb::executed('/INSERT INTO Qualifications/'));
    }

    public function testRunTracksClassUnresolvedButStillImports(): void
    {
        $_SESSION['TourRealWhenTo'] = '2024-07-14';
        \FakeDb::on('/FROM LookUpEntries/', [(array) $this->sampleLue()]);
        // No handler for FROM Classes -> resolve_class returns null (unresolved).
        \FakeDb::on('/FROM Countries/', [['CoId' => 42]]);
        \FakeDb::willInsertId(123);

        $result = \pl_bibimport_run(7, '5083', 'R', 1);

        $this->assertSame(1, $result['imported']);
        $this->assertCount(1, $result['classUnresolved']);
        $this->assertSame('5083', $result['classUnresolved'][0]['code']);
        $this->assertSame(['begin', 'commit'], \FakeDb::$tx);
    }

    public function testRunRollsBackAndReportsErrorOnWriteFailure(): void
    {
        $_SESSION['TourRealWhenTo'] = '2024-07-14';
        \FakeDb::on('/FROM LookUpEntries/', [(array) $this->sampleLue()]);
        \FakeDb::on('/FROM Classes/', [['ClId' => 5]]);
        \FakeDb::on('/FROM Countries/', [['CoId' => 42]]);
        \FakeDb::throwOn('/INSERT INTO Entries/', 'duplicate key');

        $result = \pl_bibimport_run(7, '5083', 'R', 1);

        $this->assertSame(0, $result['imported']);
        $this->assertSame('duplicate key', $result['error']);
        $this->assertSame(['begin', 'rollback'], \FakeDb::$tx);
    }
}
