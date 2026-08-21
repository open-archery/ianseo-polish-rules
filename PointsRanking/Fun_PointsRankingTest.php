<?php

namespace PL\Tests\PointsRanking;

if (PHP_SAPI !== 'cli') {
    exit;
}

require_once __DIR__ . '/Fun_PointsRanking.php';
require_once __DIR__ . '/PointsRankingCalc.php';
require_once __DIR__ . '/Presets.php';

final class Fun_PointsRankingTest extends \PlTestCase
{
    // --- pl_points_ensure_tables ----------------------------------------

    public function testEnsureTablesCreatesBothTablesWhenMissing(): void
    {
        \pl_points_ensure_tables();

        $this->assertCount(1, \FakeDb::executed('/CREATE TABLE PLPointsTournamentConfig/'));
        $this->assertCount(1, \FakeDb::executed('/CREATE TABLE PLVoivodeshipMap/'));
    }

    public function testEnsureTablesSkipsCreateWhenTablesExist(): void
    {
        \FakeDb::on("/SHOW TABLES LIKE 'PLPointsTournamentConfig'/", [['t' => 'x']]);
        \FakeDb::on("/SHOW TABLES LIKE 'PLVoivodeshipMap'/", [['t' => 'x']]);

        \pl_points_ensure_tables();

        $this->assertCount(0, \FakeDb::executed('/CREATE TABLE/'));
    }

    // --- tournament preset get/set ----------------------------------------

    public function testGetTournamentPresetReturnsNullWhenUnset(): void
    {
        $this->assertNull(\pl_points_get_tournament_preset(7));
    }

    public function testGetTournamentPresetReturnsStoredKey(): void
    {
        \FakeDb::on('/FROM PLPointsTournamentConfig/', [['PltcPresetKey' => 'lzs']]);

        $this->assertSame('lzs', \pl_points_get_tournament_preset(7));
    }

    public function testSetTournamentPresetInsertsWhenNotExists(): void
    {
        \pl_points_set_tournament_preset(7, 'lzs');

        $writes = \FakeDb::executed('/INSERT INTO PLPointsTournamentConfig/');
        $this->assertCount(1, $writes);
        $this->assertStringContainsString("'lzs'", $writes[0]);
    }

    public function testSetTournamentPresetUpdatesWhenExists(): void
    {
        \FakeDb::on('/SELECT PltcTournament FROM PLPointsTournamentConfig/', [['PltcTournament' => 7]]);

        \pl_points_set_tournament_preset(7, 'oom');

        $this->assertCount(1, \FakeDb::executed('/UPDATE PLPointsTournamentConfig/'));
        $this->assertCount(0, \FakeDb::executed('/INSERT INTO PLPointsTournamentConfig/'));
    }

    // --- voivodeship map -----------------------------------------------------

    public function testGetVoivodeshipMapKeyedByCoCode(): void
    {
        \FakeDb::on('/FROM PLVoivodeshipMap/', [
            ['PlvmCoCode' => 'ORL', 'PlvmVoivodeship' => 'Małopolskie'],
        ]);

        $map = \pl_points_get_voivodeship_map();

        $this->assertSame(['ORL' => 'Małopolskie'], $map);
    }

    public function testSaveVoivodeshipInsertsWhenNotExists(): void
    {
        \pl_points_save_voivodeship('ORL', 'Małopolskie');

        $this->assertCount(1, \FakeDb::executed('/INSERT INTO PLVoivodeshipMap/'));
    }

    public function testSaveVoivodeshipUpdatesWhenExists(): void
    {
        \FakeDb::on('/SELECT PlvmCoCode FROM PLVoivodeshipMap/', [['PlvmCoCode' => 'ORL']]);

        \pl_points_save_voivodeship('ORL', 'Śląskie');

        $this->assertCount(1, \FakeDb::executed('/UPDATE PLVoivodeshipMap/'));
    }

    public function testSaveVoivodeshipDeletesWhenClearedAndExists(): void
    {
        \FakeDb::on('/SELECT PlvmCoCode FROM PLVoivodeshipMap/', [['PlvmCoCode' => 'ORL']]);

        \pl_points_save_voivodeship('ORL', '');

        $this->assertCount(1, \FakeDb::executed('/DELETE FROM PLVoivodeshipMap/'));
    }

    public function testSaveVoivodeshipIgnoresEmptyCoCode(): void
    {
        \pl_points_save_voivodeship('', 'małopolskie');

        $this->assertCount(0, \FakeDb::executed('/PLVoivodeshipMap/'));
    }

    public function testSaveVoivodeshipRejectsValueOutsideTheWhitelist(): void
    {
        \pl_points_save_voivodeship('ORL', 'Nieprzypisane');

        $this->assertCount(0, \FakeDb::executed('/INSERT INTO PLVoivodeshipMap/'));
        $this->assertCount(0, \FakeDb::executed('/UPDATE PLVoivodeshipMap/'));
    }

    // --- pl_points_format_number (pure) --------------------------------------

    public function testFormatNumberSuppressesTrailingZerosOnWholeValues(): void
    {
        $this->assertSame('25', \pl_points_format_number(25));
    }

    public function testFormatNumberKeepsOneFractionalDigit(): void
    {
        $this->assertSame('12,5', \pl_points_format_number(12.5));
    }

    public function testFormatNumberRoundsToTwoDecimals(): void
    {
        $this->assertSame('7,33', \pl_points_format_number(22 / 3));
    }

    // --- pl_points_in_scope (pure) -------------------------------------------

    public function testInScopeAllowsEverythingWhenScopeEmpty(): void
    {
        $this->assertTrue(\pl_points_in_scope('R', 'M', ['divisions' => [], 'classes' => []]));
    }

    public function testInScopeRejectsDivisionOutsideList(): void
    {
        $this->assertFalse(\pl_points_in_scope('C', 'U21M', ['divisions' => ['R'], 'classes' => []]));
    }

    public function testInScopeRejectsClassOutsideList(): void
    {
        $this->assertFalse(\pl_points_in_scope('R', 'M', ['divisions' => ['R'], 'classes' => ['U21M']]));
    }

    public function testInScopeAcceptsWhenBothMatch(): void
    {
        $this->assertTrue(\pl_points_in_scope('R', 'U21M', ['divisions' => ['R'], 'classes' => ['U21M']]));
    }

    // --- pl_ranking_div_labels -------------------------------------------------

    public function testDivLabelsOrdersDivisionsRCBThenAlphabetical(): void
    {
        \FakeDb::on('/FROM Divisions/', [
            ['DivId' => 'B', 'DivDescription' => 'Łuk goły'],
            ['DivId' => 'T', 'DivDescription' => 'Tradycyjny'],
            ['DivId' => 'R', 'DivDescription' => 'Łuk klasyczny'],
            ['DivId' => 'C', 'DivDescription' => 'Łuk bloczkowy'],
        ]);
        \FakeDb::on('/FROM Classes/', [
            ['ClId' => 'M', 'ClDescription' => 'Seniorzy', 'ClViewOrder' => 1],
        ]);

        $labels = \pl_ranking_div_labels(1);

        $this->assertSame(0, $labels['RM']['order'][0]);
        $this->assertSame(1, $labels['CM']['order'][0]);
        $this->assertSame(2, $labels['BM']['order'][0]);
        $this->assertSame(3, $labels['TM']['order'][0]); // unknown division sorts after R,C,B
        $this->assertSame('Łuk klasyczny Seniorzy', $labels['RM']['label']);
    }

    // --- pl_points_load_categories -----------------------------------------

    public function testLoadCategoriesFiltersIndividualsByScope(): void
    {
        \FakeDb::on('/FROM Divisions/', [['DivId' => 'R', 'DivDescription' => 'Recurve']]);
        \FakeDb::on('/FROM Classes/', [['ClId' => 'M', 'ClDescription' => 'Men', 'ClViewOrder' => 1], ['ClId' => 'W', 'ClDescription' => 'Women', 'ClViewOrder' => 2]]);
        \FakeDb::on('/FROM Entries/', [
            ['EnDivision' => 'R', 'EnClass' => 'M'],
            ['EnDivision' => 'C', 'EnClass' => 'M'], // out of scope
        ]);
        \FakeDb::on('/FROM EventClass/', []);

        $categories = \pl_points_load_categories(1, ['divisions' => ['R'], 'classes' => []]);

        $this->assertArrayHasKey('RM', $categories['individual']);
        $this->assertArrayNotHasKey('CM', $categories['individual']);
    }

    public function testLoadCategoriesKeepsTeamEventWithAtLeastOneInScopePair(): void
    {
        \FakeDb::on('/FROM Divisions/', [['DivId' => 'R', 'DivDescription' => 'Recurve']]);
        \FakeDb::on('/FROM Classes/', [['ClId' => 'U21M', 'ClDescription' => 'U21 Men', 'ClViewOrder' => 1]]);
        \FakeDb::on('/FROM Entries/', []);
        \FakeDb::on('/FROM EventClass/', [
            ['EvCode' => 'RU21M', 'DivCode' => 'R', 'Class' => 'U21M'],
        ]);

        $categories = \pl_points_load_categories(1, ['divisions' => ['R'], 'classes' => ['U21M']]);

        $this->assertArrayHasKey('RU21M', $categories['team']);
    }

    public function testLoadCategoriesDropsTeamEventWithNoInScopePair(): void
    {
        \FakeDb::on('/FROM Divisions/', [['DivId' => 'C', 'DivDescription' => 'Compound']]);
        \FakeDb::on('/FROM Classes/', [['ClId' => 'M', 'ClDescription' => 'Men', 'ClViewOrder' => 1]]);
        \FakeDb::on('/FROM Entries/', []);
        \FakeDb::on('/FROM EventClass/', [
            ['EvCode' => 'CM', 'DivCode' => 'C', 'Class' => 'M'],
        ]);

        $categories = \pl_points_load_categories(1, ['divisions' => ['R'], 'classes' => []]);

        $this->assertSame([], $categories['team']);
    }

    // --- pl_points_load_individuals ------------------------------------------

    public function testLoadIndividualsUsesQualPlaceForQualSource(): void
    {
        \pl_points_load_individuals(1, 'QUAL', ['RM' => []]);

        $this->assertCount(1, \FakeDb::executed('/Individuals\.IndRank AS Place/'));
    }

    public function testLoadIndividualsUsesElimFallbackForElimSource(): void
    {
        \pl_points_load_individuals(1, 'ELIM', ['RM' => []]);

        $this->assertCount(1, \FakeDb::executed('/IF\(Events\.EvFinalFirstPhase=0, Individuals\.IndRank, ABS\(Individuals\.IndRankFinal\)\)/'));
    }

    public function testLoadIndividualsReturnsEmptyWhenNoCategories(): void
    {
        $this->assertSame([], \pl_points_load_individuals(1, 'QUAL', []));
    }

    public function testLoadIndividualsAttachesClubFromDirectory(): void
    {
        \FakeDb::on('/FROM Individuals/', [
            ['EnId' => 1, 'EnCode' => 'PL001', 'EnName' => 'Kowalski', 'EnFirstName' => 'Jan', 'EnDivision' => 'R', 'EnClass' => 'M', 'ClubId' => 5, 'Place' => 1],
        ]);
        \FakeDb::on('/FROM Countries/', [['CoId' => 5, 'CoCode' => 'ORL', 'CoName' => 'Orzeł']]);

        $rows = \pl_points_load_individuals(1, 'QUAL', ['RM' => []]);

        $this->assertSame('ORL', $rows[0]['CoCode']);
        $this->assertSame('Jan Kowalski', $rows[0]['Name']);
        $this->assertSame('RM', $rows[0]['Category']);
    }

    // --- pl_points_load_teams --------------------------------------------

    public function testLoadTeamsFiltersMixedEvents(): void
    {
        \pl_points_load_teams(1, 'QUAL', true, ['RX' => []]);

        $this->assertCount(1, \FakeDb::executed('/EvMixedTeam = 1/'));
    }

    public function testLoadTeamsFiltersNonMixedEvents(): void
    {
        \pl_points_load_teams(1, 'QUAL', false, ['RM' => []]);

        $this->assertCount(1, \FakeDb::executed('/EvMixedTeam = 0/'));
    }

    public function testLoadTeamsUsesElimPlaceExpressionForElimSource(): void
    {
        \pl_points_load_teams(1, 'ELIM', false, ['RM' => []]);

        $this->assertCount(1, \FakeDb::executed('/ABS\(Teams\.TeRankFinal\)/'));
    }

    // --- pl_points_load_rosters --------------------------------------------

    public function testLoadRostersReadsTeamComponentForQual(): void
    {
        \FakeDb::on('/FROM TeamComponent/', [['ClubId' => 1, 'SubTeam' => 0, 'Event' => 'RM', 'EnId' => 10]]);

        $rosters = \pl_points_load_rosters(1, 'QUAL', [['ClubId' => 1, 'SubTeam' => 0, 'Event' => 'RM']]);

        $this->assertSame([10], $rosters['1_0_RM']);
        $this->assertCount(1, \FakeDb::executed('/TcFinEvent = 1/'));
    }

    public function testLoadRostersReadsTeamFinComponentForElim(): void
    {
        \FakeDb::on('/FROM TeamFinComponent/', [['ClubId' => 1, 'SubTeam' => 0, 'Event' => 'RM', 'EnId' => 11]]);

        $rosters = \pl_points_load_rosters(1, 'ELIM', [['ClubId' => 1, 'SubTeam' => 0, 'Event' => 'RM']]);

        $this->assertSame([11], $rosters['1_0_RM']);
    }

    public function testLoadRostersReturnsEmptyArrayForMissingTeam(): void
    {
        \FakeDb::on('/FROM TeamComponent/', []);

        $rosters = \pl_points_load_rosters(1, 'QUAL', [['ClubId' => 1, 'SubTeam' => 0, 'Event' => 'RM']]);

        $this->assertSame([], $rosters['1_0_RM']);
    }

    // --- pl_points_load_parent_ranks ----------------------------------------

    public function testLoadParentRanksFiltersBySameEventCode(): void
    {
        \FakeDb::on('/FROM Individuals/', [['IndId' => 10, 'IndRank' => 5]]);

        $ranks = \pl_points_load_parent_ranks(1, [10], 'RU21M');

        $this->assertSame(5, $ranks[10]);
        $this->assertCount(1, \FakeDb::executed("/IndEvent = 'RU21M'/"));
    }

    // --- pl_points_load_starters --------------------------------------------

    public function testLoadStartersCountsValidQualificationPlacesOnly(): void
    {
        \FakeDb::on('/FROM Individuals/', [['Category' => 'RM', 'Cnt' => 12]]);
        \FakeDb::on('/FROM Teams/', [['Category' => 'RM', 'Cnt' => 4]]);

        $starters = \pl_points_load_starters(1, ['individual' => ['RM' => []], 'team' => ['RM' => []]]);

        $this->assertSame(12, $starters['individual']['RM']);
        $this->assertSame(4, $starters['team']['RM']);
    }

    // --- pl_points_load_entries_directory -----------------------------------

    public function testLoadEntriesDirectoryMapsClubAndCategory(): void
    {
        \FakeDb::on('/FROM Entries/', [
            ['EnId' => 3, 'EnCode' => 'PL003', 'EnName' => 'Nowak', 'EnFirstName' => 'Anna', 'EnDivision' => 'R', 'EnClass' => 'W', 'ClubId' => 5],
        ]);
        \FakeDb::on('/FROM Countries/', [['CoId' => 5, 'CoCode' => 'ORL', 'CoName' => 'Orzeł']]);

        $dir = \pl_points_load_entries_directory(1);

        $this->assertSame('RW', $dir[3]['category']);
        $this->assertSame('ORL', $dir[3]['club_code']);
        $this->assertSame('Anna Nowak', $dir[3]['name']);
    }

    // --- pl_points_load_clubs_in_tournament -----------------------------------

    public function testLoadClubsInTournamentKeyedByCoCode(): void
    {
        \FakeDb::on('/FROM Entries/', [
            ['CoCode' => 'ORL', 'CoName' => 'Orzeł Warszawa'],
        ]);

        $clubs = \pl_points_load_clubs_in_tournament(1);

        $this->assertSame(['ORL' => 'Orzeł Warszawa'], $clubs);
    }

    // --- pl_points_calculate: end-to-end wiring (LZS-shaped scenario) --------

    public function testCalculateWiresLoadersIntoClubTotalsForAQualOnlyTeamPreset(): void
    {
        $preset = \PL_POINTS_PRESETS['lzs'];

        \FakeDb::on('/FROM Divisions/', [['DivId' => 'R', 'DivDescription' => 'Recurve']]);
        \FakeDb::on('/FROM Classes/', [['ClId' => 'U21M', 'ClDescription' => 'U21 Men', 'ClViewOrder' => 1]]);
        \FakeDb::on('/SELECT DISTINCT EnDivision/', [['EnDivision' => 'R', 'EnClass' => 'U21M']]);
        \FakeDb::on('/FROM EventClass/', [['EvCode' => 'RU21M', 'DivCode' => 'R', 'Class' => 'U21M']]);
        \FakeDb::on('/FROM PLVoivodeshipMap/', []);

        // Individual classification ('ind'): one athlete, place 1 -> 9 points.
        \FakeDb::on('/FROM Individuals\b.*INNER JOIN Entries/s', [
            ['EnId' => 1, 'EnCode' => 'PL001', 'EnName' => 'Kowalski', 'EnFirstName' => 'Jan', 'EnDivision' => 'R', 'EnClass' => 'U21M', 'ClubId' => 5, 'Place' => 1],
        ]);
        // Starters (individual): also matches "FROM Individuals" — registered after, so it wins for that query text.
        \FakeDb::on('/CONCAT\(Entries\.EnDivision, Entries\.EnClass\) AS Category/', [['Category' => 'RU21M', 'Cnt' => 8]]);

        // Team classification ('tea'): one team, place 1 -> 9 points, roster of 3.
        \FakeDb::on('/FROM Teams\b/', [
            ['TeCoId' => 5, 'TeSubTeam' => 0, 'TeEvent' => 'RU21M', 'Place' => 1],
        ]);
        \FakeDb::on('/Teams\.TeEvent AS Category/', [['Category' => 'RU21M', 'Cnt' => 2]]);
        \FakeDb::on('/FROM TeamComponent/', [
            ['ClubId' => 5, 'SubTeam' => 0, 'Event' => 'RU21M', 'EnId' => 1],
            ['ClubId' => 5, 'SubTeam' => 0, 'Event' => 'RU21M', 'EnId' => 2],
            ['ClubId' => 5, 'SubTeam' => 0, 'Event' => 'RU21M', 'EnId' => 3],
        ]);

        \FakeDb::on('/FROM Countries/', [['CoId' => 5, 'CoCode' => 'ORL', 'CoName' => 'Orzeł']]);
        \FakeDb::on('/SELECT EnId, EnCode, EnName/', [
            ['EnId' => 1, 'EnCode' => 'PL001', 'EnName' => 'Kowalski', 'EnFirstName' => 'Jan', 'EnDivision' => 'R', 'EnClass' => 'U21M', 'ClubId' => 5],
            ['EnId' => 2, 'EnCode' => 'PL002', 'EnName' => 'Nowak', 'EnFirstName' => 'Piotr', 'EnDivision' => 'R', 'EnClass' => 'U21M', 'ClubId' => 5],
            ['EnId' => 3, 'EnCode' => 'PL003', 'EnName' => 'Wiśniewski', 'EnFirstName' => 'Adam', 'EnDivision' => 'R', 'EnClass' => 'U21M', 'ClubId' => 5],
        ]);

        $result = \pl_points_calculate(1, $preset);

        $clubReport = current(array_filter($result['reports'], fn ($r) => $r['kind'] === 'CLUB'));
        $this->assertNotFalse($clubReport, 'CLUB report should be present');
        // 9 (individual, place 1) + 9 (team, place 1, uncapped) = 18.
        $this->assertSame(18, $clubReport['rows'][0]['points']);
    }

    public function testCalculateShowsBothAthleteNamesForAMixedPair(): void
    {
        $preset = \PL_POINTS_PRESETS['pp'];

        \FakeDb::on('/FROM Divisions/', [['DivId' => 'R', 'DivDescription' => 'Recurve']]);
        \FakeDb::on('/FROM Classes/', [['ClId' => 'M', 'ClDescription' => 'Mężczyźni', 'ClViewOrder' => 1]]);
        \FakeDb::on('/SELECT DISTINCT EnDivision/', []);
        \FakeDb::on('/FROM EventClass/', [['EvCode' => 'RX', 'DivCode' => 'R', 'Class' => 'M']]);
        \FakeDb::on('/FROM PLVoivodeshipMap/', []);

        \FakeDb::on('/FROM Teams\b/', [
            ['TeCoId' => 5, 'TeSubTeam' => 0, 'TeEvent' => 'RX', 'Place' => 1],
        ]);
        \FakeDb::on('/Teams\.TeEvent AS Category/', []); // starters query also matches "FROM Teams" — override to avoid the wrong row shape
        \FakeDb::on('/FROM TeamFinComponent/', [
            ['ClubId' => 5, 'SubTeam' => 0, 'Event' => 'RX', 'EnId' => 10],
            ['ClubId' => 5, 'SubTeam' => 0, 'Event' => 'RX', 'EnId' => 20],
        ]);

        \FakeDb::on('/FROM Countries/', [['CoId' => 5, 'CoCode' => 'ORL', 'CoName' => 'Orzeł']]);
        \FakeDb::on('/SELECT EnId, EnCode, EnName/', [
            ['EnId' => 10, 'EnCode' => 'PL010', 'EnName' => 'Kowalska', 'EnFirstName' => 'Anna', 'EnDivision' => 'R', 'EnClass' => 'W', 'ClubId' => 5],
            ['EnId' => 20, 'EnCode' => 'PL020', 'EnName' => 'Nowak', 'EnFirstName' => 'Piotr', 'EnDivision' => 'R', 'EnClass' => 'M', 'ClubId' => 5],
        ]);

        $result = \pl_points_calculate(1, $preset);

        $mixReport = current(array_filter($result['reports'], fn ($r) => $r['label'] === 'Klasyfikacja mikstów'));
        $this->assertNotFalse($mixReport, 'mixed SEPARATE report should be present');
        $pairRow = $mixReport['sections'][0]['rows'][0];
        $this->assertSame('Anna Kowalska / Piotr Nowak', $pairRow['name']);
    }
}
