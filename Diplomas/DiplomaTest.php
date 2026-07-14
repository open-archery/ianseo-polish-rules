<?php

namespace PL\Tests\Diplomas;

use PHPUnit\Framework\Attributes\DataProvider;

if (PHP_SAPI !== 'cli') {
    exit;
}

require_once __DIR__ . '/Fun_Diploma.php';
require_once __DIR__ . '/DiplomaSetup.php';

final class DiplomaTest extends \PlTestCase
{
    #[DataProvider('compositeKeys')]
    public function testRawEventCode(string $compositeKey, string $expected): void
    {
        $this->assertSame($expected, \pl_diploma_raw_event_code($compositeKey));
    }

    public static function compositeKeys(): array
    {
        return [
            'individual prefix' => ['I:RM', 'RM'],
            'team prefix' => ['T:CU21M', 'CU21M'],
            'mixed prefix' => ['M:RX', 'RX'],
            'no prefix, no colon' => ['RC', 'RC'],
            'colon at wrong position is not a prefix' => ['AB:CD', 'AB:CD'],
            'exactly two chars is too short to strip' => ['R:', 'R:'],
            'empty string' => ['', ''],
        ];
    }

    // --- pl_diploma_get_title_defaults (pure) --------------------------------

    #[DataProvider('titleDefaults')]
    public function testGetTitleDefaults(string $rawEventCode, array $expected): void
    {
        $this->assertSame($expected, \pl_diploma_get_title_defaults($rawEventCode));
    }

    public static function titleDefaults(): array
    {
        return [
            'senior individual' => ['RM', ['prefix' => '', 'text' => 'Polski Seniorów']],
            'senior mixed' => ['RX', ['prefix' => '', 'text' => 'Polski Seniorów']],
            'U24 individual' => ['RU24M', ['prefix' => 'Młodzieżowego', 'text' => 'Polski']],
            'U24 mixed' => ['RU24X', ['prefix' => 'Młodzieżowego', 'text' => 'Polski']],
            'U21 individual' => ['RU21M', ['prefix' => '', 'text' => 'Polski Juniorów']],
            'U18 recurve (Olimpiada Młodzieży)' => ['RU18M', ['prefix' => '', 'text' => 'Ogólnopolskiej Olimpiady Młodzieży']],
            'U18 non-recurve' => ['CU18M', ['prefix' => '', 'text' => 'Polski Juniorów Młodszych']],
            'U18 mixed recurve' => ['RU18X', ['prefix' => '', 'text' => 'Ogólnopolskiej Olimpiady Młodzieży']],
            '50+ individual' => ['R50M', ['prefix' => '', 'text' => '']],
            'U15 individual' => ['RU15M', ['prefix' => 'Międzywojewódzkiego', 'text' => 'Młodzików']],
            'U12 individual' => ['RU12M', ['prefix' => '', 'text' => '']],
            'unknown age code falls back to empty' => ['RU99M', ['prefix' => '', 'text' => '']],
        ];
    }

    // --- pl_diploma_build_title (pure) ---------------------------------------

    public function testBuildTitleForIndividualFirstPlace(): void
    {
        $this->assertSame(
            'i zdobywa tytuł Mistrza Polski Seniorów na rok 2026',
            \pl_diploma_build_title(1, '', 'Polski Seniorów', 2026, false, false)
        );
    }

    public function testBuildTitleForTeamSecondPlaceAddsZespolowego(): void
    {
        $this->assertSame(
            'i zdobywa tytuł Zespołowego Wicemistrza Polski Seniorów na rok 2026',
            \pl_diploma_build_title(2, '', 'Polski Seniorów', 2026, true, false)
        );
    }

    public function testBuildTitleForMixedTeamThirdPlaceAddsWMiksie(): void
    {
        $this->assertSame(
            'i zdobywa tytuł II Wicemistrza Polski Seniorów w mikście na rok 2026',
            \pl_diploma_build_title(3, '', 'Polski Seniorów', 2026, true, true)
        );
    }

    public function testBuildTitleIncludesPrefixWhenPresent(): void
    {
        $this->assertSame(
            'i zdobywa tytuł Młodzieżowego Mistrza Polski na rok 2026',
            \pl_diploma_build_title(1, 'Młodzieżowego', 'Polski', 2026, false, false)
        );
    }

    public function testBuildTitleReturnsEmptyForEmptyText(): void
    {
        $this->assertSame('', \pl_diploma_build_title(1, '', '', 2026, false, false));
    }

    #[DataProvider('outOfRangeRanks')]
    public function testBuildTitleReturnsEmptyForOutOfRangeRank(int $rank): void
    {
        $this->assertSame('', \pl_diploma_build_title($rank, '', 'Polski Seniorów', 2026, false, false));
    }

    public static function outOfRangeRanks(): array
    {
        return [
            'zero' => [0],
            'negative' => [-1],
            'fourth place' => [4],
        ];
    }

    // --- pl_diploma_extract_year (pure) --------------------------------------

    #[DataProvider('dateStrings')]
    public function testExtractYear(string $datesString, int $expected): void
    {
        $this->assertSame($expected, \pl_diploma_extract_year($datesString));
    }

    public static function dateStrings(): array
    {
        return [
            'day-month-year range' => ['15-17.03.2026', 2026],
            'iso format' => ['2026-03-15', 2026],
            'year embedded in longer text' => ['Zawody 2025/2026, runda 1', 2025],
        ];
    }

    public function testExtractYearFallsBackToCurrentYearWhenNoneFound(): void
    {
        $this->assertSame((int) date('Y'), \pl_diploma_extract_year('no year here'));
    }

    // --- pl_diploma_get_events (DB-wrapped) ----------------------------------

    public function testGetEventsBuildsCompositeKeysByType(): void
    {
        \FakeDb::on('/FROM Events/', [
            ['EvCode' => 'RM', 'EvEventName' => 'Recurve Men', 'EvTeamEvent' => 0, 'EvMixedTeam' => 0],
            ['EvCode' => 'RM', 'EvEventName' => 'Recurve Men Team', 'EvTeamEvent' => 1, 'EvMixedTeam' => 0],
            ['EvCode' => 'RX', 'EvEventName' => 'Recurve Mixed', 'EvTeamEvent' => 1, 'EvMixedTeam' => 1],
        ]);

        $events = \pl_diploma_get_events();

        $this->assertArrayHasKey('I:RM', $events);
        $this->assertArrayHasKey('T:RM', $events);
        $this->assertArrayHasKey('M:RX', $events);
        $this->assertSame('I', $events['I:RM']['type']);
        $this->assertSame('T', $events['T:RM']['type']);
        $this->assertSame('M', $events['M:RX']['type']);
    }

    public function testGetEventsFiltersByIndividualType(): void
    {
        \pl_diploma_get_events('individual');

        $this->assertCount(1, \FakeDb::executed('/AND EvTeamEvent = 0/'));
    }

    public function testGetEventsFiltersByTeamType(): void
    {
        \pl_diploma_get_events('team');

        $this->assertCount(1, \FakeDb::executed('/AND EvTeamEvent = 1 AND EvMixedTeam = 0/'));
    }

    public function testGetEventsReturnsEmptyArrayWhenNoRows(): void
    {
        $this->assertSame([], \pl_diploma_get_events());
    }

    // --- pl_diploma_get_athlete (DB-wrapped) ---------------------------------

    public function testGetAthleteReturnsDetailsWhenFound(): void
    {
        \FakeDb::on('/FROM Entries/', [['EnId' => 5, 'EnFullName' => 'Kowalski Jan', 'CoName' => 'Orzeł Warszawa']]);

        $athlete = \pl_diploma_get_athlete(5);

        $this->assertSame(5, $athlete['EnId']);
        $this->assertSame('Kowalski Jan', $athlete['EnFullName']);
        $this->assertCount(1, \FakeDb::executed('/EnId = 5/'));
    }

    public function testGetAthleteReturnsNullWhenNotFound(): void
    {
        $this->assertNull(\pl_diploma_get_athlete(999));
    }

    // --- pl_diploma_get_all_athletes (DB-wrapped) ----------------------------

    public function testGetAllAthletesWithoutSearchOmitsLikeFilter(): void
    {
        \pl_diploma_get_all_athletes();

        $this->assertCount(0, \FakeDb::executed('/LIKE/'));
    }

    public function testGetAllAthletesWithSearchAddsLikeFilter(): void
    {
        \pl_diploma_get_all_athletes('Kowal');

        $this->assertCount(1, \FakeDb::executed("/LIKE '%Kowal%'/"));
    }

    // --- pl_diploma_get_ind_qual_results (DB-wrapped) ------------------------

    public function testGetIndQualResultsMapsRowsAndDefaultsPlaceRange(): void
    {
        \FakeDb::on('/FROM Individuals/', [
            ['EnFullName' => 'Kowalski Jan', 'CoName' => 'Orzeł Warszawa', 'IndEvent' => 'RM', 'EvEventName' => 'Recurve Men', 'QuScore' => 650, 'QuClRank' => 1],
        ]);

        $results = \pl_diploma_get_ind_qual_results();

        $this->assertCount(1, $results);
        $this->assertSame(1, $results[0]['Rank']);
        $this->assertCount(1, \FakeDb::executed('/QuClRank >= 1/'));
        $this->assertCount(1, \FakeDb::executed('/QuClRank <= 3/'));
    }

    public function testGetIndQualResultsFiltersByEventCode(): void
    {
        \pl_diploma_get_ind_qual_results(['I:RM']);

        $this->assertCount(1, \FakeDb::executed("/IndEvent IN \('RM'\)/"));
    }

    // --- pl_diploma_get_team_qual_results (DB-wrapped grouping) --------------

    public function testGetTeamQualResultsGroupsAthletesUnderSameTeam(): void
    {
        \FakeDb::on('/FROM Teams/', [
            ['TeCoId' => 1, 'TeSubTeam' => 0, 'TeEvent' => 'RM', 'TeRank' => 1, 'TeScore' => 1900,
                'EvEventName' => 'Recurve Men Team', 'EvMixedTeam' => 0,
                'EnFullName' => 'Kowalski Jan', 'CoName' => 'Orzeł Warszawa', 'QuScore' => 650, 'TcOrder' => 1],
            ['TeCoId' => 1, 'TeSubTeam' => 0, 'TeEvent' => 'RM', 'TeRank' => 1, 'TeScore' => 1900,
                'EvEventName' => 'Recurve Men Team', 'EvMixedTeam' => 0,
                'EnFullName' => 'Nowak Piotr', 'CoName' => 'Orzeł Warszawa', 'QuScore' => 640, 'TcOrder' => 2],
        ]);

        $teams = \pl_diploma_get_team_qual_results();

        $this->assertCount(1, $teams);
        $team = array_values($teams)[0];
        $this->assertSame(1, $team['Rank']);
        $this->assertCount(2, $team['Athletes']);
        $this->assertSame('Kowalski Jan', $team['Athletes'][0]['EnFullName']);
        $this->assertSame('Nowak Piotr', $team['Athletes'][1]['EnFullName']);
    }

    public function testGetTeamQualResultsSeparatesDifferentTeams(): void
    {
        \FakeDb::on('/FROM Teams/', [
            ['TeCoId' => 1, 'TeSubTeam' => 0, 'TeEvent' => 'RM', 'TeRank' => 1, 'TeScore' => 1900,
                'EvEventName' => 'Recurve Men Team', 'EvMixedTeam' => 0,
                'EnFullName' => 'Kowalski Jan', 'CoName' => 'Orzeł Warszawa', 'QuScore' => 650, 'TcOrder' => 1],
            ['TeCoId' => 2, 'TeSubTeam' => 0, 'TeEvent' => 'RM', 'TeRank' => 2, 'TeScore' => 1850,
                'EvEventName' => 'Recurve Men Team', 'EvMixedTeam' => 0,
                'EnFullName' => 'Zielinski Adam', 'CoName' => 'Sokół Kraków', 'QuScore' => 620, 'TcOrder' => 1],
        ]);

        $teams = \pl_diploma_get_team_qual_results();

        $this->assertCount(2, $teams);
    }

    public function testGetTeamQualResultsSeparatesBySubTeamAndEventTooNotJustClub(): void
    {
        \FakeDb::on('/FROM Teams/', [
            ['TeCoId' => 1, 'TeSubTeam' => 0, 'TeEvent' => 'RM', 'TeRank' => 1, 'TeScore' => 1900,
                'EvEventName' => 'Recurve Men Team', 'EvMixedTeam' => 0,
                'EnFullName' => 'Kowalski Jan', 'CoName' => 'Orzeł Warszawa', 'QuScore' => 650, 'TcOrder' => 1],
            // Same TeCoId, different TeSubTeam (a club fielding a second sub-team).
            ['TeCoId' => 1, 'TeSubTeam' => 1, 'TeEvent' => 'RM', 'TeRank' => 3, 'TeScore' => 1700,
                'EvEventName' => 'Recurve Men Team', 'EvMixedTeam' => 0,
                'EnFullName' => 'Nowak Piotr', 'CoName' => 'Orzeł Warszawa', 'QuScore' => 600, 'TcOrder' => 1],
            // Same TeCoId/TeSubTeam as the first row, different TeEvent.
            ['TeCoId' => 1, 'TeSubTeam' => 0, 'TeEvent' => 'CM', 'TeRank' => 1, 'TeScore' => 1800,
                'EvEventName' => 'Compound Men Team', 'EvMixedTeam' => 0,
                'EnFullName' => 'Kowalski Jan', 'CoName' => 'Orzeł Warszawa', 'QuScore' => 640, 'TcOrder' => 1],
        ]);

        $teams = \pl_diploma_get_team_qual_results();

        $this->assertCount(3, $teams);
    }
}
