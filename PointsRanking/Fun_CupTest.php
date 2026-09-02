<?php

require_once __DIR__ . '/Fun_Cup.php';

/**
 * Data-layer tests for the cup module: table install, per-tournament config,
 * qualification scores, snapshot building, round storage and the CSV transport.
 */
final class Fun_CupTest extends PlTestCase
{
    private function resultRow(array $overrides = [])
    {
        return array_merge([
            'enId' => 10, 'name' => 'Jan Kowalski', 'code' => 'PL0001', 'category' => 'RM',
            'club_id' => 5, 'club_code' => 'KLUB1', 'club_name' => 'Klub Pierwszy',
            'place' => 1, 'points' => 25, 'rank' => 1,
        ], $overrides);
    }

    private function separateResult(array $indRows, array $mixRows = [])
    {
        $reports = [[
            'kind' => 'SEPARATE', 'label' => 'Klasyfikacja indywidualna', 'subject' => 'IND',
            'sections' => [['category' => 'RM', 'label' => 'Senior', 'order' => [0, 1], 'rows' => $indRows]],
        ]];
        if (!empty($mixRows)) {
            $reports[] = [
                'kind' => 'SEPARATE', 'label' => 'Klasyfikacja mikstów', 'subject' => 'MIXED',
                'sections' => [['category' => 'RMX', 'label' => 'Mikst', 'order' => [0, 9], 'rows' => $mixRows]],
            ];
        }
        return ['reports' => $reports, 'warnings' => []];
    }

    // --- Auto-install ------------------------------------------------------

    public function testEnsureTablesCreatesAllThreeTables()
    {
        pl_cup_ensure_tables();

        $this->assertCount(1, FakeDb::executed('/CREATE TABLE IF NOT EXISTS PLCupConfig/'));
        $this->assertCount(1, FakeDb::executed('/CREATE TABLE IF NOT EXISTS PLCupRound/'));
        $this->assertCount(1, FakeDb::executed('/CREATE TABLE IF NOT EXISTS PLCupBarrage/'));

        $this->assertCount(1, FakeDb::executed('/PRIMARY KEY \(PlCcTournament\)/'));
        $this->assertCount(1, FakeDb::executed('/PRIMARY KEY \(PlCrEdition, PlCrRound, PlCrClassification, PlCrCategory, PlCrIdentity\)/'));
        $this->assertCount(1, FakeDb::executed('/PRIMARY KEY \(PlCbEdition, PlCbClassification, PlCbCategory, PlCbIdentity\)/'));
    }

    public function testEnsureTablesSkipsCreationWhenTablesExist()
    {
        FakeDb::on('/SHOW TABLES LIKE/', [['x' => 'PLCupConfig']]);

        pl_cup_ensure_tables();

        $this->assertSame([], FakeDb::executed('/CREATE TABLE/'));
    }

    // --- Configuration -----------------------------------------------------

    public function testConfigDefaultsToTheTournamentEndYear()
    {
        $_SESSION['TourRealWhenTo'] = '2026-09-13';

        $config = pl_cup_get_config(1);

        $this->assertSame(2026, $config['Edition']);
        $this->assertSame(0, $config['Round']);
        $this->assertSame('', $config['DiplomaName']);
    }

    public function testConfigIsReadBack()
    {
        FakeDb::on('/SELECT PlCcEdition/', [[
            'PlCcEdition' => 2025, 'PlCcRound' => 4, 'PlCcDiplomaName' => 'Puchar Polski 2025',
        ]]);

        $config = pl_cup_get_config(1);

        $this->assertSame(['Edition' => 2025, 'Round' => 4, 'DiplomaName' => 'Puchar Polski 2025'], $config);
    }

    public function testSetConfigInsertsThenUpdates()
    {
        pl_cup_set_config(7, 2026, 4, 'Puchar Polski 2026');
        $this->assertCount(1, FakeDb::executed('/INSERT INTO PLCupConfig .*VALUES \(7, 2026, 4/s'));

        FakeDb::on('/SELECT PlCcTournament FROM PLCupConfig/', [['PlCcTournament' => 7]]);
        pl_cup_set_config(7, 2026, 2, 'Inna nazwa');
        $this->assertCount(1, FakeDb::executed('/UPDATE PLCupConfig SET PlCcEdition = 2026, PlCcRound = 2/'));
    }

    public function testSetConfigRejectsARoundOutsideTheCup()
    {
        pl_cup_set_config(7, 2026, 9, '');

        $this->assertCount(1, FakeDb::executed('/VALUES \(7, 2026, 0,/'));
    }

    // --- Qualification scores ----------------------------------------------

    public function testLoadQualScoresBuildsBothMaps()
    {
        FakeDb::on('/FROM Qualifications/', [['EnId' => 10, 'QualScore' => 645], ['EnId' => 11, 'QualScore' => 638]]);
        FakeDb::on('/FROM Teams/', [['ClubId' => 5, 'Event' => 'RMX', 'QualScore' => 1290]]);

        $scores = pl_cup_load_qual_scores(1);

        $this->assertSame([10 => 645, 11 => 638], $scores['ind']);
        $this->assertSame(['5_RMX' => 1290], $scores['mix']);
    }

    public function testLoadQualScoresReadsQualificationsNotIndividuals()
    {
        // Individuals.IndScore is left at its -1 sentinel by this ruleset's
        // competitions; the round total lives in Qualifications.QuScore.
        pl_cup_load_qual_scores(1);

        $this->assertCount(1, FakeDb::executed('/FROM Qualifications\s+INNER JOIN Entries/s'));
        $this->assertSame([], FakeDb::executed('/FROM Individuals/'));
    }

    public function testLoadQualScoresTreatsASentinelAsNoScore()
    {
        FakeDb::on('/FROM Qualifications/', [['EnId' => 10, 'QualScore' => -1]]);
        FakeDb::on('/FROM Teams/', [['ClubId' => 5, 'Event' => 'RMX', 'QualScore' => -1]]);

        $scores = pl_cup_load_qual_scores(1);

        $this->assertSame([10 => 0], $scores['ind']);
        $this->assertSame(['5_RMX' => 0], $scores['mix']);
    }

    // --- Snapshot ----------------------------------------------------------

    public function testSnapshotRowsCarryIdentityPlacePointsAndQualification()
    {
        $result = $this->separateResult(
            [$this->resultRow()],
            [[
                'category' => 'RMX', 'club_id' => 5, 'club_code' => 'KLUB1', 'club_name' => 'Klub Pierwszy',
                'points' => 21, 'place' => 2, 'rank' => 2, 'members' => [], 'roster_empty' => false,
                'name' => 'Anna K. / Jan K.',
            ]]
        );

        $rows = pl_cup_rows_from_result($result, ['ind' => [10 => 645], 'mix' => ['5_RMX' => 1290]]);

        $this->assertSame([
            ['classification' => 'ind', 'category' => 'RM', 'identity' => 'PL0001', 'name' => 'Jan Kowalski',
             'club_name' => 'Klub Pierwszy', 'place' => 1, 'points' => 25, 'qual' => 645],
            // No pair names: a mixed cup row belongs to the club, and its two
            // athletes may differ between rounds.
            ['classification' => 'mix', 'category' => 'RMX', 'identity' => 'KLUB1', 'name' => '',
             'club_name' => 'Klub Pierwszy', 'place' => 2, 'points' => 21, 'qual' => 1290],
        ], $rows);
    }

    public function testSnapshotKeepsPlacedRowsThatScoredNoPoints()
    {
        // Their place and qualification score can still decide a tie-break
        // ("w dowolnej rundzie"), so the round has to carry them.
        $result = $this->separateResult([
            $this->resultRow(),
            $this->resultRow(['enId' => 11, 'code' => 'PL0002', 'points' => 0, 'place' => 40]),
        ]);

        $rows = pl_cup_rows_from_result($result, ['ind' => [11 => 540], 'mix' => []]);

        $this->assertSame(['PL0001', 'PL0002'], array_column($rows, 'identity'));
        $this->assertSame(['place' => 40, 'points' => 0, 'qual' => 540], [
            'place' => $rows[1]['place'], 'points' => $rows[1]['points'], 'qual' => $rows[1]['qual'],
        ]);
    }

    public function testSnapshotSkipsRowsWithoutAResult()
    {
        $result = $this->separateResult([
            $this->resultRow(),
            $this->resultRow(['enId' => 11, 'code' => 'PL0002', 'points' => 0, 'place' => 0]),
            $this->resultRow(['enId' => 12, 'code' => 'PL0003', 'points' => 0, 'place' => 29999]),
        ]);

        $rows = pl_cup_rows_from_result($result, ['ind' => [], 'mix' => []]);

        $this->assertSame(['PL0001'], array_column($rows, 'identity'));
    }

    public function testTheSameLicenceTwiceInOneCategoryIsRejected()
    {
        // Also the stored round's primary key - a duplicate INSERT dies inside
        // ianseo's safe_w_sql() without an exception to roll back.
        $row = [
            'classification' => 'ind', 'category' => 'RM', 'identity' => 'PL0001', 'name' => 'Jan Kowalski',
            'club_name' => 'Klub Pierwszy', 'place' => 1, 'points' => 25, 'qual' => 0,
        ];

        $errors = pl_cup_validate_rows([$row, $row]);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('Ten sam numer licencji dwa razy', $errors[0]);
    }

    public function testMissingLicenceRejectsTheWholeSet()
    {
        $rows = pl_cup_rows_from_result(
            $this->separateResult([$this->resultRow(['code' => ''])]),
            ['ind' => [], 'mix' => []]
        );

        $errors = pl_cup_validate_rows($rows);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('Brak numeru licencji', $errors[0]);
        $this->assertStringContainsString('Jan Kowalski', $errors[0]);
    }

    public function testMissingClubCodeOnAMixedRowIsRejected()
    {
        $errors = pl_cup_validate_rows([[
            'classification' => 'mix', 'category' => 'RMX', 'identity' => '', 'name' => 'Para',
            'club_name' => 'Klub Pierwszy', 'place' => 1, 'points' => 25, 'qual' => 0,
        ]]);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('Brak kodu klubu', $errors[0]);
    }

    public function testTwoMixedPairsOfOneClubAreRejected()
    {
        $row = [
            'classification' => 'mix', 'category' => 'RMX', 'identity' => 'KLUB1', 'name' => 'Para',
            'club_name' => 'Klub Pierwszy', 'place' => 1, 'points' => 25, 'qual' => 0,
        ];

        $errors = pl_cup_validate_rows([$row, $row]);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('Dwa miksty tego samego klubu', $errors[0]);
    }

    // --- Round storage -----------------------------------------------------

    private function storedRow($category = 'RM', $identity = 'PL0001', $classification = 'ind')
    {
        return [
            'classification' => $classification, 'category' => $category, 'identity' => $identity,
            'name' => 'Jan Kowalski', 'club_name' => 'Klub Pierwszy', 'place' => 1, 'points' => 25, 'qual' => 645,
        ];
    }

    public function testStoreImportDeletesThenInserts()
    {
        $error = pl_cup_store_import(2026, 4, [$this->storedRow()], 'IV Runda PP (T4RPP)', 131);

        $this->assertSame('', $error);
        $this->assertSame(['begin', 'commit'], FakeDb::$tx);

        $writes = FakeDb::executed('/DELETE FROM PLCupRound|INSERT INTO PLCupRound/');
        $this->assertCount(2, $writes);
        $this->assertStringStartsWith('DELETE FROM PLCupRound', $writes[0]);
        $this->assertStringStartsWith('INSERT INTO PLCupRound', $writes[1]);
    }

    public function testStoreImportReplacesOnlyItsOwnCategories()
    {
        // A round is assembled from several sources: the juniors from an ianseo
        // competition, the barebow and compound rounds from their own files.
        pl_cup_store_import(2026, 1, [$this->storedRow('BM', 'PL9'), $this->storedRow('BW', 'PL8')], 'CSV', 131);

        $delete = FakeDb::executed('/DELETE FROM PLCupRound/')[0];
        $this->assertStringContainsString("PlCrCategory = 'BM'", $delete);
        $this->assertStringContainsString("PlCrCategory = 'BW'", $delete);
        $this->assertStringNotContainsString('RU21M', $delete);
        $this->assertMatchesRegularExpression('/PlCrRound = 1\s+AND \(/', $delete);
    }

    public function testStoreImportRecordsItsProvenance()
    {
        pl_cup_store_import(2026, 2, [$this->storedRow()], 'II Runda PP (2RPP)', 77);

        $insert = FakeDb::executed('/INSERT INTO PLCupRound/')[0];
        $this->assertStringContainsString('PlCrImportedAt, PlCrSource, PlCrImportTournament', $insert);
        $this->assertStringContainsString("'II Runda PP (2RPP)'", $insert);
        $this->assertStringContainsString(', 77)', $insert);
    }

    public function testStoreImportWithoutRowsChangesNothing()
    {
        $this->assertSame('', pl_cup_store_import(2026, 1, [], 'CSV', 131));

        $this->assertSame([], FakeDb::executed('/DELETE FROM PLCupRound|INSERT INTO PLCupRound/'));
    }

    public function testStoreImportRollsBackOnAFailedWrite()
    {
        FakeDb::throwOn('/INSERT INTO PLCupRound/', 'duplicate key');

        $error = pl_cup_store_import(2026, 4, [$this->storedRow()], 'CSV', 131);

        $this->assertStringContainsString('duplicate key', $error);
        $this->assertSame(['begin', 'rollback'], FakeDb::$tx);
    }

    // --- Import history ----------------------------------------------------

    public function testImportsAreGroupedPerRoundSourceAndTimestamp()
    {
        FakeDb::on('/GROUP BY PlCrRound, PlCrSource/', [[
            'PlCrRound' => 1, 'PlCrSource' => 'I Runda PP (1RPPJJM)', 'PlCrImportTournament' => 124,
            'PlCrImportedAt' => '2026-09-02 10:00:00', 'RowCnt' => 62,
            'Categories' => 'ind:RU18M,ind:RU18W,ind:RU21M,ind:RU21W',
        ], [
            'PlCrRound' => 1, 'PlCrSource' => 'bloczki_r1.csv', 'PlCrImportTournament' => 131,
            'PlCrImportedAt' => '2026-09-02 11:30:00', 'RowCnt' => 42, 'Categories' => 'ind:BM,mix:BX',
        ]]);

        $imports = pl_cup_load_imports(2026);

        $this->assertCount(2, $imports);
        $this->assertSame(62, $imports[0]['rows']);
        $this->assertSame(['RU18M', 'RU18W', 'RU21M', 'RU21W'], array_column($imports[0]['categories'], 'category'));
        $this->assertSame('bloczki_r1.csv', $imports[1]['source']);
        $this->assertSame(
            [['classification' => 'ind', 'category' => 'BM'], ['classification' => 'mix', 'category' => 'BX']],
            $imports[1]['categories']
        );
    }

    public function testDeleteImportTargetsOneSourceAndTimestamp()
    {
        pl_cup_delete_import(2026, 1, 'bloczki_r1.csv', '2026-09-02 11:30:00');

        $delete = FakeDb::executed('/DELETE FROM PLCupRound/')[0];
        $this->assertStringContainsString('PlCrRound = 1', $delete);
        $this->assertStringContainsString("PlCrSource = 'bloczki_r1.csv'", $delete);
        $this->assertStringContainsString("PlCrImportedAt = '2026-09-02 11:30:00'", $delete);
    }

    public function testDeleteImportHandlesRowsStoredBeforeProvenanceExisted()
    {
        pl_cup_delete_import(2026, 1, '', '');

        $this->assertStringContainsString('PlCrImportedAt IS NULL', FakeDb::executed('/DELETE FROM PLCupRound/')[0]);
    }

    public function testDeleteRoundRemovesEverythingOfThatRound()
    {
        pl_cup_delete_round(2026, 3);

        $delete = FakeDb::executed('/DELETE FROM PLCupRound/')[0];
        $this->assertStringContainsString('PlCrEdition = 2026', $delete);
        $this->assertStringContainsString('PlCrRound = 3', $delete);
        $this->assertStringNotContainsString('PlCrSource', $delete);
    }

    public function testLoadRoundsAreOrderedByCategoryThenPlace()
    {
        pl_cup_load_rounds(2026);

        $this->assertMatchesRegularExpression(
            '/ORDER BY PlCrRound, PlCrClassification, PlCrCategory, PlCrPlace/',
            FakeDb::executed('/FROM PLCupRound/')[0]
        );
    }

    public function testCsvWriteClampsALegacySentinelScore()
    {
        $rows = $this->csvRows();
        $rows[0]['qual'] = -1;

        $csv = pl_cup_csv_write($rows);

        $this->assertStringContainsString('Klub Pierwszy;1;25;0', $csv);
        $this->assertSame([], pl_cup_csv_parse($csv, ['ind' => ['RM'], 'mix' => []])['errors']);
    }

    public function testLoadRoundsReadsStoredRows()
    {
        FakeDb::on('/FROM PLCupRound/', [[
            'PlCrRound' => 2, 'PlCrClassification' => 'ind', 'PlCrCategory' => 'RM', 'PlCrIdentity' => 'PL0001',
            'PlCrName' => 'Jan Kowalski', 'PlCrClubName' => 'Klub Pierwszy', 'PlCrPlace' => 3,
            'PlCrPoints' => 18, 'PlCrQualScore' => 630,
        ]]);

        $rows = pl_cup_load_rounds(2026, 2);

        $this->assertSame(2, $rows[0]['round']);
        $this->assertSame(18, $rows[0]['points']);
        $this->assertSame(630, $rows[0]['qual']);
    }

    // --- Baraż -------------------------------------------------------------

    public function testBarrageOutcomeIsUpsertedNotDeletedAndReinserted()
    {
        pl_cup_set_barrage(2026, 'ind', 'RU18M', 'PL0001', 1);

        // Delete-then-insert would lose the recorded outcome if the insert failed.
        $this->assertCount(1, FakeDb::executed('/INSERT INTO PLCupBarrage .*ON DUPLICATE KEY UPDATE PlCbOrder/s'));
        $this->assertSame([], FakeDb::executed('/DELETE FROM PLCupBarrage/'));
    }

    public function testBarrageOutcomeIsClearedByAZeroOrder()
    {
        pl_cup_set_barrage(2026, 'ind', 'RU18M', 'PL0001', 0);

        $this->assertCount(1, FakeDb::executed('/DELETE FROM PLCupBarrage/'));
        $this->assertSame([], FakeDb::executed('/INSERT INTO PLCupBarrage/'));
    }

    public function testBarragesAreLoadedKeyedByIdentity()
    {
        FakeDb::on('/FROM PLCupBarrage/', [[
            'PlCbClassification' => 'ind', 'PlCbCategory' => 'RU18M', 'PlCbIdentity' => 'PL0001', 'PlCbOrder' => 2,
        ]]);

        $this->assertSame(['ind|RU18M|PL0001' => 2], pl_cup_load_barrages(2026));
    }

    // --- CSV ---------------------------------------------------------------

    private function csvRows()
    {
        return [[
            'classification' => 'ind', 'category' => 'RM', 'identity' => 'PL0001', 'name' => 'Jan Kowalski',
            'club_name' => 'Klub Pierwszy', 'place' => 1, 'points' => 25, 'qual' => 645,
        ]];
    }

    public function testCsvWriteEmitsBomHeaderAndRow()
    {
        $csv = pl_cup_csv_write($this->csvRows());

        $this->assertStringStartsWith("\xEF\xBB\xBF" . 'Klasyfikacja;Kategoria;Identyfikator;Nazwa;Klub;Miejsce;Punkty;Kwalifikacje', $csv);
        $this->assertStringContainsString('ind;RM;PL0001;Jan Kowalski;Klub Pierwszy;1;25;645', $csv);
    }

    public function testCsvCarriesTheSourceCompetition()
    {
        $csv = pl_cup_csv_write($this->csvRows(), 'I Runda PP (1RPPJJM)');

        $this->assertStringContainsString('#Zawody: I Runda PP (1RPPJJM)', $csv);

        $parsed = pl_cup_csv_parse($csv, ['ind' => ['RM'], 'mix' => []]);
        $this->assertSame('I Runda PP (1RPPJJM)', $parsed['source']);
        $this->assertSame($this->csvRows(), $parsed['rows']);
    }

    public function testCsvWithoutASourceLineParsesAsBefore()
    {
        $parsed = pl_cup_csv_parse(pl_cup_csv_write($this->csvRows()), ['ind' => ['RM'], 'mix' => []]);

        $this->assertSame('', $parsed['source']);
        $this->assertSame([], $parsed['errors']);
    }

    public function testCsvIgnoresOtherCommentLines()
    {
        $csv = "# wyeksportowane ręcznie\r\nind;RM;PL0001;Jan;Klub;1;25;645\r\n";

        $parsed = pl_cup_csv_parse($csv, ['ind' => ['RM'], 'mix' => []]);

        $this->assertSame([], $parsed['errors']);
        $this->assertCount(1, $parsed['rows']);
    }

    public function testCsvRoundTripKeepsTheRowsIdentical()
    {
        $parsed = pl_cup_csv_parse(pl_cup_csv_write($this->csvRows()), ['ind' => ['RM'], 'mix' => []]);

        $this->assertSame([], $parsed['errors']);
        $this->assertSame($this->csvRows(), $parsed['rows']);
    }

    public function testCsvQuotesSeparatorsInNames()
    {
        $rows = $this->csvRows();
        $rows[0]['club_name'] = 'Klub "A"; Oddział';

        $parsed = pl_cup_csv_parse(pl_cup_csv_write($rows), ['ind' => ['RM'], 'mix' => []]);

        $this->assertSame($rows, $parsed['rows']);
    }

    public function testCsvAcceptsACommaDecimalSeparator()
    {
        $csv = "Klasyfikacja;Kategoria;Identyfikator;Nazwa;Klub;Miejsce;Punkty;Kwalifikacje\r\nind;RM;PL0001;Jan;Klub;1;25,0;645\r\n";

        $parsed = pl_cup_csv_parse($csv, ['ind' => ['RM'], 'mix' => []]);

        $this->assertSame([], $parsed['errors']);
        $this->assertSame(25, $parsed['rows'][0]['points']);
    }

    public function testCsvRejectsAWrongColumnCount()
    {
        $csv = "ind;RM;PL0001;Jan;Klub;1;25\r\n";

        $parsed = pl_cup_csv_parse($csv, ['ind' => ['RM'], 'mix' => []]);

        $this->assertCount(1, $parsed['errors']);
        $this->assertStringContainsString('Wiersz 1', $parsed['errors'][0]);
        $this->assertStringContainsString('8 kolumn', $parsed['errors'][0]);
    }

    public function testCsvRejectsAnUnknownClassification()
    {
        $csv = "tea;RM;PL0001;Jan;Klub;1;25;645\r\n";

        $parsed = pl_cup_csv_parse($csv, ['ind' => ['RM'], 'mix' => []]);

        $this->assertStringContainsString('nieznana klasyfikacja', $parsed['errors'][0]);
    }

    public function testCsvRejectsAnUnknownCategoryWithItsLineNumber()
    {
        $csv = "Klasyfikacja;Kategoria;Identyfikator;Nazwa;Klub;Miejsce;Punkty;Kwalifikacje\r\n"
            . "ind;RM;PL0001;Jan;Klub;1;25;645\r\n"
            . "ind;XX;PL0002;Adam;Klub;2;21;630\r\n";

        $parsed = pl_cup_csv_parse($csv, ['ind' => ['RM'], 'mix' => []]);

        $this->assertCount(1, $parsed['errors']);
        $this->assertStringContainsString('Wiersz 3', $parsed['errors'][0]);
        $this->assertStringContainsString('"XX"', $parsed['errors'][0]);
    }

    public function testCsvKeepsAFieldContainingALineBreak()
    {
        $rows = $this->csvRows();
        $rows[0]['club_name'] = "KS Alfa
Oddział Zielona Góra";

        $parsed = pl_cup_csv_parse(pl_cup_csv_write($rows), ['ind' => ['RM'], 'mix' => []]);

        $this->assertSame([], $parsed['errors']);
        $this->assertSame($rows, $parsed['rows']);
    }

    public function testCsvSkipsRowsWithoutAResult()
    {
        // A result list from another host names everyone who entered; an archer
        // who did not finish has nothing to contribute and is not an error.
        foreach (['DNF', 'dns', 'DSQ', 'ABS', '-', '0', '29999'] as $place) {
            $parsed = pl_cup_csv_parse("ind;RM;PL0001;Jan;Klub;$place;;\r\n", ['ind' => ['RM'], 'mix' => []]);

            $this->assertSame([], $parsed['errors'], $place);
            $this->assertSame([], $parsed['rows'], $place);
            $this->assertSame(1, $parsed['skipped'], $place);
        }
    }

    public function testSkippedRowsAreReportedOnce()
    {
        $csv = "ind;RM;PL0001;Jan Kowalski;Klub;1;;600\r\n"
            . "ind;RM;PL0002;Adam Nowak;Klub;DNS;;\r\n"
            . "ind;RM;PL0003;Piotr Wilk;Klub;DNF;;\r\n";

        $parsed = pl_cup_csv_parse($csv, ['ind' => ['RM'], 'mix' => []]);

        $this->assertCount(1, $parsed['rows']);
        $this->assertSame(2, $parsed['skipped']);
        $this->assertStringContainsString('Pominięto 2 wierszy bez wyniku', $parsed['warnings'][0]);
    }

    public function testAMistypedPlaceIsStillAnError()
    {
        // "1O" is a typo, not a marker - it must not be silently dropped.
        $parsed = pl_cup_csv_parse("ind;RM;PL0001;Jan;Klub;1O;;600\r\n", ['ind' => ['RM'], 'mix' => []]);

        $this->assertSame(0, $parsed['skipped']);
        $this->assertStringContainsString('kolumna Miejsce nie jest liczbą', $parsed['errors'][0]);
    }

    public function testCsvRejectsANegativeQualificationScore()
    {
        $parsed = pl_cup_csv_parse("ind;RM;PL0001;Jan;Klub;1;25;-1\r\n", ['ind' => ['RM'], 'mix' => []]);

        $this->assertSame([], $parsed['rows']);
        $this->assertStringContainsString('ujemny wynik kwalifikacji', $parsed['errors'][0]);
    }

    public function testANegativePointsColumnOnlyWarns()
    {
        // The column is a cross-check now - the stored value comes from the place.
        $parsed = pl_cup_csv_parse("ind;RM;PL0001;Jan;Klub;1;-5;645
", ['ind' => ['RM'], 'mix' => []]);

        $this->assertSame([], $parsed['errors']);
        $this->assertSame(25, $parsed['rows'][0]['points']);
        $this->assertNotEmpty($parsed['warnings']);
    }

    public function testCsvAcceptsAPlacedRowWithoutPoints()
    {
        $csv = "ind;RM;PL0001;Jan;Klub;40;0;540
";

        $parsed = pl_cup_csv_parse($csv, ['ind' => ['RM'], 'mix' => []]);

        $this->assertSame([], $parsed['errors']);
        $this->assertSame(540, $parsed['rows'][0]['qual']);
    }

    // --- Required values and computed points -------------------------------

    public function testCsvComputesPointsFromThePlace()
    {
        $csv = "ind;RM;PL0001;Jan Kowalski;Klub;3;;600\r\n"
            . "ind;RM;PL0002;Adam Nowak;Klub;12;;590\r\n"
            . "mix;RMX;KLUB1;;Klub;12;;1100\r\n";

        $parsed = pl_cup_csv_parse($csv, ['ind' => ['RM'], 'mix' => ['RMX']]);

        $this->assertSame([], $parsed['errors']);
        $this->assertSame([18, 5, 5], array_column($parsed['rows'], 'points'));
    }

    public function testCsvIgnoresThePointsColumnButWarnsWhenItDisagrees()
    {
        $csv = "ind;RM;PL0001;Jan Kowalski;Klub;3;25;600\r\n";

        $parsed = pl_cup_csv_parse($csv, ['ind' => ['RM'], 'mix' => []]);

        $this->assertSame([], $parsed['errors']);
        $this->assertSame(18, $parsed['rows'][0]['points']);
        $this->assertStringContainsString('różnią się od wyliczonych', $parsed['warnings'][0]);
    }

    public function testCsvRequiresTheAthleteName()
    {
        $parsed = pl_cup_csv_parse("ind;RM;PL0001;;Klub;3;18;600\r\n", ['ind' => ['RM'], 'mix' => []]);

        $this->assertStringContainsString('brak nazwiska zawodnika', $parsed['errors'][0]);
    }

    public function testCsvDoesNotRequireANameForAMixedRow()
    {
        $parsed = pl_cup_csv_parse("mix;RMX;KLUB1;;Klub;3;18;1100\r\n", ['ind' => [], 'mix' => ['RMX']]);

        $this->assertSame([], $parsed['errors']);
    }

    public function testCsvRequiresAnExplicitQualificationScore()
    {
        $parsed = pl_cup_csv_parse("ind;RM;PL0001;Jan Kowalski;Klub;3;18;\r\n", ['ind' => ['RM'], 'mix' => []]);

        $this->assertStringContainsString('brak wyniku kwalifikacji', $parsed['errors'][0]);
        $this->assertStringContainsString('wpisz 0', $parsed['errors'][0]);
    }

    public function testCsvAcceptsAnExplicitZeroQualificationScore()
    {
        $parsed = pl_cup_csv_parse("ind;RM;PL0001;Jan Kowalski;Klub;3;18;0\r\n", ['ind' => ['RM'], 'mix' => []]);

        $this->assertSame([], $parsed['errors']);
        $this->assertSame(0, $parsed['rows'][0]['qual']);
    }

    public function testCsvRequiresThePlace()
    {
        $parsed = pl_cup_csv_parse("ind;RM;PL0001;Jan Kowalski;Klub;;18;600\r\n", ['ind' => ['RM'], 'mix' => []]);

        $this->assertStringContainsString('brak miejsca', $parsed['errors'][0]);
    }

    // --- Athlete identity across rounds ------------------------------------

    public function testNamesMatchDespiteOrderCaseDiacriticsAndTypos()
    {
        $this->assertTrue(pl_cup_names_match('Kowalski Jan', 'Jan Kowalski'));
        $this->assertTrue(pl_cup_names_match('Wiśniewska Anna', 'Wisniewska  ANNA'));
        $this->assertTrue(pl_cup_names_match('Szkolnicki Oskar', 'Szkolnicky Oskar'));
        $this->assertFalse(pl_cup_names_match('Kowalski Jan', 'Nowak Adam'));
    }

    public function testTwoLicencesAreOnlyOnePersonWhenTheNamesMatchExactly()
    {
        // Sisters, not a typo - one letter apart but two different athletes.
        $this->assertFalse(pl_cup_same_person('Zapała Daria', 'Zapała Maria'));
        $this->assertFalse(pl_cup_same_person('Szkolnicki Oskar', 'Szkolnicky Oskar'));

        $this->assertTrue(pl_cup_same_person('Kowalski Jan', 'Jan Kowalski'));
        $this->assertTrue(pl_cup_same_person('Wiśniewska Anna', 'wisniewska  ANNA'));
    }

    public function testTwoAthletesSharingASurnameAreNotAConflict()
    {
        $stored = [[
            'round' => 1, 'classification' => 'ind', 'category' => 'RU18W', 'identity' => '5584',
            'name' => 'Zapała Daria', 'club_name' => 'Klub', 'place' => 1, 'points' => 25, 'qual' => 600,
        ]];
        $incoming = [[
            'classification' => 'ind', 'category' => 'RU18W', 'identity' => '6132',
            'name' => 'Zapała Maria', 'club_name' => 'Klub', 'place' => 2, 'points' => 21, 'qual' => 590,
        ]];

        $this->assertSame([], pl_cup_identity_conflicts($incoming, $stored));
    }

    public function testOneLicenceWithTwoDifferentAthletesIsAConflict()
    {
        $stored = [[
            'round' => 1, 'classification' => 'ind', 'category' => 'RM', 'identity' => 'PL0001',
            'name' => 'Jan Kowalski', 'club_name' => 'Klub', 'place' => 1, 'points' => 25, 'qual' => 600,
        ]];
        $incoming = [[
            'classification' => 'ind', 'category' => 'RM', 'identity' => 'PL0001',
            'name' => 'Adam Nowak', 'club_name' => 'Inny Klub', 'place' => 2, 'points' => 21, 'qual' => 590,
        ]];

        $conflicts = pl_cup_identity_conflicts($incoming, $stored);

        $this->assertCount(1, $conflicts);
        $this->assertStringContainsString('Licencja PL0001', $conflicts[0]);
        $this->assertStringContainsString('runda 1', $conflicts[0]);
    }

    public function testOneAthleteUnderTwoLicencesIsAConflict()
    {
        $stored = [[
            'round' => 1, 'classification' => 'ind', 'category' => 'RM', 'identity' => 'PL0001',
            'name' => 'Jan Kowalski', 'club_name' => 'Klub', 'place' => 1, 'points' => 25, 'qual' => 600,
        ]];
        $incoming = [[
            'classification' => 'ind', 'category' => 'RM', 'identity' => 'PL9999',
            'name' => 'KOWALSKI  jan', 'club_name' => 'Klub', 'place' => 2, 'points' => 21, 'qual' => 590,
        ]];

        $conflicts = pl_cup_identity_conflicts($incoming, $stored);

        $this->assertCount(1, $conflicts);
        $this->assertStringContainsString('PL9999', $conflicts[0]);
        $this->assertStringContainsString('PL0001', $conflicts[0]);
    }

    public function testADifferentClubNameIsNotAConflict()
    {
        $stored = [[
            'round' => 1, 'classification' => 'ind', 'category' => 'RM', 'identity' => 'PL0001',
            'name' => 'Jan Kowalski', 'club_name' => 'KS Stella Kielce', 'place' => 1, 'points' => 25, 'qual' => 600,
        ]];
        $incoming = [[
            'classification' => 'ind', 'category' => 'RM', 'identity' => 'PL0001',
            'name' => 'Jan Kowalski', 'club_name' => 'Stella Kielce', 'place' => 2, 'points' => 21, 'qual' => 590,
        ]];

        $this->assertSame([], pl_cup_identity_conflicts($incoming, $stored));
    }

    public function testAContradictionInsideOneFileIsAlsoAConflict()
    {
        $incoming = [
            ['classification' => 'ind', 'category' => 'RM', 'identity' => 'PL0001',
             'name' => 'Jan Kowalski', 'club_name' => 'Klub', 'place' => 1, 'points' => 25, 'qual' => 600],
            ['classification' => 'ind', 'category' => 'RW', 'identity' => 'PL0001',
             'name' => 'Anna Nowak', 'club_name' => 'Klub', 'place' => 1, 'points' => 25, 'qual' => 580],
        ];

        $conflicts = pl_cup_identity_conflicts($incoming, []);

        $this->assertCount(1, $conflicts);
        $this->assertStringContainsString('ten sam plik', $conflicts[0]);
    }

    public function testMixedRowsAreNotComparedByName()
    {
        $stored = [[
            'round' => 1, 'classification' => 'mix', 'category' => 'RMX', 'identity' => 'KLUB1',
            'name' => '', 'club_name' => 'Klub', 'place' => 1, 'points' => 25, 'qual' => 1100,
        ]];
        $incoming = [[
            'classification' => 'mix', 'category' => 'RMX', 'identity' => 'KLUB2',
            'name' => '', 'club_name' => 'Inny Klub', 'place' => 2, 'points' => 21, 'qual' => 1090,
        ]];

        $this->assertSame([], pl_cup_identity_conflicts($incoming, $stored));
    }

    public function testCsvRejectsANonNumericPlace()
    {
        $csv = "ind;RM;PL0001;Jan;Klub;pierwszy;25;645\r\n";

        $parsed = pl_cup_csv_parse($csv, ['ind' => ['RM'], 'mix' => []]);

        $this->assertStringContainsString('kolumna Miejsce', $parsed['errors'][0]);
    }

    public function testCsvReportsIdentityViolations()
    {
        $csv = "ind;RM;;Jan;Klub;1;25;645\r\n";

        $parsed = pl_cup_csv_parse($csv, ['ind' => ['RM'], 'mix' => []]);

        $this->assertStringContainsString('Brak numeru licencji', $parsed['errors'][0]);
    }

    public function testCategoryMetaCoversOnlyWhatThisCompetitionRuns()
    {
        FakeDb::on('/FROM Divisions/', [['DivId' => 'R', 'DivDescription' => 'Łuk klasyczny']]);
        FakeDb::on('/FROM Classes/', [['ClId' => 'U18M', 'ClDescription' => 'Junior młodszy', 'ClViewOrder' => 7]]);
        FakeDb::on('/FROM Entries/', [['EnDivision' => 'R', 'EnClass' => 'U18M']]);
        FakeDb::on('/FROM EventClass/', [['EvCode' => 'RU18X', 'DivCode' => 'R', 'Class' => 'U18M']]);
        // Mixed sections come from the pairs that actually started here.
        FakeDb::on('/FROM Teams/', []);

        $meta = pl_cup_category_meta(1);

        $this->assertSame(['RU18M'], array_keys($meta['ind']));
        $this->assertSame([], $meta['mix']);
    }

    public function testValidCategoriesComeFromConfiguredClassesAndMixedEvents()
    {
        FakeDb::on('/FROM Divisions/', [['DivId' => 'R', 'DivDescription' => 'Łuk klasyczny']]);
        FakeDb::on('/FROM Classes/', [['ClId' => 'M', 'ClDescription' => 'Senior', 'ClViewOrder' => 1]]);
        FakeDb::on('/EvMixedTeam = 1/', [['Event' => 'RMX']]);

        $categories = pl_cup_valid_categories(1);

        $this->assertSame(['RM'], $categories['ind']);
        $this->assertSame(['RMX'], $categories['mix']);
    }
}
